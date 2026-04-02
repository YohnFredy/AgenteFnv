<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\BotSetting;
use Illuminate\Support\Facades\DB;

$key = 'system_instruction';
$setting = BotSetting::find($key);

if (!$setting) {
    die("❌ No se encontró el registro 'system_instruction' en la base de datos.\n");
}

$oldLink = 'noe-kvxm-wxq';
$newLink = 'qcn-wfhf-gar';

$oldValue = $setting->value;
$count = substr_count($oldValue, $oldLink);

if ($count === 0) {
    echo "✅ No se encontró el enlace antiguo '$oldLink' en la base de datos. ¡Todo parece estar bien!\n";
    die();
}

$newValue = str_replace($oldLink, $newLink, $oldValue);

// Actualizar en DB
$setting->update(['value' => $newValue]);

echo "🚀 SE ENCONTRARON Y CORRIGIERON $count OCURRENCIAS DEL ENLACE ANTIGUO.\n";
echo "✅ Base de datos actualizada.\n";

// También actualizar el archivo backup si es posible
$path = resource_path('docs/system_instruction_v2.md');
if (file_exists($path)) {
    file_put_contents($path, $newValue);
    echo "✅ Archivo backup MD también actualizado.\n";
}

// Limpiar cache
\Illuminate\Support\Facades\Cache::forget('system_instruction');
echo "⚡ Cache limpiada. El bot ya debería estar usando el nuevo enlace.\n";
