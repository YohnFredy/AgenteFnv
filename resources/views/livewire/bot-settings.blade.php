<div class="max-w-4xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
    <div class="bg-white dark:bg-zinc-900 overflow-hidden shadow-sm sm:rounded-xl p-4 sm:p-6 border dark:border-zinc-700">
        <h1 class="text-xl sm:text-2xl font-bold mb-3 text-gray-800 dark:text-gray-100">⚙️ Configuración del Bot</h1>

        <p class="mb-6 text-sm sm:text-base text-gray-600 dark:text-gray-400">
            Define aquí la instrucción maestra. La IA actuará basada en este texto para
            <strong class="text-gray-800 dark:text-gray-200">todos</strong> los usuarios que escriban.
        </p>

        @if (session()->has('message'))
        <div class="mb-4 bg-green-100 dark:bg-green-900/30 border border-green-400 dark:border-green-700 text-green-700 dark:text-green-300 px-4 py-3 rounded-lg relative flex items-center gap-2">
            <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
            </svg>
            {{ session('message') }}
        </div>
        @endif

        <div class="mb-4">
            <label for="instruction" class="block font-medium text-sm text-gray-700 dark:text-gray-300 mb-2">
                Instrucción del Sistema
            </label>
            <textarea
                id="instruction"
                wire:model="systemInstruction"
                rows="12"
                class="block w-full border-gray-300 dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-100 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500 rounded-xl shadow-sm p-4 text-sm sm:text-base transition"
                placeholder="Ej: Eres un experto en bienes raíces. Tu objetivo es agendar citas con potenciales clientes..."></textarea>
            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                💡 Tip: Sé específico sobre el tono, objetivos y límites de la IA.
            </p>
        </div>

        <div class="flex flex-col sm:flex-row items-center justify-end gap-3 pt-4 border-t dark:border-zinc-700">
            <button
                wire:click="save"
                class="w-full sm:w-auto inline-flex items-center justify-center px-6 py-3 bg-indigo-600 border border-transparent rounded-xl font-semibold text-sm text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150 shadow-sm active:scale-95">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
                Guardar Configuración
            </button>
        </div>
    </div>
</div>