<?php

/**
 * WEBHOOK PROXY PARA YCLOUD - BYPASS MODSECURITY (VERSIÓN ESTABLE)
 * 
 * URL FINAL PARA ENLAZAR CON CLOUDFLARE: https://agente.fornuvi.com/webhook-ycloud.php
 */

// Cargar motor interno de Laravel silenciosamente
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Chat;
use App\Models\Message;
use App\Jobs\ProcessWhatsappMessage;
use Illuminate\Support\Facades\Log;

// === 1. VERIFICACIÓN DE YCLOUD (GET) ===
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $token = $_GET['hub_verify_token'] ?? '';
    $challenge = $_GET['hub_challenge'] ?? '';
    $mode = $_GET['hub_mode'] ?? '';

    $expectedToken = config('services.ycloud.webhook_token');

    if ($mode === 'subscribe' && $token === $expectedToken) {
        http_response_code(200);
        echo $challenge;
        exit;
    }
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

// === 2. PROCESAMIENTO DEL MENSAJE (POST) ===
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

// 2a. Leer el cuerpo de la petición (texto plano desde Cloudflare)
$rawContent = file_get_contents('php://input');
$payload = json_decode($rawContent, true);

if (!$payload) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}

// 2b. VALIDACIONES DE FIRMA DE SEGURIDAD
$headers = getallheaders();
// Convertir headers a minúsculas para encontrar firmas sin importar mayúsculas
$headers = array_change_key_case($headers, CASE_LOWER);
$signature = $headers['x-ycloud-signature'] ?? null;
$webhookSecret = config('services.ycloud.webhook_secret');

if ($webhookSecret && $signature) {
    $expected = hash_hmac('sha256', $rawContent, $webhookSecret);
    if (!hash_equals($expected, $signature)) {
        Log::warning("YCloud Proxy: Firma inválida detectada.");
        http_response_code(403);
        echo json_encode(['error' => 'Invalid signature']);
        exit;
    }
}

// === 3. LÓGICA DE MENSAJE ===

// 3a. SISTEMA - ECHO (Respuesta del humano a través del celular del negocio)
if (isset($payload['type']) && $payload['type'] === 'whatsapp.smb.message.echoes') {
    $messageData = $payload['whatsappMessage'];
    $remoteJid = $messageData['to']; 
    $messageId = $messageData['wamid'] ?? $messageData['id'];

    $remoteJidClean = ltrim($remoteJid, '+');
    $remoteJidFormated = str_contains($remoteJidClean, '@s.whatsapp.net') ? $remoteJidClean : $remoteJidClean . '@s.whatsapp.net';

    $chat = Chat::firstOrCreate(
        ['remote_jid' => $remoteJidFormated],
        ['name' => 'Cliente', 'provider' => 'ycloud']
    );

    if (Message::where('whatsapp_id', $messageId)->exists()) {
        echo json_encode(['status' => 'echo_duplicate_ignored']);
        exit;
    }

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

    echo json_encode(['status' => 'echo_saved']);
    exit;
}

// 3b. INBOUND - Mensaje de Cliente a Negocio
if (isset($payload['type']) && $payload['type'] === 'whatsapp.inbound_message.received') {
    $messageData = $payload['whatsappInboundMessage'];
    $remoteJid = $messageData['from'];
    $messageId = $messageData['wamid'] ?? $messageData['id'];
    $messageType = $messageData['type'] ?? 'text';
    $userName = $messageData['customerProfile']['name'] ?? 'Unknown';

    $remoteJidClean = ltrim($remoteJid, '+');
    $remoteJidFormated = str_contains($remoteJidClean, '@s.whatsapp.net') ? $remoteJidClean : $remoteJidClean . '@s.whatsapp.net';

    $chat = Chat::firstOrCreate(
        ['remote_jid' => $remoteJidFormated],
        ['name' => $userName, 'provider' => 'ycloud', 'is_active' => true]
    );

    if ($chat->wasRecentlyCreated) {
        $setting = \App\Models\BotSetting::find('default_new_user_tag_id');
        if ($setting && $setting->value) {
            $chat->tags()->syncWithoutDetaching([$setting->value]);
        }
    }

    if ($chat->provider !== 'ycloud') {
        $chat->update(['provider' => 'ycloud']);
    }

    // Extraer datos multimedia o texto
    $text = null;
    $mediaUrl = null;
    $mediaType = null;

    if ($messageType === 'text') {
        $text = $messageData['text']['body'] ?? '[Empty Text Message]';
    } elseif ($messageType === 'image') {
        $mediaType = 'image';
        $text = $messageData['image']['caption'] ?? '[Image Message]';
        $mediaUrl = $messageData['image']['link'] ?? ($messageData['image']['id'] ?? null);
    } elseif ($messageType === 'audio') {
        $mediaType = 'audio';
        $text = '[Audio Message]';
        $mediaUrl = $messageData['audio']['link'] ?? ($messageData['audio']['id'] ?? null);
    } elseif ($messageType === 'reaction') {
        $mediaType = 'text';
        $text = $messageData['reaction']['emoji'] ?? '👍';
    } else {
        $text = '[Unsupported Message]';
        $mediaType = $messageType;
    }

    // Verificar Duplicidad
    if (Message::where('whatsapp_id', $messageId)->exists()) {
        echo json_encode(['status' => 'duplicate_ignored']);
        exit;
    }

    // Guardar
    $newMessage = Message::create([
        'chat_id' => $chat->id,
        'role' => 'user',
        'content' => $text,
        'whatsapp_id' => $messageId,
        'media_url' => $mediaUrl,
        'media_type' => $mediaType
    ]);

    // Modo Pruebas
    $testNumber = env('BOT_TEST_NUMBER');
    if ($testNumber && !str_contains($remoteJidFormated, $testNumber)) {
        echo json_encode(['status' => 'ignored_test_mode']);
        exit;
    }

    // Handoff / Bot Pausado
    if (!$chat->is_active) {
        echo json_encode(['status' => 'saved_no_reply_handoff']);
        exit;
    }

    // Calcular demora humana (Delay)
    $randomDelay = rand(2, 4);
    if ($mediaType === 'audio' || $mediaType === 'image') {
        $randomDelay = rand(10, 15);
    } elseif (is_string($text) && strlen($text) > 100) {
        $randomDelay = rand(6, 10);
    } else {
        $randomDelay = rand(2, 5);
    }

    // Lanzar Trabajo Principal de Procesamiento a la Cola
    ProcessWhatsappMessage::dispatch($chat, $text, $newMessage->id)
        ->delay(now()->addSeconds($randomDelay));

    // AVISAR A CRON INMEDIATAMENTE
    // Engaño asíncrono optimizado: Toca el cron.php con timeout mínimo para forzar la respuesta
    // sin quedarse esperando eternamente
    try {
        $host = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
        $url = $host . '/cron.php?key=fornuvi2026cron';
        
        $ctx = stream_context_create(['http' => ['timeout' => 1]]);
        @file_get_contents($url, false, $ctx);
    } catch (\Exception $e) { }

    echo json_encode(['status' => 'processed']);
    exit;
}

echo json_encode(['status' => 'ignored']);
exit;
