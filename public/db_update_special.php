<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Script de Actualización de Base de Datos para Producción (Sin Terminal-SSH)
 * 
 * Uso: https://tu-dominio.com/db_update_special.php?key=fornuvi2026update
 */

// 1. Cargar el entorno de Laravel
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// 2. Seguridad simple
$secretKey = 'fornuvi2026update';
if (!isset($_GET['key']) || $_GET['key'] !== $secretKey) {
    die("Acceso denegado. Clave incorrecta.");
}

echo "<h1>Actualizando Base de Datos...</h1><pre>";

try {
    // 3. CAMBIO 1: Agregar remove_tag_id
    if (!Schema::hasColumn('automation_rules', 'remove_tag_id')) {
        echo "Agregando columna 'remove_tag_id' a 'automation_rules'...\n";
        Schema::table('automation_rules', function (Blueprint $table) {
            $table->foreignId('remove_tag_id')->nullable()->constrained('tags')->nullOnDelete()->after('tag_id');
        });
        echo "OK: Columna 'remove_tag_id' agregada.\n";
    } else {
        echo "SKIP: La columna 'remove_tag_id' ya existe.\n";
    }

    // 4. CAMBIO 2: Agregar followup_delay_hours
    if (!Schema::hasColumn('automation_rules', 'followup_delay_hours')) {
        echo "Agregando columna 'followup_delay_hours' a 'automation_rules'...\n";
        Schema::table('automation_rules', function (Blueprint $table) {
            $table->integer('followup_delay_hours')->default(0)->after('match_type');
        });
        echo "OK: Columna 'followup_delay_hours' agregada.\n";
    } else {
        echo "SKIP: La columna 'followup_delay_hours' ya existe.\n";
    }

    echo "\n\n--- ACTUALIZACIÓN COMPLETADA EXITOSAMENTE ---";
} catch (\Exception $e) {
    echo "ERROR CRÍTICO: " . $e->getMessage();
}

echo "</pre>";
