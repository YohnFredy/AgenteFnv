<?php
require 'vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$setting = App\Models\BotSetting::where('key', 'system_instruction')->first();
file_put_contents(__DIR__ . '/storage/app/system_instruction.xml', $setting->value);
