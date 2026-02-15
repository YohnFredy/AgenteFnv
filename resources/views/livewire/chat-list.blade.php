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

        /* Hide the default Laravel mobile header to merge with WhatsApp UI */
        header.lg\:hidden {
            display: none !important;
        }

        /* We don't hide the header globally, just ensure our container covers what it needs */

        .whatsapp-container {
            width: 100%;
            height: 100vh;
            display: flex;
            overflow: hidden;
            background-color: #fff;
        }

        /* Removed `:has(.sidebar-compact:hover)` rule to prevent content jitter/reflow */

        /* Removed `:has(.sidebar-compact:hover)` rule to prevent content jitter/reflow */

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
        <div class="w-full md:w-[400px] flex flex-col border-r border-zinc-200 dark:border-zinc-800 bg-white dark:bg-[#111b21] {{ $selectedChatId ? 'hidden md:flex' : 'flex' }}">
            <!-- Header -->
            <div class="p-4 bg-zinc-50 dark:bg-[#202c33] flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <button x-on:click="$dispatch('flux-sidebar-toggle')" class="lg:hidden text-zinc-500 p-2 -ml-2 hover:bg-zinc-100 dark:hover:bg-[#202c33] rounded-full transition">
                        <flux:icon.bars-2 class="size-6" />
                    </button>
                    <div class="h-10 w-10 rounded-full bg-indigo-500 flex items-center justify-center text-white font-bold hidden sm:flex">
                        {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                    </div>
                    <h1 class="text-lg font-bold text-zinc-900 dark:text-zinc-100 italic">WhatsApp</h1>
                </div>
                <div class="flex gap-4 text-zinc-500 dark:text-zinc-400">
                    <button title="Comunidades">
                        <flux:icon.users class="size-5" />
                    </button>
                    <button title="Estado"><flux:icon.circle-stack class="size-5" /></button>
                    <button title="Nuevo chat"><flux:icon.chat-bubble-left-right class="size-5" /></button>
                    <button title="Menú"><flux:icon.ellipsis-vertical class="size-5" /></button>
                </div>
            </div>

            <!-- Search and Filter -->
            <div class="p-2 space-y-2 border-b border-zinc-100 dark:border-zinc-800">
                <div class="relative group">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <flux:icon.magnifying-glass class="size-4 text-zinc-400 group-focus-within:text-emerald-500 transition-colors" />
                    </div>
                    <input
                        type="text"
                        wire:model.live.debounce.300ms="search"
                        placeholder="Busca un chat o inicia uno nuevo"
                        class="block w-full pl-10 pr-4 py-2 text-sm border-none bg-zinc-100 dark:bg-[#202c33] text-zinc-900 dark:text-zinc-100 rounded-lg focus:ring-0 transition-all">
                </div>

                <!-- Filter Chips -->
                <div class="flex gap-2 overflow-x-auto pb-1 scrollbar-hide no-scrollbar">
                    <button class="px-3 py-1 text-xs font-semibold rounded-full bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400">Todos</button>
                    <button class="px-3 py-1 text-xs font-semibold rounded-full bg-zinc-100 text-zinc-600 dark:bg-[#202c33] dark:text-zinc-400 hover:bg-zinc-200 transition">No leídos</button>
                    <button class="px-3 py-1 text-xs font-semibold rounded-full bg-zinc-100 text-zinc-600 dark:bg-[#202c33] dark:text-zinc-400 hover:bg-zinc-200 transition">Favoritos</button>
                    <button class="px-3 py-1 text-xs font-semibold rounded-full bg-zinc-100 text-zinc-600 dark:bg-[#202c33] dark:text-zinc-400 hover:bg-zinc-200 transition">Grupos</button>
                </div>
            </div>

            <!-- List -->
            <div class="flex-1 overflow-y-auto no-scrollbar">
                @forelse($chats as $chat)
                <div
                    wire:key="chat-{{ $chat->id }}"
                    wire:click="selectChat({{ $chat->id }})"
                    class="group flex items-center px-4 py-3 cursor-pointer hover:bg-zinc-50 dark:hover:bg-[#202c33] transition-colors border-b border-zinc-50 dark:border-zinc-800/50 {{ $selectedChatId == $chat->id ? 'bg-zinc-100 dark:bg-[#2a3942] border-l-4 border-l-emerald-500' : '' }}">
                    <!-- Avatar -->
                    <div class="h-12 w-12 flex-shrink-0 rounded-full bg-zinc-200 dark:bg-zinc-700 flex items-center justify-center text-zinc-500 dark:text-zinc-400 font-bold overflow-hidden shadow-sm">
                        @if($chat->avatar_url)
                        <img src="{{ $chat->avatar_url }}" alt="{{ $chat->name }}" class="h-full w-full object-cover">
                        @else
                        <flux:icon.user class="size-6" />
                        @endif
                    </div>

                    <!-- Chat Info -->
                    <div class="ml-4 flex-1 min-w-0">
                        <div class="flex items-center justify-between mb-0.5">
                            <h3 class="text-base font-medium text-zinc-900 dark:text-zinc-100 truncate">
                                @if($editingChatId === $chat->id)
                                <input type="text" wire:model="editingName"
                                    class="w-full bg-transparent border-none p-0 focus:ring-0 text-base font-medium"
                                    wire:keydown.enter.stop="saveChat" wire:keydown.escape.stop="cancelEdit" onclick="event.stopPropagation()">
                                @else
                                {{ $chat->name ?? str_replace('@s.whatsapp.net', '', $chat->remote_jid) }}
                                @if($chat->is_active)
                                <span class="ml-1 inline-block w-1.5 h-1.5 rounded-full bg-emerald-500 shadow-sm shadow-emerald-200" title="Bot Activo"></span>
                                @endif
                                @endif
                            </h3>
                            <span class="text-[11px] text-zinc-500 dark:text-zinc-400 font-medium">
                                {{ \Carbon\Carbon::parse($chat->last_activity)->format('H:i') }}
                            </span>
                        </div>

                        <div class="flex items-center justify-between">
                            <p class="text-[13px] text-zinc-500 dark:text-zinc-400 truncate pr-4">
                                @if($chat->last_message_role === 'assistant')
                                <flux:icon.check-badge class="size-3 inline mr-1 text-emerald-500" />
                                @endif
                                @if($chat->is_active)
                                <span class="bg-indigo-50 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-400 px-1.5 rounded text-[10px] font-bold mr-1">Fase {{ $chat->stage }}</span>
                                @endif
                                Esperando interacción...
                            </p>

                            <!-- Hover Actions -->
                            <div class="hidden group-hover:flex items-center gap-2" onclick="event.stopPropagation()">
                                <button wire:click.stop="toggleActive({{ $chat->id }})" class="p-1 hover:text-emerald-500 transition-colors">
                                    <flux:icon.bolt class="size-4 {{ $chat->is_active ? 'text-emerald-500' : '' }}" />
                                </button>
                                <button wire:click.stop="editChat({{ $chat->id }})" class="p-1 hover:text-indigo-500 transition-colors">
                                    <flux:icon.pencil-square class="size-4" />
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                @empty
                <div class="p-10 text-center">
                    <p class="text-zinc-500 dark:text-zinc-400 text-sm">No se encontraron chats.</p>
                </div>
                @endforelse
            </div>

            <!-- Pagination -->
            @if($chats->hasPages())
            <div class="p-2 border-t border-zinc-100 dark:border-zinc-800">
                {{ $chats->links() }}
            </div>
            @endif
        </div>

        <!-- Right Panel (SPA Container) -->
        <div class="flex-1 bg-zinc-50 dark:bg-[#222e35] overflow-hidden {{ $selectedChatId ? 'flex flex-col' : 'hidden md:flex' }} w-full min-w-0">
            @if($selectedChatId && $selectedChat)
            <livewire:chat-detail :chat="$selectedChat" :key="'chat-detail-' . $selectedChatId" />
            @else
            <!-- Placeholder for Detail (Desktop) -->
            <div class="hidden md:flex flex-1 h-full items-center justify-center relative">
                <div class="max-w-md text-center space-y-4">
                    <div class="inline-flex h-20 w-20 rounded-full bg-zinc-200 dark:bg-zinc-800 items-center justify-center text-zinc-400">
                        <flux:icon.chat-bubble-left-right class="size-10" />
                    </div>
                    <h2 class="text-2xl font-light text-zinc-800 dark:text-zinc-300">WhatsApp Web</h2>
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">Envía y recibe mensajes sin necesidad de tener tu teléfono conectado. <br> Usa WhatsApp en hasta 4 dispositivos vinculados y 1 teléfono a la vez.</p>
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
    </div>
</div>