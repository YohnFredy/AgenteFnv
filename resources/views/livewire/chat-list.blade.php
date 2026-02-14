<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
    <!-- Header Section -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <h1 class="text-3xl font-extrabold text-zinc-900 dark:text-white tracking-tight">Monitor de Interacciones</h1>
            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">Supervisa y gestiona tus conversaciones de WhatsApp en tiempo real.</p>
        </div>
        <div class="relative group">
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <svg class="h-5 w-5 text-zinc-400 group-focus-within:text-indigo-500 transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
            </div>
            <input
                type="text"
                wire:model.live.debounce.300ms="search"
                placeholder="Buscar cliente..."
                class="block w-full sm:w-80 pl-10 pr-4 py-2.5 border-none bg-white dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100 rounded-2xl shadow-sm ring-1 ring-zinc-200 dark:ring-zinc-700 focus:ring-2 focus:ring-indigo-500 transition-all">
        </div>
    </div>

    @if (session()->has('message'))
    <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)"
        class="p-4 rounded-2xl bg-emerald-50 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-300 border border-emerald-100 dark:border-emerald-800 flex items-center shadow-sm">
        <svg class="h-5 w-5 mr-3" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
        </svg>
        <span class="text-sm font-medium">{{ session('message') }}</span>
    </div>
    @endif

    <!-- Main Container -->
    <div class="bg-white dark:bg-zinc-900 rounded-3xl shadow-xl shadow-zinc-200/50 dark:shadow-none border border-zinc-200 dark:border-zinc-800 overflow-hidden">

        <!-- Desktop View -->
        <div class="hidden lg:block overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-zinc-50 dark:bg-zinc-800/50">
                        <th class="px-6 py-4 text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider cursor-pointer hover:text-indigo-600 transition" wire:click="sortBy('remote_jid')">
                            Cliente
                        </th>
                        <th class="px-6 py-4 text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider cursor-pointer hover:text-indigo-600 transition" wire:click="sortBy('stage')">
                            Etapa (Stage)
                        </th>
                        <th class="px-6 py-4 text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">
                            Control de Bot
                        </th>
                        <th class="px-6 py-4 text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider cursor-pointer hover:text-indigo-600 transition" wire:click="sortBy('updated_at')">
                            Última Actividad
                        </th>
                        <th class="px-6 py-4 text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider text-right">
                            Acciones
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @forelse($chats as $chat)
                    <tr class="group hover:bg-zinc-50/80 dark:hover:bg-zinc-800/40 transition-colors {{ $editingChatId === $chat->id ? 'bg-indigo-50/30 dark:bg-indigo-900/10' : '' }}">
                        <td class="px-6 py-5">
                            <div class="flex items-center">
                                <div class="h-12 w-12 rounded-2xl bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center text-white font-bold text-xl shadow-lg shadow-indigo-200 dark:shadow-none transition-transform group-hover:scale-105">
                                    {{ strtoupper(substr($chat->name ?? '?', 0, 1)) }}
                                </div>
                                <div class="ml-4">
                                    @if($editingChatId === $chat->id)
                                    <input type="text" wire:model="editingName"
                                        class="w-full px-3 py-1.5 text-sm border-none bg-white dark:bg-zinc-800 rounded-lg ring-1 ring-indigo-500 focus:ring-2 focus:ring-indigo-600 transition-all"
                                        wire:keydown.enter="saveChat" wire:keydown.escape="cancelEdit" autofocus>
                                    @else
                                    <div class="text-sm font-bold text-zinc-900 dark:text-zinc-100">{{ $chat->name ?? 'Sin Nombre' }}</div>
                                    <div class="text-xs text-zinc-500 dark:text-zinc-500 font-medium tracking-tight">
                                        {{ str_replace(['@s.whatsapp.net', '+'], '', $chat->remote_jid) }}
                                    </div>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-5">
                            @if($editingChatId === $chat->id)
                            <div class="flex items-center gap-2">
                                <input type="number" wire:model="editingStage"
                                    class="w-20 px-3 py-1.5 text-sm border-none bg-white dark:bg-zinc-800 rounded-lg ring-1 ring-indigo-500 focus:ring-2 focus:ring-indigo-600 transition-all">
                            </div>
                            @else
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-indigo-50 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-400 ring-1 ring-indigo-100 dark:ring-indigo-800">
                                <span class="w-1.5 h-1.5 rounded-full bg-indigo-500 mr-2"></span>
                                Fase {{ $chat->stage }}
                            </span>
                            @endif
                        </td>
                        <td class="px-6 py-5">
                            <button wire:click="toggleActive({{ $chat->id }})"
                                class="relative inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-bold transition-all duration-300 overflow-hidden
                                    {{ $chat->is_active 
                                        ? 'bg-emerald-50 text-emerald-700 hover:bg-emerald-100 dark:bg-emerald-900/20 dark:text-emerald-400' 
                                        : 'bg-rose-50 text-rose-700 hover:bg-rose-100 dark:bg-rose-900/20 dark:text-rose-400' }}">
                                <span class="relative flex h-2 w-2">
                                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full {{ $chat->is_active ? 'bg-emerald-400' : 'bg-rose-400' }} opacity-75"></span>
                                    <span class="relative inline-flex rounded-full h-2 w-2 {{ $chat->is_active ? 'bg-emerald-500' : 'bg-rose-500' }}"></span>
                                </span>
                                {{ $chat->is_active ? 'ASISTENTE ACTIVO' : 'MODO HUMANO' }}
                            </button>
                        </td>
                        <td class="px-6 py-5">
                            <div class="text-sm font-medium text-zinc-900 dark:text-zinc-200">
                                {{ \Carbon\Carbon::parse($chat->last_activity)->diffForHumans() }}
                            </div>
                            <div class="text-[10px] text-zinc-400 font-semibold uppercase tracking-widest mt-0.5">
                                {{ \Carbon\Carbon::parse($chat->last_activity)->format('H:i:s d/m/Y') }}
                            </div>
                        </td>
                        <td class="px-6 py-5 text-right">
                            <div class="flex items-center justify-end gap-2">
                                @if($editingChatId === $chat->id)
                                <button wire:click="saveChat" class="p-2 text-emerald-600 hover:bg-emerald-50 dark:hover:bg-emerald-900/20 rounded-lg transition-colors" title="Guardar">
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                    </svg>
                                </button>
                                <button wire:click="cancelEdit" class="p-2 text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-900/20 rounded-lg transition-colors" title="Cancelar">
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                                @else
                                <button wire:click="editChat({{ $chat->id }})" class="p-2 text-zinc-400 hover:text-indigo-600 hover:bg-zinc-100 dark:hover:bg-zinc-800 rounded-lg transition-all" title="Editar">
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                    </svg>
                                </button>
                                <a href="{{ route('chat.detail', $chat->id) }}" class="inline-flex items-center px-4 py-2 bg-zinc-900 dark:bg-white text-white dark:text-zinc-900 text-xs font-bold rounded-xl hover:bg-zinc-800 dark:hover:bg-zinc-100 transition shadow-lg shadow-zinc-200 dark:shadow-none">
                                    ABRIR
                                    <svg class="w-4 h-4 ml-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                    </svg>
                                </a>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="px-6 py-20 text-center">
                            <div class="inline-flex items-center justify-center w-16 h-16 rounded-3xl bg-zinc-50 dark:bg-zinc-800 text-zinc-300 dark:text-zinc-600 mb-4">
                                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                                </svg>
                            </div>
                            <p class="text-zinc-500 dark:text-zinc-400 font-medium">No hay conversaciones activas.</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Mobile View -->
        <div class="lg:hidden divide-y divide-zinc-100 dark:divide-zinc-800">
            @forelse($chats as $chat)
            <div class="p-6 space-y-4">
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <div class="h-14 w-14 rounded-2xl bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center text-white font-bold text-2xl shadow-lg">
                            {{ strtoupper(substr($chat->name ?? '?', 0, 1)) }}
                        </div>
                        <div class="ml-4">
                            @if($editingChatId === $chat->id)
                            <input type="text" wire:model="editingName" class="w-full px-3 py-1 text-sm border-none bg-zinc-100 dark:bg-zinc-800 rounded-lg ring-1 ring-indigo-500">
                            @else
                            <h3 class="text-lg font-extrabold text-zinc-900 dark:text-zinc-100">{{ $chat->name ?? 'Sin Nombre' }}</h3>
                            <p class="text-xs font-bold text-zinc-500 tracking-tight">{{ str_replace(['@s.whatsapp.net', '+'], '', $chat->remote_jid) }}</p>
                            @endif
                        </div>
                    </div>
                    <div class="text-right">
                        <p class="text-[10px] font-bold text-zinc-400 uppercase tracking-widest">{{ \Carbon\Carbon::parse($chat->last_activity)->diffForHumans(['short' => true]) }}</p>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div class="p-3 bg-zinc-50 dark:bg-zinc-800/40 rounded-2xl">
                        <p class="text-[10px] font-bold text-zinc-400 uppercase mb-1">Estado</p>
                        @if($editingChatId === $chat->id)
                        <input type="number" wire:model="editingStage" class="w-full py-0 bg-transparent border-none text-sm font-bold p-0 focus:ring-0">
                        @else
                        <p class="text-sm font-bold text-indigo-600">Fase {{ $chat->stage }}</p>
                        @endif
                    </div>
                    <div class="p-3 bg-zinc-50 dark:bg-zinc-800/40 rounded-2xl">
                        <p class="text-[10px] font-bold text-zinc-400 uppercase mb-1">Bot</p>
                        <button wire:click="toggleActive({{ $chat->id }})" class="text-sm font-bold {{ $chat->is_active ? 'text-emerald-600' : 'text-rose-600' }}">
                            {{ $chat->is_active ? 'HABILITADO' : 'PAUSADO' }}
                        </button>
                    </div>
                </div>

                <div class="flex gap-2">
                    @if($editingChatId === $chat->id)
                    <button wire:click="saveChat" class="flex-1 py-3 bg-emerald-600 text-white font-bold rounded-2xl shadow-lg shadow-emerald-200/50">GUARDAR</button>
                    <button wire:click="cancelEdit" class="px-5 py-3 bg-zinc-100 dark:bg-zinc-800 text-zinc-500 font-bold rounded-2xl">X</button>
                    @else
                    <a href="{{ route('chat.detail', $chat->id) }}" class="flex-1 py-4 bg-zinc-900 dark:bg-zinc-100 text-white dark:text-zinc-900 text-xs font-black tracking-widest text-center rounded-2xl shadow-xl">ABRIR CHAT</a>
                    <button wire:click="editChat({{ $chat->id }})" class="px-5 py-4 bg-zinc-100 dark:bg-zinc-800 text-zinc-500 rounded-2xl">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                        </svg>
                    </button>
                    @endif
                </div>
            </div>
            @empty
            <!-- Empty state handled in desktop view since they share the container -->
            @endforelse
        </div>

        <!-- Pagination Footer -->
        @if($chats->hasPages())
        <div class="px-6 py-4 bg-zinc-50 dark:bg-zinc-800/50 border-t border-zinc-100 dark:border-zinc-800">
            {{ $chats->links() }}
        </div>
        @endif
    </div>
</div>