<?php

namespace App\Console\Commands;

use App\Models\BotSetting;
use Illuminate\Console\Command;

class UpdateSystemInstruction extends Command
{
    protected $signature = 'bot:update-instruction';
    protected $description = 'Actualiza la instrucción del sistema desde el archivo MD';

    public function handle()
    {
        // [INVERTIDO] Ahora sincronizamos de BD a ARCHIVO para que el archivo sea un backup y no el maestro
        $setting = BotSetting::find('system_instruction');

        if (!$setting) {
            $this->error('No se encontró la instrucción en la base de datos.');
            return 1;
        }

        $path = resource_path('docs/system_instruction_v2.md');
        $content = $setting->value;

        try {
            if (!is_dir(dirname($path))) {
                mkdir(dirname($path), 0755, true);
            }
            file_put_contents($path, $content);
            $this->info('✅ Archivo MD de backup actualizado desde la Base de Datos.');
            $this->info('📄 Caracteres: ' . strlen($content));
        } catch (\Exception $e) {
            $this->error('❌ Error al escribir el archivo: ' . $e->getMessage());
            return 1;
        }

        return 0;
    }
}
