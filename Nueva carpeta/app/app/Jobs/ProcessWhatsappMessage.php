<?php

namespace App\Jobs;


use App\Services\EvolutionService;
use App\Services\GeminiService;
use App\Services\YCloudService;
use App\Models\Chat;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Models\Message;
use Illuminate\Bus\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;


class ProcessWhatsappMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Tiempo máximo de ejecución del job (segundos).
     * 3 minutos es suficiente para llamadas a APIs de IA con imágenes/audio.
     */
    public $timeout = 180;

    /**
     * Número de intentos antes de marcar el job como fallido.
     */
    public $tries = 2;

    /**
     * Segundos de espera entre reintentos (backoff exponencial).
     */
    public $backoff = [30, 60];

    /**
     * Máximo de excepciones permitidas antes de fallar.
     */
    public $maxExceptions = 2;

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

    public function handle(GeminiService $geminiService, EvolutionService $evolutionService, YCloudService $ycloudService)
    {
        Log::info("Job iniciado para el chat: " . $this->chat->remote_jid . " (Trigger ID: {$this->triggeringMessageId}) [Provider: {$this->chat->provider}]");

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

        // ============================================================
        // SISTEMA DE COMPOSING HUMANIZADO - INICIO
        // ============================================================
        // Simula comportamiento humano: "lee el mensaje, piensa, y empieza a escribir"

        // 1. Pequeña pausa inicial (simula "lectura" del mensaje) - 1 a 2 segundos
        $initialPause = rand(1000, 2000) / 1000; // 1.0 a 2.0 segundos
        usleep((int)($initialPause * 1000000));

        // 2. Ahora envía el primer "escribiendo..." 
        // 2. Ahora envía el primer "escribiendo..." 
        if ($this->chat->provider === 'evolution') {
            $evolutionService->sendPresence($this->chat->remote_jid, 'composing');
        } else {
            $ycloudService->sendPresence($this->chat->remote_jid, 'composing');
        }
        $lastComposingTime = microtime(true);

        // Helper closure para refrescar composing si pasaron más de 4 segundos
        $refreshComposing = function () use ($evolutionService, $ycloudService, &$lastComposingTime) {
            $elapsed = microtime(true) - $lastComposingTime;
            if ($elapsed >= 4.0) {
                if ($this->chat->provider === 'evolution') {
                    $evolutionService->sendPresence($this->chat->remote_jid, 'composing');
                } else {
                    $ycloudService->sendPresence($this->chat->remote_jid, 'composing');
                }
                $lastComposingTime = microtime(true);
                Log::debug("Composing refreshed para {$this->chat->remote_jid}");
            }
        };

        // 0.a [MODIFICADO] PROCESAR AUDIO SI ES NECESARIO
        // Usamos Evolution API para obtener el audio desencriptado (WhatsApp envía URLs encriptadas)
        $triggerMessage = Message::find($this->triggeringMessageId);

        // Si el mensaje actual tiene audio y el contenido es el placeholder, transcribimos
        if ($triggerMessage && $triggerMessage->media_type === 'audio' && $triggerMessage->whatsapp_id && $triggerMessage->content === '[Audio Message]') {
            Log::info("Transcribiendo audio para mensaje ID: {$this->triggeringMessageId}");

            // Obtener el base64 del audio desencriptado desde Evolution API
            $base64Audio = null;
            if ($this->chat->provider === 'evolution') {
                $base64Audio = $evolutionService->getMediaBase64($triggerMessage->whatsapp_id, $this->chat->remote_jid);
            } else {
                // YCloud logic: Download from URL
                // The media_url column stores the YCloud Media ID or direct link.
                // In the controller we stored ID/Link in 'media_url'. 
                // YCloud payload for audio contains 'link'. Let's check accessing it.
                // We need the YCloud API Key to download if it requires auth, but usually direct AWS/Cloud links are signed.
                // However, YCloud docs say use GET /v2/whatsapp/media/{id} or just use the link provided.
                // Ideally we use the 'media_url' which should have been populated with the 'link' or can be fetched via ID.

                // For now, let's assume media_url has the ID, we need to fetch the download URL or if it's a link use it.
                // But in our controller update we stored ID or Link.
                // Let's rely on the fact that if it's YCloud, we fetch the content.

                $urlOrId = $triggerMessage->media_url;
                Log::info("YCloud Processing: media_url content: " . substr($urlOrId, 0, 50) . "...");

                if ($urlOrId) {
                    $mediaUrl = $urlOrId;

                    // If it is an ID (no http), generic fetch (omitted for now as payload usually gives link)
                    // But in the user request payload, we see "link" inside "audio" object.
                    // Let's assume media_url holds something accessbile.
                    // Actually, if we look at the Controller again, we stored ID or Link.
                    // If it is a full URL, try to download.

                    if (filter_var($mediaUrl, FILTER_VALIDATE_URL)) {
                        $response = Http::timeout(30)->get($mediaUrl);
                        if ($response->successful()) {
                            $base64Audio = base64_encode($response->body());
                            Log::info("Audio descargado de YCloud (URL directa).");
                        } else {
                            Log::error("Fallo al descargar audio YCloud: " . $response->status());
                        }
                    } else {
                        // It is an ID. We need to fetch the URL using YCloud API.
                        // GET https://api.ycloud.com/v2/whatsapp/media/{media_id}
                        // Headers: X-API-Key
                        $apiKey = config('services.ycloud.api_key');
                        $response = Http::timeout(30)->withHeaders(['X-API-Key' => $apiKey])->get("https://api.ycloud.com/v2/whatsapp/media/{$mediaUrl}");

                        if ($response->successful()) { // Contains download link or binary?
                            // YCloud API usually returns metadata with a 'url' field
                            $data = $response->json();
                            $downloadUrl = $data['url'] ?? null;
                            if ($downloadUrl) {
                                $fileContent = Http::get($downloadUrl)->body();
                                $base64Audio = base64_encode($fileContent);
                            }
                        }
                    }
                }
            }

            if ($base64Audio) {
                // Guardar el audio localmente para reproducción posterior
                $audioPath = $this->saveAudioFile($base64Audio, $triggerMessage->id);

                $transcription = $geminiService->transcribeAudio($base64Audio);
                Log::info("Transcripción completada: " . $transcription);

                // Refresh composing después de transcripción (operación larga)
                $refreshComposing();

                // Actualizamos el mensaje en DB con la transcripción y la ruta del audio
                $triggerMessage->update([
                    'content' => $transcription,
                    'media_path' => $audioPath
                ]);

                // Actualizamos también la variable local para que el resto del job use el texto
                $this->userMessageText = $transcription;
            } else {
                Log::error("No se pudo obtener el audio de Evolution para mensaje ID: {$this->triggeringMessageId}");
                $triggerMessage->update(['content' => '(Error al obtener audio)']);
                $this->userMessageText = '(Error al obtener audio)';
            }
        }

        // También transcribimos cualquier otro audio pendiente en este chat
        $pendingAudios = Message::where('chat_id', $this->chat->id)
            ->where('role', 'user')
            ->where('media_type', 'audio')
            ->where('content', '[Audio Message]')
            ->whereNotNull('whatsapp_id')
            ->get();

        foreach ($pendingAudios as $audioMsg) {
            Log::info("Transcribiendo audio pendiente ID: {$audioMsg->id}");
            $base64 = null;
            if ($this->chat->provider === 'evolution') {
                $base64 = $evolutionService->getMediaBase64($audioMsg->whatsapp_id, $this->chat->remote_jid);
            } else {
                // YCloud Audio Logic for Pending
                $urlOrId = $audioMsg->media_url;
                if ($urlOrId) {
                    $mediaUrl = $urlOrId;
                    if (filter_var($mediaUrl, FILTER_VALIDATE_URL)) {
                        $response = Http::timeout(30)->get($mediaUrl);
                        if ($response->successful()) {
                            $base64 = base64_encode($response->body());
                        }
                    } else {
                        $apiKey = config('services.ycloud.api_key');
                        $response = Http::timeout(30)->withHeaders(['X-API-Key' => $apiKey])->get("https://api.ycloud.com/v2/whatsapp/media/{$mediaUrl}");
                        if ($response->successful()) {
                            $data = $response->json();
                            $downloadUrl = $data['url'] ?? null;
                            if ($downloadUrl) {
                                $fileContent = Http::get($downloadUrl)->body();
                                $base64 = base64_encode($fileContent);
                            }
                        }
                    }
                }
            }
            if ($base64) {
                // Guardar el audio localmente
                $audioPath = $this->saveAudioFile($base64, $audioMsg->id);

                $text = $geminiService->transcribeAudio($base64);
                $audioMsg->update([
                    'content' => $text,
                    'media_path' => $audioPath
                ]);

                // Refresh composing después de cada transcripción
                $refreshComposing();
            } else {
                $audioMsg->update(['content' => '(Error al obtener audio)']);
            }
        }

        // 0.b PROCESAR IMAGEN SI ES NECESARIO
        // Variable para almacenar el base64 de la imagen si existe (se usará en la consulta a Gemini)
        $imageBase64ForVision = null;

        if ($triggerMessage && $triggerMessage->media_type === 'image' && $triggerMessage->whatsapp_id) {
            Log::info("Procesando imagen para mensaje ID: {$this->triggeringMessageId}");

            // Obtener el base64 de la imagen desde Evolution API
            $base64Image = null;
            if ($this->chat->provider === 'evolution') {
                $base64Image = $evolutionService->getMediaBase64($triggerMessage->whatsapp_id, $this->chat->remote_jid);
            } else {
                // YCloud Image Logic
                $urlOrId = $triggerMessage->media_url;
                Log::info("YCloud Processing Image: media_url content: " . substr($urlOrId, 0, 50) . "...");

                if ($urlOrId) {
                    $mediaUrl = $urlOrId;

                    if (filter_var($mediaUrl, FILTER_VALIDATE_URL)) {
                        $response = Http::timeout(30)->get($mediaUrl);
                        if ($response->successful()) {
                            $base64Image = base64_encode($response->body());
                            Log::info("Imagen descargada de YCloud (URL directa).");
                        } else {
                            Log::error("Fallo al descargar imagen YCloud: " . $response->status());
                        }
                    } else {
                        // Fallback ID fetch
                        $apiKey = config('services.ycloud.api_key');
                        $response = Http::timeout(30)->withHeaders(['X-API-Key' => $apiKey])->get("https://api.ycloud.com/v2/whatsapp/media/{$mediaUrl}");
                        if ($response->successful()) {
                            $data = $response->json();
                            $downloadUrl = $data['url'] ?? null;
                            if ($downloadUrl) {
                                $fileContent = Http::get($downloadUrl)->body();
                                $base64Image = base64_encode($fileContent);
                            }
                        }
                    }
                }
            }

            if ($base64Image) {
                // Guardar la imagen localmente para visualización posterior
                $imagePath = $this->saveImageFile($base64Image, $triggerMessage->id);

                // Actualizar el mensaje con la ruta de la imagen
                $triggerMessage->update(['media_path' => $imagePath]);

                // Guardar el base64 para usarlo en la consulta de visión a Gemini
                $imageBase64ForVision = $base64Image;

                // Refresh composing después de procesar imagen
                $refreshComposing();

                Log::info("Imagen guardada: {$imagePath}");
            } else {
                Log::error("No se pudo obtener la imagen de Evolution para mensaje ID: {$this->triggeringMessageId}");
            }
        }

        // 0.b.2 PROCESAR IMÁGENES PENDIENTES (por si el buffering canceló jobs anteriores con imágenes)
        $pendingImages = Message::where('chat_id', $this->chat->id)
            ->where('role', 'user')
            ->where('media_type', 'image')
            ->whereNull('media_path')
            ->whereNotNull('whatsapp_id')
            ->get();

        foreach ($pendingImages as $imgMsg) {
            Log::info("Procesando imagen pendiente ID: {$imgMsg->id}");
            $base64Img = null;
            if ($this->chat->provider === 'evolution') {
                $base64Img = $evolutionService->getMediaBase64($imgMsg->whatsapp_id, $this->chat->remote_jid);
            } else {
                // YCloud Image Logic for Pending
                $urlOrId = $imgMsg->media_url;
                if ($urlOrId) {
                    $mediaUrl = $urlOrId;
                    if (filter_var($mediaUrl, FILTER_VALIDATE_URL)) {
                        $response = Http::timeout(30)->get($mediaUrl);
                        if ($response->successful()) {
                            $base64Img = base64_encode($response->body());
                        }
                    } else {
                        $apiKey = config('services.ycloud.api_key');
                        $response = Http::timeout(30)->withHeaders(['X-API-Key' => $apiKey])->get("https://api.ycloud.com/v2/whatsapp/media/{$mediaUrl}");
                        if ($response->successful()) {
                            $data = $response->json();
                            $downloadUrl = $data['url'] ?? null;
                            if ($downloadUrl) {
                                $fileContent = Http::get($downloadUrl)->body();
                                $base64Img = base64_encode($fileContent);
                            }
                        }
                    }
                }
            }
            if ($base64Img) {
                $imgPath = $this->saveImageFile($base64Img, $imgMsg->id);
                $imgMsg->update(['media_path' => $imgPath]);

                // Si este mensaje pendiente tiene placeholder, guardamos su base64 para visión
                // (solo si no hay otra imagen ya procesada en el trigger)
                if (!$imageBase64ForVision && $imgMsg->content === '[Image Message]') {
                    $imageBase64ForVision = $base64Img;
                }
            }
        }

        // 0c. REFRESCAR "ESCRIBIENDO..." antes de continuar con IA
        $refreshComposing();

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

            // Filtrar placeholders internos del texto combinado
            $fullUserMessage = $recentUserMessages
                ->pluck('content')
                ->filter(function ($content) {
                    return !in_array($content, ['[Image Message]', '[Audio Message]', '[Unsupported Message]']);
                })
                ->implode(' ');

            // Si está vacío, usamos el texto del trigger
            if (empty(trim($fullUserMessage))) {
                $fallback = $this->userMessageText;

                // CASO ESPECIAL: Archivo no soportado y sin texto adicional
                if ($fallback === '[Unsupported Message]') {
                    Log::info("Mensaje no soportado detectado sin contexto adicional. Enviando respuesta de cortesía.");

                    $politeMessage = "👋 Hola. He recibido tu archivo, pero por el momento mi sistema solo está optimizado para analizar **Texto, Audios e Imágenes**.\n\n¿Podrías por favor escribirme tu consulta o enviármela en una nota de voz? Estaré encantado de ayudarte. 😊";

                    if ($this->chat->provider === 'evolution') {
                        $evolutionService->sendMessage($this->chat->remote_jid, $politeMessage);
                    } else {
                        $ycloudService->sendMessage($this->chat->remote_jid, $politeMessage);
                    }

                    Message::create([
                        'chat_id' => $this->chat->id,
                        'role' => 'assistant',
                        'content' => $politeMessage,
                    ]);

                    return; // Detener Job
                }

                if (!in_array($fallback, ['[Image Message]', '[Audio Message]'])) {
                    $fullUserMessage = $fallback;
                }
            }

            Log::info("Evaluando reglas para mensaje combinado: '{$fullUserMessage}' (Trigger ID: {$this->triggeringMessageId})");


            Log::info("Chat ID: {$this->chat->id}, Remote JID: {$this->chat->remote_jid}, Current Stage: {$this->chat->stage}");

            $rules = \App\Models\BotRule::where('trigger_stage', $this->chat->stage)
                ->where('is_active', true)
                ->get();

            Log::info("Found " . count($rules) . " rules for stage {$this->chat->stage}");

            foreach ($rules as $rule) {
                // Normalizamos keywords y mensaje
                $keywords = array_map('trim', explode(',', strtolower($rule->keywords)));
                $messageLower = strtolower($fullUserMessage);

                $match = false;
                foreach ($keywords as $keyword) {
                    if (!empty($keyword) && str_contains($messageLower, $keyword)) {
                        Log::info("Match found for keyword: '{$keyword}' in message: '{$messageLower}'");
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
                        // Reemplazar {saludo} y limpiar espacios/puntos dobles si el saludo está vacío
                        $finalContent = str_replace('{saludo}', $saludo, $msg->content);

                        // Si el saludo está vacío, eliminamos posibles inicios feos como ". " o "  "
                        if (empty($saludo)) {
                            $finalContent = ltrim($finalContent, "., ");
                            $finalContent = ucfirst($finalContent); // Asegurar que empiece en mayúscula
                        }

                        // Enviar a WhatsApp
                        if ($this->chat->provider === 'evolution') {
                            $evolutionService->sendMessage($this->chat->remote_jid, $finalContent);
                        } else {
                            $ycloudService->sendMessage($this->chat->remote_jid, $finalContent);
                        }

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

            // [MODIFICADO] Saludo dinámico según la hora (Solo una vez al día)
            $hasGreetedToday = Message::where('chat_id', $this->chat->id)
                ->where('role', 'assistant')
                ->whereDate('created_at', now()->toDateString())
                ->exists();

            $hour = now()->hour;
            $greetingText = ($hour < 12) ? 'Buenos días' : (($hour < 18) ? 'Buenas tardes' : 'Buenas noches');

            // Si ya saludó hoy, dejamos el saludo vacío para no ser repetitivo
            $saludo = $hasGreetedToday ? '' : $greetingText;

            // Obtener instrucción global
            $globalSetting = \App\Models\BotSetting::find('system_instruction');
            $baseInstruction = $globalSetting ? $globalSetting->value : 'Eres un asistente útil y amable.';

            // [MODIFICADO] Construir instrucción con saludo
            if (!$hasGreetedToday) {
                $systemInstruction = "Instrucción de Cortesía: Es la primera vez que hablas con el usuario hoy. Puedes saludarlo diciendo \"{$saludo}\" al inicio de tu mensaje SI el usuario te saludó o si es apropiado para iniciar la respuesta. Si el usuario hace una pregunta directa de qué trata el negocio, responde a la pregunta de forma natural sin ser excesivamente formal.\n" . $baseInstruction;
            } else {
                $systemInstruction = "Instrucción de Cortesía: Ya has saludado al usuario hoy. NO repitas saludos formales como 'Buenos días' o 'Buenas tardes'; ve directo al grano.\n" . $baseInstruction;
            }

            // [MODIFICADO] Instrucción Handoff
            $handoffInstruction = "\nIMPORTANTE: Si el usuario solicita explícitamente hablar con una persona/humano, o expresa frustración grave y pide ayuda real, DEBES responder ÚNICAMENTE con la etiqueta: [TRANSFER_TO_HUMAN]. No contestes nada más si usas esa etiqueta.";
            $systemInstruction .= $handoffInstruction;

            // 2. Consultar a Gemini (con o sin imagen)
            if ($imageBase64ForVision) {
                // === CONSULTA CON VISIÓN (IMAGEN) ===
                Log::info("Consultando Gemini con visión para imagen en mensaje ID: {$this->triggeringMessageId}");

                // Construir contexto de historial para incluir en el prompt de visión
                $historyContext = "";
                foreach ($history as $msg) {
                    // Excluir el mensaje actual de imagen del historial
                    if ($msg['content'] !== '[Image Message]') {
                        $role = $msg['role'] === 'user' ? 'Usuario' : 'Asistente';
                        $historyContext .= "{$role}: {$msg['content']}\n";
                    }
                }

                // El texto del usuario (caption de la imagen o pregunta)
                $userText = $this->userMessageText;
                if ($userText === '[Image Message]') {
                    $userText = '';
                }

                // Si hay historial, agregarlo al contexto
                $fullImgPrompt = "";
                if (!empty($historyContext)) {
                    $fullImgPrompt = "Contexto de conversación previa:\n{$historyContext}\n\n";
                }
                $fullImgPrompt .= $userText;

                $aiResponse = $geminiService->analyzeImageWithText(
                    $imageBase64ForVision,
                    $fullImgPrompt,
                    $systemInstruction
                );
            } else {
                // === CONSULTA NORMAL (SOLO TEXTO) ===
                // Construimos un "prompt estructurado"
                $fullPrompt = "Instrucción del Sistema: " . $systemInstruction . "\n\n";
                $fullPrompt .= "Historial de conversación (incluye último mensaje del usuario):\n";
                foreach ($history as $msg) {
                    $role = $msg['role'] === 'user' ? 'Usuario' : 'Asistente';
                    $fullPrompt .= "{$role}: {$msg['content']}\n";
                }
                $fullPrompt .= "\nAsistente:";

                $aiResponse = $geminiService->askGemini($fullPrompt);
            }

            // Refresh composing después de recibir respuesta de IA
            $refreshComposing();

            // [MODIFICADO] Verificar Handoff
            if (str_contains($aiResponse, '[TRANSFER_TO_HUMAN]')) {
                Log::info("Intención de Humano detectada. Iniciando Handoff para chat: " . $this->chat->remote_jid);

                // a. Desactivar el bot
                $this->chat->update(['is_active' => false]);

                // b. Mensaje de Despedida/Transferencia
                $handoffMessage = "Entendido. Voy a avisar a un asesor humano para que se ponga en contacto contigo en breve.";

                // c. Enviar a WP
                if ($this->chat->provider === 'evolution') {
                    $evolutionService->sendMessage($this->chat->remote_jid, $handoffMessage);
                } else {
                    $ycloudService->sendMessage($this->chat->remote_jid, $handoffMessage);
                }

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
            // Composing FINAL justo antes de enviar (el estado desaparecerá naturalmente al llegar el mensaje)
            if ($this->chat->provider === 'evolution') {
                $evolutionService->sendPresence($this->chat->remote_jid, 'composing');
                $sent = $evolutionService->sendMessage($this->chat->remote_jid, $aiResponse);
            } else {
                $ycloudService->sendPresence($this->chat->remote_jid, 'composing');
                $sent = $ycloudService->sendMessage($this->chat->remote_jid, $aiResponse);
            }

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

    /**
     * Guarda el audio base64 en el storage y devuelve la ruta relativa.
     *
     * @param string $base64Audio Audio en formato base64
     * @param int $messageId ID del mensaje para nombrar el archivo
     * @return string Ruta relativa del archivo guardado
     */
    protected function saveAudioFile(string $base64Audio, int $messageId): string
    {
        // Crear directorio si no existe
        $directory = storage_path('app/public/audios');
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        // Decodificar y guardar el archivo
        $audioData = base64_decode($base64Audio);
        $filename = "audio_{$messageId}_" . time() . ".ogg";
        $filepath = $directory . '/' . $filename;

        file_put_contents($filepath, $audioData);

        // Devolver ruta relativa para uso en la web
        return 'audios/' . $filename;
    }

    /**
     * Guarda la imagen base64 en el storage y devuelve la ruta relativa.
     *
     * @param string $base64Image Imagen en formato base64
     * @param int $messageId ID del mensaje para nombrar el archivo
     * @return string Ruta relativa del archivo guardado
     */
    protected function saveImageFile(string $base64Image, int $messageId): string
    {
        // Crear directorio si no existe
        $directory = storage_path('app/public/images');
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        // Decodificar y guardar el archivo
        $imageData = base64_decode($base64Image);
        $filename = "image_{$messageId}_" . time() . ".jpg";
        $filepath = $directory . '/' . $filename;

        file_put_contents($filepath, $imageData);

        // Devolver ruta relativa para uso en la web
        return 'images/' . $filename;
    }

    /**
     * Maneja el fallo definitivo del job después de agotar todos los reintentos.
     * Notifica al administrador y guarda un mensaje de error en el chat.
     *
     * @param \Throwable $exception
     * @return void
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("Job ProcessWhatsappMessage falló definitivamente para chat: {$this->chat->remote_jid}", [
            'chat_id' => $this->chat->id,
            'trigger_id' => $this->triggeringMessageId,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);

        try {
            // Guardar mensaje de error en el chat para el usuario
            Message::create([
                'chat_id' => $this->chat->id,
                'role' => 'assistant',
                'content' => '⚠️ Lo siento, tuve un problema técnico procesando tu mensaje. Un asesor revisará tu caso pronto.',
            ]);

            // Notificar al administrador
            $adminNumber = env('ADMIN_WHATSAPP_NUMBER');
            if ($adminNumber) {
                $evolutionService = app(EvolutionService::class);
                $adminMsg = "🚨 *ERROR EN JOB DE WHATSAPP*\n\n" .
                    "Chat: *{$this->chat->name}* ({$this->chat->remote_jid})\n" .
                    "Error: {$exception->getMessage()}\n" .
                    "Link: " . route('chat.detail', $this->chat->id);

                // Admin notification always via Evolution for now (assuming admin is on Evolution/local)
                $evolutionService->sendMessage($adminNumber . '@s.whatsapp.net', $adminMsg);
            }
        } catch (\Exception $e) {
            Log::error("Error en método failed(): " . $e->getMessage());
        }
    }
}
