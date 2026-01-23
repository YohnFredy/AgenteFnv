<div class="flex flex-col h-[calc(100vh-10rem)] bg-[#efeae2] dark:bg-[#0b141a] rounded-lg shadow-lg overflow-hidden border dark:border-zinc-700">
    <!-- Header -->
    <div class="bg-gray-100 dark:bg-zinc-800 px-4 py-3 flex items-center justify-between border-b dark:border-zinc-700">
        <div class="flex items-center">
            <a href="{{ route('chat.list') }}" class="mr-3 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                </svg>
            </a>
            <div class="h-10 w-10 rounded-full bg-gray-300 flex items-center justify-center text-gray-600 font-bold text-lg mr-3">
                {{ strtoupper(substr($chat->name ?? '?', 0, 1)) }}
            </div>
            <div>
                <h3 class="font-bold text-gray-800 dark:text-gray-100">{{ $chat->name ?? 'Desconocido' }}</h3>
                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $chat->remote_jid }}</p>
            </div>
        </div>
        <div>
            <span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
                Stage: {{ $chat->stage }}
            </span>
        </div>
    </div>

    <!-- Chat Area -->
    <div class="flex-1 overflow-y-auto p-4 space-y-4" style="background-image: url('https://user-images.githubusercontent.com/15075759/28719144-86dc0f70-73b1-11e7-911d-60d70fcded21.png'); background-repeat: repeat;">
        @foreach($messages as $msg)
        <div class="flex {{ $msg->role === 'user' ? 'justify-end' : 'justify-start' }}">
            <div class="max-w-[70%] rounded-lg px-4 py-2 shadow-sm relative text-sm 
                    {{ $msg->role === 'user' 
                        ? 'bg-[#d9fdd3] text-gray-800 rounded-tr-none' 
                        : 'bg-white text-gray-800 rounded-tl-none' 
                    }}">
                <p class="whitespace-pre-wrap">{{ $msg->content }}</p>
                <div class="text-[10px] text-gray-500 text-right mt-1">
                    {{ $msg->created_at->format('h:i A') }}
                </div>
            </div>
        </div>
        @endforeach
    </div>

    <!-- Footer (Controls) -->
    <div class="bg-gray-100 dark:bg-zinc-800 px-4 py-3 border-t dark:border-zinc-700 flex flex-col space-y-3">

        <!-- Memory & Status Controls -->
        <div class="flex items-center justify-between">
            <div class="flex items-center space-x-2">
                <span class="text-sm font-semibold {{ $chat->is_active ? 'text-green-600' : 'text-red-500' }}">
                    Estado: {{ $chat->is_active ? '🤖 Bot Activo' : '👤 Esperando Humano' }}
                </span>

                @if(!$chat->is_active)
                <button wire:click="toggleActive" class="px-3 py-1 bg-green-600 hover:bg-green-700 text-white text-xs rounded shadow">
                    Reactivar Bot
                </button>
                @else
                <button wire:click="toggleActive" class="px-3 py-1 bg-red-500 hover:bg-red-600 text-white text-xs rounded shadow">
                    Desactivar (Handoff Manual)
                </button>
                @endif
            </div>
        </div>

        <!-- Add Memory Input -->
        <div class="flex gap-2">
            <input
                wire:model="memoryInput"
                type="text"
                placeholder="Agregar nota a memoria (ej: 'Se acordó llamar mañana')..."
                class="flex-1 rounded border-gray-300 dark:border-zinc-600 dark:bg-zinc-900 dark:text-gray-100 text-sm p-2 focus:ring-green-500 focus:border-green-500">
            <button wire:click="addMemory" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded text-sm font-medium">
                Guardar Memoria
            </button>
        </div>

        <div class="text-center text-[10px] text-gray-400">
            Historial local. Los mensajes nuevos aparecerán al recargar.
        </div>
    </div>
</div>