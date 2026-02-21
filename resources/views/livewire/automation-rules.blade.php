<div class="p-6 space-y-8">
    <div class="flex justify-between items-center">
        <div>
            <h1 class="text-2xl font-bold">Automatización</h1>
            <p class="text-zinc-500">Configura reglas automáticas para el bot.</p>
        </div>
    </div>

    <!-- Configuración General -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <flux:card class="space-y-6">
            <h2 class="text-lg font-bold">Etiqueta por Defecto</h2>
            <div class="space-y-4">
                <flux:select wire:model="defaultTagId" label="Etiqueta para Nuevos Usuarios" placeholder="Selecciona una etiqueta">
                    <flux:select.option value="">Ninguna</flux:select.option>
                    @foreach($availableTags as $tag)
                    <flux:select.option value="{{ $tag->id }}">{{ $tag->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <div class="flex justify-end">
                    <flux:button wire:click="saveDefaultTag" variant="primary">Guardar Etiqueta</flux:button>
                </div>
            </div>
        </flux:card>

        <flux:card class="space-y-6">
            <h2 class="text-lg font-bold">Horario de Oficina</h2>
            <p class="text-sm text-zinc-500">Define el horario en el que cuentan las horas de espera.</p>
            <div class="grid grid-cols-2 gap-4">
                <flux:input wire:model="office_hours_start" type="time" label="Hora Inicio" />
                <flux:input wire:model="office_hours_end" type="time" label="Hora Fin" />
            </div>
            <div class="flex justify-end">
                <flux:button wire:click="saveOfficeHours" variant="primary">Guardar Horario</flux:button>
            </div>
        </flux:card>
    </div>

    <!-- Reglas de Automatización -->
    <div class="space-y-4">
        <div class="flex justify-between items-center">
            <h2 class="text-lg font-bold">Reglas de Respuesta</h2>
            <flux:button wire:click="openModal" icon="plus" variant="primary">Nueva Regla</flux:button>
        </div>

        <flux:table>
            <flux:table.columns>
                <flux:table.column>Desencadenante</flux:table.column>
                <flux:table.column>Acción: Asignar Etiqueta</flux:table.column>
                <flux:table.column>Acción: Eliminar Etiqueta</flux:table.column>
                <flux:table.column>Acción: Campaña</flux:table.column>
                <flux:table.column>Espera</flux:table.column>
                <flux:table.column>Estado</flux:table.column>
                <flux:table.column>Acciones</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse($automationRules as $rule)
                <flux:table.row>
                    <flux:table.cell>
                        <div class="font-medium">{{ $rule->trigger_content }}</div>
                        <div class="text-xs text-zinc-500">{{ $rule->match_type === 'exact' ? 'Coincidencia Exacta' : 'Contiene' }}</div>
                    </flux:table.cell>
                    <flux:table.cell>
                        @if($rule->tag)
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium" style="background-color: {{ $rule->tag->color }}20; color: {{ $rule->tag->color }}">
                            {{ $rule->tag->name }}
                        </span>
                        @else
                        <span class="text-zinc-400">-</span>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>
                        @if($rule->removeTag)
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium" style="background-color: {{ $rule->removeTag->color }}20; color: {{ $rule->removeTag->color }}">
                            {{ $rule->removeTag->name }}
                        </span>
                        @else
                        <span class="text-zinc-400">-</span>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>
                        @if($rule->campaign)
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-indigo-50 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-400">
                            {{ $rule->campaign->name }}
                        </span>
                        @else
                        <span class="text-zinc-400">-</span>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>
                        @if($rule->followup_delay_hours > 0)
                        <span class="text-zinc-700 font-medium">+{{ $rule->followup_delay_hours }} h</span>
                        @else
                        <span class="text-zinc-400">Inmediato</span>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>
                        <flux:badge size="sm" color="{{ $rule->is_active ? 'green' : 'zinc' }}">{{ $rule->is_active ? 'Activo' : 'Inactivo' }}</flux:badge>
                    </flux:table.cell>
                    <flux:table.cell>
                        <flux:dropdown>
                            <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" />
                            <flux:menu>
                                <flux:menu.item wire:click="editRule({{ $rule->id }})" icon="pencil-square">Editar</flux:menu.item>
                                <flux:menu.item wire:click="deleteRule({{ $rule->id }})" icon="trash" variant="danger" wire:confirm="¿Estás seguro de eliminar esta regla?">Eliminar</flux:menu.item>
                            </flux:menu>
                        </flux:dropdown>
                    </flux:table.cell>
                </flux:table.row>
                @empty
                <flux:table.row>
                    <flux:table.cell colspan="7" class="text-center text-zinc-500 py-4">No hay reglas configuradas.</flux:table.cell>
                </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>

    <!-- Modal Form -->
    <flux:modal wire:model="showModal" class="min-w-[500px]">
        <div class="space-y-6">
            <div>
                <h2 class="text-lg font-bold">{{ $editingRuleId ? 'Editar Regla' : 'Nueva Regla' }}</h2>
                <p class="text-sm text-zinc-500">Define qué sucede cuando el bot envía cierto contenido.</p>
            </div>

            <div class="space-y-4">
                <flux:input wire:model="trigger_content" label="Contenido Desencadenante" placeholder="Ej: https://youtu.be/..." description="Si el bot envía esto, se ejecutará la regla." />

                <flux:radio.group wire:model="match_type" label="Tipo de Coincidencia">
                    <flux:radio value="contains" label="Contiene" description="El mensaje contiene el texto" />
                    <flux:radio value="exact" label="Exacto" description="El mensaje es exactamente el texto" />
                </flux:radio.group>

                <div class="border-t border-zinc-100 dark:border-zinc-700 pt-4 mt-4">
                    <h3 class="text-sm font-bold mb-2">Acciones</h3>
                    <div class="grid gap-4">
                        <flux:select wire:model="tag_id" label="Asignar Etiqueta (Opcional)" placeholder="Selecciona una etiqueta">
                            <flux:select.option value="">Ninguna</flux:select.option>
                            @foreach($availableTags as $tag)
                            <flux:select.option value="{{ $tag->id }}">{{ $tag->name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:select wire:model="remove_tag_id" label="Eliminar Etiqueta (Opcional)" placeholder="Selecciona una etiqueta">
                            <flux:select.option value="">Ninguna</flux:select.option>
                            @foreach($availableTags as $tag)
                            <flux:select.option value="{{ $tag->id }}">{{ $tag->name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <div class="grid grid-cols-2 gap-4">
                            <flux:select wire:model="campaign_id" label="Iniciar Campaña (Opcional)" placeholder="Selecciona una campaña" class="col-span-2">
                                <flux:select.option value="">Ninguna</flux:select.option>
                                @foreach($availableCampaigns as $campaign)
                                <flux:select.option value="{{ $campaign->id }}">{{ $campaign->name }}</flux:select.option>
                                @endforeach
                            </flux:select>
                            <flux:input wire:model="followup_delay_hours" type="number" min="0" label="Horas de Espera" description="En horario de oficina" class="col-span-2" />
                        </div>
                    </div>
                </div>

                <flux:checkbox wire:model="is_active" label="Regla Activa" />
            </div>

            <div class="flex justify-end gap-2">
                <flux:button wire:click="$set('showModal', false)">Cancelar</flux:button>
                <flux:button wire:click="saveRule" variant="primary">Guardar</flux:button>
            </div>
        </div>
    </flux:modal>
</div>