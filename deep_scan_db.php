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
    
    $value = $setting->value;
    $oldLink = 'noe-kvxm-wxq';
    $newLink = 'qcn-wfhf-gar';
    
    $countOld = substr_count($value, $oldLink);
    $countNew = substr_count($value, $newLink);
    
    echo "OLD LINK COUNT: $countOld\n";
    echo "NEW LINK COUNT: $countNew\n";
    
    if ($countOld > 0) {
        echo "POSITIONS OF OLD LINK:\n";
        $lastPos = 0;
        while (($lastPos = strpos($value, $oldLink, $lastPos)) !== false) {
             echo " - Position $lastPos: " . substr($value, max(0, $lastPos - 40), 100) . "\n";
             $lastPos = $lastPos + strlen($oldLink);
        }
    }
} else {
    echo "SETTING NOT FOUND\n";
}
