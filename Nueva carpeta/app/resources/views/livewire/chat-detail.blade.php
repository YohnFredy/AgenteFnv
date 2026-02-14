<div class="flex flex-col h-[100dvh] sm:h-[calc(100vh-10rem)] bg-[#efeae2] dark:bg-[#0b141a] sm:rounded-lg shadow-lg overflow-hidden sm:border dark:border-zinc-700">
    <!-- Header -->
    <div class="bg-[#008069] dark:bg-[#202c33] px-3 sm:px-4 py-3 flex items-center justify-between shadow-md">
        <div class="flex items-center min-w-0">
            <a href="{{ route('chat.list') }}" class="mr-2 sm:mr-3 text-white/80 hover:text-white p-1 -ml-1 rounded-full hover:bg-white/10 transition">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                </svg>
            </a>
            <div class="h-10 w-10 rounded-full bg-gradient-to-br from-gray-300 to-gray-400 flex items-center justify-center text-gray-600 font-bold text-lg mr-2 sm:mr-3 flex-shrink-0 shadow">
                {{ strtoupper(substr($chat->name ?? '?', 0, 1)) }}
            </div>
            <div class="min-w-0">
                <h3 class="font-semibold text-white truncate">{{ $chat->name ?? 'Desconocido' }}</h3>
                <p class="text-xs text-white/70 truncate">{{ str_replace('@s.whatsapp.net', '', $chat->remote_jid) }}</p>
            </div>
        </div>
        <div class="flex items-center gap-2 flex-shrink-0">
            <span class="hidden sm:inline-flex px-2.5 py-1 text-xs font-semibold rounded-full bg-white/20 text-white backdrop-blur">
                Stage {{ $chat->stage }}
            </span>
            <span class="sm:hidden px-2 py-0.5 text-[10px] font-semibold rounded-full bg-white/20 text-white">
                {{ $chat->stage }}
            </span>
        </div>
    </div>

    <!-- Chat Area -->
    <div class="flex-1 overflow-y-auto p-3 sm:p-4 space-y-2 sm:space-y-3" style="background-image: url('https://user-images.githubusercontent.com/15075759/28719144-86dc0f70-73b1-11e7-911d-60d70fcded21.png'); background-repeat: repeat; background-size: 400px;">
        @foreach($messages as $msg)
        <div class="flex {{ $msg->role === 'user' ? 'justify-end' : 'justify-start' }}">
            <div class="max-w-[85%] sm:max-w-[70%] rounded-lg px-3 sm:px-4 py-2 shadow-sm relative text-sm 
                    {{ $msg->role === 'user' 
                        ? 'bg-[#d9fdd3] text-gray-800 rounded-tr-none' 
                        : 'bg-white text-gray-800 rounded-tl-none' 
                    }}">

                {{-- AUDIO --}}
                @if($msg->media_type === 'audio' && ($msg->media_path || $msg->media_url))
                <div class="mb-2">
                    @if($msg->media_path)
                    <audio controls class="w-full h-8 mt-1 max-w-[250px] sm:max-w-full">
                        <source src="{{ asset('storage/' . $msg->media_path) }}" type="audio/ogg">
                        <source src="{{ asset('storage/' . $msg->media_path) }}" type="audio/mpeg">
                        Tu navegador no soporta el elemento de audio.
                    </audio>
                    @else
                    <div class="flex items-center gap-2 text-gray-500 text-xs bg-gray-100 rounded px-2 py-1.5">
                        <svg class="w-4 h-4 animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z"></path>
                        </svg>
                        <span>Procesando audio...</span>
                    </div>
                    @endif
                </div>
                @if($msg->content && $msg->content !== '[Audio Message]')
                <p class="text-[10px] text-gray-500 font-semibold mb-1">📝 Transcripción:</p>
                @endif
                @endif

                {{-- IMAGEN --}}
                @if($msg->media_type === 'image')
                <div class="mb-2">
                    @if($msg->media_path)
                    <img src="{{ asset('storage/' . $msg->media_path) }}"
                        alt="Imagen enviada"
                        class="rounded-lg max-w-full max-h-48 sm:max-h-64 cursor-pointer hover:opacity-90 transition shadow-sm"
                        onclick="window.open(this.src, '_blank')">
                    @else
                    <div class="flex items-center gap-2 text-gray-500 text-xs bg-gray-100 rounded px-2 py-1.5">
                        <svg class="w-4 h-4 animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                        <span>Procesando imagen...</span>
                    </div>
                    @endif
                </div>
                @if($msg->content && $msg->content !== '[Image Message]')
                <p class="text-[10px] text-gray-500 font-semibold mb-1">💬 Mensaje:</p>
                @endif
                @endif

                {{-- Don't show placeholder content --}}
                @if($msg->content && !in_array($msg->content, ['[Audio Message]', '[Image Message]']))
                <p class="whitespace-pre-wrap break-words">{{ $msg->content }}</p>
                @endif

                <div class="text-[10px] text-gray-500 text-right mt-1 flex items-center justify-end gap-1">
                    {{ $msg->created_at->format('h:i A') }}
                    @if($msg->role === 'assistant')
                    <svg class="w-3.5 h-3.5 text-blue-500" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z" />
                    </svg>
                    @endif
                </div>
            </div>
        </div>
        @endforeach
    </div>

    <!-- Footer (Controls) -->
    <div class="bg-gray-100 dark:bg-zinc-800 px-3 sm:px-4 py-3 border-t dark:border-zinc-700 space-y-3">

        <!-- Status Row -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
            <div class="flex items-center gap-2">
                <span class="text-sm font-semibold {{ $chat->is_active ? 'text-green-600' : 'text-red-500' }}">
                    {{ $chat->is_active ? '🤖 Bot Activo' : '👤 Esperando Humano' }}
                </span>
            </div>

            <div class="flex gap-2">
                @if(!$chat->is_active)
                <button wire:click="toggleActive" class="flex-1 sm:flex-none px-4 py-2 bg-green-600 hover:bg-green-700 text-white text-xs sm:text-sm rounded-lg shadow font-medium transition active:scale-95">
                    ✓ Reactivar Bot
                </button>
                @else
                <button wire:click="toggleActive" class="flex-1 sm:flex-none px-4 py-2 bg-red-500 hover:bg-red-600 text-white text-xs sm:text-sm rounded-lg shadow font-medium transition active:scale-95">
                    ✕ Desactivar Bot
                </button>
                @endif
            </div>
        </div>

        <!-- Memory Input -->
        <div class="flex gap-2">
            <input
                wire:model="memoryInput"
                type="text"
                placeholder="Agregar nota a memoria..."
                class="flex-1 rounded-lg border-gray-300 dark:border-zinc-600 dark:bg-zinc-900 dark:text-gray-100 text-sm p-2.5 focus:ring-2 focus:ring-green-500 focus:border-green-500 transition">
            <button wire:click="addMemory" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium shadow transition active:scale-95 flex-shrink-0">
                <span class="hidden sm:inline">Guardar</span>
                <span class="sm:hidden">💾</span>
            </button>
        </div>

        <div class="text-center text-[10px] text-gray-400">
            Los mensajes nuevos aparecerán al recargar
        </div>
    </div>
</div>