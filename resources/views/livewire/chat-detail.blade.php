<div class="flex flex-col h-full w-full bg-[#efeae2] dark:bg-[#0b141a] overflow-hidden relative shadow-inner">
    <!-- Header -->
    <div class="bg-zinc-50 dark:bg-[#202c33] px-4 py-2 border-b border-zinc-200 dark:border-zinc-800 flex items-center justify-between z-10">
        <div class="flex items-center min-w-0">
            <!-- Back button (Mobile only) -->
            <button wire:click="$parent.resetSelection()" class="md:hidden mr-2 text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 p-2 rounded-full hover:bg-zinc-200 dark:hover:bg-[#2a3942] transition">
                <flux:icon.chevron-left class="size-5" />
            </button>

            <!-- Sidebar Toggle (Mobile only) -->
            <button x-on:click="$dispatch('flux-sidebar-toggle')" class="lg:hidden mr-2 text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 p-2 rounded-full hover:bg-zinc-200 dark:hover:bg-[#2a3942] transition">
                <flux:icon.bars-2 class="size-5" />
            </button>

            <div class="h-10 w-10 rounded-full bg-zinc-200 dark:bg-zinc-700 flex items-center justify-center text-zinc-500 dark:text-zinc-400 font-bold mr-3 flex-shrink-0">
                @if($chat->avatar_url)
                <img src="{{ $chat->avatar_url }}" alt="{{ $chat->name }}" class="h-full w-full object-cover rounded-full">
                @else
                {{ strtoupper(substr($chat->name ?? '?', 0, 1)) }}
                @endif
            </div>
            <div class="min-w-0">
                <h3 class="font-medium text-zinc-900 dark:text-zinc-100 truncate text-base">{{ $chat->name ?? 'Desconocido' }}</h3>
                <p class="text-[12px] text-zinc-500 dark:text-zinc-400 truncate">hace clic aquí para ver la información de contacto</p>
            </div>
        </div>
        <div class="flex items-center gap-5 text-zinc-500 dark:text-zinc-400">
            <button class="hover:bg-zinc-200 dark:hover:bg-[#2a3942] p-2 rounded-full transition"><flux:icon.video-camera class="size-5" /></button>
            <button class="hover:bg-zinc-200 dark:hover:bg-[#2a3942] p-2 rounded-full transition"><flux:icon.magnifying-glass class="size-5" /></button>
            <button class="hover:bg-zinc-200 dark:hover:bg-[#2a3942] p-2 rounded-full transition"><flux:icon.ellipsis-vertical class="size-5" /></button>
        </div>
    </div>

    <!-- Chat Area with WhatsApp Pattern -->
    <div class="flex-1 overflow-y-auto no-scrollbar p-4 space-y-4 relative"
        style="background-image: url('https://user-images.githubusercontent.com/15075759/28719144-86dc0f70-73b1-11e7-911d-60d70fcded21.png'); background-repeat: repeat; background-size: 400px; background-attachment: local;">

        <!-- Welcome Message / System Note -->
        <div class="flex justify-center mb-6">
            <span class="bg-white/90 dark:bg-[#111b21]/90 backdrop-blur-sm px-4 py-1.5 rounded-lg text-[12px] font-medium text-zinc-500 dark:text-zinc-400 shadow-sm uppercase tracking-wider">
                {{ \Carbon\Carbon::parse($chat->created_at)->format('d F Y') }}
            </span>
        </div>

        @foreach($messages as $msg)
        <div class="flex {{ $msg->role === 'user' ? 'justify-end' : 'justify-start' }}">
            <div class="max-w-[85%] sm:max-w-[65%] rounded-lg px-3 py-1.5 shadow-sm relative text-[14.2px] 
                    {{ $msg->role === 'assistant' 
                        ? 'bg-white dark:bg-[#202c33] text-zinc-900 dark:text-[#e9edef] rounded-tl-none' 
                        : 'bg-[#d9fdd3] dark:bg-[#005c4b] text-[#111b21] dark:text-[#e9edef] rounded-tr-none' 
                    }}">

                {{-- Audio Content --}}
                @if($msg->media_type === 'audio' && ($msg->media_path || $msg->media_url))
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
                @if($msg->media_type === 'image')
                <div class="mb-2 -mx-1 -mt-1">
                    <img src="{{ asset('storage/' . $msg->media_path) }}" class="rounded-lg shadow-inner max-h-80 w-full object-cover">
                </div>
                @endif

                {{-- Text Content --}}
                @if($msg->content && !in_array($msg->content, ['[Audio Message]', '[Image Message]']))
                <p class="leading-relaxed whitespace-pre-wrap">{{ $msg->content }}</p>
                @endif

                <div class="flex items-center justify-end gap-1 mt-1">
                    <span class="text-[11px] opacity-60">
                        {{ \Carbon\Carbon::parse($msg->created_at)->format('H:i') }}
                    </span>
                    @if($msg->role === 'assistant')
                    <flux:icon.check-badge class="size-3 text-blue-400" />
                    @endif
                </div>

                {{-- Bubble Tail Wrapper (CSS handled via custom classes) --}}
                <div class="absolute top-0 {{ $msg->role === 'user' ? '-right-2 w-0 h-0 border-t-[10px] border-t-[#d9fdd3] dark:border-t-[#005c4b] border-r-[10px] border-r-transparent' : '-left-2 w-0 h-0 border-t-[10px] border-t-white dark:border-t-[#202c33] border-l-[10px] border-l-transparent' }}"></div>
            </div>
        </div>
        @endforeach
    </div>

    <!-- Footer / Input Area -->
    <div class="bg-zinc-50 dark:bg-[#202c33] p-2 flex items-center gap-2 z-10 border-t border-zinc-200 dark:border-zinc-800">
        <div class="flex gap-2 text-zinc-500 dark:text-zinc-400 px-2">
            <button class="hover:bg-zinc-200 dark:hover:bg-[#2a3942] p-2 rounded-full transition"><flux:icon.face-smile class="size-6" /></button>
            <button class="hover:bg-zinc-200 dark:hover:bg-[#2a3942] p-2 rounded-full transition">
                <flux:icon.plus class="size-6" />
            </button>
        </div>

        <div class="flex-1 relative">
            <input
                wire:model="memoryInput"
                wire:keydown.enter="addMemory"
                type="text"
                placeholder="Escribe un mensaje"
                class="w-full py-2.5 px-4 bg-white dark:bg-[#2a3942] border-none text-[15px] dark:text-[#e9edef] rounded-lg focus:ring-0 transition-all placeholder-zinc-400">
        </div>

        <div class="px-2">
            @if($memoryInput)
            <button wire:click="addMemory" class="bg-emerald-500 hover:bg-emerald-600 text-white p-2.5 rounded-full shadow transition transform active:scale-90">
                <flux:icon.paper-airplane class="size-6" />
            </button>
            @else
            <button class="text-zinc-500 dark:text-zinc-400 hover:bg-zinc-200 dark:hover:bg-[#2a3942] p-2 rounded-full transition">
                <flux:icon.microphone class="size-6" />
            </button>
            @endif
        </div>
    </div>

    <!-- Bot Control floating bar (Sleek Overlay) -->
    <div class="absolute top-16 left-1/2 -translate-x-1/2 z-20">
        <button wire:click="toggleActive"
            class="flex items-center gap-2 px-6 py-2 rounded-full text-xs font-bold transition-all shadow-lg backdrop-blur-md
            {{ $chat->is_active 
                ? 'bg-emerald-500/90 text-white hover:bg-emerald-600' 
                : 'bg-rose-500/90 text-white hover:bg-rose-600' }}">
            <span class="relative flex h-2 w-2">
                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-white opacity-75"></span>
                <span class="relative inline-flex rounded-full h-2 w-2 bg-white"></span>
            </span>
            {{ $chat->is_active ? 'ASISTENTE ACTIVO' : 'MODO HUMANO' }}
        </button>
    </div>
</div>