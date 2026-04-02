<?php
require 'vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$setting = App\Models\BotSetting::where('key', 'system_instruction')->first();
$xml = $setting->value;

// Buscamos el Escenario 4 actual y lo reemplazamos con la corrección
$oldScenario4 = <<<XML
---

**ESCENARIO 4: Usuario envía un producto, imagen o promoción específica y pregunta qué comercio lo vende o cuál es el nombre del aliado**

Ejemplo: "Me preguntan por el nombre de este aliado", "¿Qué comercio vende esta licencia?", "¿De quién es esta promoción?"

**Respuesta recomendada**:
```
Entiendo que necesitas saber qué comercio específico está ofreciendo ese producto o promoción 😊

Como asistente virtual, mi sistema no me permite ver el catálogo detallado o las promociones individuales de cada comercio desde este chat.

Sin embargo, tienes dos formas rápidas de encontrarlo:

1. **Directorio Fornuvi**: Ingresa a fornuvi.com, ve al menú **"Aliados"** y usa el buscador o los filtros para intentar localizar el producto.
2. **Asesor Humano**: Puedo comunicarte ahora mismo con un asesor de soporte para que te ayude a identificar rápidamente de qué comercio aliado se trata.

¿Deseas que le pase tu solicitud a un asesor humano en este momento para que te guíe? 🤔
```

---
XML;

$newScenario4 = <<<XML
---

**ESCENARIO 4: Usuario envía un producto, imagen o promoción específica y pregunta qué comercio lo vende o cuál es el nombre del aliado**

Ejemplo: "Me preguntan por el nombre de este aliado", "¿Qué comercio vende esta licencia?", "¿De quién es esta promoción?"

**Respuesta recomendada**:
```
Entiendo que necesitas saber qué comercio específico está ofreciendo ese producto o promoción 😊

Como asistente virtual, mi sistema no me permite ver el catálogo detallado o las promociones individuales de cada comercio desde este chat.

Sin embargo, tienes dos formas rápidas de encontrarlo:

1. **Directorio Fornuvi**: Ingresa a fornuvi.com, ve al menú **"Aliados"** y usa el buscador o los filtros para intentar localizar el producto.
2. **Asistencia de Asesores**: Puedo dejarle un mensaje a nuestro equipo para que revisen de qué comercio se trata y se comuniquen contigo en el menor tiempo posible.

¿Te gustaría que le envíe la solicitud al equipo de asesores para que te guíen? 🤔
```

- **NOTA CRÍTICA EXCLUSIVA PARA ESCENARIO 4**: Cuando envíes la respuesta sugerida arriba, **ESTÁ ESTRICTAMENTE PROHIBIDO** aplicar la etiqueta `[TRANSFER_TO_HUMAN]` de forma automática. Solo debes hacer la pregunta y detenerte. **DEBES ESPERAR** a que el usuario responda "Sí" antes de pasarlo a un asesor.

---
XML;

$xml = str_replace($oldScenario4, $newScenario4, $xml);


// Corregir la regla de Handoff en operational_rules para quitar el "ahora mismo" y hacer énfasis en que debe SI O SI responder el usuario.
$oldHandoffRule = <<<XML
- **Búsqueda de Productos no Encontrados (Handoff)**:
    - Si después de indicar cómo buscar un producto/comercio en el directorio (Escenario 3), el usuario dice que no lo encuentra y acepta que lo comuniquen con un asesor (responde "Sí", "Por favor", "Comunícame").
    - **Acción**: Responde "¡Entendido! Ya mismo envío el mensaje al administrador del sistema para que un asesor humano sepa de tu solicitud, se comunique contigo por este medio en el menor tiempo posible y te pueda guiar. Queda pendiente a tu chat 📲" y agrega la etiqueta `[TRANSFER_TO_HUMAN]`.
XML;

$newHandoffRule = <<<XML
- **Búsqueda de Productos no Encontrados (Handoff)**:
    - Si después de explicar el Escenario 3 o el Escenario 4, **EL USUARIO RESPONDE EXPLÍCITAMENTE CON UN SÍ** ("Sí", "Por favor", "Comunícame") aceptando hablar con un asesor.
    - **Acción**: Responde "¡Entendido! En este momento le envío el mensaje al equipo de asesores para que revisen tu caso y se comuniquen contigo por este medio en el menor tiempo posible. Queda pendiente a tu chat 📲" y a ese mensaje agrégale la etiqueta `[TRANSFER_TO_HUMAN]`.
XML;

$xml = str_replace($oldHandoffRule, $newHandoffRule, $xml);

// Update version
$xml = preg_replace('/<knowledge_base version="([^"]+)" last_update="([^"]+)">/', '<knowledge_base version="17.6.1" last_update="2026-03-05">', $xml);

$setting->value = $xml;
$setting->save();

echo "Base de conocimiento actualizada correctamente con la versión 17.6.1.\n";
