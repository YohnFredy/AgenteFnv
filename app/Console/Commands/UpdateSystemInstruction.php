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
        $path = resource_path('docs/system_instruction_v2.md');

        if (!file_exists($path)) {
            $this->error('Archivo no encontrado: ' . $path);
            return 1;
        }

        $content = file_get_contents($path);

        BotSetting::updateOrCreate(
            ['key' => 'system_instruction'],
            ['value' => $content]
        );

        $this->info('✅ Instrucción del sistema actualizada correctamente.');
        $this->info('📄 Caracteres: ' . strlen($content));

        return 0;
    }
}
