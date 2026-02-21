<?php

namespace App\Observers;

use App\Models\Message;

class MessageObserver
{
    /**
     * Handle the Message "created" event.
     */
    public function created(Message $message): void
    {
        // Solo procesamos mensajes enviados por el asistente (bot o IA)
        if ($message->role !== 'assistant') {
            return;
        }

        $rules = \App\Models\AutomationRule::where('is_active', true)->get();

        foreach ($rules as $rule) {
            $match = match ($rule->match_type) {
                'exact' => $message->content === $rule->trigger_content,
                'contains' => str_contains(strtolower($message->content), strtolower($rule->trigger_content)),
                default => false,
            };

            if ($match) {
                $chat = $message->chat;

                // 1. Eliminar etiqueta (si aplica)
                if ($rule->remove_tag_id) {
                    $chat->tags()->detach($rule->remove_tag_id);
                }

                // 2. Asignar Etiqueta
                if ($rule->tag_id) {
                    $chat->tags()->syncWithoutDetaching([$rule->tag_id]);
                }

                // 3. Programar Seguimiento
                if ($rule->campaign_id) {
                    // Buscar la última interacción del usuario
                    $lastUserMessage = $chat->messages()->where('role', 'user')->latest()->first();
                    $lastInteraction = $lastUserMessage ? $lastUserMessage->created_at : now();

                    // --- CÁLCULO DE HORARIO ---
                    $scheduledAt = now(); // Default por si falla

                    // Obtener configuración global de horario laboral
                    $startHour = \App\Models\BotSetting::find('office_hours_start')?->value ?? '07:00';
                    $endHour = \App\Models\BotSetting::find('office_hours_end')?->value ?? '19:00';
                    $delayHours = $rule->followup_delay_hours ?? 0;

                    // Si hay delay configurado, usamos la calculadora
                    if ($delayHours > 0) {
                        try {
                            $calculator = new \App\Services\OfficeHoursCalculator();
                            // Usamos lastInteraction como base, no 'now()', para que sea justo.
                            // O 'now()' si queremos contar desde que se procesa.
                            // El requerimiento dice: "Si el usuario escribe a las 5pm... 10h después".
                            // Usaremos 'now()' porque es cuando se dispara la regla.
                            $scheduledAt = $calculator->calculateScheduledTime(now(), $delayHours, $startHour, $endHour);
                        } catch (\Exception $e) {
                            \Illuminate\Support\Facades\Log::error("Error calculando office hours: " . $e->getMessage());
                            $scheduledAt = now()->addHours($delayHours); // Fallback simple
                        }
                    } else {
                        // Sin delay o delay 0 -> inmediato (o default 12h si no se especifica nada? No, 0 es inmediato)
                        // El código anterior ponía +12h hardcoded. Ahora respetamos la regla.
                        // Si es 0, es inmediato.
                    }

                    // Buscar seguimiento activo existente (scheduled o pending)
                    $followup = \App\Models\ContactFollowup::where('chat_id', $chat->id)
                        ->whereIn('status', ['scheduled', 'pending'])
                        ->first();

                    if ($followup) {
                        $followup->update([
                            'campaign_id' => $rule->campaign_id,
                            'status' => 'scheduled',
                            'scheduled_at' => $scheduledAt,
                            'last_interaction_at' => $lastInteraction,
                        ]);
                    } else {
                        \App\Models\ContactFollowup::create([
                            'chat_id' => $chat->id,
                            'campaign_id' => $rule->campaign_id,
                            'status' => 'scheduled',
                            'scheduled_at' => $scheduledAt,
                            'last_interaction_at' => $lastInteraction,
                        ]);
                    }
                }
            }
        }
    }

    /**
     * Handle the Message "updated" event.
     */
    public function updated(Message $message): void
    {
        //
    }

    /**
     * Handle the Message "deleted" event.
     */
    public function deleted(Message $message): void
    {
        //
    }

    /**
     * Handle the Message "restored" event.
     */
    public function restored(Message $message): void
    {
        //
    }

    /**
     * Handle the Message "force deleted" event.
     */
    public function forceDeleted(Message $message): void
    {
        //
    }
}
