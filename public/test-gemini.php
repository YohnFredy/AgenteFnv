<?php

/**
 * PRUEBA DIRECTA DE GEMINI API
 * URL: https://agente.fornuvi.com/test-gemini.php?key=fornuvi2026
 */

$secretKey = 'fornuvi2026';

if (!isset($_GET['key']) || $_GET['key'] !== $secretKey) {
    http_response_code(403);
    die('Forbidden');
}

// Cargar Laravel
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "<pre style='background:#1a1a2e;color:#0f0;padding:20px;font-family:monospace;'>";
echo "=== DIAGNÓSTICO GEMINI API ===\n\n";

// Mostrar configuración
$apiKey = config('services.gemini.api_key') ?? env('GEMINI_API_KEY');
$model = config('services.gemini.model') ?? env('GEMINI_MODEL') ?? 'gemini-2.0-flash';

echo "API Key: " . substr($apiKey, 0, 10) . "..." . substr($apiKey, -4) . "\n";
echo "Model: {$model}\n\n";

// Probar conexión directa
echo "=== PROBANDO CONEXIÓN ===\n";

$url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

$payload = [
    'contents' => [
        ['parts' => [['text' => 'Responde solo con: OK']]]
    ]
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "HTTP Code: {$httpCode}\n";
if ($error) {
    echo "CURL Error: {$error}\n";
}
echo "Response:\n";
echo $response;

echo "\n\n=== FIN DIAGNÓSTICO ===\n";
echo "</pre>";
