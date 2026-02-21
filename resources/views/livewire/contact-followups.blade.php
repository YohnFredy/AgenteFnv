<div class="p-6">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-100">Seguimientos Activos (Contact Followups)</h1>
        <flux:button variant="primary" wire:click="create" icon="plus">Nuevo Seguimiento</flux:button>
    </div>

    <!-- Filtros -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Buscar por cliente o teléfono..." />

        <select wire:model.live="filterStatus" class="rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500">
            <option value="">Todos los Estados</option>
            <option value="scheduled">Programado</option>
            <option value="pending">Pendiente</option>
            <option value="completed">Completado</option>
            <option value="canceled">Cancelado</option>
        </select>

        <select wire:model.live="filterCampaign" class="rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500">
            <option value="">Todas las Campañas</option>
            @foreach($campaigns as $campaign)
            <option value="{{ $campaign->id }}">{{ $campaign->name }}</option>
            @endforeach
        </select>
    </div>

    <!-- Tabla -->
    <div class="overflow-x-auto bg-white dark:bg-zinc-900 rounded-lg shadow border border-zinc-200 dark:border-zinc-800">
        <table class="w-full text-left text-sm text-gray-600 dark:text-gray-300">
            <thead class="bg-gray-50 dark:bg-zinc-800 uppercase text-xs font-semibold text-gray-700 dark:text-gray-200">
                <tr>
                    <th class="px-6 py-3">Cliente</th>
                    <th class="px-6 py-3">Campaña</th>
                    <th class="px-6 py-3">Programado Para</th>
                    <th class="px-6 py-3">Última Interacción</th>
                    <th class="px-6 py-3 text-center">Estado</th>
                    <th class="px-6 py-3 text-right">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @forelse($followups as $followup)
                <tr class="hover:bg-gray-50 dark:hover:bg-zinc-800/50 transition">
                    <td class="px-6 py-4">
                        <div class="font-medium text-gray-900 dark:text-gray-100">{{ $followup->chat->name ?? 'Desconocido' }}</div>
                        <div class="text-xs text-zinc-500">{{ $followup->chat->remote_jid ?? '-' }}</div>
                    </td>
                    <td class="px-6 py-4">
                        <flux:badge size="sm" color="indigo">{{ $followup->campaign->name ?? 'Sin Campaña' }}</flux:badge>
                    </td>
                    <td class="px-6 py-4">
                        <div class="text-gray-900 dark:text-gray-100 font-medium">
                            {{ $followup->scheduled_at ? $followup->scheduled_at->format('d/m/Y H:i') : '-' }}
                        </div>
                        <div class="text-xs text-zinc-500">
                            {{ $followup->scheduled_at ? $followup->scheduled_at->diffForHumans() : '' }}
                        </div>
                    </td>
                    <td class="px-6 py-4 text-xs text-zinc-500">
                        {{ $followup->last_interaction_at ? $followup->last_interaction_at->format('d/m/Y H:i') : '-' }}
                    </td>
                    <td class="px-6 py-4 text-center">
                        @php
                        $colors = [
                        'scheduled' => 'blue',
                        'pending' => 'yellow',
                        'completed' => 'green',
                        'canceled' => 'zinc',
                        ];
                        @endphp
                        <flux:badge size="sm" color="{{ $colors[$followup->status] ?? 'zinc' }}">
                            {{ ucfirst($followup->status) }}
                        </flux:badge>
                    </td>
                    <td class="px-6 py-4 text-right space-x-2">
                        <flux:dropdown>
                            <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" />
                            <flux:menu>
                                <flux:menu.item wire:click="edit({{ $followup->id }})" icon="pencil-square">Editar</flux:menu.item>

                                <flux:menu.separator />

                                @if($followup->status !== 'completed')
                                <flux:menu.item wire:click="markAsCompleted({{ $followup->id }})" icon="check-circle">Marcar Completado</flux:menu.item>
                                @endif

                                @if($followup->status !== 'canceled')
                                <flux:menu.item wire:click="markAsCanceled({{ $followup->id }})" icon="x-circle">Cancelar</flux:menu.item>
                                @endif

                                <flux:menu.separator />

                                <flux:menu.item wire:click="delete({{ $followup->id }})" icon="trash" variant="danger" wire:confirm="¿Estás seguro de eliminar este seguimiento?">Eliminar</flux:menu.item>
                            </flux:menu>
                        </flux:dropdown>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="px-6 py-10 text-center text-gray-500">No hay seguimientos registrados con estos filtros.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $followups->links() }}
    </div>

    <!-- Modal Crear/Editar -->
    <flux:modal wire:model="showModal" class="min-w-[400px]">
        <div class="space-y-6">
            <div>
                <h2 class="text-lg font-bold">{{ $editingId ? 'Editar Seguimiento' : 'Nuevo Seguimiento' }}</h2>
                <p class="text-sm text-gray-500">Programa o modifica un seguimiento para un contacto.</p>
            </div>

            <div class="space-y-4">
                <div>
                    <flux:label>Cliente (Chat)</flux:label>
                    <select wire:model="chat_id" class="w-full rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500">
                        <option value="">Selecciona un cliente...</option>
                        @foreach($chats as $chat)
                        <option value="{{ $chat->id }}">{{ $chat->name }} ({{ $chat->remote_jid }})</option>
                        @endforeach
                    </select>
                    @error('chat_id') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>

                <div>
                    <flux:label>Campaña</flux:label>
                    <select wire:model="campaign_id" class="w-full rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500">
                        <option value="">Selecciona una campaña...</option>
                        @foreach($campaigns as $campaign)
                        <option value="{{ $campaign->id }}">{{ $campaign->name }}</option>
                        @endforeach
                    </select>
                    @error('campaign_id') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>

                <flux:input type="datetime-local" label="Fecha Programada" wire:model="scheduled_at" required />

                <div>
                    <flux:label>Estado</flux:label>
                    <select wire:model="status" class="w-full rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500">
                        <option value="scheduled">Programado</option>
                        <option value="pending">Pendiente</option>
                        <option value="completed">Completado</option>
                        <option value="canceled">Cancelado</option>
                    </select>
                    @error('status') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>
            </div>

            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" wire:click="$set('showModal', false)">Cancelar</flux:button>
                <flux:button variant="primary" wire:click="save">Guardar</flux:button>
            </div>
        </div>
    </flux:modal>
</div>