<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';

// Manually trigger the framework to boot so we can use Facades
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$response = $kernel->handle(
    $request = Illuminate\Http\Request::capture()
);

echo "<pre>";
echo "<h2>🛠️ Actualizando tabla 'messages'...</h2>";

if (Schema::hasTable('messages')) {
    Schema::table('messages', function (Blueprint $table) {
        $added = [];

        if (!Schema::hasColumn('messages', 'type')) {
            $table->string('type')->nullable()->after('content');
            $added[] = 'type';
        }

        if (!Schema::hasColumn('messages', 'status')) {
            $table->string('status')->nullable()->after('type'); // e.g., 'sent', 'delivered', 'read', 'failed'
            $added[] = 'status';
        }

        if (!Schema::hasColumn('messages', 'metadata')) {
            $table->json('metadata')->nullable()->after('status'); // Additional info
            $added[] = 'metadata';
        }

        if (!empty($added)) {
            echo "✅ Campos agregados: " . implode(', ', $added) . "\n";
        } else {
            echo "ℹ️ Los campos ya existían.\n";
        }
    });

    echo "<h3>Todo listo 🚀</h3>";
} else {
    echo "❌ Error: La tabla 'messages' no existe.";
}
echo "</pre>";
