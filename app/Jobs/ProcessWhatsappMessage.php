<?php

namespace App\Jobs;


use App\Services\EvolutionService;
use App\Services\GeminiService;
use App\Models\Chat;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Models\Message;
use Illuminate\Bus\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;


class ProcessWhatsappMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $chat;
    protected $userMessageText;
    protected $triggeringMessageId;

    /**
     * Crea una nueva instancia del trabajo.
     * Recibimos el Chat, el texto y el ID del mensaje que disparó este job.
     */
    public function __construct(Chat $chat, string $userMessageText, int $triggeringMessageId)
    {
        $this->chat = $chat;
        $this->userMessageText = $userMessageText;
        $this->triggeringMessageId = $triggeringMessageId;
    }

    public function handle(GeminiService $geminiService, EvolutionService $evolutionService)
    {
        Log::info("Job iniciado para el chat: " . $this->chat->remote_jid . " (Trigger ID: {$this->triggeringMessageId})");

        // 0. VERIFICACIÓN DE BUFFERING (ID Based Debounce)
        // Buscamos si existe ALGÚN mensaje de usuario con ID mayor al que disparó este job.
        // Si existe, significa que llegó un mensaje DESPUÉS, por lo tanto, hay un job más nuevo en cola.
        $newerMessageExists = Message::where('chat_id', $this->chat->id)
            ->where('role', 'user')
            ->where('id', '>', $this->triggeringMessageId)
            ->exists();

        if ($newerMessageExists) {
            Log::info("Job cancelado por Buffering: Existe un mensaje más nuevo (ID > {$this->triggeringMessageId}).");
            return;
        }

        // 0b. REFRESCAR "ESCRIBIENDO..."
        // Como pasaron 10s, el estado original pudo haber expirado. Lo enviamos de nuevo.
        $evolutionService->sendPresence($this->chat->remote_jid, 'composing');

        try {
            // c. VERIFICACIÓN DE REGLAS DE RESPUESTA (Stage Filtering)
            // [MODIFICADO] Buscamos mensajes recientes del usuario que NO hayan sido respondidos aún por el asistente.
            // Esto permite que si el usuario manda "quiero" y luego "precio", la regla "quiero precio" se active.

            // 1. Buscamos el último mensaje del asistente
            $lastAssistantMessage = Message::where('chat_id', $this->chat->id)
                ->where('role', 'assistant')
                ->latest()
                ->first();

            $lastAssistantId = $lastAssistantMessage ? $lastAssistantMessage->id : 0;

            // 2. Traemos todos los mensajes del usuario POSTERIORES a esa respuesta (o todos si es nuevo)
            $recentUserMessages = Message::where('chat_id', $this->chat->id)
                ->where('role', 'user')
                ->where('id', '>', $lastAssistantId)
                ->orderBy('id', 'asc') // Orden cronológico relevante para concatenar
                ->get();

            // 3. Concatenamos el contenido
            // OJO: Si por alguna razón el job se ejecuta y el mensaje actual NO está en DB todavía (raro, pero posible en race conditions extremas), 
            // aseguramos usar $this->userMessageText como fallback o adición. 
            // PERO la lógica actual asume que el mensaje trigger YA está en DB.

            $fullUserMessage = $recentUserMessages->pluck('content')->implode(' ');

            // Si está vacío (no debería), usamos el texto del trigger
            if (empty(trim($fullUserMessage))) {
                $fullUserMessage = $this->userMessageText;
            }

            Log::info("Evaluando reglas para mensaje combinado: '{$fullUserMessage}' (Trigger ID: {$this->triggeringMessageId})");


            $rules = \App\Models\BotRule::where('trigger_stage', $this->chat->stage)
                ->where('is_active', true)
                ->get();

            foreach ($rules as $rule) {
                // Normalizamos keywords y mensaje
                $keywords = array_map('trim', explode(',', strtolower($rule->keywords)));
                $messageLower = strtolower($fullUserMessage);

                $match = false;
                foreach ($keywords as $keyword) {
                    if (!empty($keyword) && str_contains($messageLower, $keyword)) {
                        $match = true;
                        break;
                    }
                }

                if ($match) {
                    Log::info("Regla activada: Stage {$rule->trigger_stage} -> {$rule->next_stage} (Keyword matched in combined message)");

                    // Preparar saludo dinámico
                    $hour = now()->hour;
                    $saludo = ($hour < 12) ? 'Buenos días' : (($hour < 18) ? 'Buenas tardes' : 'Buenas noches');

                    foreach ($rule->messages as $msg) {
                        $finalContent = str_replace('{saludo}', $saludo, $msg->content);

                        // Enviar a WhatsApp
                        $evolutionService->sendMessage($this->chat->remote_jid, $finalContent);

                        // Guardar en DB como asistente
                        Message::create([
                            'chat_id' => $this->chat->id,
                            'role' => 'assistant',
                            'content' => $finalContent,
                        ]);

                        // Delay simulado entre mensajes (si es > 0)
                        if ($msg->delay > 0) {
                            sleep($msg->delay);
                        }
                    }

                    // Actualizar Stage del Chat
                    $this->chat->update(['stage' => $rule->next_stage]);

                    // DETENER EL JOB AQUÍ (No consultar a Gemini)
                    return;
                }
            }

            // 1. Preparar el contexto (Memoria + Instrucción Global)
            $history = $this->chat->getHistoryForAi(50);

            // Obtener instrucción global
            $globalSetting = \App\Models\BotSetting::find('system_instruction');
            $baseInstruction = $globalSetting ? $globalSetting->value : 'Eres un asistente útil y amable.';

            // [MODIFICADO] Instrucción Handoff
            $handoffInstruction = "\nIMPORTANTE: Si el usuario solicita explícitamente hablar con una persona/humano, o expresa frustración grave y pide ayuda real, DEBES responder ÚNICAMENTE con la etiqueta: [TRANSFER_TO_HUMAN]. No contestes nada más si usas esa etiqueta.";
            $systemInstruction = $baseInstruction . $handoffInstruction;

            // Construimos un "prompt estructurado"
            // NOTA: $history ya incluye el mensaje actual (el trigger) porque está grabado en DB.
            // Asi que solo concatenamos la instrucción y el historial.

            $fullPrompt = "Instrucción del Sistema: " . $systemInstruction . "\n\n";
            $fullPrompt .= "Historial de conversación (incluye último mensaje del usuario):\n";
            foreach ($history as $msg) {
                $role = $msg['role'] === 'user' ? 'Usuario' : 'Asistente';
                $fullPrompt .= "{$role}: {$msg['content']}\n";
            }
            $fullPrompt .= "\nAsistente:";

            // 2. Consultar a Gemini
            $aiResponse = $geminiService->askGemini($fullPrompt);

            // [MODIFICADO] Verificar Handoff
            if (str_contains($aiResponse, '[TRANSFER_TO_HUMAN]')) {
                Log::info("Intención de Humano detectada. Iniciando Handoff para chat: " . $this->chat->remote_jid);

                // a. Desactivar el bot
                $this->chat->update(['is_active' => false]);

                // b. Mensaje de Despedida/Transferencia
                $handoffMessage = "Entendido. Voy a avisar a un asesor humano para que se ponga en contacto contigo en breve.";

                // c. Enviar a WP
                $evolutionService->sendMessage($this->chat->remote_jid, $handoffMessage);

                // d. Guardar en DB como asistente
                Message::create([
                    'chat_id' => $this->chat->id,
                    'role' => 'assistant',
                    'content' => $handoffMessage,
                ]);

                // [NUEVO] Notificar al Administrador
                $adminNumber = env('ADMIN_WHATSAPP_NUMBER');
                if ($adminNumber) {
                    $adminMsg = "🔔 *ATENCIÓN - SOLICITUD DE HUMANO*\n\n" .
                        "El cliente *{$this->chat->name}* ({$this->chat->remote_jid}) ha solicitado hablar con un asesor.\n" .
                        "Link al Chat: " . route('chat.detail', $this->chat->id);

                    $evolutionService->sendMessage($adminNumber . '@s.whatsapp.net', $adminMsg);
                    Log::info("Notificación de Handoff enviada al administrador: {$adminNumber}");
                }

                // e. Salir
                return;
            }

            // 3. Guardar la respuesta de la IA en la base de datos (Si no es handoff)
            Message::create([
                'chat_id' => $this->chat->id,
                'role' => 'assistant',
                'content' => $aiResponse,
                'whatsapp_id' => null
            ]);

            // 4. Enviar mensaje a WhatsApp
            $sent = $evolutionService->sendMessage($this->chat->remote_jid, $aiResponse);

            if ($sent) {
                Log::info("Job completado: Respuesta enviada a {$this->chat->remote_jid}");
            } else {
                Log::error("Job falló: No se pudo enviar mensaje a Evolution API. Chat: {$this->chat->remote_jid}");
            }
        } catch (\Exception $e) {
            Log::error("Error crítico en ProcessWhatsappMessage: " . $e->getMessage());
            // Opcional: $this->release(10); // Reintentar en 10 segundos si falla
        }
    }
}
