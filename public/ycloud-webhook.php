<?php

/**
 * PROXY WEBHOOK PARA YCLOUD
 * 
 * Este archivo recibe el webhook de YCloud y lo reenvía internamente a Laravel.
 * Evita las reglas de ModSecurity que bloquean peticiones POST de APIs externas.
 * 
 * URL para configurar en YCloud: https://agente.fornuvi.com/ycloud-webhook.php
 */

// Verificación GET (YCloud verification challenge)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $hubMode = $_GET['hub_mode'] ?? '';
    $hubChallenge = $_GET['hub_challenge'] ?? '';
    $hubToken = $_GET['hub_verify_token'] ?? '';

    // Token de verificación (debe coincidir con YCLOUD_WEBHOOK_TOKEN en .env)
    $expectedToken = 'secure_token_12345'; // CAMBIA ESTO si tu token es diferente

    if ($hubMode === 'subscribe' && $hubToken === $expectedToken) {
        header('Content-Type: text/plain');
        echo $hubChallenge;
        exit;
    }

    http_response_code(403);
    echo 'Forbidden';
    exit;
}

// Solo procesar POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo 'Method Not Allowed';
    exit;
}

// Obtener el payload raw
$rawPayload = file_get_contents('php://input');
$headers = getallheaders();

// Preparar headers para reenviar
$forwardHeaders = [
    'Content-Type: application/json',
    'Accept: application/json',
];

// Incluir headers importantes de YCloud
if (isset($headers['X-Ycloud-Signature'])) {
    $forwardHeaders[] = 'X-Ycloud-Signature: ' . $headers['X-Ycloud-Signature'];
}

// URL interna de Laravel (mismo servidor, evita ModSecurity)
// Usamos la URL interna localhost o el path directo
$laravelUrl = 'http://127.0.0.1/api/ycloud/webhook';

// Alternativa: llamar directamente a Laravel bootstrapeando la app
// Esto es más confiable en hosting compartido

// Cambiar al directorio raíz de Laravel
chdir(dirname(__DIR__));

// Bootstrap Laravel
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

// Crear una request simulada
$request = Illuminate\Http\Request::create(
    '/api/ycloud/webhook',
    'POST',
    [],
    [],
    [],
    [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_YCLOUD_SIGNATURE' => $headers['X-Ycloud-Signature'] ?? '',
    ],
    $rawPayload
);

// Ejecutar la request a través de Laravel
$response = $kernel->handle($request);

// Enviar la respuesta
http_response_code($response->getStatusCode());
header('Content-Type: application/json');
echo $response->getContent();

$kernel->terminate($request, $response);
