<?php

use App\Models\Message;

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$messages = Message::latest()->take(5)->get();

echo "--- Recent Messages ---\n";
foreach ($messages as $msg) {
    echo "ID: {$msg->id} | Role: {$msg->role} | Type: {$msg->media_type}\n";
    echo "Content: {$msg->content}\n";
    echo "Media URL: " . substr($msg->media_url, 0, 50) . "...\n";
    echo "Created: {$msg->created_at}\n";
    echo "-----------------------\n";
}
