<?php

use Illuminate\Support\Facades\DB;

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$value = DB::table('bot_settings')->where('key', 'system_instruction')->value('value');

file_put_contents('current_instruction.md', $value);

echo "Instrucción guardada en current_instruction.md\n";
