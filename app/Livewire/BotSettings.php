<?php

namespace App\Livewire;

use App\Models\BotSetting;
use Livewire\Component;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class BotSettings extends Component
{
    public $systemInstruction;

    public function mount()
    {
        // Cargar instrucción actual o valor por defecto
        $setting = BotSetting::find('system_instruction');
        $this->systemInstruction = $setting ? $setting->value : "Eres un asistente virtual útil y amable.";
    }

    public function save()
    {
        try {
            // [NUEVO] Limpieza automática preventiva: eliminar cualquier enlace antiguo conocido
            $this->systemInstruction = $this->replaceAllOldLinks($this->systemInstruction);

            // 1. Guardar en Base de Datos
            BotSetting::updateOrCreate(
                ['key' => 'system_instruction'],
                ['value' => $this->systemInstruction]
            );

            // 2. [MEJORADO] Sincronizar con el archivo MD para backup
            $path = resource_path('docs/system_instruction_v2.md');
            $fileStatus = " (Archivo backup no actualizado por error de permisos)";
            
            try {
                if (!is_dir(dirname($path))) {
                    mkdir(dirname($path), 0755, true);
                }
                file_put_contents($path, $this->systemInstruction);
                $fileStatus = " (Archivo backup actualizado)";
            } catch (\Exception $e) {
                \Log::warning("No se pudo escribir el archivo de backup docs/system_instruction_v2.md: " . $e->getMessage());
            }

            // 3. Limpiar cache
            Cache::forget('system_instruction');

            session()->flash('message', 'Instrucción global guardada en Base de Datos.' . $fileStatus);
        } catch (\Exception $e) {
            \Log::error("Error al guardar configuración del bot: " . $e->getMessage());
            session()->flash('error', 'Error crítico al guardar en Base de Datos: ' . $e->getMessage());
        }
    }

    /**
     * Reemplaza todos los enlaces de Meet conocidos como viejos por el actual en la BD.
     * Se obtiene el enlace actual leyendo el training_schedule de la instrucción.
     */
    private function replaceAllOldLinks(string $text): string
    {
        // Lista de enlaces viejos de Meet que deben ser eliminados
        $oldLinks = [
            'noe-kvxm-wxq',
            'qcn-wfhf-gar',
        ];
        // Extraer el enlace ACTUAL del training_schedule (el que el usuario quiere usar)
        // Busca el patrón meet.google.com/xxx-xxxx-xxx en training_schedule
        preg_match_all('/meet\.google\.com\/([a-z]{3}-[a-z]{4}-[a-z]{3})/', $text, $matches);
        
        // Si hay un enlace que NO está en los viejos, ese es el nuevo
        $newLink = null;
        foreach (($matches[1] ?? []) as $found) {
            if (!in_array($found, $oldLinks)) {
                $newLink = $found;
                break;
            }
        }
        
        // Si no encontramos ningún enlace nuevo, no hacemos nada
        if (!$newLink) {
            return $text;
        }
        
        foreach ($oldLinks as $old) {
            $text = str_replace($old, $newLink, $text);
        }
        
        return $text;
    }

    /**
     * Detecta todos los enlaces viejos en la instrucción de la BD y los reemplaza
     * por el enlace actual. Guarda directamente en la BD y limpia el caché.
     */
    public function fixOldLinks()
    {
        $setting = BotSetting::find('system_instruction');
        if (!$setting) {
            session()->flash('error', 'No se encontró la instrucción en la base de datos.');
            return;
        }
        
        $oldLinks = ['noe-kvxm-wxq', 'qcn-wfhf-gar'];
        $originalText = $setting->value;
        
        // Obtener el enlace actual de la DB (el que el usuario quiere usar ahora)
        preg_match_all('/meet\.google\.com\/([a-z]{3}-[a-z]{4}-[a-z]{3})/', $originalText, $matches);
        
        $currentLink = null;
        $counts = [];
        foreach (($matches[1] ?? []) as $found) {
            $counts[$found] = ($counts[$found] ?? 0) + 1;
        }
        // El enlace vigente es el que más veces aparece (o el único que no es viejo)
        foreach ($counts as $link => $cnt) {
            if (!in_array($link, $oldLinks)) {
                $currentLink = $link;
                break;
            }
        }
        // Si todos son viejos, usamos el más frecuente
        if (!$currentLink && !empty($counts)) {
            arsort($counts);
            $currentLink = array_key_first($counts);
        }
        
        if (!$currentLink) {
            session()->flash('error', 'No se encontró ningún enlace de Meet en la instrucción.');
            return;
        }
        
        $totalFixed = 0;
        $newText = $originalText;
        foreach ($oldLinks as $old) {
            if ($old === $currentLink) continue;
            $cnt = substr_count($newText, $old);
            if ($cnt > 0) {
                $newText = str_replace($old, $currentLink, $newText);
                $totalFixed += $cnt;
            }
        }
        
        if ($totalFixed === 0) {
            session()->flash('message', '✅ No se encontraron enlaces antiguos. Todo está correcto.');
            return;
        }
        
        // Guardar en DB y limpiar caché
        $setting->update(['value' => $newText]);
        $this->systemInstruction = $newText;
        Cache::forget('system_instruction');
        
        session()->flash('message', "🚀 ¡Listo! Se corrigieron $totalFixed ocurrencias de enlaces antiguos. El bot ya usa el enlace correcto: meet.google.com/{$currentLink}");
    }

    public function render()
    {
        return view('livewire.bot-settings');
    }
}
