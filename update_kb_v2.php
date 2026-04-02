<?php
require 'vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$setting = App\Models\BotSetting::where('key', 'system_instruction')->first();
$xml = $setting->value;

// 1. Add new `<commercial_directory>` Scenario 4
$newDirectoryScenario4 = <<<XML
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

**RESTRICCIONES IMPORTANTES**:
XML;

$xml = str_replace("---

**RESTRICCIONES IMPORTANTES**:", $newDirectoryScenario4, $xml);


// 2. Fix references to "Oficina Virtual" for products to point to the main page menu instead.

// Replacement 1: Escenario 1
$xml = str_replace(
    'los Productos Naturales disponibles en tu Oficina Virtual (menú "Productos")',
    'los Productos Naturales disponibles en el menú principal de la página fornuvi.com (sección "Productos")',
    $xml
);

// Replacement 2: Escenario 2
$xml = str_replace(
    '**Productos Naturales en tu Oficina Virtual**: Disponibles en el menú "Productos"',
    '**Productos Naturales en la página principal**: Disponibles en el menú "Productos" de fornuvi.com',
    $xml
);

// Replacement 3: Activation Points definition
$xml = str_replace(
    'Acceso desde el menú "Productos" en tu Oficina Virtual.',
    'Acceso desde el menú "Productos" en la página principal fornuvi.com.',
    $xml
);

// Replacement 4: FAQ Optimized
$xml = str_replace(
    'los Productos Naturales de tu Oficina Virtual.',
    'los Productos Naturales desde el menú de la página principal.',
    $xml
);

// Update timestamp and version in knowledge_base tag
$xml = preg_replace('/<knowledge_base version="([^"]+)" last_update="([^"]+)">/', '<knowledge_base version="17.6.0" last_update="2026-03-05">', $xml);

$setting->value = $xml;
$setting->save();

echo "Base de conocimiento actualizada correctamente con la versión 17.6.0.\n";
