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
                    {{ $chat->name ?? 'Desconocido' }}
                </h3>
                <p class="text-[12px] text-zinc-700 dark:text-zinc-400 truncate">
                    {{ str_replace('@s.whatsapp.net', '', $chat->remote_jid) }}
                </p>
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
    <div
        x-data
        x-init="$el.scrollTop = $el.scrollHeight"
        x-on:livewire:navigated.window="$nextTick(() => $el.scrollTop = $el.scrollHeight)"
        wire:updated="$nextTick(() => $el.scrollTop = $el.scrollHeight)"
        class="flex-1 overflow-y-auto no-scrollbar p-4 space-y-4 relative">

        <!-- Welcome Message / System Note -->
        <div class="flex justify-center mb-6">
            <span
                class="bg-white dark:bg-[#111b21]/90 backdrop-blur-sm px-2 py-1 rounded-lg text-xs font-medium text-zinc-500 dark:text-zinc-400 shadow-sm uppercase tracking-wider">
                @php
                $chatDate = \Carbon\Carbon::parse($chat->created_at)->startOfDay();
                $today = \Carbon\Carbon::today();
                $yesterday = \Carbon\Carbon::yesterday();
                if ($chatDate->equalTo($today)) {
                $chatLabel = 'Hoy';
                } elseif ($chatDate->equalTo($yesterday)) {
                $chatLabel = 'Ayer';
                } else {
                $chatLabel = $chatDate->translatedFormat('d F Y');
                }
                @endphp
                {{ $chatLabel }}
            </span>
        </div>

        @php $lastMsgDate = null; @endphp
        @foreach ($messages as $msg)
        @php
        $msgDay = \Carbon\Carbon::parse($msg->created_at)->startOfDay();
        $todayDay = \Carbon\Carbon::today();
        $yesterdayDay = \Carbon\Carbon::yesterday();
        if ($lastMsgDate === null || !$msgDay->equalTo($lastMsgDate)) {
        if ($msgDay->equalTo($todayDay)) {
        $dayLabel = 'Hoy';
        } elseif ($msgDay->equalTo($yesterdayDay)) {
        $dayLabel = 'Ayer';
        } else {
        $dayLabel = $msgDay->translatedFormat('d F Y');
        }
        $lastMsgDate = $msgDay->copy();
        } else {
        $dayLabel = null;
        }
        @endphp
        @if ($dayLabel)
        <div class="flex justify-center my-3">
            <span
                class="bg-white dark:bg-[#111b21]/90 backdrop-blur-sm px-3 py-1 rounded-lg text-xs font-medium text-zinc-500 dark:text-zinc-400 shadow-sm uppercase tracking-wider">
                {{ $dayLabel }}
            </span>
        </div>
        @endif
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
                        {{ \Carbon\Carbon::parse($msg->created_at)->format('g:i A') }}
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
    <div class="px-3 py-2">

        @if (session('error'))
        <div class="text-xs text-red-500 text-center mb-1 px-2">{{ session('error') }}</div>
        @endif

        <div class="flex items-center space-x-2">
            <!-- Mode Toggle -->
            <div class="space-y-1 flex-none">
                <button wire:click="setSendMode('whatsapp')"
                    class="flex items-center gap-1 text-[11px] px-2.5 py-1 rounded-full border transition-all
                        {{ $sendMode === 'whatsapp'
                            ? 'bg-emerald-100 border-emerald-400 text-emerald-800 font-semibold'
                            : 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50' }}">
                    <span class=" hidden md:block"> 📤 </span>Enviar
                </button>

                <button wire:click="setSendMode('note')"
                    class="flex items-center gap-1 text-[11px] px-2.5 py-1 rounded-full border transition-all
                        {{ $sendMode === 'note'
                            ? 'bg-zinc-100 border-zinc-700 text-zinc-800 font-semibold'
                            : 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50' }}">
                    <span class=" hidden md:block"> 🗒️ </span>Interna
                </button>
            </div>

            <!-- Input Row -->
            <div x-data="{ rows: 1 }"
                class="flex flex-1 items-end gap-2 px-4 py-2 shadow-sm transition-all
                {{ $sendMode === 'whatsapp' ? 'bg-white border-2 border-emerald-400' : 'bg-white border-2 border-zinc-700' }}"
                :class="rows > 1 ? 'rounded-2xl' : 'rounded-xl'">

                <!-- Textarea auto-grow -->
                <textarea wire:model="memoryInput" x-ref="textarea" rows="2"
                    placeholder="{{ $sendMode === 'whatsapp' ? 'Escribe un mensaje para enviar...' : 'Escribe una nota interna...' }}"
                    class="flex-1 outline-none text-sm text-gray-800 placeholder-gray-400 bg-transparent resize-none leading-5 max-h-40 overflow-y-auto py-0.5"
                    @input="
                        $el.style.height = 'auto';
                        $el.style.height = $el.scrollHeight + 'px';
                        rows = $el.value.split('\n').length;
                    "
                    @keydown.enter.prevent="
                        if ($event.shiftKey) {
                            let start = $el.selectionStart;
                            let end   = $el.selectionEnd;
                            $el.value = $el.value.substring(0, start) + '\n' + $el.value.substring(end);
                            $el.selectionStart = $el.selectionEnd = start + 1;
                            $el.dispatchEvent(new Event('input'));
                        } else {
                            $wire.call('submitMessage');
                            $nextTick(() => {
                                $el.style.height = 'auto';
                                rows = 1;
                            });
                        }
                    "></textarea>

                <!-- Send Button -->
                <button wire:click="submitMessage"
                    class="rounded-full p-1.5 mb-0.5 shrink-0 transition-colors
                        {{ $sendMode === 'whatsapp'
                            ? 'bg-emerald-500 hover:bg-emerald-600 text-white'
                            : 'bg-zinc-400 hover:bg-zinc-500 text-white' }}">
                    @if ($sendMode === 'whatsapp')
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" stroke="currentColor"
                        stroke-width="2" viewBox="0 0 24 24">
                        <path d="M22 2L11 13" />
                        <path d="M22 2L15 22l-4-9-9-4 20-7z" />
                    </svg>
                    @else
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" stroke="currentColor"
                        stroke-width="2" viewBox="0 0 24 24">
                        <path d="M12 5v14m-7-7h14" />
                    </svg>
                    @endif
                </button>
            </div>
        </div>
    </div>
</div>