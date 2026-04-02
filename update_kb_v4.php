<?php
require 'vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$setting = App\Models\BotSetting::where('key', 'system_instruction')->first();
$xml = $setting->value;

// 1. Reemplazamos la regla de Escalamiento actual, que era un disparador simple
$oldEscalationRule = <<<XML
- **Escalamiento**: Usar `[TRANSFER_TO_HUMAN]` ante:
    - Molestias graves del usuario.
    - Problemas de pagos serios.
    - Temas legales.
    - Solicitud explícita del usuario.
    - Confirmación positiva para contactar asesor de negocios.
XML;

$newEscalationRule = <<<XML
- **Protocolo Global de Escalamiento (ANTI-TRANSFERENCIA AUTOMÁTICA)**:
    - **REGLA ABSOLUTA**: Tienes ESTRICTAMENTE PROHIBIDO usar la etiqueta `[TRANSFER_TO_HUMAN]` de forma proactiva o automática, sin importar la situación.
    - Ante quejas, problemas de pagos, dudas legales, peticiones de citas privadas, o cualquier solicitud compleja, **NUNCA** apliques la etiqueta de inmediato en esa primera respuesta.
    - **Flujo Obligatorio**:
        1. Responde amablemente y ofrece enviar la solicitud a un asesor.
        2. Termina siempre con la pregunta concreta: "¿Deseas que le envíe el mensaje a un asesor para que te contacte?"
        3. **DETÉNTE**. NO agregues la etiqueta `[TRANSFER_TO_HUMAN]`.
        4. Solo aplica la etiqueta en el *siguiente* mensaje en el que el usuario haya respondido explícitamente "Sí", "Por favor", "Comunícame", etc.
XML;

$xml = str_replace($oldEscalationRule, $newEscalationRule, $xml);


// 2. Modificamos el agendamiento personal que también obligaba la transferencia automática
$oldMeetingProtocol = <<<XML
    - **Protocolo de Agendamiento Personal**: 
        - Si el usuario pide una hora específica o "conectarnos ahora":
        - **Respuesta**: "Entiendo que quieres conectarte. Voy a informarle de inmediato a un asesor humano para que se comunique contigo lo antes posible y coordinen una reunión personalizada."
        - **Acción**: Incluir etiqueta `[TRANSFER_TO_HUMAN]`.
XML;

$newMeetingProtocol = <<<XML
    - **Protocolo de Agendamiento Personal**: 
        - Si el usuario pide una hora específica o "conectarnos ahora":
        - **Respuesta**: "Entiendo que quieres conectarte. Sin embargo, no tengo capacidad para agendar citas privadas. Si lo deseas, puedo dejarle un mensaje a un asesor humano para que intenten coordinar un espacio contigo en otro momento. ¿Te gustaría que les envíe la solicitud?"
        - *(Esperar el 'Sí' como ordena la regla global de escalamiento)*.
XML;

$xml = str_replace($oldMeetingProtocol, $newMeetingProtocol, $xml);


// Update version
$xml = preg_replace('/<knowledge_base version="([^"]+)" last_update="([^"]+)">/', '<knowledge_base version="17.6.2" last_update="2026-03-05">', $xml);

$setting->value = $xml;
$setting->save();

echo "Base de conocimiento actualizada correctamente con la versión 17.6.2.\n";
