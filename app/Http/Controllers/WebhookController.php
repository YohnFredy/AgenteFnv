<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessWhatsappMessage; // IMPORTANTE
use App\Models\Chat;
use App\Models\MensajeEvolution;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    public function handle(Request $request)
    {
        MensajeEvolution::create(['payload' => $request->all()]);

        $data = $request->all();

        // 2. Validaciones básicas
        if (($data['event'] ?? '') !== 'messages.upsert') {
            return response()->json(['status' => 'ignored_event']);
        }

        $msgData = $data['data'];
        $remoteJid = $msgData['key']['remoteJid'];
        $fromMe = $msgData['key']['fromMe'];

        // Ignorar grupos, estados, mis propios mensajes y LIDs (identificadores privados)
        if (str_contains($remoteJid, '@g.us') || str_contains($remoteJid, 'status') || str_contains($remoteJid, '@lid') || $fromMe) {
            return response()->json(['status' => 'ignored']);
        }

        // 3. Obtener Chat y Texto / Audio / Imagen
        $chat = Chat::firstOrCreate(
            ['remote_jid' => $remoteJid],
            ['name' => $msgData['pushName'] ?? 'Desconocido']
        );

        $text = $this->extractText($msgData);
        $mediaInfo = $this->extractMediaInfo($msgData);
        $mediaUrl = $mediaInfo['url'] ?? null;
        $mediaType = $mediaInfo['type'] ?? null;

        // Si no hay texto ni media soportada, ignorar (stickers, etc)
        if (empty($text) && empty($mediaUrl)) {
            return response()->json(['status' => 'no_text_or_media_found']);
        }

        // Definir placeholder según tipo de media
        if ($mediaType === 'audio' && empty($text)) {
            $text = "[Audio Message]";
        } elseif ($mediaType === 'image' && empty($text)) {
            $text = "[Image Message]";
        }

        // 4. IDEMPOTENCIA: Verificar si ya existe este mensaje
        $existingMessage = Message::where('whatsapp_id', $msgData['key']['id'])->first();
        if ($existingMessage) {
            return response()->json(['status' => 'duplicate_ignored']);
        }

        // 4. Guardar Mensaje del USUARIO
        $newMessage = Message::create([
            'chat_id' => $chat->id,
            'role' => 'user',
            'content' => $text,
            'whatsapp_id' => $msgData['key']['id'] ?? null,
            'media_url' => $mediaUrl,
            'media_type' => $mediaType
        ]);

        // ======================================================
        // 5. SISTEMA DE BUFFERING (DEBOUNCE)
        // ======================================================

        // [NUEVO] MODO PRUEBAS: Si hay un número de prueba definido, ignorar a todos los demás.
        $testNumber = env('BOT_TEST_NUMBER');
        if ($testNumber && !str_contains($remoteJid, $testNumber)) {
            Log::info("Modo Pruebas Activo: Mensaje de {$remoteJid} ignorado (Solo se permite: {$testNumber}).");
            return response()->json(['status' => 'ignored_test_mode']);
        }

        // 5a. [MODIFICADO] Verificar si el Bot está ACTIVO
        if (!$chat->is_active) {
            Log::info("Mensaje recibido para Chat Inactivo (Handoff): {$remoteJid}. Mensaje guardado, pero sin respuesta automática.");
            return response()->json(['status' => 'saved_no_reply_handoff']);
        }

        // 5b. Enviamos estado
        $evolutionService = app(\App\Services\EvolutionService::class);
        $evolutionService->sendPresence($remoteJid, $mediaType === 'audio' ? 'recording' : 'composing');

        // 5c. Despachamos el trabajo con RETRASO (ej: 10 segundos)
        // Pasamos el ID del mensaje actual para verificar luego
        ProcessWhatsappMessage::dispatch($chat, $text, $newMessage->id)
            ->delay(now()->addSeconds(10));

        return response()->json(['status' => 'buffered']);
    }

    private function extractText($msgData)
    {
        if (isset($msgData['message']['conversation'])) return $msgData['message']['conversation'];
        if (isset($msgData['message']['extendedTextMessage']['text'])) return $msgData['message']['extendedTextMessage']['text'];
        // Si hay caption en medios
        if (isset($msgData['message']['imageMessage']['caption'])) return $msgData['message']['imageMessage']['caption'];
        if (isset($msgData['message']['videoMessage']['caption'])) return $msgData['message']['videoMessage']['caption'];

        return null;
    }

    /**
     * Extrae información de media (audio, imagen) del mensaje.
     * 
     * @return array ['type' => 'audio'|'image'|null, 'url' => string|null]
     */
    private function extractMediaInfo($msgData): array
    {
        // Detectar audio
        if (isset($msgData['message']['audioMessage'])) {
            return [
                'type' => 'audio',
                'url' => $msgData['message']['audioMessage']['url'] ?? null
            ];
        }

        // Detectar imagen
        if (isset($msgData['message']['imageMessage'])) {
            return [
                'type' => 'image',
                'url' => $msgData['message']['imageMessage']['url'] ?? null
            ];
        }

        // Sin media soportada
        return ['type' => null, 'url' => null];
    }
}
