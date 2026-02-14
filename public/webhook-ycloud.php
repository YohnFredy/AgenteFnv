<?php

/**
 * WEBHOOK DIRECTO PARA YCLOUD - BYPASS MODSECURITY
 * 
 * Este archivo recibe el webhook, procesa el JSON con PHP puro,
 * y guarda los datos directamente en la base de datos.
 * 
 * URL para YCloud: https://agente.fornuvi.com/webhook-ycloud.php
 */

// Cargar Laravel
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Chat;
use App\Models\Message;
use App\Jobs\ProcessWhatsappMessage;
use Illuminate\Support\Facades\Log;

// === VERIFICACIÓN GET ===
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $token = $_GET['hub_verify_token'] ?? '';
    $challenge = $_GET['hub_challenge'] ?? '';
    $mode = $_GET['hub_mode'] ?? '';

    $expectedToken = config('services.ycloud.webhook_token');

    if ($mode === 'subscribe' && $token === $expectedToken) {
        echo $challenge;
        exit;
    }
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

// === SOLO POST ===
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

// Leer y parsear payload
$input = file_get_contents('php://input');
$payload = json_decode($input, true);

if (!$payload) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}

// Log para debug
Log::info("YCloud Webhook Raw: " . substr($input, 0, 500));

// PROCESAR MENSAJE ENTRANTE
if (isset($payload['type']) && $payload['type'] === 'whatsapp.inbound_message.received') {
    $messageData = $payload['whatsappInboundMessage'];
    $remoteJid = $messageData['from'];
    $messageId = $messageData['wamid'] ?? $messageData['id'];
    $messageType = $messageData['type'] ?? 'text';
    $userName = $messageData['customerProfile']['name'] ?? 'Unknown';

    // Normalizar JID
    $remoteJidClean = ltrim($remoteJid, '+');
    if (!str_contains($remoteJidClean, '@s.whatsapp.net')) {
        $remoteJidFormated = $remoteJidClean . '@s.whatsapp.net';
    } else {
        $remoteJidFormated = $remoteJidClean;
    }

    // Crear/obtener chat
    $chat = Chat::firstOrCreate(
        ['remote_jid' => $remoteJidFormated],
        ['name' => $userName, 'provider' => 'ycloud']
    );

    if ($chat->provider !== 'ycloud') {
        $chat->update(['provider' => 'ycloud']);
    }

    // Extraer contenido
    $text = null;
    $mediaUrl = null;
    $mediaType = null;

    if ($messageType === 'text') {
        $text = $messageData['text']['body'] ?? '';
    } elseif ($messageType === 'image') {
        $mediaType = 'image';
        $text = $messageData['image']['caption'] ?? '[Image Message]';
        $mediaUrl = $messageData['image']['link'] ?? ($messageData['image']['id'] ?? null);
    } elseif ($messageType === 'audio') {
        $mediaType = 'audio';
        $text = '[Audio Message]';
        $mediaUrl = $messageData['audio']['link'] ?? ($messageData['audio']['id'] ?? null);
    } else {
        $text = '[Unsupported Message]';
        $mediaType = $messageType;
    }

    // Verificar duplicados
    $existingMessage = Message::where('whatsapp_id', $messageId)->first();
    if ($existingMessage) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'duplicate_ignored']);
        exit;
    }

    // Guardar mensaje
    $newMessage = Message::create([
        'chat_id' => $chat->id,
        'role' => 'user',
        'content' => $text,
        'whatsapp_id' => $messageId,
        'media_url' => $mediaUrl,
        'media_type' => $mediaType
    ]);

    // Modo pruebas
    $testNumber = env('BOT_TEST_NUMBER');
    if ($testNumber && !str_contains($remoteJidFormated, $testNumber)) {
        Log::info("Modo Pruebas: Mensaje de {$remoteJidFormated} ignorado.");
        header('Content-Type: application/json');
        echo json_encode(['status' => 'ignored_test_mode']);
        exit;
    }

    // Verificar si chat activo
    if (!$chat->is_active) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'saved_no_reply_handoff']);
        exit;
    }

    // Calcular delay
    $randomDelay = rand(2, 4);
    if ($mediaType === 'audio' || $mediaType === 'image') {
        $randomDelay = rand(10, 15);
    } elseif (is_string($text) && strlen($text) > 100) {
        $randomDelay = rand(6, 10);
    } else {
        $randomDelay = rand(2, 5);
    }

    Log::info("YCloud Webhook: Mensaje guardado, job programado en {$randomDelay}s");

    // Dispatch job
    ProcessWhatsappMessage::dispatch($chat, $text ?? '', $newMessage->id)
        ->delay(now()->addSeconds($randomDelay));

    header('Content-Type: application/json');
    echo json_encode(['status' => 'processed']);
    exit;
}

// PROCESAR ECHO (mensajes enviados desde celular)
if (isset($payload['type']) && $payload['type'] === 'whatsapp.smb.message.echoes') {
    $messageData = $payload['whatsappMessage'];
    $remoteJid = $messageData['to'];
    $messageId = $messageData['wamid'] ?? $messageData['id'];

    $remoteJidClean = ltrim($remoteJid, '+');
    if (!str_contains($remoteJidClean, '@s.whatsapp.net')) {
        $remoteJidFormated = $remoteJidClean . '@s.whatsapp.net';
    } else {
        $remoteJidFormated = $remoteJidClean;
    }

    $chat = Chat::where('remote_jid', $remoteJidFormated)->first();
    if (!$chat) {
        $chat = Chat::create([
            'remote_jid' => $remoteJidFormated,
            'name' => 'Cliente',
            'provider' => 'ycloud'
        ]);
    }

    $existingMessage = Message::where('whatsapp_id', $messageId)->first();
    if ($existingMessage) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'echo_duplicate_ignored']);
        exit;
    }

    $text = '';
    $messageType = $messageData['type'] ?? 'text';

    if ($messageType === 'text') {
        $text = $messageData['text']['body'] ?? '';
    } elseif ($messageType === 'image') {
        $text = $messageData['image']['caption'] ?? '[Imagen enviada]';
    } else {
        $text = '[Mensaje enviado]';
    }

    Message::create([
        'chat_id' => $chat->id,
        'role' => 'assistant',
        'content' => $text,
        'whatsapp_id' => $messageId
    ]);

    Log::info("YCloud Echo guardado: {$text}");

    header('Content-Type: application/json');
    echo json_encode(['status' => 'echo_saved']);
    exit;
}

// Otros eventos ignorados
header('Content-Type: application/json');
echo json_encode(['status' => 'ignored_event_type']);
