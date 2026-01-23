<div class="max-w-7xl mx-auto py-10 sm:px-6 lg:px-8">
    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg p-6">
        <h1 class="text-2xl font-bold mb-4 text-gray-800 dark:text-gray-200">Configuración del Bot (Cerebro Central)</h1>
        
        <p class="mb-6 text-gray-600 dark:text-gray-400">
            Define aquí la instrucción maestra. La Inteligencia Artificial actuará basada en este texto para 
            <strong>todos</strong> los usuarios que escriban.
        </p>

        @if (session()->has('message'))
            <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative">
                {{ session('message') }}
            </div>
        @endif

        <div class="mb-4">
            <label for="instruction" class="block font-medium text-sm text-gray-700 dark:text-gray-300">Instrucción del Sistema</label>
            <textarea 
                id="instruction"
                wire:model="systemInstruction" 
                rows="10" 
                class="block w-full mt-1 border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm p-4"
                placeholder="Ej: Eres un experto en bienes raíces. Tu objetivo es agendar citas..."></textarea>
        </div>

        <div class="flex items-center justify-end">
            <button 
                wire:click="save" 
                class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                Guardar Configuración
            </button>
        </div>
    </div>
</div>
