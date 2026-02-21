<div class="p-6">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-100">Campañas de Seguimiento</h1>
        <flux:button variant="primary" wire:click="create" icon="plus">Nueva Campaña</flux:button>
    </div>

    <!-- Buscador -->
    <div class="mb-4">
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Buscar campañas..." />
    </div>

    <!-- Tabla de Campañas -->
    <div class="overflow-x-auto bg-white dark:bg-zinc-900 rounded-lg shadow border border-zinc-200 dark:border-zinc-800">
        <table class="w-full text-left text-sm text-gray-600 dark:text-gray-300">
            <thead class="bg-gray-50 dark:bg-zinc-800 uppercase text-xs font-semibold text-gray-700 dark:text-gray-200">
                <tr>
                    <th class="px-6 py-3">Nombre</th>
                    <th class="px-6 py-3">Descripción</th>
                    <th class="px-6 py-3">Disparador</th>
                    <th class="px-6 py-3 text-center">Estado</th>
                    <th class="px-6 py-3 text-right">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @forelse($campaigns as $campaign)
                <tr class="hover:bg-gray-50 dark:hover:bg-zinc-800/50 transition">
                    <td class="px-6 py-4 font-medium text-gray-900 dark:text-gray-100">{{ $campaign->name }}</td>
                    <td class="px-6 py-4 truncate max-w-xs">{{ $campaign->description }}</td>
                    <td class="px-6 py-4">
                        <flux:badge size="sm" color="zinc">{{ $campaign->trigger_type ?? 'Manual' }}</flux:badge>
                    </td>
                    <td class="px-6 py-4 text-center">
                        <flux:switch wire:click="toggleActive({{ $campaign->id }})" wire:model.defer="dummyvar" :checked="$campaign->is_active" />
                    </td>
                    <td class="px-6 py-4 text-right space-x-2">
                        <flux:button size="sm" icon="list-bullet" wire:click="manageSteps({{ $campaign->id }})" title="Gestionar Pasos">Pasos</flux:button>
                        <flux:button size="sm" icon="pencil-square" wire:click="edit({{ $campaign->id }})">Editar</flux:button>
                        <flux:button size="sm" variant="danger" icon="trash" wire:click="delete({{ $campaign->id }})" wire:confirm="¿Estás seguro de eliminar esta campaña?">Eliminar</flux:button>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="px-6 py-10 text-center text-gray-500">No hay campañas creadas aún.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $campaigns->links() }}
    </div>

    <!-- Modal Crear/Editar Campaña -->
    <flux:modal wire:model="showModal" class="min-w-[400px]">
        <div class="space-y-6">
            <div>
                <h2 class="text-lg font-bold">{{ $campaignId ? 'Editar Campaña' : 'Nueva Campaña' }}</h2>
                <p class="text-sm text-gray-500">Configura los detalles básicos de la campaña.</p>
            </div>

            <div class="space-y-4">
                <flux:input label="Nombre" wire:model="name" placeholder="Ej: Bienvenida Clientes" required />
                <flux:textarea label="Descripción" wire:model="description" placeholder="Propósito de esta campaña..." />

                <div>
                    <flux:label>Disparador (Trigger)</flux:label>
                    <select wire:model="trigger_type" class="w-full rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500">
                        <option value="">Selecciona un disparador...</option>
                        <option value="manual">Manual</option>
                        <option value="tag">Al asignar Etiqueta</option>
                        <option value="keyword">Palabra Clave</option>
                    </select>
                </div>

                <flux:checkbox label="Activa" wire:model="is_active" />
            </div>

            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" wire:click="$set('showModal', false)">Cancelar</flux:button>
                <flux:button variant="primary" wire:click="save">Guardar</flux:button>
            </div>
        </div>
    </flux:modal>

    <!-- Modal Gestionar Pasos -->
    <flux:modal wire:model="showStepsModal" class="md:min-w-[700px]">
        <div class="space-y-6">
            <div class="flex justify-between items-start">
                <div>
                    <h2 class="text-lg font-bold">Pasos de la Campaña: {{ $selectedCampaign?->name }}</h2>
                    <p class="text-sm text-gray-500">Define la secuencia de mensajes.</p>
                </div>
                <flux:button size="sm" wire:click="$set('showStepsModal', false)" icon="x-mark" />
            </div>

            <!-- Lista de Pasos -->
            <div class="space-y-3 max-h-[400px] overflow-y-auto pr-2">
                @foreach($steps as $index => $step)
                <div class="flex items-start gap-3 p-3 rounded-lg border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/50">
                    <div class="flex-shrink-0 flex h-8 w-8 items-center justify-center rounded-full bg-indigo-100 text-indigo-600 font-bold text-sm">
                        {{ $index + 1 }}
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex justify-between items-center mb-1">
                            <span class="text-xs font-semibold uppercase tracking-wider text-gray-500">{{ $step->message_type }}</span>
                            <span class="text-xs bg-zinc-200 dark:bg-zinc-700 px-2 py-0.5 rounded text-gray-600 dark:text-gray-300">
                                Delay: {{ $step->delay }}s
                            </span>
                        </div>
                        <p class="text-sm text-gray-800 dark:text-gray-200 line-clamp-2">{{ $step->message_content }}</p>
                    </div>
                    <div class="flex flex-col gap-1">
                        <flux:button size="xs" icon="pencil-square" wire:click="editStep({{ $step->id }})" />
                        <flux:button size="xs" variant="danger" icon="trash" wire:click="deleteStep({{ $step->id }})" />
                    </div>
                </div>
                @endforeach

                @if(count($steps) === 0)
                <div class="text-center py-8 text-gray-500 border-2 border-dashed border-zinc-200 rounded-lg">
                    No hay pasos definidos.
                </div>
                @endif
            </div>

            <!-- Formulario Agregar/Editar Paso -->
            <div class="border-t border-zinc-200 dark:border-zinc-700 pt-4 mt-4">
                <h3 class="text-sm font-semibold mb-3">{{ $stepId ? 'Editar Paso' : 'Agregar Nuevo Paso' }}</h3>
                <div class="grid gap-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <flux:label>Tipo de Mensaje</flux:label>
                            <select wire:model="step_message_type" class="w-full rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500">
                                <option value="text">Texto</option>
                                <option value="image">Imagen (URL)</option>
                                <option value="video">Video (URL)</option>
                            </select>
                        </div>
                        <flux:input type="number" label="Retraso (segundos)" wire:model="step_delay" min="0" />
                    </div>

                    <flux:textarea label="Contenido del Mensaje" wire:model="step_message_content" rows="3" placeholder="Escribe el mensaje aquí..." />

                    <div class="flex justify-end gap-2">
                        @if($stepId)
                        <flux:button variant="ghost" wire:click="addStep">Cancelar Edición</flux:button>
                        @endif
                        <flux:button variant="primary" wire:click="saveStep">{{ $stepId ? 'Actualizar Paso' : 'Agregar Paso' }}</flux:button>
                    </div>
                </div>
            </div>
        </div>
    </flux:modal>
</div>