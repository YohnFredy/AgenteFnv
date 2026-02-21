<div>
    <style>
        /* Aggressive layout override to remove all margins and gaps */
        main,
        .flux-main,
        .flux-container,
        [data-flux-main] {
            padding: 0 !important;
            max-width: none !important;
            margin: 0 !important;
            width: 100% !important;
            height: 100% !important;
        }

        header.lg\:hidden {
            display: none !important;
        }

        .whatsapp-container {
            width: 100%;
            height: 100vh;
            display: flex;
            overflow: hidden;
            background-color: #fff;
        }

        @media (max-width: 1023px) {
            .whatsapp-container {
                position: relative;
                left: 0;
                width: 100%;
                /* Mobile height fix */
                height: 100vh;
                z-index: 10;
            }
        }
    </style>
    <div class="whatsapp-container flex h-screen bg-white dark:bg-[#111b21] overflow-hidden">
        <!-- Chat List Sidebar -->
        <div
            class="w-full md:w-100 h-screen bg-white border-r border-gray-300 flex flex-col {{ $selectedChatId ? 'hidden md:flex' : 'flex' }}">
            <!-- Header -->

            <div class="flex items-center justify-between px-4 py-3">

                <div class=" flex items-center">
                    <button x-on:click="$dispatch('flux-sidebar-toggle')"
                        class="lg:hidden text-zinc-500 p-2  mr-2 hover:bg-zinc-100 dark:hover:bg-[#202c33] rounded-full transition">
                        <flux:icon.bars-2 class="size-6" />
                    </button>

                    <p class="text-xl font-semibold text-gray-800">WhatsApp</p>
                </div>


                <div class="flex items-center gap-4 text-gray-600">
                    <!-- Icon Add -->
                    <button class="hover:text-gray-900">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" stroke="currentColor"
                            stroke-width="2" viewBox="0 0 24 24">
                            <path d="M12 5v14m-7-7h14" />
                        </svg>
                    </button>
                    <!-- Menú Lateral -->
                    <flux:tooltip content="Menú">
                        <flux:dropdown>
                            <flux:button variant="ghost" icon="ellipsis-vertical" title="Menú" />
                            <flux:menu>
                                <flux:menu.item wire:click="openTagModal" icon="tag">Etiquetas</flux:menu.item>
                                <flux:menu.item wire:click="toggleSelectionMode" icon="check-circle">
                                    {{ $selectMode ? 'Cancelar Selección' : 'Seleccionar Chats' }}
                                </flux:menu.item>
                                <flux:menu.separator />
                                <flux:menu.item icon="cog-6-tooth">Configuración</flux:menu.item>
                                <flux:menu.item icon="arrow-right-start-on-rectangle">Cerrar sesión</flux:menu.item>
                            </flux:menu>
                        </flux:dropdown>
                    </flux:tooltip>
                </div>
            </div>

            <!-- Search -->
            <div class="px-4 pb-3">
                <div class="relative">
                    <input type="text" wire:model.live.debounce.300ms="search" placeholder="Busca un chat"
                        class="w-full bg-neutral-100 text-sm rounded-full pl-10 pr-4 py-2 focus:outline-none focus:ring-2 focus:ring-gray-800 focus:bg-white border hover:border-gray-400 ">
                    <div class="absolute left-3 top-2.5 text-gray-700">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" stroke="currentColor"
                            stroke-width="2" viewBox="0 0 24 24">
                            <circle cx="11" cy="11" r="8" />
                            <path d="M21 21l-4.3-4.3" />
                        </svg>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="px-4 pb-3 flex flex-wrap gap-2 text-sm">
                <button wire:click="setFilterMode('all')"
                    class="px-3 py-1 rounded-full {{ $filterMode === 'all' ? 'bg-gray-200 border border-gray-400 hover:bg-gray-300' : 'bg-white border border-gray-300 hover:bg-gray-100' }}">Todos</button>

                <button wire:click="setFilterMode('human')"
                    class="px-3 py-1 rounded-full {{ $filterMode === 'human' ? 'bg-gray-200 border border-gray-400 hover:bg-gray-300' : 'bg-white border border-gray-300 hover:bg-gray-100' }} ">Humanos</button>

                <button wire:click="setFilterMode('bot')"
                    class="px-3 py-1 rounded-full {{ $filterMode === 'bot' ? 'bg-gray-200 border border-gray-400 hover:bg-gray-300' : 'bg-white border border-gray-300 hover:bg-gray-100' }} ">Asistente</button>

                <flux:dropdown>
                    <button
                        class="px-3 py-1 rounded-full bg-white border border-gray-300 hover:bg-gray-100 flex items-center gap-1">
                        Etiquetas
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3" fill="none" stroke="currentColor"
                            stroke-width="2" viewBox="0 0 24 24">
                            <path d="M6 9l6 6 6-6" />
                        </svg>
                    </button>

                    <flux:menu class="shadow-md shadow-gray-600 px-4 py-6 space-y-4">

                        <button wire:click="setFilterTag(null)" class="flex items-centercapitalize cursor-pointer ">
                            <flux:icon.tag variant="mini" class=" mr-4 text-gray-600" />
                            Todas
                        </button>

                        @foreach ($availableTags as $tag)
                            <button wire:click="setFilterTag({{ $tag->id }})"
                                class=" flex items-centercapitalize cursor-pointer capitalize ">
                                <flux:icon.tag variant="mini" class=" mr-4" style="color: {{ $tag->color }}; " />
                                {{ $tag->name }}
                            </button>
                        @endforeach
                    </flux:menu>
                </flux:dropdown>
            </div>

            <!-- Chat List -->
            <div class="flex-1 overflow-y-auto ">
                @forelse($chats as $chat)
                    <div wire:key="chat-{{ $chat->id }}"
                        @if (!$selectMode) wire:click="selectChat({{ $chat->id }})" @endif
                        class="flex justify-between items-center p-2.5 m-1.5 rounded-xl hover:bg-gray-100 cursor-pointer {{ $selectedChatId == $chat->id ? 'bg-zinc-200 hover:bg-zinc-300' : '' }}">

                        <div class=" flex items-center">
                            <!-- Selection Checkbox -->
                            @if ($selectMode)
                                <div class="mr-3">
                                    <input type="checkbox" value="{{ $chat->id }}" wire:model.live="selectedChats"
                                        class="rounded border-zinc-300 text-emerald-600 focus:ring-emerald-500">
                                </div>
                            @else
                                <div class="hidden group-hover:block absolute left-2 z-10">
                                    <div class="bg-white dark:bg-[#202c33] rounded-full p-1 shadow">
                                        <input type="checkbox" value="{{ $chat->id }}"
                                            wire:click.stop="toggleSelectionMode; $set('selectedChats', ['{{ $chat->id }}'])"
                                            class="rounded border-zinc-300 text-emerald-600 focus:ring-emerald-500">
                                    </div>
                                </div>
                            @endif
                            <div class=" flex gap-3">
                                @if ($chat->is_active)
                                    <div
                                        class="w-12 h-12 rounded-full border-2 bg-gray-100 border-gray-400 flex justify-center 
                                        items-center">
                                        {{ strtoupper(substr($chat->name ?? '?', 0, 2)) }}
                                    </div>
                                @else
                                    <div
                                        class="w-12 h-12 rounded-full border-2 bg-gray-100 border-red-400 text-red-500 flex justify-center items-center ">
                                        {{ strtoupper(substr($chat->name ?? '?', 0, 2)) }}
                                    </div>
                                @endif

                                <div class="">
                                    <div class="flex justify-between items-center">

                                        <div class="font-medium text-gray-900 truncate flex items-center">
                                            <span class=" mr-5">
                                                {{ $chat->name ?? str_replace('@s.whatsapp.net', '', $chat->remote_jid) }}</span>

                                            <!-- Tags -->
                                            @if ($chat->tags->count() > 0)
                                                @foreach ($chat->tags as $tag)
                                                    <flux:icon.tag variant="mini" style="color: {{ $tag->color }}; "
                                                        class=" -ml-3" />
                                                @endforeach
                                            @endif
                                        </div>
                                    </div>
                                    <p class="text-sm text-gray-600 truncate">
                                        {{ str_replace('@s.whatsapp.net', '', $chat->remote_jid) }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="text-xs text-gray-500">
                            {{ \Carbon\Carbon::parse($chat->last_activity)->format('H:i') }}
                        </div>

                    </div>
                @empty
                    <div class="p-10 text-center">
                        <p class="text-zinc-500 dark:text-zinc-400 text-sm">No se encontraron chats.</p>
                    </div>
                @endforelse
            </div>

            <!-- Pagination -->
            @if ($chats->hasPages())
                <div class="p-2 border-t border-zinc-100 dark:border-zinc-800">
                    {{ $chats->links() }}
                </div>
            @endif
        </div>

        <!-- Right Panel (SPA Container) -->
        <div
            class="flex-1 bg-zinc-50 dark:bg-[#222e35] overflow-hidden {{ $selectedChatId ? 'flex flex-col' : 'hidden md:flex' }} w-full min-w-0">
            @if ($selectedChatId && $selectedChat)
                <livewire:chat-detail :chat="$selectedChat" :key="'chat-detail-' . $selectedChatId" />
            @else
                <!-- Placeholder for Detail (Desktop) -->
                <div class="hidden md:flex flex-1 h-full items-center justify-center relative">
                    <div class="max-w-md text-center space-y-4">
                        <div
                            class="inline-flex h-20 w-20 rounded-full bg-zinc-200 dark:bg-zinc-800 items-center justify-center text-zinc-400">
                            <flux:icon.chat-bubble-left-right class="size-10" />
                        </div>
                        <h2 class="text-2xl font-light text-zinc-800 dark:text-zinc-300">WhatsApp Web</h2>
                        <p class="text-sm text-zinc-500 dark:text-zinc-400">Envía y recibe mensajes sin necesidad de
                            tener tu teléfono conectado. <br> Usa WhatsApp en hasta 4 dispositivos vinculados y 1
                            teléfono a la vez.</p>
                        <div class="pt-10 flex items-center justify-center gap-2 text-zinc-400 text-xs">
                            <flux:icon.lock-closed class="size-3" />
                            Cifrado de extremo a extremo
                        </div>
                    </div>
                    <!-- Bottom Line Decor -->
                    <div class="absolute bottom-0 left-0 right-0 h-1.5 bg-emerald-500"></div>
                </div>
            @endif
        </div>

        <!-- Modal Etiquetas -->
        <flux:modal wire:model="showTagModal" class="min-w-100">
            <div class="space-y-6">
                <div>
                    <h2 class="text-lg font-bold">Gestionar Etiquetas</h2>
                    <p class="text-sm text-gray-500">
                        @if (empty($selectedChats) && !$selectedChatId)
                            Administra tus etiquetas.
                        @else
                            Asignar etiqueta a {{ count($selectedChats) ?: 1 }} chat(s).
                        @endif
                    </p>
                </div>

                <!-- Crear Nueva -->
                <div class="flex gap-2 items-end">
                    <div class="flex-1">
                        <flux:input wire:model="tagName" label="Nueva Etiqueta" placeholder="Ej: Venta Cerrada" />
                    </div>
                    <div>
                        <flux:input type="color" wire:model="tagColor" class="h-10 w-12 p-1" />
                    </div>
                    <flux:button wire:click="createTag" icon="plus" variant="primary">Crear</flux:button>
                </div>

                <div class="border-t border-zinc-100 dark:border-zinc-700 my-2"></div>

                <!-- Lista de Etiquetas -->
                <div class="space-y-2 max-h-75 overflow-y-auto">
                    @php
                        // Determine the effective list of chat IDs to check against
                        $targetChatIds = $selectedChats;
                        if (empty($targetChatIds) && $selectedChatId) {
                            $targetChatIds = [$selectedChatId];
                        }
                        $targetChatIdsCount = count($targetChatIds);
                    @endphp

                    @forelse($availableTags as $tag)
                        @php
                            // Check how many of the target chats have this tag
                            $hasTagCount = 0;
                            if ($targetChatIdsCount > 0) {
                                $hasTagCount = \Illuminate\Support\Facades\DB::table('lead_tag')
                                    ->whereIn('chat_id', $targetChatIds)
                                    ->where('tag_id', $tag->id)
                                    ->count();
                            }
                            $isChecked = $targetChatIdsCount > 0 && $hasTagCount === $targetChatIdsCount;
                            $isIndeterminate = $hasTagCount > 0 && $hasTagCount < $targetChatIdsCount;
                        @endphp

                        <div
                            class="flex items-center justify-between p-2 hover:bg-zinc-50 dark:hover:bg-zinc-800 rounded group">
                            <div class="flex items-center gap-3 flex-1 cursor-pointer"
                                wire:click="toggleTag({{ $tag->id }})">
                                <div
                                    class="relative flex items-center justify-center w-5 h-5 border rounded {{ $isChecked ? 'bg-emerald-500 border-emerald-500' : 'border-zinc-300 dark:border-zinc-600 bg-white dark:bg-zinc-900' }}">
                                    @if ($isChecked)
                                        <flux:icon.check class="size-3.5 text-white" />
                                    @elseif($isIndeterminate)
                                        <div class="w-2.5 h-0.5 bg-emerald-500 rounded"></div>
                                    @endif
                                </div>

                                <span class="w-4 h-4 rounded-full"
                                    style="background-color: {{ $tag->color }}"></span>
                                <span
                                    class="font-medium text-zinc-700 dark:text-zinc-300 flex-1">{{ $tag->name }}</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <flux:icon.trash class="size-4 text-zinc-400 hover:text-red-500 cursor-pointer"
                                    wire:click="deleteTag({{ $tag->id }})"
                                    wire:confirm="¿Eliminar esta etiqueta?" title="Eliminar" />
                            </div>
                        </div>
                    @empty
                        <div class="text-center text-gray-400 text-sm py-4">No tienes etiquetas creadas.</div>
                    @endforelse
                </div>
            </div>
        </flux:modal>
    </div>
</div>
