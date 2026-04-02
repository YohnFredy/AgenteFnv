<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$rules = DB::table('bot_rules')->get();

foreach ($rules as $rule) {
    if (str_contains($rule->response, 'noe-kvxm-wxq')) {
        echo "RULE ID: {$rule->id}\n";
        echo "TRIGGER: {$rule->trigger_text}\n";
        echo "RESPONSE: {$rule->response}\n";
        echo "-------------------\n";
    }
}

if ($rules->isEmpty()) {
    echo "NO RULES FOUND\n";
} else {
    echo "CHECKED " . count($rules) . " RULES.\n";
}
