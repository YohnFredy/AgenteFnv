<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\BotSetting;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $setting = BotSetting::find('system_instruction');

        if (!$setting) {
            return;
        }

        $currentInstruction = $setting->value;

        // Define the meeting safety protocol XML block
        $meetingProtocol = <<<'EOT'

<meeting_safety_protocol>
    - **PROHIBICIÓN ESTRICTA DE CITAS**: NO tienes capacidad para agendar citas, reuniones personales ni definir horas específicas (ej: "2:15 PM").
    - **Uso de Enlace Meet**: El enlace de Google Meet (https://meet.google.com/qcn-wfhf-gar) es EXCLUSIVO para las presentaciones de Lunes y Miércoles a las 7:30 PM.
    - **NUNCA** envíes este enlace para reuniones fuera de ese horario.
    - **Protocolo de Agendamiento Personal**: 
        - Si el usuario pide una hora específica o "conectarnos ahora":
        - **Respuesta**: "Entiendo que quieres conectarte. Voy a informarle de inmediato a un asesor humano para que se comunique contigo lo antes posible y coordinen una reunión personalizada."
        - **Acción**: Incluir etiqueta `[TRANSFER_TO_HUMAN]`.
</meeting_safety_protocol>
EOT;

        // Safe Merge Strategy: Inject inside <operational_rules> before it closes
        if (Str::contains($currentInstruction, '</operational_rules>')) {
            // Remove existing block if it exists to avoid duplication
            if (Str::contains($currentInstruction, '<meeting_safety_protocol>')) {
                $currentInstruction = preg_replace('/<meeting_safety_protocol>.*?<\/meeting_safety_protocol>/s', '', $currentInstruction);
            }

            // Insert new block
            $newInstruction = str_replace(
                '</operational_rules>',
                $meetingProtocol . "\n</operational_rules>",
                $currentInstruction
            );

            // Update version and date
            $newInstruction = preg_replace(
                '/last_update="\d{4}-\d{2}-\d{2}"/',
                'last_update="' . date('Y-m-d') . '"',
                $newInstruction
            );

            $setting->update(['value' => $newInstruction]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $setting = BotSetting::find('system_instruction');

        if (!$setting) {
            return;
        }

        $currentInstruction = $setting->value;

        // Remove the meeting_safety_protocol block
        if (Str::contains($currentInstruction, '<meeting_safety_protocol>')) {
            $newInstruction = preg_replace('/<meeting_safety_protocol>.*?<\/meeting_safety_protocol>/s', '', $currentInstruction);
            $setting->update(['value' => $newInstruction]);
        }
    }
};
