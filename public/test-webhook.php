<?php

/**
 * TEST WEBHOOK - Para diagnosticar qué bloquea ModSecurity
 * URL: https://agente.fornuvi.com/test-webhook.php
 */

// Log todo lo que llega
$log = "=== " . date('Y-m-d H:i:s') . " ===\n";
$log .= "METHOD: " . $_SERVER['REQUEST_METHOD'] . "\n";
$log .= "HEADERS:\n";

foreach (getallheaders() as $name => $value) {
    $log .= "  $name: $value\n";
}

$log .= "BODY:\n";
$log .= file_get_contents('php://input') . "\n\n";

// Guardar log
file_put_contents(__DIR__ . '/../storage/logs/webhook-test.log', $log, FILE_APPEND);

// Responder OK
header('Content-Type: application/json');
echo json_encode(['status' => 'received', 'time' => date('Y-m-d H:i:s')]);
