<div class="space-y-4">
    <!-- Header with Search -->
    <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-3">
        <h2 class="text-xl font-bold text-gray-800 dark:text-gray-200">Monitor de Chats</h2>
        <div class="relative w-full sm:w-64">
            <input
                type="text"
                wire:model.live.debounce.300ms="search"
                placeholder="Buscar por nombre o número..."
                class="w-full pl-10 pr-4 py-2.5 border rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 dark:bg-zinc-800 dark:border-zinc-700 dark:text-gray-200 transition">
            <div class="absolute left-3 top-3 text-gray-400">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
            </div>
        </div>
    </div>

    <!-- Mobile Cards View (visible on small screens) -->
    <div class="lg:hidden space-y-3">
        @forelse($chats as $chat)
        <a href="{{ route('chat.detail', $chat->id) }}"
            class="block bg-white dark:bg-zinc-800 rounded-xl shadow-sm border dark:border-zinc-700 p-4 hover:shadow-md transition {{ !$chat->is_active ? 'border-l-4 border-l-red-500' : 'border-l-4 border-l-green-500' }}">
            <div class="flex items-center justify-between">
                <div class="flex items-center min-w-0 flex-1">
                    <!-- Avatar -->
                    <div class="h-12 w-12 rounded-full bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center text-white font-bold text-lg flex-shrink-0 shadow-md">
                        {{ strtoupper(substr($chat->name ?? '?', 0, 1)) }}
                    </div>

                    <!-- Info -->
                    <div class="ml-3 min-w-0 flex-1">
                        <div class="flex items-center justify-between">
                            <p class="text-sm font-semibold text-gray-900 dark:text-gray-100 truncate">
                                {{ $chat->name ?? 'Desconocido' }}
                            </p>
                            <span class="text-xs text-gray-500 dark:text-gray-400 ml-2 flex-shrink-0">
                                {{ $chat->updated_at->diffForHumans(['short' => true]) }}
                            </span>
                        </div>
                        <p class="text-xs text-gray-500 dark:text-gray-400 truncate">
                            {{ str_replace('@s.whatsapp.net', '', $chat->remote_jid) }}
                        </p>

                        <!-- Status Badges -->
                        <div class="flex items-center gap-2 mt-2">
                            <span class="px-2 py-0.5 text-[10px] font-semibold rounded-full bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-200">
                                Stage {{ $chat->stage }}
                            </span>
                            <span class="px-2 py-0.5 text-[10px] font-semibold rounded-full {{ $chat->is_active ? 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-200' : 'bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-200' }}">
                                {{ $chat->is_active ? '🤖 Bot' : '👤 Humano' }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Arrow -->
                <svg class="w-5 h-5 text-gray-400 ml-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                </svg>
            </div>
        </a>
        @empty
        <div class="bg-white dark:bg-zinc-800 rounded-xl p-8 text-center text-gray-500 dark:text-gray-400 border dark:border-zinc-700">
            <svg class="w-12 h-12 mx-auto mb-3 text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
            </svg>
            <p>No se encontraron chats.</p>
        </div>
        @endforelse
    </div>

    <!-- Desktop Table View (hidden on small screens) -->
    <div class="hidden lg:block bg-white dark:bg-zinc-900 shadow-sm overflow-hidden rounded-xl border dark:border-zinc-700">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-zinc-700">
            <thead class="bg-gray-50 dark:bg-zinc-800">
                <tr>
                    <th scope="col" class="px-6 py-4 text-left text-xs font-bold uppercase tracking-wider cursor-pointer group hover:bg-gray-100 dark:hover:bg-zinc-700 transition" wire:click="sortBy('remote_jid')">
                        <span class="flex items-center text-gray-500 dark:text-gray-400">
                            Usuario
                            @if($sortField === 'remote_jid')
                            <span class="ml-1 text-indigo-500">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                            @endif
                        </span>
                    </th>
                    <th scope="col" class="px-6 py-4 text-left text-xs font-bold uppercase tracking-wider cursor-pointer hover:bg-gray-100 dark:hover:bg-zinc-700 transition" wire:click="sortBy('stage')">
                        <span class="flex items-center text-gray-500 dark:text-gray-400">
                            Stage
                            @if($sortField === 'stage')
                            <span class="ml-1 text-indigo-500">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                            @endif
                        </span>
                    </th>
                    <th scope="col" class="px-6 py-4 text-left text-xs font-bold uppercase tracking-wider cursor-pointer hover:bg-gray-100 dark:hover:bg-zinc-700 transition" wire:click="sortBy('is_active')">
                        <span class="flex items-center text-gray-500 dark:text-gray-400">
                            Estado
                            @if($sortField === 'is_active')
                            <span class="ml-1 text-indigo-500">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                            @endif
                        </span>
                    </th>
                    <th scope="col" class="px-6 py-4 text-left text-xs font-bold uppercase tracking-wider cursor-pointer hover:bg-gray-100 dark:hover:bg-zinc-700 transition" wire:click="sortBy('updated_at')">
                        <span class="flex items-center text-gray-500 dark:text-gray-400">
                            Última Actividad
                            @if($sortField === 'updated_at')
                            <span class="ml-1 text-indigo-500">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                            @endif
                        </span>
                    </th>
                    <th scope="col" class="px-6 py-4 text-left text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                        Acciones
                    </th>
                </tr>
            </thead>
            <tbody class="bg-white dark:bg-zinc-900 divide-y divide-gray-200 dark:divide-zinc-700">
                @forelse($chats as $chat)
                <tr class="hover:bg-gray-50 dark:hover:bg-zinc-800 transition {{ !$chat->is_active ? 'bg-red-50/50 dark:bg-red-900/10' : '' }}">
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 h-11 w-11">
                                <div class="h-11 w-11 rounded-full bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center text-white font-bold text-lg shadow-md">
                                    {{ strtoupper(substr($chat->name ?? '?', 0, 1)) }}
                                </div>
                            </div>
                            <div class="ml-4">
                                <div class="text-sm font-semibold text-gray-900 dark:text-gray-200">
                                    {{ $chat->name ?? 'Desconocido' }}
                                </div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ str_replace('@s.whatsapp.net', '', $chat->remote_jid) }}
                                </div>
                            </div>
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                            {{ $chat->stage }}
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <button wire:click="toggleActive({{ $chat->id }})"
                            class="px-3 py-1.5 inline-flex text-xs leading-5 font-semibold rounded-full cursor-pointer transition-all duration-200 hover:scale-105 active:scale-95
                                {{ $chat->is_active ? 'bg-green-100 text-green-800 hover:bg-green-200 dark:bg-green-900 dark:text-green-200' : 'bg-red-100 text-red-800 hover:bg-red-200 dark:bg-red-900 dark:text-red-200' }}">
                            {{ $chat->is_active ? '🤖 Activo' : '👤 Esperando Humano' }}
                        </button>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                        {{ $chat->updated_at->diffForHumans() }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                        <a href="{{ route('chat.detail', $chat->id) }}" class="inline-flex items-center px-3 py-1.5 bg-indigo-50 text-indigo-600 hover:bg-indigo-100 dark:bg-indigo-900/30 dark:text-indigo-400 dark:hover:bg-indigo-900/50 rounded-lg font-semibold transition">
                            Ver Chat
                            <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                            </svg>
                        </a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                        <svg class="w-12 h-12 mx-auto mb-3 text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                        </svg>
                        <p>No se encontraron chats.</p>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <div class="mt-4">
        {{ $chats->links() }}
    </div>
</div>