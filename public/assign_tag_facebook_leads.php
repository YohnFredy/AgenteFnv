<?php

use App\Models\Chat;
use App\Models\Tag;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';

// Boot Laravel
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$response = $kernel->handle(
    $request = Request::capture()
);

$hours = 72;
$targetPhrases = [
    '¡Hola! Quiero más información',
    '[Mensaje no disponible - Error 131060]',
    '[Unsupported Message]'
];

// Query Logic:
// 1. Chats updated in last 72 hours
// 2. AND have at least one message in last 72h with specific content
$query = Chat::where('updated_at', '>=', now()->subHours($hours))
    ->whereHas('messages', function (Builder $q) use ($hours, $targetPhrases) {
        $q->where('created_at', '>=', now()->subHours($hours))
            ->where(function ($subQ) use ($targetPhrases) {
                foreach ($targetPhrases as $phrase) {
                    $subQ->orWhere('content', 'LIKE', '%' . $phrase . '%');
                }
            });
    });

$count = $query->count();
$tags = Tag::where('is_active', true)->orderBy('name')->get();
$message = '';

if ($request->isMethod('post')) {
    $tagId = $request->input('tag_id');

    if ($tagId && $tag = Tag::find($tagId)) {
        // Ejecutar query
        $chats = $query->get();
        $processed = 0;

        foreach ($chats as $chat) {
            $chat->tags()->syncWithoutDetaching([$tagId]);
            $processed++;
        }

        $message = "✅ Se asignó la etiqueta '{$tag->name}' a {$processed} usuarios encontrados.";
        // Refresh count (might remain same if we don't exclude already tagged, but user just wants to ensure they have it)
        $count = $query->count();
    } else {
        $message = "❌ Error: Debes seleccionar una etiqueta válida.";
    }
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Etiquetar Leads Facebook (72h)</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-100 min-h-screen flex items-center justify-center">
    <div class="bg-white p-8 rounded-lg shadow-md max-w-lg w-full">
        <h1 class="text-2xl font-bold mb-4 text-gray-800">🏷️ Etiquetar Leads de Facebook</h1>

        <div class="mb-4 text-sm text-gray-600 bg-gray-50 p-3 rounded">
            <p class="font-bold">Criterios de búsqueda:</p>
            <ul class="list-disc pl-5 mt-1 space-y-1">
                <li>Actividad en las últimas <strong>72 horas</strong>.</li>
                <li>Mensaje contiene:
                    <ul class="list-circle pl-5 text-xs text-gray-500">
                        <?php foreach ($targetPhrases as $phrase): ?>
                            <li>"<?php echo htmlspecialchars($phrase); ?>"</li>
                        <?php endforeach; ?>
                    </ul>
                </li>
            </ul>
        </div>

        <div class="bg-indigo-50 border-l-4 border-indigo-500 p-4 mb-6">
            <p class="text-indigo-700">Usuarios encontrados:</p>
            <p class="text-4xl font-bold text-indigo-900"><?php echo $count; ?></p>
        </div>

        <?php if ($message): ?>
            <div class="mb-6 p-4 rounded <?php echo str_contains($message, '✅') ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-4">
            <div>
                <label for="tag_id" class="block text-sm font-medium text-gray-700 mb-1">Selecciona la etiqueta a asignar:</label>
                <select name="tag_id" id="tag_id" class="w-full border-gray-300 rounded-md shadow-sm p-2 border focus:ring-indigo-500 focus:border-indigo-500">
                    <option value="">-- Seleccionar Etiqueta --</option>
                    <?php foreach ($tags as $tag): ?>
                        <option value="<?php echo $tag->id; ?>">
                            <?php echo htmlspecialchars($tag->name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <button type="submit"
                class="w-full bg-indigo-600 text-white font-bold py-3 px-4 rounded hover:bg-indigo-700 transition duration-150 flex justify-center items-center gap-2"
                <?php if ($count == 0): ?> disabled class="w-full bg-gray-400 text-white font-bold py-3 px-4 rounded cursor-not-allowed" <?php endif; ?>
                onclick="return confirm('¿Asignar etiqueta a estos <?php echo $count; ?> usuarios?')">
                ✨ Procesar Usuarios
            </button>
        </form>

        <p class="text-xs text-gray-500 mt-4 text-center">Herramienta temporal.</p>
    </div>
</body>

</html>