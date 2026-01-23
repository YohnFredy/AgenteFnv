<div class="p-6 bg-white shadow rounded-lg">
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-xl font-bold text-gray-800">Reglas de Respuesta Automática</h2>
        @if(!$isEditing)
            <button wire:click="create" class="bg-indigo-600 text-white px-4 py-2 rounded hover:bg-indigo-700">
                + Nueva Regla
            </button>
        @endif
    </div>

    @if($isEditing)
        <div class="bg-gray-50 p-4 rounded border mb-6">
            <h3 class="text-lg font-semibold mb-4">{{ $editingRuleId ? 'Editar Regla' : 'Nueva Regla' }}</h3>
            
            <div class="grid grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Stage Entrada</label>
                    <input type="number" wire:model="trigger_stage" class="mt-1 block w-full rounded border-gray-300 shadow-sm">
                    @error('trigger_stage') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Stage Salida (Next)</label>
                    <input type="number" wire:model="next_stage" class="mt-1 block w-full rounded border-gray-300 shadow-sm">
                </div>
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700">Palabras Clave (separadas por coma)</label>
                <input type="text" wire:model="keywords" placeholder="info, precio, video" class="mt-1 block w-full rounded border-gray-300 shadow-sm">
                @error('keywords') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
            </div>

            <div class="mb-4">
                <label class="flex items-center">
                    <input type="checkbox" wire:model="is_active" class="rounded border-gray-300 text-indigo-600 shadow-sm">
                    <span class="ml-2 text-sm text-gray-600">Regla Activa</span>
                </label>
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Mensajes de Respuesta (Secuencia)</label>
                @foreach($ruleMessages as $index => $msg)
                    <div class="flex gap-2 mb-2 items-start">
                        <div class="flex-1">
                            <textarea wire:model="ruleMessages.{{ $index }}.content" class="w-full rounded border-gray-300 shadow-sm" rows="2" placeholder="Contenido del mensaje..."></textarea>
                        </div>
                        <div class="w-24">
                            <input type="number" wire:model="ruleMessages.{{ $index }}.delay" class="w-full rounded border-gray-300 shadow-sm" placeholder="Delay (s)">
                        </div>
                        <button wire:click="removeMessageField({{ $index }})" class="text-red-500 hover:text-red-700 p-2">
                            🗑️
                        </button>
                    </div>
                @endforeach
                <button wire:click="addMessageField" class="text-sm text-indigo-600 hover:underline">+ Agregar Mensaje</button>
            </div>

            <div class="flex justify-end gap-2">
                <button wire:click="cancel" class="bg-gray-300 text-gray-700 px-4 py-2 rounded hover:bg-gray-400">Cancelar</button>
                <button wire:click="save" class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700">Guardar</button>
            </div>
        </div>
    @else
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Stage In -> Out</th>
                        <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Keywords</th>
                        <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Mensajes</th>
                        <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Estado</th>
                        <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Acciones</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @foreach($rules as $rule)
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="bg-blue-100 text-blue-800 px-2 py-1 rounded text-xs">{{ $rule->trigger_stage }}</span>
                                ->
                                <span class="bg-green-100 text-green-800 px-2 py-1 rounded text-xs">{{ $rule->next_stage }}</span>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-500">{{ Str::limit($rule->keywords, 30) }}</td>
                            <td class="px-6 py-4 text-sm text-gray-500">{{ $rule->messages->count() }} msgs</td>
                            <td class="px-6 py-4">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $rule->is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                    {{ $rule->is_active ? 'Activo' : 'Inactivo' }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm font-medium">
                                <button wire:click="edit({{ $rule->id }})" class="text-indigo-600 hover:text-indigo-900 mr-2">Editar</button>
                                <button wire:click="delete({{ $rule->id }})" class="text-red-600 hover:text-red-900" onclick="return confirm('¿Seguro que deseas eliminar esta regla?') || event.stopImmediatePropagation()">Eliminar</button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
