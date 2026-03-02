<?php

/**
 * fix_meeting_protocol.php
 * Segundo parche: Refina el meeting_safety_protocol para exigir
 * confirmación EXPLÍCITA antes de transferir a humano.
 */

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\BotSetting;

$setting = BotSetting::find('system_instruction');
$current = $setting->value;

// Reemplazar el bloque meeting_safety_protocol completo usando regex flexible
$pattern = '/<meeting_safety_protocol>.*?<\/meeting_safety_protocol>/s';

$newBlock = '<meeting_safety_protocol>
    - **PROHIBICIÓN ESTRICTA DE CITAS**: NO tienes capacidad para agendar citas, reuniones personales ni definir horas específicas fuera del horario oficial.
    - **Uso de Enlace Meet**: El enlace de Google Meet (https://meet.google.com/qcn-wfhf-gar) es EXCLUSIVO para las presentaciones de Lunes y Miércoles a las 7:30 PM.
    - **NUNCA** envíes este enlace para reuniones fuera de ese horario.
    - **Protocolo de Agendamiento Personal ESTRICTO**:
        - SOLO activa este protocolo si el usuario pide UNA HORA ESPECÍFICA fuera del horario oficial O dice "conectémonos ahora mismo" de forma explícita.
        - En ese caso PREGUNTA PRIMERO: "¿Te gustaría que un asesor se contacte contigo para coordinar una reunión personalizada?"
        - SOLO si responde afirmativamente (sí/claro/quiero) a esa pregunta específica, entonces responde con cortesía y agrega `[TRANSFER_TO_HUMAN]`.
        - **NO actives** este protocolo por frases como "gracias", "vale", "ok", "sí", "perfecto" en respuesta a información que tú enviaste. Eso es acuse de recibo normal.
        - **NO preguntes** "¿Te gustaría que un asesor te contacte?" si el usuario solo está confirmando lo que dijiste (vale, ok, perfecto, claro que sí). Continúa la conversación naturalmente.
</meeting_safety_protocol>';

$patched = preg_replace($pattern, $newBlock, $current);

if ($patched === $current) {
    echo "❌ No se pudo aplicar el parche regex. El bloque puede tener formato diferente.\n";
    exit(1);
}

$setting->value = $patched;
$setting->save();

echo "✅ meeting_safety_protocol actualizado correctamente.\n";
echo "\n🔎 Verificación:\n";
echo (str_contains($patched, '[TRANSFER_TO_HUMAN]') ? "  ✅ [TRANSFER_TO_HUMAN] presente\n" : "  ❌ FALTA [TRANSFER_TO_HUMAN]\n");
echo (str_contains($patched, 'anti_false_handoff') ? "  ✅ <anti_false_handoff> presente\n" : "  ❌ FALTA anti_false_handoff\n");
echo (str_contains($patched, 'acuse de recibo normal') ? "  ✅ Regla anti-falso-handoff en meeting_safety presente\n" : "  ❌ FALTA regla anti-falso-handoff\n");
