<?php
require 'vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$setting = App\Models\BotSetting::where('key', 'system_instruction')->first();
$xml = $setting->value;

// Add new `<commercial_directory>` Scenario 3
$newDirectoryScenario = <<<XML
---

**ESCENARIO 3: Usuario pregunta qué comercio vende un producto o servicio específico, o si en su ciudad lo venden**

Ejemplo: "¿Qué comercio vende zapatos?", "¿Alguien vende celulares en Cali?", "¿Dónde compro arroz?"

**Respuesta recomendada**:
```
Para ayudarte a encontrar exactamente lo que necesitas, te invito a explorar nuestro directorio oficial de la siguiente manera 😊

1. Ingresa a fornuvi.com
2. En el menú principal, haz clic en **"Aliados"**.
3. Allí puedes usar los filtros para buscar por **producto, categoría, subcategoría, país, departamento y ciudad**.
4. Si necesitas hacer una nueva búsqueda, solo dale clic al botón **"Limpiar filtro"** y empieza de nuevo.

**Dos datos importantes:**
• La mayoría de comercios en nuestra sección de Aliados tienen la capacidad de enviar productos a cualquier parte del país 📦
• Si buscas un **producto natural**, haz clic en el enlace **"Productos"** del menú principal. Allí encontrarás una serie de excelentes opciones disponibles para enviar a todo el país 🌿

Si después de buscar el comercio o producto en la plataforma no logras encontrarlo, ¿te gustaría que te comunique con un asesor para que te guíe de forma personalizada en el menor tiempo posible? 🤔
```

---

**RESTRICCIONES IMPORTANTES**:
XML;

$xml = str_replace("---

**RESTRICCIONES IMPORTANTES**:", $newDirectoryScenario, $xml);


// Add the handoff rule specifically for product search in `<operational_rules>`
$newHandoffRule = <<<XML
- **Búsqueda de Productos no Encontrados (Handoff)**:
    - Si después de indicar cómo buscar un producto/comercio en el directorio (Escenario 3), el usuario dice que no lo encuentra y acepta que lo comuniquen con un asesor (responde "Sí", "Por favor", "Comunícame").
    - **Acción**: Responde "¡Entendido! Ya mismo envío el mensaje al administrador del sistema para que un asesor humano sepa de tu solicitud, se comunique contigo por este medio en el menor tiempo posible y te pueda guiar. Queda pendiente a tu chat 📲" y agrega la etiqueta `[TRANSFER_TO_HUMAN]`.

- **Escalamiento**: Usar `[TRANSFER_TO_HUMAN]` ante:
XML;

$xml = str_replace("- **Escalamiento**: Usar `[TRANSFER_TO_HUMAN]` ante:", $newHandoffRule, $xml);


// Update timestamp and version in knowledge_base tag
$xml = preg_replace('/<knowledge_base version="([^"]+)" last_update="([^"]+)">/', '<knowledge_base version="17.5.0" last_update="2026-03-05">', $xml);

$setting->value = $xml;
$setting->save();

echo "Base de conocimiento actualizada correctamente con la versión 17.5.0.\n";
