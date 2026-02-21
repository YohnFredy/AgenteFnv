<?php

use App\Models\Chat;
use App\Models\Tag;
use Illuminate\Http\Request;

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';

// Boot Laravel
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$response = $kernel->handle(
    $request = Request::capture()
);

// Logic
$untaggedCount = Chat::doesntHave('tags')->where('is_active', false)->count(); // Focusing on 'human' chats usually, but let's do all?
// Better to just engage all chats that are untagged.
$untaggedCount = Chat::doesntHave('tags')->count();

$tags = Tag::where('is_active', true)->orderBy('name')->get();
$message = '';

if ($request->isMethod('post')) {
    $tagId = $request->input('tag_id');

    if ($tagId && $tag = Tag::find($tagId)) {
        $chats = Chat::doesntHave('tags')->get();
        $count = 0;

        foreach ($chats as $chat) {
            $chat->tags()->attach($tagId);
            $count++;
        }

        $message = "✅ Se asignó la etiqueta '{$tag->name}' a {$count} usuarios que no tenían ninguna etiqueta.";
        // Refresh count
        $untaggedCount = Chat::doesntHave('tags')->count();
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
    <title>Asignar Etiqueta a Sin Etiquetar</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-100 min-h-screen flex items-center justify-center">
    <div class="bg-white p-8 rounded-lg shadow-md max-w-md w-full">
        <h1 class="text-2xl font-bold mb-4 text-gray-800">🏷️ Autocompletar Etiquetas</h1>

        <div class="bg-blue-50 border-l-4 border-blue-500 p-4 mb-6">
            <p class="text-blue-700">Usuarios actuales sin ninguna etiqueta:</p>
            <p class="text-3xl font-bold text-blue-800"><?php echo $untaggedCount; ?></p>
        </div>

        <?php if ($message): ?>
            <div class="mb-6 p-4 rounded <?php echo str_contains($message, '✅') ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-4">
            <div>
                <label for="tag_id" class="block text-sm font-medium text-gray-700 mb-1">Selecciona la etiqueta a asignar:</label>
                <select name="tag_id" id="tag_id" class="w-full border-gray-300 rounded-md shadow-sm p-2 border focus:ring-blue-500 focus:border-blue-500">
                    <option value="">-- Seleccionar Etiqueta --</option>
                    <?php foreach ($tags as $tag): ?>
                        <option value="<?php echo $tag->id; ?>">
                            <?php echo htmlspecialchars($tag->name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <button type="submit"
                class="w-full bg-blue-600 text-white font-bold py-2 px-4 rounded hover:bg-blue-700 transition duration-150"
                onclick="return confirm('¿Estás seguro? Esto afectará a <?php echo $untaggedCount; ?> usuarios.')">
                🚀 Asignar a TODOS los sin etiqueta
            </button>
        </form>

        <p class="text-xs text-gray-500 mt-4 text-center">Herramienta temporal de mantenimiento.</p>
    </div>
</body>

</html>