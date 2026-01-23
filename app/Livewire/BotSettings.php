<?php

namespace App\Livewire;

use App\Models\BotSetting;
use Livewire\Component;

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
        BotSetting::updateOrCreate(
            ['key' => 'system_instruction'],
            ['value' => $this->systemInstruction]
        );

        session()->flash('message', 'Instrucción global guardada correctamente.');
    }

    public function render()
    {
        return view('livewire.bot-settings');
    }
}
