<div class="flex flex-col h-full w-full  dark:bg-[#0b141a] overflow-hidden relative shadow-inner"
    style="background-image: url('https://user-images.githubusercontent.com/15075759/28719144-86dc0f70-73b1-11e7-911d-60d70fcded21.png'); background-repeat: repeat; background-size: 400px; background-attachment: local;">
    <!-- Header -->
    <div
        class="bg-white dark:bg-[#202c33] px-4 py-2 border-b border-zinc-200 dark:border-zinc-800 flex items-center justify-between z-10">
        <div class="flex items-center min-w-0">
            <!-- Back button (Mobile only) -->
            <button wire:click="$parent.resetSelection()"
                class="md:hidden text-zinc-500 mr-3 -ml-1 hover:text-zinc-700 dark:text-zinc-400 transition cursor-pointer">
                <flux:icon.chevron-left class="size-5" />
            </button>

            <div
                class="h-10 w-10 rounded-full bg-zinc-100 dark:bg-zinc-700 border border-gray-400 flex items-center justify-center text-zinc-600 dark:text-zinc-400 font-bold mr-3 shrink-0">
                @if ($chat->avatar_url)
                    <img src="{{ $chat->avatar_url }}" alt="{{ $chat->name }}"
                        class="h-full w-full object-cover rounded-full">
                @else
                    {{ strtoupper(substr($chat->name ?? '?', 0, 2)) }}
                @endif
            </div>
            <div class="min-w-0">
                <h3 class="font-medium text-zinc-900 dark:text-zinc-100 truncate text-base">
                    {{ $chat->name ?? 'Desconocido' }}</h3>
                <p class="text-[12px] text-zinc-700 dark:text-zinc-400 truncate">
                    {{ str_replace('@s.whatsapp.net', '', $chat->remote_jid) }}</p>
            </div>
        </div>
        <div class="flex items-center gap-1 md:gap-5 text-zinc-500 dark:text-zinc-400">

            <!-- Bot Control floating bar (Sleek Overlay) -->
            <button wire:click="toggleActive"
                class=" text-sm md:text-sm p-3.5 md:py-1.5 md:px-3 rounded-2xl {{ $chat->is_active
                    ? 'bg-emerald-500/90 text-white hover:bg-emerald-600'
                    : 'bg-rose-500/90 text-white hover:bg-rose-600' }}">
               <span class=" hidden md:block"> {{ $chat->is_active ? 'ASISTENTE ACTIVO' : 'MODO HUMANO' }}</span>
            </button>

            <button
                class="hover:bg-zinc-200 dark:hover:bg-[#2a3942] p-2 rounded-full transition"><flux:icon.ellipsis-vertical
                    class="size-5" /></button>
        </div>
    </div>

    <!-- Chat Area with WhatsApp Pattern -->
    <div class="flex-1 overflow-y-auto no-scrollbar p-4 space-y-4 relative">

        <!-- Welcome Message / System Note -->
        <div class="flex justify-center mb-6">
            <span
                class="bg-white dark:bg-[#111b21]/90 backdrop-blur-sm px-2 py-1 rounded-lg text-xs font-medium text-zinc-500 dark:text-zinc-400 shadow-sm uppercase tracking-wider">
                {{ \Carbon\Carbon::parse($chat->created_at)->format('d F Y') }}
            </span>
        </div>

        @foreach ($messages as $msg)
            <div class="flex {{ $msg->role === 'user' ? 'justify-end' : 'justify-start' }}">
                <div
                    class="max-w-[85%] sm:max-w-[65%] rounded-lg px-3 py-1.5 shadow-sm relative text-[14.2px] 
                    {{ $msg->role === 'assistant'
                        ? 'bg-white dark:bg-[#202c33] text-zinc-900 dark:text-[#e9edef] rounded-tl-none'
                        : 'bg-[#d9fdd3] dark:bg-[#005c4b] text-[#111b21] dark:text-[#e9edef] rounded-tr-none' }}">

                    {{-- Audio Content --}}
                    @if ($msg->media_type === 'audio' && ($msg->media_path || $msg->media_url))
                        <div class="flex items-center gap-3 p-1">
                            <button class="text-zinc-500 dark:text-zinc-400 hover:text-emerald-500 transition-colors">
                                <flux:icon.play-pause class="size-8" />
                            </button>
                            <div class="flex-1 space-y-1">
                                <div class="h-1 bg-zinc-200 dark:bg-zinc-600 rounded-full relative overflow-hidden">
                                    <div class="absolute inset-y-0 left-0 bg-emerald-500 w-[60%]"></div>
                                </div>
                                <div class="flex justify-between text-[11px] text-zinc-500">
                                    <span>0:45</span>
                                    <span>{{ $msg->created_at->format('H:i') }}</span>
                                </div>
                            </div>
                        </div>
                    @endif

                    {{-- Image Content --}}
                    @if ($msg->media_type === 'image')
                        <div class="mb-2 -mx-1 -mt-1">
                            <img src="{{ asset('storage/' . $msg->media_path) }}"
                                class="rounded-lg shadow-inner max-h-80 w-full object-cover">
                        </div>
                    @endif

                    {{-- Text Content --}}
                    @if ($msg->content && !in_array($msg->content, ['[Audio Message]', '[Image Message]']))
                        <p class="leading-relaxed whitespace-pre-wrap">{{ $msg->content }}</p>
                    @endif

                    <div class="flex items-center justify-end gap-1 mt-1">
                        <span class="text-[11px] opacity-60">
                            {{ \Carbon\Carbon::parse($msg->created_at)->format('H:i') }}
                        </span>
                        @if ($msg->role === 'assistant')
                            <flux:icon.check-badge class="size-3 text-blue-400" />
                        @endif
                    </div>

                    {{-- Bubble Tail Wrapper (CSS handled via custom classes) --}}
                    <div
                        class="absolute top-0 {{ $msg->role === 'user' ? '-right-2 w-0 h-0 border-t-10 border-t-[#d9fdd3] dark:border-t-[#005c4b] border-r-10 border-r-transparent' : '-left-2 w-0 h-0 border-t-10 border-t-white dark:border-t-[#202c33] border-l-10 border-l-transparent' }}">
                    </div>
                </div>
            </div>
        @endforeach

    </div>

    <!-- Footer / Input Area -->
    <div class="p-4">

        <div class="flex items-center gap-3 bg-white rounded-full px-4 py-2 shadow-sm">

            <!-- Icono + -->
            <button class="text-gray-900 hover:text-gray-700 transition">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" stroke="currentColor"
                    stroke-width="2" viewBox="0 0 24 24">
                    <path d="M12 5v14m-7-7h14" />
                </svg>
            </button>

            <!-- Icono Emoji -->
            <button class="text-gray-900 hover:text-gray-700 transition">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" stroke="currentColor"
                    stroke-width="2" viewBox="0 0 24 24">
                    <circle cx="12" cy="12" r="10" />
                    <path d="M8 15s1.5 2 4 2 4-2 4-2" />
                    <line x1="9" y1="9" x2="9.01" y2="9" />
                    <line x1="15" y1="9" x2="15.01" y2="9" />
                </svg>
            </button>

            <!-- Input -->
            <input wire:model="memoryInput" wire:keydown.enter="addMemory" type="text"
                placeholder="Escribe un mensaje"
                class="flex-1 outline-none text-sm text-gray-800 placeholder-gray-700 bg-transparent" />

            <!-- Icono Micrófono -->
            <button class="text-gray-900 hover:text-gray-700 transition">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" stroke="currentColor"
                    stroke-width="2" viewBox="0 0 24 24">
                    <path d="M12 1v11" />
                    <rect x="9" y="1" width="6" height="11" rx="3" />
                    <path d="M5 11a7 7 0 0014 0" />
                    <line x1="12" y1="19" x2="12" y2="23" />
                    <line x1="8" y1="23" x2="16" y2="23" />
                </svg>
            </button>
        </div>
    </div>
</div>
