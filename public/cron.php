<?php

/**
 * EJECUTOR DE COLAS PARA HOSTING COMPARTIDO
 * 
 * Este archivo reemplaza el cron job tradicional y sirve como
 * trigger asíncrono para mensajes instantáneos sin saturar el servidor.
 * 
 * URL: https://agente.fornuvi.com/cron.php?key=TU_CLAVE_SECRETA
 */

// Clave de seguridad - CAMBIA ESTO
$secretKey = 'fornuvi2026cron';

// Evitar que el proceso muera si el cliente se desconecta (por el timeout de 1 segundo)
ignore_user_abort(true);
set_time_limit(120); // Permitir que corra por hasta 2 minutos

// Verificar clave
if (!isset($_GET['key']) || $_GET['key'] !== $secretKey) {
    http_response_code(403);
    die('Forbidden');
}

// Cambiar al directorio raíz
chdir(dirname(__DIR__));

// Bootstrap Laravel para usar sus funciones sin exec()
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// --- SISTEMA ANTI-COLAPSOS PARA HOSTGATOR ---
// Previene que se abran múltiples procesos de 55 segundos al mismo tiempo
// y agoten la memoria RAM / Límite de conexiones (EP) de tu Hosting compartido.
$lockFile = storage_path('framework/cron-worker.lock');

if (file_exists($lockFile) && (time() - filemtime($lockFile)) < 50) {
    // Ya hay un trabajador despierto esperando mensajes. No creamos otro.
    if (isset($_GET['debug'])) echo "Worker already running.";
    exit;
}
// Renovar o crear el archivo de bloqueo (lock) con la fecha actual
touch($lockFile);
// --------------------------------------------

try {
    // 1. Ejecutar el scheduler
    \Illuminate\Support\Facades\Artisan::call('schedule:run');
    $output = \Illuminate\Support\Facades\Artisan::output();

    // 2. Ejecutar la cola para procesar los mensajes de WhatsApp
    // Usamos --max-time=55 para que el worker quede vivo casi 1 minuto esperando los mensajes.
    // Esto es vital porque los mensajes de WhatsApp tienen un delay aleatorio de 2-15 segs
    \Illuminate\Support\Facades\Artisan::call('queue:work', [
        '--max-time' => 55,
        '--tries' => 3,
        '--timeout' => 90
    ]);
    $output .= "\n" . \Illuminate\Support\Facades\Artisan::output();

} catch (\Exception $e) {
    $output = "Error: " . $e->getMessage();
}

// Log opcional (puedes comentar esto en producción)
if (isset($_GET['debug'])) {
    header('Content-Type: text/plain');
    echo implode("\n", explode("\n", $output));
} else {
    // Respuesta silenciosa para cron
    http_response_code(200);
    echo 'OK';
}
