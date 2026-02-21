<?php

use App\Services\YCloudService;
use Illuminate\Support\Facades\Log;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$response = $kernel->handle(
    $request = Illuminate\Http\Request::capture()
);

// Configuration - REPLACE WITH REAL DATA
// valid test number
$latestChat = \App\Models\Chat::whereNotNull('remote_jid')->where('is_active', true)->latest('updated_at')->first();
if (!$latestChat) {
    die("No active chat found to test with.\n");
}
$testPhone = $latestChat->remote_jid;
$templateName = 'template_marketing_20260218134317';

echo "--- YCloud Template Debug ---\n";
echo "Target: $testPhone\n";
echo "Template: $templateName\n\n";

$ycloud = new YCloudService();

// We need to access the protected/private logic or use the public method and inspect logs.
// Since we want raw output, let's copy the logic here slightly modified to echo output.

$apiKey = config('services.ycloud.api_key');
$fromNumber = config('services.ycloud.from_number');

echo "API Key: " . substr($apiKey, 0, 5) . "...\n";
echo "From: $fromNumber\n";

$to = str_replace('@s.whatsapp.net', '', $testPhone);
if (!str_starts_with($to, '+')) {
    $to = '+' . $to;
}

$url = 'https://api.ycloud.com/v2/whatsapp/messages';
$payload = [
    'from' => $fromNumber,
    'to' => $to,
    'type' => 'template',
    'template' => [
        'name' => $templateName,
        'language' => [
            'code' => 'es',
            'policy' => 'deterministic'
        ]
    ]
];

echo "\nPayload:\n" . json_encode($payload, JSON_PRETTY_PRINT) . "\n\n";

echo "Sending request...\n";

try {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'X-API-Key: ' . $apiKey,
        'Content-Type: application/json'
    ]);

    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    echo "HTTP Code: $httpCode\n";
    echo "Response:\n$result\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
