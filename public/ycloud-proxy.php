<?php

/**
 * PROXY WEBHOOK - BOLD → HOSTGATOR
 * 
 * Este archivo recibe el webhook de YCloud en Bold (donde funciona)
 * y lo reenvía a HostGator.
 * 
 * Sube este archivo a tu hosting Bold.
 * Configura YCloud para enviar webhooks a: https://tu-dominio-bold.com/ycloud-proxy.php
 */

// URL destino en HostGator
$targetUrl = 'https://agente.fornuvi.com/api/ycloud/webhook';

// Verificación GET (challenge de YCloud)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Reenviar la verificación al destino
    $queryString = $_SERVER['QUERY_STRING'] ?? '';
    $response = file_get_contents($targetUrl . '?' . $queryString);
    echo $response;
    exit;
}

// Solo procesar POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo 'Method Not Allowed';
    exit;
}

// Obtener el payload
$payload = file_get_contents('php://input');
$headers = getallheaders();

// Configurar contexto para curl o file_get_contents
$options = [
    'http' => [
        'method' => 'POST',
        'header' => [
            'Content-Type: application/json',
            'Accept: application/json',
        ],
        'content' => $payload,
        'timeout' => 30,
        'ignore_errors' => true,
    ],
    'ssl' => [
        'verify_peer' => false,
        'verify_peer_name' => false,
    ]
];

// Añadir header de firma si existe
if (isset($headers['X-Ycloud-Signature'])) {
    $options['http']['header'][] = 'X-Ycloud-Signature: ' . $headers['X-Ycloud-Signature'];
}

$context = stream_context_create($options);
$response = file_get_contents($targetUrl, false, $context);

// Obtener código de respuesta
$statusCode = 200;
if (isset($http_response_header[0])) {
    preg_match('/\d{3}/', $http_response_header[0], $matches);
    $statusCode = (int)($matches[0] ?? 200);
}

http_response_code($statusCode);
header('Content-Type: application/json');
echo $response ?: json_encode(['status' => 'forwarded']);
