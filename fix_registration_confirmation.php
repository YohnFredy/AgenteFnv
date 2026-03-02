<?php

/**
 * fix_registration_confirmation.php
 * 
 * PROBLEMA: El bot interpreta "vale", "muy bien", "ok" como si el 
 * usuario estuviera confirmando que ya se registró, y le envía el 
 * enlace del grupo de WhatsApp prematuramente.
 *
 * SOLUCIÓN: Añadir reglas estrictas sobre qué frases SÍ son confirmación
 * de registro válida y cuáles son simples acuses de recibo.
 */

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\BotSetting;

$setting = BotSetting::find('system_instruction');
if (!$setting) {
    echo "ERROR: No se encontró 'system_instruction'.\n";
    exit(1);
}

$current = $setting->value;

// ─── BLOQUE A INSERTAR ────────────────────────────────────────────────────────
// Añadir justo ANTES de </operational_rules> (junto al anti_false_handoff ya existente)

$registrationConfirmationRules = '
<anti_false_registration>
**⚠️ REGLA CRÍTICA — CONFIRMACIÓN DE REGISTRO (LECTURA OBLIGATORIA)**

El enlace del Grupo Oficial de WhatsApp y el video de estrategia post-registro 
SOLO pueden enviarse si el usuario confirma su registro con frases EXPLÍCITAS Y CLARAS.

✅ FRASES QUE **SÍ** SON CONFIRMACIÓN DE REGISTRO VÁLIDA:
- "Ya me registré"
- "Ya completé el registro"
- "Ya lo hice", "Ya lo hice Fredy"
- "Listo, ya me registré"
- "Ya tengo cuenta"
- "Ya me inscribí"
- "Ya quedé registrado"
- "Terminé el registro"
- "Hice el registro"
- "Me acabo de registrar"

❌ FRASES QUE **NO** SON CONFIRMACIÓN DE REGISTRO:
- "Vale", "ok", "muy bien", "perfecto", "listo", "entendido" — son acuses de recibo
- "Buenas tardes", "hola", "claro que sí" — son saludos o confirmaciones genéricas
- "Esta noche hago el registro", "haré el registro" — son compromisos FUTUROS, no confirmaciones
- "Me alegra", "qué bueno", "gracias" — son respuestas de cortesía

⚠️ CASO CLAVE — COMPROMISO FUTURO:
Si el usuario dice "esta noche hago el registro", "mañana me registro" o similar,
el bot DEBE responder con ánimo y recordatorio de espera:
"¡Perfecto! Quedo atento. Avísame cuando hayas completado el registro para enviarte el acceso al grupo. 😊"
**NO** envíes el grupo ni el video de estrategia todavía.

📋 REGLA DE ORO: Si tienes duda de si el usuario ya se registró, PREGUNTA directamente:
"¿Ya pudiste completar tu registro?"
Espera la respuesta antes de enviar cualquier recurso post-registro.
</anti_false_registration>

';

// Insertar antes de </operational_rules>
if (str_contains($current, '</operational_rules>')) {
    $patched = str_replace(
        '</operational_rules>',
        $registrationConfirmationRules . '</operational_rules>',
        $current
    );
    echo "✅ Bloque <anti_false_registration> insertado en operational_rules.\n";
} else {
    echo "❌ No se encontró </operational_rules>. Revisión manual requerida.\n";
    exit(1);
}

// Actualizar versión patch
$patched = preg_replace_callback(
    '/knowledge_base version="(\d+)\.(\d+)\.(\d+)"/',
    fn($m) => 'knowledge_base version="' . $m[1] . '.' . $m[2] . '.' . ((int)$m[3] + 1) . '"',
    $patched
);
$patched = preg_replace('/last_update="\d{4}-\d{2}-\d{2}"/', 'last_update="' . date('Y-m-d') . '"', $patched);

$setting->value = $patched;
$setting->save();

echo "✅ system_instruction actualizado en la base de datos.\n";
echo "\n🔎 Verificación — Palabras sagradas presentes:\n";
echo (str_contains($patched, '[TRANSFER_TO_HUMAN]')       ? "  ✅ [TRANSFER_TO_HUMAN] presente\n"        : "  ❌ FALTA [TRANSFER_TO_HUMAN]\n");
echo (str_contains($patched, 'chat.whatsapp.com')         ? "  ✅ Enlace Grupo WhatsApp presente\n"        : "  ❌ POSIBLE FALTA enlace WhatsApp\n");
echo (str_contains($patched, 'anti_false_handoff')        ? "  ✅ <anti_false_handoff> presente\n"         : "  ❌ FALTA anti_false_handoff\n");
echo (str_contains($patched, 'anti_false_registration')   ? "  ✅ <anti_false_registration> presente\n"    : "  ❌ FALTA anti_false_registration\n");
echo (str_contains($patched, 'compromiso FUTURO')         ? "  ✅ Regla compromiso futuro presente\n"      : "  ❌ FALTA regla compromiso futuro\n");
