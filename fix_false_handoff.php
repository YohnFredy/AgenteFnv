<?php

/**
 * fix_false_handoff.php
 * Corrige el comportamiento del bot que hace handoff innecesario
 * cuando el usuario dice "vale", "ok", "sí", "perfecto", etc.
 *
 * ESTRATEGIA: Merge quirúrgico - solo toca las dos secciones problemáticas:
 *   1. Añade bloque <anti_false_handoff> al inicio de <operational_rules>
 *   2. Refina <meeting_safety_protocol> para exigir criterio explícito
 */

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\BotSetting;

$setting = BotSetting::find('system_instruction');
if (!$setting) {
    echo "ERROR: No se encontró 'system_instruction' en bot_settings.\n";
    exit(1);
}

$current = $setting->value;

// ─── PATCH 1: Añadir <anti_false_handoff> justo ANTES de <meeting_safety_protocol> ───
// Este bloque le enseña al bot a NO hacer handoff en confirmaciones banales.

$antiHandoffBlock = '
<anti_false_handoff>
**⚠️ REGLA CRÍTICA — PREVENCIÓN DE FALSOS HANDOFFS (LEE ESTO PRIMERO)**

Las siguientes respuestas del usuario son CONFIRMACIONES NORMALES de la conversación.
**NUNCA** las interpretes como una solicitud de asesor humano ni accioPEnes `[TRANSFER_TO_HUMAN]`:

❌ PALABRAS QUE **NO** ACTIVAN HANDOFF:
- "Vale", "ok", "okay", "sí", "si", "claro", "perfecto", "entendido", "listo"
- "Muchas gracias", "gracias", "de nada", "chévere", "excelente", "genial"
- "👍", "👋", "😊", reacciones con emoji (reactions de WhatsApp)
- Frases cortas de acuse de recibo: "qué bueno", "está bien", "ya veo"

✅ SOLO activa `[TRANSFER_TO_HUMAN]` en estos casos CONCRETOS Y EXPLÍCITOS:
1. El usuario dice LITERALMENTE: "quiero hablar con un humano", "comunícame con un asesor", "quiero un asesor real", "quiero hablar con una persona".
2. El usuario muestra molestia o frustración MUY evidente y sostenida (no una sola queja).
3. Problemas graves de pago confirmados por el usuario.
4. Temas legales explícitos.
5. El usuario confirma afirmativamente (sí/claro) DESPUÉS de que TÚ hayas preguntado: "¿Te gustaría que un asesor te contacte?". PERO solo si TÚ hiciste esa pregunta en respuesta a un caso de la lista anterior.

⚠️ TRAMPA COMÚN: Si en tu turno anterior le preguntaste "¿Te gustaría que un asesor te contacte?" SIN que hubiera un motivo válido de la lista anterior, entonces un "Sí" del usuario NO activa el handoff. Es un falso positivo que TÚ generaste. Continúa la conversación normalmente.
</anti_false_handoff>

';

// ─── PATCH 2: Refinar meeting_safety_protocol para no ser tan agresivo ───
$oldMeetingProtocol = '<meeting_safety_protocol>
    - **PROHIBICIÓN ESTRICTA DE CITAS**: NO tienes capacidad para agendar citas, reuniones personales ni definir horas específicas (ej: "2:15 PM").
    - **Uso de Enlace Meet**: El enlace de Google Meet (https://meet.google.com/qcn-wfhf-gar) es EXCLUSIVO para las presentaciones de Lunes y Miércoles a las 7:30 PM.
    - **NUNCA** envíes este enlace para reuniones fuera de ese horario.
    - **Protocolo de Agendamiento Personal**: 
        - Si el usuario pide una hora específica o "conectarnos ahora":
        - **Respuesta**: "Entiendo que quieres conectarte. Voy a informarle de inmediato a un asesor humano para que se comunique contigo lo antes posible y coordinen una reunión personalizada."
        - **Acción**: Incluir etiqueta `[TRANSFER_TO_HUMAN]`.
</meeting_safety_protocol>';

$newMeetingProtocol = '<meeting_safety_protocol>
    - **PROHIBICIÓN ESTRICTA DE CITAS**: NO tienes capacidad para agendar citas, reuniones personales ni definir horas específicas fuera del horario oficial.
    - **Uso de Enlace Meet**: El enlace de Google Meet (https://meet.google.com/qcn-wfhf-gar) es EXCLUSIVO para las presentaciones de Lunes y Miércoles a las 7:30 PM.
    - **NUNCA** envíes este enlace para reuniones fuera de ese horario.
    - **Protocolo de Agendamiento Personal ESTRICTO**: 
        - SOLO activa este protocolo si el usuario pide UNA HORA ESPECÍFICA fuera del horario oficial O dice "conectémonos ahora mismo" de forma explícita.
        - En ese caso PREGUNTA PRIMERO: "¿Te gustaría que un asesor se contacte contigo para coordinar una reunión personalizada?"
        - SOLO si responde afirmativamente (sí/claro/quiero), entonces responde con cortesía y agrega `[TRANSFER_TO_HUMAN]`.
        - **NO actives** este protocolo por frases como "gracias", "vale", "ok", "sí" en respuesta a información que tú enviaste. Eso es solo acuse de recibo.
</meeting_safety_protocol>';

// Aplicar parches
if (str_contains($current, $oldMeetingProtocol)) {
    // Insertar anti_false_handoff ANTES del bloque meeting_safety_protocol dentro de operational_rules
    $patched = str_replace(
        $oldMeetingProtocol,
        $antiHandoffBlock . $newMeetingProtocol,
        $current
    );
    echo "✅ Parche 1 (anti_false_handoff) aplicado.\n";
    echo "✅ Parche 2 (meeting_safety_protocol refinado) aplicado.\n";
} else {
    // Si el texto exacto no matchea (puede haber diferencias de espacio), intentar parcialmente
    echo "⚠️  No se encontró meeting_safety_protocol exacto. Aplicando solo anti_false_handoff...\n";

    // Insertar el bloque anti_false_handoff antes de </operational_rules>
    if (str_contains($current, '</operational_rules>')) {
        $patched = str_replace(
            '</operational_rules>',
            $antiHandoffBlock . '</operational_rules>',
            $current
        );
        echo "✅ Parche 1 (anti_false_handoff) aplicado antes de </operational_rules>.\n";
    } else {
        echo "❌ No se pudo aplicar el parche. Revisa la estructura del XML.\n";
        exit(1);
    }
}

// Actualizar versión: buscar el número de versión y aumentar patch
$patched = preg_replace_callback(
    '/knowledge_base version="(\d+)\.(\d+)\.(\d+)"/',
    function ($m) {
        return 'knowledge_base version="' . $m[1] . '.' . $m[2] . '.' . ((int)$m[3] + 1) . '"';
    },
    $patched
);

// Actualizar fecha
$patched = preg_replace(
    '/last_update="\d{4}-\d{2}-\d{2}"/',
    'last_update="' . date('Y-m-d') . '"',
    $patched
);

$setting->value = $patched;
$setting->save();

echo "✅ system_instruction actualizado correctamente en la base de datos.\n";
echo "📋 Versión y fecha actualizadas.\n";
echo "\n🔎 Verificación — Palabras sagradas presentes:\n";
echo (str_contains($patched, '[TRANSFER_TO_HUMAN]') ? "  ✅ [TRANSFER_TO_HUMAN] presente\n" : "  ❌ FALTA [TRANSFER_TO_HUMAN]\n");
echo (str_contains($patched, 'chat.whatsapp.com') ? "  ✅ Enlace Grupo WhatsApp presente\n" : "  ❌ POSIBLE FALTA enlace WhatsApp\n");
echo (str_contains($patched, 'anti_false_handoff') ? "  ✅ <anti_false_handoff> presente\n" : "  ❌ FALTA anti_false_handoff\n");
