<div class="p-6 max-w-7xl mx-auto space-y-8">

    {{-- Header --}}
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-zinc-900 dark:text-zinc-100">
                🚀 Marketing Blaster
            </h1>
            <p class="text-sm text-zinc-500 dark:text-zinc-400 mt-1">
                Envío masivo de mensajes automatizados con filtros inteligentes y protección anti-bloqueo.
            </p>
        </div>

        {{-- Mode Switcher (Tabs) --}}
        <div class="bg-zinc-100 dark:bg-zinc-800 p-1 rounded-lg inline-flex">
            <button
                wire:click="$set('mode', 'manual')"
                class="px-4 py-2 text-sm font-medium rounded-md transition-colors {{ $mode === 'manual' ? 'bg-white dark:bg-zinc-600 shadow-sm text-zinc-900 dark:text-white' : 'text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300' }}">
                ✍️ Mensaje Manual
            </button>
            <button
                wire:click="$set('mode', 'template')"
                class="px-4 py-2 text-sm font-medium rounded-md transition-colors {{ $mode === 'template' ? 'bg-white dark:bg-zinc-600 shadow-sm text-zinc-900 dark:text-white' : 'text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300' }}">
                📋 Plantilla
            </button>
        </div>
    </div>

    {{-- Success Message --}}
    @if($successMessage)
    <div class="bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 rounded-lg p-4 flex items-center gap-3">
        <div class="flex-shrink-0 text-emerald-500">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
        </div>
        <p class="text-emerald-700 dark:text-emerald-300 font-medium">{{ $successMessage }}</p>
    </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

        {{-- Left Column: Filters & Configuration --}}
        <div class="lg:col-span-1 space-y-6">
            <flux:card class="space-y-6">
                <div>
                    <h2 class="text-lg font-semibold mb-4">🔍 Filtros de Audiencia</h2>

                    {{-- Explanation based on mode --}}
                    <div class="bg-blue-50 dark:bg-blue-900/20 p-3 rounded-md mb-4 text-xs text-blue-700 dark:text-blue-300 border border-blue-100 dark:border-blue-800">
                        @if($mode === 'manual')
                        <span class="font-bold">MODO MANUAL:</span> Filtra usuarios recientes. Buscamos chats activos en las últimas X horas.
                        @else
                        <span class="font-bold">MODO PLANTILLA:</span> Filtra usuarios inactivos. Buscamos chats que NO han escrito en más de X horas.
                        @endif
                    </div>

                    {{-- Time Filter --}}
                    <div class="mb-4">
                        <flux:input
                            wire:model.live.debounce.500ms="timeHours"
                            type="number"
                            label="{{ $mode === 'manual' ? '⏰ Escribieron hace menos de (horas)' : '⏰ Inactivos desde hace (horas)' }}"
                            min="1" />
                    </div>

                    {{-- Tag Exclusion Filter --}}
                    <div class="space-y-2">
                        <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">🚫 Excluir etiquetas</label>
                        <div class="space-y-2 max-h-48 overflow-y-auto p-2 border border-zinc-200 dark:border-zinc-700 rounded-lg">
                            @foreach($availableTags as $tag)
                            <div class="flex items-center gap-2">
                                <input type="checkbox" wire:model.live="selectedTags" value="{{ $tag->id }}" id="tag-{{ $tag->id }}" class="rounded border-zinc-300 text-indigo-600 focus:ring-indigo-500">
                                <label for="tag-{{ $tag->id }}" class="text-sm text-zinc-600 dark:text-zinc-400 cursor-pointer flex items-center gap-2">
                                    <span class="w-2 h-2 rounded-full" style="background-color: {{ $tag->color ?? '#cbd5e1' }}"></span>
                                    {{ $tag->name }}
                                </label>
                            </div>
                            @endforeach
                        </div>
                        <p class="text-xs text-zinc-500">Si un usuario tiene alguna de estas etiquetas, será omitido.</p>
                    </div>
                </div>
            </flux:card>

            {{-- Message Configuration --}}
            <flux:card class="space-y-6">
                <div>
                    <h2 class="text-lg font-semibold mb-4">✉️ Configuración del Mensaje</h2>

                    @if($mode === 'manual')
                    <flux:textarea
                        wire:model="messageBody"
                        label="Contenido del Mensaje"
                        placeholder="Hola! Te escribimos para contarte..."
                        rows="6"
                        description="Se enviará tal cual lo escribas." />
                    @else
                    <flux:input
                        wire:model="templateName"
                        label="Nombre de la Plantilla"
                        placeholder="ej: template_marketing_..."
                        description="Debe ser el nombre técnico (sin espacios ni idiomas)." />

                    <flux:textarea
                        wire:model="templateContext"
                        label="Contenido de la Plantilla (Para Historial)"
                        placeholder="Escribe aquí el texto que recibe el usuario..."
                        rows="4"
                        description="Este texto se guardará en el chat para que sepas qué se envió." />
                    @endif

                    <div class="mt-4">
                        <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">🏷️ Etiqueta Post-envío (Opcional)</label>
                        <select wire:model="postSendTagId" class="w-full rounded-md border-zinc-300 dark:border-zinc-700 dark:bg-zinc-900 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            <option value="">-- Ninguna --</option>
                            @foreach($availableTags as $tag)
                            <option value="{{ $tag->id }}">{{ $tag->name }}</option>
                            @endforeach
                        </select>
                        <p class="text-xs text-zinc-500 mt-1">Esta etiqueta se agregará automáticamente al usuario si el envío es exitoso.</p>
                    </div>
                </div>

                <div class="pt-4 border-t border-zinc-200 dark:border-zinc-700">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-sm font-medium text-zinc-600">Total a enviar:</span>
                        <span class="text-xl font-bold text-zinc-900 dark:text-white">{{ $total }} usuarios</span>
                    </div>

                    <flux:button
                        wire:click="sendCampaign"
                        variant="primary"
                        class="w-full"
                        wire:loading.attr="disabled"
                        wire:confirm="¿Estás seguro de enviar esta campaña a {{ $total }} usuarios?">
                        <span wire:loading.remove>🚀 Enviar Campaña</span>
                        <span wire:loading>Enviando...</span>
                    </flux:button>
                    <p class="text-center text-xs text-zinc-400 mt-2">
                        Se enviará 1 mensaje cada 2 segundos.
                    </p>
                </div>
            </flux:card>
        </div>

        {{-- Right Column: Preview Table --}}
        <div class="lg:col-span-2">
            <flux:card>
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-semibold">📋 Vista Previa de Destinatarios</h2>
                    <span class="bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-400 text-xs px-2 py-1 rounded">
                        Mostrando primeros 10 de {{ $total }}
                    </span>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
                        <thead>
                            <tr class="bg-zinc-50 dark:bg-zinc-800">
                                <th scope="col" class="px-3 py-3 text-left text-xs font-medium text-zinc-500 uppercase tracking-wider">Nombre</th>
                                <th scope="col" class="px-3 py-3 text-left text-xs font-medium text-zinc-500 uppercase tracking-wider">Teléfono</th>
                                <th scope="col" class="px-3 py-3 text-left text-xs font-medium text-zinc-500 uppercase tracking-wider">Última Interacción</th>
                                <th scope="col" class="px-3 py-3 text-left text-xs font-medium text-zinc-500 uppercase tracking-wider">Etiquetas Actuales</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700 bg-white dark:bg-zinc-900">
                            @forelse($users as $user)
                            <tr>
                                <td class="px-3 py-3 whitespace-nowrap">
                                    <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $user->name ?? 'Desconocido' }}</div>
                                </td>
                                <td class="px-3 py-3 whitespace-nowrap">
                                    <div class="text-sm text-zinc-500">{{ $user->remote_jid }}</div>
                                </td>
                                <td class="px-3 py-3 whitespace-nowrap">
                                    <div class="text-sm text-zinc-500">
                                        {{ $user->last_user_message_at ? $user->last_user_message_at->diffForHumans() : 'Nunca' }}
                                    </div>
                                </td>
                                <td class="px-3 py-3">
                                    <div class="flex flex-wrap gap-1">
                                        @forelse($user->tags as $tag)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium" style="background-color: {{ $tag->color }}20; color: {{ $tag->color }}">
                                            {{ $tag->name }}
                                        </span>
                                        @empty
                                        <span class="text-xs text-zinc-400">-</span>
                                        @endforelse
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="4" class="px-3 py-8 text-center text-sm text-zinc-500">
                                    No se encontraron usuarios con estos filtros.
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-4">
                    {{ $users->links() }}
                </div>
            </flux:card>
        </div>
    </div>
</div>