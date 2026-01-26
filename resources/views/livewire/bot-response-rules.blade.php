<div class="p-4 sm:p-6 bg-white dark:bg-zinc-900 shadow-sm rounded-xl border dark:border-zinc-700">
    <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-3 mb-6">
        <h2 class="text-xl font-bold text-gray-800 dark:text-gray-100">Reglas de Respuesta Automática</h2>
        @if(!$isEditing)
        <button wire:click="create" class="w-full sm:w-auto bg-indigo-600 text-white px-4 py-2.5 rounded-lg hover:bg-indigo-700 font-medium transition active:scale-95 shadow-sm">
            + Nueva Regla
        </button>
        @endif
    </div>

    @if($isEditing)
    <div class="bg-gray-50 dark:bg-zinc-800 p-4 sm:p-6 rounded-xl border dark:border-zinc-700 mb-6">
        <h3 class="text-lg font-semibold mb-4 text-gray-800 dark:text-gray-100">{{ $editingRuleId ? 'Editar Regla' : 'Nueva Regla' }}</h3>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Stage Entrada</label>
                <input type="number" wire:model="trigger_stage" class="w-full rounded-lg border-gray-300 dark:border-zinc-600 dark:bg-zinc-900 dark:text-gray-100 shadow-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                @error('trigger_stage') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Stage Salida (Next)</label>
                <input type="number" wire:model="next_stage" class="w-full rounded-lg border-gray-300 dark:border-zinc-600 dark:bg-zinc-900 dark:text-gray-100 shadow-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
            </div>
        </div>

        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Palabras Clave (separadas por coma)</label>
            <input type="text" wire:model="keywords" placeholder="info, precio, video" class="w-full rounded-lg border-gray-300 dark:border-zinc-600 dark:bg-zinc-900 dark:text-gray-100 shadow-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
            @error('keywords') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
        </div>

        <div class="mb-4">
            <label class="flex items-center cursor-pointer">
                <input type="checkbox" wire:model="is_active" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500 w-5 h-5">
                <span class="ml-2 text-sm text-gray-600 dark:text-gray-300">Regla Activa</span>
            </label>
        </div>

        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Mensajes de Respuesta (Secuencia)</label>
            @foreach($ruleMessages as $index => $msg)
            <div class="flex flex-col sm:flex-row gap-2 mb-3 items-start sm:items-center bg-white dark:bg-zinc-900 p-3 rounded-lg border dark:border-zinc-700">
                <div class="flex-1 w-full">
                    <textarea wire:model="ruleMessages.{{ $index }}.content" class="w-full rounded-lg border-gray-300 dark:border-zinc-600 dark:bg-zinc-800 dark:text-gray-100 shadow-sm focus:ring-2 focus:ring-indigo-500" rows="2" placeholder="Contenido del mensaje..."></textarea>
                </div>
                <div class="flex items-center gap-2 w-full sm:w-auto">
                    <div class="flex-1 sm:w-20">
                        <input type="number" wire:model="ruleMessages.{{ $index }}.delay" class="w-full rounded-lg border-gray-300 dark:border-zinc-600 dark:bg-zinc-800 dark:text-gray-100 shadow-sm text-sm" placeholder="Delay">
                        <span class="text-[10px] text-gray-400 block mt-0.5">segundos</span>
                    </div>
                    <button wire:click="removeMessageField({{ $index }})" class="text-red-500 hover:text-red-700 hover:bg-red-50 dark:hover:bg-red-900/20 p-2 rounded-lg transition">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                        </svg>
                    </button>
                </div>
            </div>
            @endforeach
            <button wire:click="addMessageField" class="text-sm text-indigo-600 dark:text-indigo-400 hover:underline font-medium">
                + Agregar Mensaje
            </button>
        </div>

        <div class="flex flex-col sm:flex-row justify-end gap-2 pt-4 border-t dark:border-zinc-700">
            <button wire:click="cancel" class="w-full sm:w-auto bg-gray-200 dark:bg-zinc-700 text-gray-700 dark:text-gray-200 px-4 py-2.5 rounded-lg hover:bg-gray-300 dark:hover:bg-zinc-600 font-medium transition">
                Cancelar
            </button>
            <button wire:click="save" class="w-full sm:w-auto bg-green-600 text-white px-6 py-2.5 rounded-lg hover:bg-green-700 font-medium transition active:scale-95 shadow-sm">
                Guardar Regla
            </button>
        </div>
    </div>
    @else
    <!-- Mobile Cards View -->
    <div class="lg:hidden space-y-3">
        @foreach($rules as $rule)
        <div class="bg-gray-50 dark:bg-zinc-800 rounded-xl border dark:border-zinc-700 p-4 {{ !$rule->is_active ? 'opacity-60' : '' }}">
            <div class="flex items-center justify-between mb-3">
                <div class="flex items-center gap-2">
                    <span class="bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200 px-2 py-1 rounded text-xs font-semibold">{{ $rule->trigger_stage }}</span>
                    <span class="text-gray-400">→</span>
                    <span class="bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200 px-2 py-1 rounded text-xs font-semibold">{{ $rule->next_stage }}</span>
                </div>
                <span class="px-2 py-0.5 text-[10px] font-semibold rounded-full {{ $rule->is_active ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' }}">
                    {{ $rule->is_active ? 'Activo' : 'Inactivo' }}
                </span>
            </div>

            <p class="text-sm text-gray-600 dark:text-gray-300 mb-2">
                <span class="font-medium">Keywords:</span> {{ Str::limit($rule->keywords, 50) }}
            </p>

            <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">
                {{ $rule->messages->count() }} mensaje(s) configurado(s)
            </p>

            <div class="flex gap-2 pt-3 border-t dark:border-zinc-700">
                <button wire:click="edit({{ $rule->id }})" class="flex-1 text-center text-indigo-600 dark:text-indigo-400 hover:bg-indigo-50 dark:hover:bg-indigo-900/20 py-2 rounded-lg text-sm font-medium transition">
                    ✏️ Editar
                </button>
                <button wire:click="delete({{ $rule->id }})" onclick="return confirm('¿Seguro que deseas eliminar esta regla?') || event.stopImmediatePropagation()" class="flex-1 text-center text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 py-2 rounded-lg text-sm font-medium transition">
                    🗑️ Eliminar
                </button>
            </div>
        </div>
        @endforeach

        @if($rules->isEmpty())
        <div class="text-center py-8 text-gray-500 dark:text-gray-400">
            <p>No hay reglas configuradas</p>
            <p class="text-sm">Crea una nueva regla para empezar</p>
        </div>
        @endif
    </div>

    <!-- Desktop Table View -->
    <div class="hidden lg:block overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-zinc-700">
            <thead class="bg-gray-50 dark:bg-zinc-800">
                <tr>
                    <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 dark:text-gray-400 uppercase">Stage In → Out</th>
                    <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 dark:text-gray-400 uppercase">Keywords</th>
                    <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 dark:text-gray-400 uppercase">Mensajes</th>
                    <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 dark:text-gray-400 uppercase">Estado</th>
                    <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 dark:text-gray-400 uppercase">Acciones</th>
                </tr>
            </thead>
            <tbody class="bg-white dark:bg-zinc-900 divide-y divide-gray-200 dark:divide-zinc-700">
                @foreach($rules as $rule)
                <tr class="hover:bg-gray-50 dark:hover:bg-zinc-800 transition">
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200 px-2.5 py-1 rounded text-xs font-semibold">{{ $rule->trigger_stage }}</span>
                        <span class="mx-2 text-gray-400">→</span>
                        <span class="bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200 px-2.5 py-1 rounded text-xs font-semibold">{{ $rule->next_stage }}</span>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">{{ Str::limit($rule->keywords, 40) }}</td>
                    <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">{{ $rule->messages->count() }} msgs</td>
                    <td class="px-6 py-4">
                        <span class="px-2.5 py-1 inline-flex text-xs leading-5 font-semibold rounded-full {{ $rule->is_active ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' }}">
                            {{ $rule->is_active ? 'Activo' : 'Inactivo' }}
                        </span>
                    </td>
                    <td class="px-6 py-4 text-sm font-medium">
                        <button wire:click="edit({{ $rule->id }})" class="text-indigo-600 dark:text-indigo-400 hover:text-indigo-900 mr-3 transition">Editar</button>
                        <button wire:click="delete({{ $rule->id }})" class="text-red-600 dark:text-red-400 hover:text-red-900 transition" onclick="return confirm('¿Seguro que deseas eliminar esta regla?') || event.stopImmediatePropagation()">Eliminar</button>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>

        @if($rules->isEmpty())
        <div class="text-center py-12 text-gray-500 dark:text-gray-400">
            <p>No hay reglas configuradas</p>
        </div>
        @endif
    </div>
    @endif
</div>