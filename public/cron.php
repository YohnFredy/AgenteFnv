<?php

/**
 * EJECUTOR DE COLAS PARA HOSTING COMPARTIDO
 * 
 * Este archivo reemplaza el cron job tradicional.
 * Configura tu cPanel para llamar a este archivo cada minuto:
 * 
 * URL: https://agente.fornuvi.com/cron.php?key=TU_CLAVE_SECRETA
 * 
 * En cPanel > Cron Jobs, usa:
 * wget -q -O /dev/null "https://agente.fornuvi.com/cron.php?key=fornuvi2026cron" >/dev/null 2>&1
 */

// Clave de seguridad - CAMBIA ESTO
$secretKey = 'fornuvi2026cron';

// Verificar clave
if (!isset($_GET['key']) || $_GET['key'] !== $secretKey) {
    http_response_code(403);
    die('Forbidden');
}

// Cambiar al directorio raíz
chdir(dirname(__DIR__));

// Ejecutar el scheduler de Laravel
$output = [];
exec('php artisan schedule:run 2>&1', $output);

// Log opcional (puedes comentar esto en producción)
if (isset($_GET['debug'])) {
    header('Content-Type: text/plain');
    echo implode("\n", $output);
} else {
    // Respuesta silenciosa para cron
    http_response_code(200);
    echo 'OK';
}
