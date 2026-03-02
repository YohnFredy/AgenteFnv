<div class="p-6 max-w-6xl mx-auto space-y-8">

    {{-- Page Header --}}
    <div class="mb-6">
        <flux:heading size="xl" class="flex items-center gap-2">
            <flux:icon name="phone" class="w-6 h-6 text-indigo-500" />
            Gestor de Teléfonos Registrados
        </flux:heading>
        <flux:subheading>
            Normaliza los números de teléfono importados y etiqueta automáticamente los chats coincidentes con <span class="font-semibold text-emerald-500">"Registrado"</span>.
        </flux:subheading>
    </div>

    {{-- Step Progress Bar --}}
    <div class="flex items-center mb-8 gap-0">
        @php
        $steps = [
        ['key' => 'preview', 'label' => 'Vista Previa', 'icon' => 'eye'],
        ['key' => 'normalized', 'label' => 'Normalización', 'icon' => 'check-circle'],
        ['key' => 'analyzed', 'label' => 'Análisis y Tags', 'icon' => 'tag'],
        ];
        $order = ['preview' => 0, 'normalized' => 1, 'analyzed' => 2];
        $currentOrder = $order[$step] ?? 0;
        @endphp
        @foreach ($steps as $i => $s)
        @php
        $sOrder = $order[$s['key']];
        $isActive = $s['key'] === $step;
        $isComplete = $sOrder < $currentOrder;
            @endphp
            <div class="flex items-center {{ $i < count($steps) - 1 ? 'flex-1' : '' }}">
            <div class="flex flex-col items-center">
                <div class="w-9 h-9 rounded-full flex items-center justify-center text-sm font-bold transition-all
                                {{ $isComplete ? 'bg-emerald-500 text-white' : ($isActive ? 'bg-indigo-600 text-white ring-4 ring-indigo-200 dark:ring-indigo-900' : 'bg-zinc-700 text-zinc-400') }}">
                    @if($isComplete)
                    <flux:icon name="check" class="w-5 h-5" />
                    @else
                    {{ $i + 1 }}
                    @endif
                </div>
                <span class="text-xs mt-1 {{ $isActive ? 'text-indigo-400 font-semibold' : 'text-zinc-500' }}">{{ $s['label'] }}</span>
            </div>
            @if ($i < count($steps) - 1)
                <div class="flex-1 h-0.5 mx-2 mt-[-16px] {{ $isComplete ? 'bg-emerald-500' : 'bg-zinc-700' }}">
    </div>
    @endif
</div>
@endforeach
</div>

{{-- Messages --}}
@if ($successMessage)
<div class="mb-4 p-4 rounded-xl bg-emerald-900/30 border border-emerald-700 text-emerald-300 text-sm">
    {{ $successMessage }}
</div>
@endif
@if ($errorMessage)
<div class="mb-4 p-4 rounded-xl bg-red-900/30 border border-red-700 text-red-300 text-sm">
    {{ $errorMessage }}
</div>
@endif

{{-- ── STEP 1 & 2: Preview / Normalized ── --}}
@if ($step === 'preview' || $step === 'normalized')
<div class="bg-zinc-900 rounded-2xl border border-zinc-700 overflow-hidden">
    {{-- Summary Cards --}}
    <div class="grid grid-cols-3 divide-x divide-zinc-700 border-b border-zinc-700">
        <div class="p-5 text-center">
            <p class="text-3xl font-bold text-white">{{ $stats['total'] ?? 0 }}</p>
            <p class="text-xs text-zinc-400 mt-1">Total en BD</p>
        </div>
        <div class="p-5 text-center">
            <p class="text-3xl font-bold text-emerald-400">{{ $stats['valid'] ?? 0 }}</p>
            <p class="text-xs text-zinc-400 mt-1">Válidos / normalizables</p>
        </div>
        <div class="p-5 text-center">
            <p class="text-3xl font-bold text-red-400">{{ $stats['invalid'] ?? 0 }}</p>
            <p class="text-xs text-zinc-400 mt-1">Inválidos / incompletos</p>
        </div>
    </div>

    {{-- Preview Table --}}
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-zinc-800 text-zinc-400 text-xs uppercase tracking-wider">
                <tr>
                    <th class="px-4 py-3 text-left">#</th>
                    <th class="px-4 py-3 text-left">Número Original</th>
                    <th class="px-4 py-3 text-left">Número Normalizado</th>
                    <th class="px-4 py-3 text-left">Estado</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-800">
                @forelse ($previewRows as $i => $row)
                <tr class="hover:bg-zinc-800/50 transition-colors">
                    <td class="px-4 py-3 text-zinc-500">{{ $i + 1 }}</td>
                    <td class="px-4 py-3 font-mono text-zinc-300">{{ $row['original'] }}</td>
                    <td class="px-4 py-3 font-mono {{ $row['is_valid'] ? 'text-emerald-400' : 'text-red-400' }}">
                        {{ $row['normalized'] }}
                    </td>
                    <td class="px-4 py-3">
                        @if ($row['is_valid'])
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs bg-emerald-900/50 text-emerald-400 border border-emerald-700">
                            <flux:icon name="check-circle" class="w-3 h-3" /> Válido
                        </span>
                        @else
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs bg-red-900/50 text-red-400 border border-red-700">
                            <flux:icon name="x-circle" class="w-3 h-3" /> Inválido
                        </span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="4" class="px-4 py-8 text-center text-zinc-500">
                        No hay registros en <code>recorded_phones</code>.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
        @if (count($previewRows) === 50 && ($stats['total'] ?? 0) > 50)
        <p class="text-center text-xs text-zinc-500 py-3">
            Mostrando los primeros 50 de {{ $stats['total'] }} registros.
        </p>
        @endif
    </div>

    {{-- Actions --}}
    <div class="px-6 py-4 border-t border-zinc-700 flex items-center gap-3">
        @if ($step === 'preview')
        <flux:button wire:click="applyNormalization" wire:loading.attr="disabled" variant="primary" icon="cpu-chip">
            <span wire:loading.remove wire:target="applyNormalization">Aplicar Normalización</span>
            <span wire:loading wire:target="applyNormalization">Procesando...</span>
        </flux:button>
        <p class="text-xs text-zinc-500">Se actualizarán los {{ $stats['valid'] ?? 0 }} registros válidos en la base de datos.</p>
        @else
        <flux:button wire:click="analyzeMatches" wire:loading.attr="disabled" variant="primary" icon="magnifying-glass">
            <span wire:loading.remove wire:target="analyzeMatches">Analizar Coincidencias en Chats</span>
            <span wire:loading wire:target="analyzeMatches">Analizando...</span>
        </flux:button>
        <flux:button wire:click="resetToPreview" variant="ghost">
            Volver al inicio
        </flux:button>
        @endif
    </div>
</div>
@endif

{{-- ── STEP 3: Analyzed ── --}}
@if ($step === 'analyzed')
<div class="space-y-6">
    {{-- Stats --}}
    <div class="grid grid-cols-3 gap-4">
        <div class="bg-zinc-900 rounded-2xl border border-zinc-700 p-5 text-center">
            <p class="text-3xl font-bold text-indigo-400">{{ $stats['matched'] ?? 0 }}</p>
            <p class="text-xs text-zinc-400 mt-1">Chats coincidentes</p>
        </div>
        <div class="bg-zinc-900 rounded-2xl border border-zinc-700 p-5 text-center">
            <p class="text-3xl font-bold text-emerald-400">{{ $stats['already_tagged'] ?? 0 }}</p>
            <p class="text-xs text-zinc-400 mt-1">Ya etiquetados</p>
        </div>
        <div class="bg-zinc-900 rounded-2xl border border-zinc-700 p-5 text-center">
            <p class="text-3xl font-bold text-amber-400">{{ $stats['to_tag'] ?? 0 }}</p>
            <p class="text-xs text-zinc-400 mt-1">Pendientes de etiquetar</p>
        </div>
    </div>

    {{-- Matches table --}}
    @if (count($analysisRows) > 0)
    <div class="bg-zinc-900 rounded-2xl border border-zinc-700 overflow-hidden">
        <div class="px-5 py-3 border-b border-zinc-700">
            <p class="text-sm font-semibold text-zinc-300">Chats coincidentes</p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-zinc-800 text-zinc-400 text-xs uppercase tracking-wider">
                    <tr>
                        <th class="px-4 py-3 text-left">JID (WhatsApp)</th>
                        <th class="px-4 py-3 text-left">Nombre</th>
                        <th class="px-4 py-3 text-left">Tag</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-800">
                    @foreach ($analysisRows as $row)
                    <tr class="hover:bg-zinc-800/50">
                        <td class="px-4 py-3 font-mono text-zinc-300 text-xs">{{ $row['jid'] }}</td>
                        <td class="px-4 py-3 text-zinc-300">{{ $row['name'] }}</td>
                        <td class="px-4 py-3">
                            @if ($row['has_tag'])
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs bg-emerald-900/50 text-emerald-400 border border-emerald-700">
                                <flux:icon name="tag" class="w-3 h-3" /> Registrado
                            </span>
                            @else
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs bg-zinc-800 text-zinc-500 border border-zinc-700">
                                Sin tag
                            </span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @else
    <div class="bg-zinc-900 rounded-2xl border border-zinc-700 p-8 text-center text-zinc-500">
        <flux:icon name="phone-x-mark" class="w-10 h-10 mx-auto mb-3 text-zinc-600" />
        <p>Ningún número de <code>recorded_phones</code> coincide con los chats activos.</p>
    </div>
    @endif

    {{-- Actions --}}
    <div class="flex items-center gap-3">
        @if (($stats['to_tag'] ?? 0) > 0)
        <flux:button wire:click="applyTags" wire:loading.attr="disabled" variant="primary" icon="tag">
            <span wire:loading.remove wire:target="applyTags">Aplicar Etiqueta "Registrado" ({{ $stats['to_tag'] }})</span>
            <span wire:loading wire:target="applyTags">Aplicando...</span>
        </flux:button>
        @else
        <div class="flex items-center gap-2 text-emerald-400 text-sm">
            <flux:icon name="check-circle" class="w-5 h-5" />
            Todos los chats coincidentes ya tienen el tag.
        </div>
        @endif
        <flux:button wire:click="resetToPreview" variant="ghost" icon="arrow-left">
            Volver al inicio
        </flux:button>
    </div>
</div>
@endif

</div>