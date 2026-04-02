<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\BotSetting;
$setting = BotSetting::find('system_instruction');

if ($setting) {
    echo "KEY: " . $setting->key . "\n";
    echo "UPDATED_AT: " . $setting->updated_at . "\n";
    $hasOldLink = str_contains($setting->value, 'noe-kvxm-wxq');
    $hasNewLink = str_contains($setting->value, 'qcn-wfhf-gar');
    echo "HAS OLD LINK: " . ($hasOldLink ? 'YES' : 'NO') . "\n";
    echo "HAS NEW LINK: " . ($hasNewLink ? 'YES' : 'NO') . "\n";
    
    // Find where the old link is
    if ($hasOldLink) {
        $pos = strpos($setting->value, 'noe-kvxm-wxq');
        echo "OLD LINK CONTEXT: " . substr($setting->value, max(0, $pos - 50), 100) . "\n";
    }
} else {
    echo "SETTING NOT FOUND\n";
}
