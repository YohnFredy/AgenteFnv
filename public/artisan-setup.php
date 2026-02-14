<?php

/**
 * ARCHIVO DE CONFIGURACIÓN INICIAL PARA PRODUCCIÓN
 * 
 * USO: https://agente.fornuvi.com/artisan-setup.php?key=TU_CLAVE_SECRETA
 * 
 * ⚠️ IMPORTANTE: Después de usar, ELIMINA este archivo del servidor por seguridad.
 */

// Clave de seguridad - CAMBIA ESTO por algo único
$secretKey = 'fornuvi2026setup';

// Verificar clave
if (!isset($_GET['key']) || $_GET['key'] !== $secretKey) {
    http_response_code(403);
    die('❌ Acceso denegado. Usa: ?key=TU_CLAVE_SECRETA');
}

// Cambiar al directorio raíz del proyecto
chdir(dirname(__DIR__));

echo "<pre style='font-family: monospace; background: #1a1a2e; color: #0f0; padding: 20px; border-radius: 10px;'>";
echo "🚀 CONFIGURACIÓN DE PRODUCCIÓN - FORNUVI AGENTE\n";
echo "================================================\n\n";

// Función para ejecutar comandos Artisan
function runArtisan($command)
{
    echo "▶ Ejecutando: php artisan {$command}\n";
    $output = [];
    $returnCode = 0;
    exec("php artisan {$command} 2>&1", $output, $returnCode);
    echo implode("\n", $output) . "\n";
    echo ($returnCode === 0 ? "✅ OK" : "❌ Error (código: {$returnCode})") . "\n\n";
    return $returnCode === 0;
}

// 1. Limpiar cachés existentes
echo "📦 PASO 1: Limpiando cachés...\n";
runArtisan('config:clear');
runArtisan('cache:clear');
runArtisan('route:clear');
runArtisan('view:clear');

// 2. Ejecutar migraciones
echo "📦 PASO 2: Ejecutando migraciones...\n";
runArtisan('migrate --force');

// 3. Crear tabla de jobs si no existe
echo "📦 PASO 3: Verificando tabla de jobs...\n";
runArtisan('queue:table 2>&1 || echo "Tabla ya existe"');
runArtisan('migrate --force');

// 4. Link de storage
echo "📦 PASO 4: Creando enlace de storage...\n";
runArtisan('storage:link');

// 5. Cachear configuración para producción
echo "📦 PASO 5: Cacheando para producción...\n";
runArtisan('config:cache');
runArtisan('route:cache');
runArtisan('view:cache');

echo "\n================================================\n";
echo "✅ CONFIGURACIÓN COMPLETADA\n";
echo "================================================\n";
echo "\n⚠️ IMPORTANTE: ELIMINA ESTE ARCHIVO (artisan-setup.php) DEL SERVIDOR\n";
echo "</pre>";
