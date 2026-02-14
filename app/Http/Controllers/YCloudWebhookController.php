<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessWhatsappMessage;
use App\Models\Chat;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class YCloudWebhookController extends Controller
{
    /**
     * Handle incoming webhook requests from YCloud.
     */
    public function handle(Request $request)
    {
        // 1. Webhook Verification (GET)
        if ($request->isMethod('get')) {
            $verifyToken = config('services.ycloud.webhook_token');
            $mode = $request->query('hub_mode');
            $token = $request->query('hub_verify_token');
            $challenge = $request->query('hub_challenge');

            if ($mode === 'subscribe' && $token === $verifyToken) {
                Log::info("YCloud Webhook Verified Successfully.");
                return response($challenge, 200);
            }

            Log::warning("YCloud Webhook Verification Failed. Token: {$token}");
            return response()->json(['error' => 'Forbidden'], 403);
        }

        // 2. Message Processing (POST)

        // 2a. VALIDACIÓN DE FIRMA (Seguridad - Previene webhooks falsificados)
        // YCloud firma cada payload con tu Secret y lo envía en el header X-Ycloud-Signature
        $signature = $request->header('X-Ycloud-Signature');
        $webhookSecret = config('services.ycloud.webhook_secret');

        // Solo validar si el secret está configurado (opcional en desarrollo)
        if ($webhookSecret && $signature) {
            $payload = $request->getContent();
            $expected = hash_hmac('sha256', $payload, $webhookSecret);

            if (!hash_equals($expected, $signature)) {
                Log::warning("YCloud Webhook: Firma inválida. Posible intento de spoofing.");
                return response()->json(['error' => 'Invalid signature'], 403);
            }
            Log::debug("YCloud Webhook: Firma verificada correctamente.");
        }

        $payload = $request->all();

        // 2b. MENSAJE ECHO (Respuestas enviadas desde el celular del negocio)
        // Estos mensajes los envía el humano desde WhatsApp, queremos guardarlos pero NO responder
        if (isset($payload['type']) && $payload['type'] === 'whatsapp.smb.message.echoes') {
            $messageData = $payload['whatsappMessage'];
            $remoteJid = $messageData['to']; // El destinatario es el cliente (invertido vs inbound)
            $messageId = $messageData['wamid'] ?? $messageData['id'];

            // Normalizar JID
            $remoteJidClean = ltrim($remoteJid, '+');
            if (!str_contains($remoteJidClean, '@s.whatsapp.net')) {
                $remoteJidFormated = $remoteJidClean . '@s.whatsapp.net';
            } else {
                $remoteJidFormated = $remoteJidClean;
            }

            // Buscar el chat existente
            $chat = Chat::where('remote_jid', $remoteJidFormated)->first();

            if (!$chat) {
                // Si no existe el chat, lo creamos (raro, pero posible)
                $chat = Chat::create([
                    'remote_jid' => $remoteJidFormated,
                    'name' => 'Cliente',
                    'provider' => 'ycloud'
                ]);
            }

            // Verificar duplicados
            $existingMessage = Message::where('whatsapp_id', $messageId)->first();
            if ($existingMessage) {
                return response()->json(['status' => 'echo_duplicate_ignored']);
            }

            // Extraer contenido del mensaje
            $text = '';
            $mediaType = null;
            $messageType = $messageData['type'] ?? 'text';

            if ($messageType === 'text') {
                $text = $messageData['text']['body'] ?? '';
            } elseif ($messageType === 'image') {
                $text = $messageData['image']['caption'] ?? '[Imagen enviada]';
                $mediaType = 'image';
            } elseif ($messageType === 'audio') {
                $text = '[Audio enviado]';
                $mediaType = 'audio';
            } else {
                $text = '[Mensaje enviado]';
            }

            // Guardar como mensaje del asistente (porque lo envió el humano/negocio)
            Message::create([
                'chat_id' => $chat->id,
                'role' => 'assistant', // Lo tratamos como respuesta del negocio
                'content' => $text,
                'whatsapp_id' => $messageId,
                'media_type' => $mediaType
            ]);

            Log::info("YCloud Echo guardado: {$text} para {$remoteJidFormated}");

            return response()->json(['status' => 'echo_saved']);
        }

        // 3. Check for YCloud specific event type (MENSAJES ENTRANTES del cliente)
        if (isset($payload['type']) && $payload['type'] === 'whatsapp.inbound_message.received') {
            $messageData = $payload['whatsappInboundMessage'];
            $remoteJid = $messageData['from']; // e.g., '+573145207814'
            $messageId = $messageData['wamid'] ?? $messageData['id'];
            $messageType = $messageData['type'];
            $userName = $messageData['customerProfile']['name'] ?? 'Unknown';
        }
        // Fallback to Standard Meta Cloud API structure (if YCloud mirrors it in some cases)
        elseif (isset($payload['entry'][0]['changes'][0]['value']['messages'][0])) {
            $changeValue = $payload['entry'][0]['changes'][0]['value'];
            $messageData = $changeValue['messages'][0];
            $contactData = $changeValue['contacts'][0] ?? null;

            $remoteJid = $messageData['from'];
            $messageId = $messageData['id'];
            $messageType = $messageData['type'];
            $userName = $contactData['profile']['name'] ?? 'Unknown';
        } else {
            // Check if it's a status update or other event
            if (isset($payload['type']) || isset($payload['entry'][0]['changes'][0]['value']['statuses'])) {
                return response()->json(['status' => 'ignored_event_type']);
            }
            return response()->json(['status' => 'no_message_found']);
        }

        // Normalize JID: Standardize to digits only + @s.whatsapp.net for our system
        // Remove '+' if present
        $remoteJidClean = ltrim($remoteJid, '+');

        if (!str_contains($remoteJidClean, '@s.whatsapp.net')) {
            $remoteJidFormated = $remoteJidClean . '@s.whatsapp.net';
        } else {
            $remoteJidFormated = $remoteJidClean;
        }

        // 3. Find or Create Chat
        $chat = Chat::firstOrCreate(
            ['remote_jid' => $remoteJidFormated],
            [
                'name' => $userName,
                'provider' => 'ycloud',
                'is_active' => true  // Asegurar que nuevos chats empiezan activos
            ]
        );

        // Ensure provider (idempotent update)
        if ($chat->provider !== 'ycloud') {
            $chat->update(['provider' => 'ycloud']);
        }

        // 4. Extract Content
        $text = null;
        $mediaUrl = null;
        $mediaType = null;

        // Log del tipo de mensaje para diagnóstico
        Log::info("YCloud Message Type: {$messageType} para {$remoteJidFormated}");

        if ($messageType === 'text') {
            $text = $messageData['text']['body'] ?? null;

            // Validación adicional: si no hay texto pero el tipo es text, logear la estructura completa
            if (empty($text)) {
                Log::warning("YCloud: Mensaje tipo 'text' sin contenido. Payload:", ['messageData' => $messageData]);
                $text = '[Empty Text Message]';
            }
        } elseif ($messageType === 'image') {
            $mediaType = 'image';
            $text = $messageData['image']['caption'] ?? '[Image Message]';
            $mediaUrl = $messageData['image']['link'] ?? ($messageData['image']['id'] ?? null);
        } elseif ($messageType === 'audio' || $messageType === 'voice') {
            $mediaType = 'audio';
            $text = '[Audio Message]';
            $mediaUrl = $messageData['audio']['link'] ?? ($messageData['audio']['id'] ?? ($messageData['voice']['link'] ?? ($messageData['voice']['id'] ?? null)));
        } else {
            // Tipos no soportados actualmente (video, document, sticker, location, etc.)
            // Log detallado para investigar qué tipo de mensaje es
            Log::warning("YCloud: Tipo de mensaje no soportado detectado: {$messageType}", [
                'from' => $remoteJidFormated,
                'messageData' => $messageData
            ]);

            $text = '[Unsupported Message]';
            $mediaType = $messageType; // Guardamos el tipo real para referencia
        }

        // if (!$text && !$mediaUrl) ... // Ya no bloqueamos aquí porque $text tendrá valor


        // 5. Idempotency Check
        $existingMessage = Message::where('whatsapp_id', $messageId)->first();
        if ($existingMessage) {
            return response()->json(['status' => 'duplicate_ignored']);
        }

        // 6. Save Message
        // For media, we initially store the Media ID in media_url. 
        // The Job will need to detect if it's a URL or an ID and act accordingly.
        $newMessage = Message::create([
            'chat_id' => $chat->id,
            'role' => 'user',
            'content' => $text,
            'whatsapp_id' => $messageId,
            'media_url' => $mediaUrl, // ID for now
            'media_type' => $mediaType
        ]);

        // 7. SISTEMA DE BUFFERING (DEBOUNCE)

        // [NUEVO] MODO PRUEBAS: Si hay un número de prueba definido, ignorar a todos los demás.
        $testNumber = env('BOT_TEST_NUMBER');
        if ($testNumber && !str_contains($remoteJidFormated, $testNumber)) {
            Log::info("Modo Pruebas Activo YCloud: Mensaje de {$remoteJidFormated} ignorado (Solo se permite: {$testNumber}).");
            return response()->json(['status' => 'ignored_test_mode']);
        }

        // a. Verificar si el Bot está ACTIVO
        if (!$chat->is_active) {
            Log::info("Mensaje recibido para Chat Inactivo (Handoff): {$remoteJidFormated}. Mensaje guardado, pero sin respuesta automática.");
            return response()->json(['status' => 'saved_no_reply_handoff']);
        }

        // b. Delay VARIABLE según tipo de mensaje (simula tiempo de lectura + permite buffering)
        // - Audio/Imagen: requieren más tiempo de "procesamiento visual/auditivo"
        // - Texto largo: más tiempo de "lectura"
        // - Texto corto: respuesta más rápida

        $randomDelay = rand(2, 4); // Default

        if ($mediaType === 'audio' || $mediaType === 'image') {
            $randomDelay = rand(10, 15); // Audio/Imagen: 10-15 segundos
        } elseif (is_string($text) && strlen($text) > 100) {
            $randomDelay = rand(6, 10);  // Texto largo: 6-10 segundos
        } else {
            $randomDelay = rand(2, 5);   // Texto corto: 2-5 segundos (ligeramente más que Evolution para asegurar buffering YCloud)
        }

        Log::info("Mensaje YCloud buffered con delay de {$randomDelay}s para {$remoteJidFormated}");

        ProcessWhatsappMessage::dispatch($chat, $text, $newMessage->id)
            ->delay(now()->addSeconds($randomDelay));

        return response()->json(['status' => 'processed']);
    }
}
