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

        // 3. Obtener Chat y Texto
        $chat = Chat::firstOrCreate(
            ['remote_jid' => $remoteJid],
            ['name' => $msgData['pushName'] ?? 'Desconocido']
        );

        $text = $this->extractText($msgData);

        if (empty($text)) {
            return response()->json(['status' => 'no_text_found']);
        }

        // 4. IDEMPOTENCIA: Verificar si ya existe este mensaje
        $existingMessage = Message::where('whatsapp_id', $msgData['key']['id'])->first();
        if ($existingMessage) {
            return response()->json(['status' => 'duplicate_ignored']);
        }

        // 4. Guardar Mensaje del USUARIO
        // 4. Guardar Mensaje del USUARIO
        $newMessage = Message::create([
            'chat_id' => $chat->id,
            'role' => 'user',
            'content' => $text,
            'whatsapp_id' => $msgData['key']['id'] ?? null
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
            // No enviamos 'composing' ni despachamos el Job. 
            // Así el mensaje queda en DB pero "no leído" por el bot y sin respuesta.
            return response()->json(['status' => 'saved_no_reply_handoff']);
        }

        // 5b. Enviamos "Escribiendo..." para que el usuario sepa que estamos ahí
        $evolutionService = app(\App\Services\EvolutionService::class);
        $evolutionService->sendPresence($remoteJid, 'composing');

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
        return null;
    }
}
