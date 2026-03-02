<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\RecordedPhone;
use App\Models\Chat;
use App\Models\Tag;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PhoneNormalizationManager extends Component
{
    // Step: 'preview' | 'normalized' | 'analyzed'
    public string $step = 'preview';

    // Stats for current step
    public array $stats = [];

    // Preview data (limited rows for display)
    public array $previewRows = [];

    // Analysis results
    public array $analysisRows = [];

    public string $successMessage = '';
    public string $errorMessage   = '';

    public function mount(): void
    {
        $this->loadPreview();
    }

    // ──────────────────────────────────────────
    //  Step 1 – Preview
    // ──────────────────────────────────────────
    public function loadPreview(): void
    {
        $all = RecordedPhone::all();

        $valid   = 0;
        $invalid = 0;
        $rows    = [];

        foreach ($all as $record) {
            $normalized = RecordedPhone::normalizeNumber($record->phone);
            if ($normalized) {
                $valid++;
            } else {
                $invalid++;
            }

            if (count($rows) < 50) {
                $rows[] = [
                    'id'         => $record->id,
                    'original'   => $record->phone,
                    'normalized' => $normalized ?? '— inválido —',
                    'is_valid'   => (bool) $normalized,
                    'already'    => $record->normalized_phone,
                ];
            }
        }

        $this->stats = [
            'total'   => $all->count(),
            'valid'   => $valid,
            'invalid' => $invalid,
        ];

        $this->previewRows = $rows;
    }

    // ──────────────────────────────────────────
    //  Step 2 – Apply Normalization
    // ──────────────────────────────────────────
    public function applyNormalization(): void
    {
        $this->successMessage = '';
        $this->errorMessage   = '';

        try {
            $updated = 0;

            RecordedPhone::all()->each(function ($record) use (&$updated) {
                $normalized = RecordedPhone::normalizeNumber($record->phone);
                if ($record->normalized_phone !== $normalized) {
                    $record->normalized_phone = $normalized;
                    $record->save();
                    $updated++;
                }
            });

            $this->step = 'normalized';
            $this->successMessage = "✅ Normalización completada. {$updated} registros actualizados.";
            $this->loadPreview(); // Refresh stats
        } catch (\Exception $e) {
            $this->errorMessage = '❌ Error: ' . $e->getMessage();
        }
    }

    // ──────────────────────────────────────────
    //  Step 3 – Analyze matches & apply tags
    // ──────────────────────────────────────────
    public function analyzeMatches(): void
    {
        $this->successMessage = '';
        $this->errorMessage   = '';

        // Get all valid normalized phones
        $normalizedPhones = RecordedPhone::whereNotNull('normalized_phone')
            ->pluck('normalized_phone')
            ->unique()
            ->toArray();

        if (empty($normalizedPhones)) {
            $this->errorMessage = '⚠️ No hay números normalizados. Aplique primero la normalización.';
            return;
        }

        // Build JID list: 57XXXXXXXXXX@s.whatsapp.net
        $jids = array_map(fn($p) => $p . '@s.whatsapp.net', $normalizedPhones);

        // Chats that match
        $matchingChats = Chat::whereIn('remote_jid', $jids)->get();

        // Tag status
        $tag = Tag::where('slug', 'registrado')->first();

        $rows = [];
        foreach ($matchingChats as $chat) {
            $hasTag = $tag ? $chat->tags()->where('tags.id', $tag->id)->exists() : false;
            $rows[] = [
                'jid'     => $chat->remote_jid,
                'name'    => $chat->name ?? '—',
                'has_tag' => $hasTag,
            ];
        }

        $this->analysisRows = $rows;
        $this->stats['matched']      = count($matchingChats);
        $this->stats['already_tagged'] = $tag
            ? $matchingChats->filter(fn($c) => $c->tags()->where('tags.id', $tag->id)->exists())->count()
            : 0;
        $this->stats['to_tag']       = $this->stats['matched'] - $this->stats['already_tagged'];
        $this->step = 'analyzed';
    }

    public function applyTags(): void
    {
        $this->successMessage = '';
        $this->errorMessage   = '';

        try {
            // Get or create the tag
            $tag = Tag::firstOrCreate(
                ['slug' => 'registrado'],
                [
                    'name'        => 'Registrado',
                    'color'       => '#10b981',  // green-500
                    'description' => 'Número encontrado en la base de datos de teléfonos registrados.',
                    'is_active'   => true,
                ]
            );

            $normalizedPhones = RecordedPhone::whereNotNull('normalized_phone')
                ->pluck('normalized_phone')
                ->unique()
                ->toArray();

            $jids = array_map(fn($p) => $p . '@s.whatsapp.net', $normalizedPhones);

            $chats  = Chat::whereIn('remote_jid', $jids)->get();
            $tagged = 0;

            foreach ($chats as $chat) {
                if (!$chat->tags()->where('tags.id', $tag->id)->exists()) {
                    $chat->tags()->attach($tag->id);
                    $tagged++;
                }
            }

            $this->successMessage = "🏷️ Etiqueta 'Registrado' aplicada a {$tagged} chats nuevos.";
            $this->analyzeMatches(); // Refresh analysis
        } catch (\Exception $e) {
            $this->errorMessage = '❌ Error: ' . $e->getMessage();
        }
    }

    public function resetToPreview(): void
    {
        $this->step           = 'preview';
        $this->successMessage = '';
        $this->errorMessage   = '';
        $this->analysisRows   = [];
        $this->loadPreview();
    }

    public function render()
    {
        return view('livewire.phone-normalization-manager');
    }
}
