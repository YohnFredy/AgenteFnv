<?php
// Diagnóstico sin Laravel - Solo PHP + MySQL directo
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Configuración - Ajusta estos valores si es necesario
$dbHost = 'localhost';
$dbName = 'fornuvi_agente'; // Ajustar según tu BD
$dbUser = 'root'; // Ajustar según tu usuario
$dbPass = ''; // Ajustar según tu password

// Intenta leer del .env si existe
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $envContent = file_get_contents($envFile);
    if (preg_match('/DB_HOST=(.+)/', $envContent, $m)) $dbHost = trim($m[1]);
    if (preg_match('/DB_DATABASE=(.+)/', $envContent, $m)) $dbName = trim($m[1]);
    if (preg_match('/DB_USERNAME=(.+)/', $envContent, $m)) $dbUser = trim($m[1]);
    if (preg_match('/DB_PASSWORD=(.+)/', $envContent, $m)) $dbPass = trim($m[1]);
}

$conn = null;
$dbError = null;

try {
    $conn = new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    $dbError = $e->getMessage();
}
?>
<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>Diagnóstico Bot</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 900px;
            margin: 20px auto;
            padding: 20px;
            background: #f5f5f5;
        }

        .card {
            background: white;
            padding: 15px;
            margin: 15px 0;
            border-radius: 5px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        h1,
        h2 {
            color: #333;
        }

        .ok {
            color: #28a745;
            font-weight: bold;
        }

        .error {
            color: #dc3545;
            font-weight: bold;
        }

        .warning {
            color: #ffc107;
            font-weight: bold;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        th,
        td {
            padding: 8px;
            text-align: left;
            border-bottom: 1px solid #ddd;
            font-size: 14px;
        }

        th {
            background: #f8f9fa;
            font-weight: bold;
        }

        pre {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 3px;
            overflow-x: auto;
            font-size: 12px;
        }
    </style>
</head>

<body>
    <h1>🔍 Diagnóstico del Bot WhatsApp</h1>
    <p><strong>Generado:</strong> <?php echo date('Y-m-d H:i:s'); ?></p>

    <!-- Estado de Conexión -->
    <div class="card">
        <h2>🔌 Conexión a Base de Datos</h2>
        <?php if ($conn): ?>
            <p class="ok">✅ Conectado a: <?php echo htmlspecialchars($dbName); ?></p>
        <?php else: ?>
            <p class="error">❌ Error de conexión</p>
            <pre><?php echo htmlspecialchars($dbError); ?></pre>
            <p><strong>Verifica:</strong> Host: <?php echo htmlspecialchars($dbHost); ?>, DB: <?php echo htmlspecialchars($dbName); ?></p>
        <?php endif; ?>
    </div>

    <?php if ($conn): ?>

        <!-- Estadísticas Generales -->
        <div class="card">
            <h2>📊 Estadísticas</h2>
            <?php
            try {
                $stmt = $conn->query("SELECT COUNT(*) as total FROM chats");
                $totalChats = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

                $stmt = $conn->query("SELECT COUNT(*) as total FROM chats WHERE is_active = 1");
                $activeChats = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

                $stmt = $conn->query("SELECT COUNT(*) as total FROM messages");
                $totalMessages = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

                echo "<table>";
                echo "<tr><td><strong>Total Chats:</strong></td><td>$totalChats</td></tr>";
                echo "<tr><td><strong>Chats Activos:</strong></td><td class='ok'>$activeChats</td></tr>";
                echo "<tr><td><strong>Total Mensajes:</strong></td><td>$totalMessages</td></tr>";
                echo "</table>";
            } catch (PDOException $e) {
                echo "<p class='error'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
            }
            ?>
        </div>

        <!-- Jobs Pendientes -->
        <div class="card">
            <h2>⏳ Jobs en Cola</h2>
            <?php
            try {
                $stmt = $conn->query("SELECT COUNT(*) as total FROM jobs");
                $pendingJobs = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

                if ($pendingJobs > 0) {
                    echo "<p class='warning'>⚠️ <strong>$pendingJobs</strong> jobs pendientes en la cola</p>";
                    echo "<p><em>Esto sugiere que el queue worker NO está corriendo</em></p>";
                } else {
                    echo "<p class='ok'>✅ No hay jobs pendientes</p>";
                }
            } catch (PDOException $e) {
                echo "<p class='error'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
            }
            ?>
        </div>

        <!-- Jobs Fallidos -->
        <div class="card">
            <h2>❌ Jobs Fallidos (Últimas 24 horas)</h2>
            <?php
            try {
                $yesterday = date('Y-m-d H:i:s', strtotime('-1 day'));
                $stmt = $conn->prepare("SELECT COUNT(*) as total FROM failed_jobs WHERE failed_at >= ?");
                $stmt->execute([$yesterday]);
                $failedCount = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

                if ($failedCount > 0) {
                    echo "<p class='error'>❌ <strong>$failedCount</strong> jobs fallidos</p>";

                    $stmt = $conn->prepare("SELECT failed_at, exception FROM failed_jobs WHERE failed_at >= ? ORDER BY failed_at DESC LIMIT 3");
                    $stmt->execute([$yesterday]);
                    $failed = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    echo "<table><tr><th>Hora</th><th>Exception</th></tr>";
                    foreach ($failed as $f) {
                        echo "<tr>";
                        echo "<td>" . htmlspecialchars($f['failed_at']) . "</td>";
                        echo "<td><pre>" . htmlspecialchars(substr($f['exception'], 0, 200)) . "...</pre></td>";
                        echo "</tr>";
                    }
                    echo "</table>";
                } else {
                    echo "<p class='ok'>✅ No hay jobs fallidos</p>";
                }
            } catch (PDOException $e) {
                echo "<p class='error'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
            }
            ?>
        </div>

        <!-- Mensajes Sin Respuesta -->
        <div class="card">
            <h2>⚠️ Mensajes Sin Respuesta (Últimas 2 horas)</h2>
            <?php
            try {
                $twoHoursAgo = date('Y-m-d H:i:s', strtotime('-2 hours'));

                $sql = "
                    SELECT 
                        c.name,
                        c.remote_jid,
                        m.content,
                        m.created_at
                    FROM messages m
                    INNER JOIN chats c ON m.chat_id = c.id
                    WHERE m.role = 'user'
                    AND m.created_at >= ?
                    AND c.is_active = 1
                    AND NOT EXISTS (
                        SELECT 1 FROM messages m2 
                        WHERE m2.chat_id = c.id 
                        AND m2.role = 'assistant' 
                        AND m2.created_at > m.created_at
                    )
                    ORDER BY m.created_at DESC
                    LIMIT 10
                ";

                $stmt = $conn->prepare($sql);
                $stmt->execute([$twoHoursAgo]);
                $unanswered = $stmt->fetchAll(PDO::FETCH_ASSOC);

                if (count($unanswered) > 0) {
                    echo "<p class='error'>⚠️ Encontrados <strong>" . count($unanswered) . "</strong> mensajes sin respuesta</p>";
                    echo "<table><tr><th>Chat</th><th>JID</th><th>Mensaje</th><th>Hora</th></tr>";
                    foreach ($unanswered as $u) {
                        echo "<tr>";
                        echo "<td>" . htmlspecialchars($u['name']) . "</td>";
                        echo "<td>" . htmlspecialchars($u['remote_jid']) . "</td>";
                        echo "<td>" . htmlspecialchars(substr($u['content'], 0, 40)) . "...</td>";
                        echo "<td>" . date('H:i', strtotime($u['created_at'])) . "</td>";
                        echo "</tr>";
                    }
                    echo "</table>";
                } else {
                    echo "<p class='ok'>✅ Todos los mensajes recientes tienen respuesta</p>";
                }
            } catch (PDOException $e) {
                echo "<p class='error'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
            }
            ?>
        </div>

        <!-- Últimos 10 Mensajes -->
        <div class="card">
            <h2>💬 Últimos 10 Mensajes</h2>
            <?php
            try {
                $stmt = $conn->query("
                    SELECT m.created_at, c.name, m.role, m.content, m.whatsapp_id
                    FROM messages m
                    LEFT JOIN chats c ON m.chat_id = c.id
                    ORDER BY m.created_at DESC
                    LIMIT 10
                ");
                $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

                echo "<table><tr><th>Hora</th><th>Chat</th><th>Rol</th><th>Mensaje</th><th>WA ID</th></tr>";
                foreach ($messages as $m) {
                    echo "<tr>";
                    echo "<td>" . date('H:i:s', strtotime($m['created_at'])) . "</td>";
                    echo "<td>" . htmlspecialchars($m['name'] ?: 'N/A') . "</td>";
                    echo "<td>" . htmlspecialchars($m['role']) . "</td>";
                    echo "<td>" . htmlspecialchars(substr($m['content'] ?: '', 0, 35)) . "...</td>";
                    echo "<td>" . ($m['whatsapp_id'] ? '✓' : '✗') . "</td>";
                    echo "</tr>";
                }
                echo "</table>";
            } catch (PDOException $e) {
                echo "<p class='error'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
            }
            ?>
        </div>

    <?php endif; ?>

    <div class="card">
        <p><em>💡 Tip: Refresca esta página para ver datos actualizados</em></p>
        <p><em>🔄 Última actualización: <?php echo date('Y-m-d H:i:s'); ?></em></p>
    </div>
</body>

</html>