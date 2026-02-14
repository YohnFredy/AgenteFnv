<?php

use Illuminate\Support\Facades\DB;

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$value = file_get_contents('current_instruction.md');

DB::table('bot_settings')->updateOrInsert(
    ['key' => 'system_instruction'],
    ['value' => $value, 'updated_at' => now()]
);

echo "Instrucción actualizada correctamente en la base de datos.\n";
