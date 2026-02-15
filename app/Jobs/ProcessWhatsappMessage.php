<?php

namespace App\Jobs;


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

    public function handle(GeminiService $geminiService, YCloudService $ycloudService)
    {
        Log::info("[JOB START] Chat: {$this->chat->remote_jid}, Trigger ID: {$this->triggeringMessageId}, Provider: {$this->chat->provider}");

        // 0. VERIFICACIÓN DE BUFFERING (ID Based Debounce)
        // Buscamos si existe ALGÚN mensaje de usuario con ID mayor al que disparó este job.
        // Si existe, significa que llegó un mensaje DESPUÉS, por lo tanto, hay un job más nuevo en cola.
        $newerMessageExists = Message::where('chat_id', $this->chat->id)
            ->where('role', 'user')
            ->where('id', '>', $this->triggeringMessageId)
            ->exists();

        if ($newerMessageExists) {
            Log::info("[JOB CANCELLED] Buffering: Newer message exists (ID > {$this->triggeringMessageId})");
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
        $ycloudService->sendPresence($this->chat->remote_jid, 'composing');
        $lastComposingTime = microtime(true);

        // Helper closure para refrescar composing si pasaron más de 4 segundos
        $refreshComposing = function () use ($ycloudService, &$lastComposingTime) {
            $elapsed = microtime(true) - $lastComposingTime;
            if ($elapsed >= 4.0) {
                $ycloudService->sendPresence($this->chat->remote_jid, 'composing');
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

            // Obtener el base64 del audio desde YCloud
            $base64Audio = $this->downloadYCloudMedia($triggerMessage->media_url);

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
                Log::error("No se pudo obtener el audio para mensaje ID: {$this->triggeringMessageId}");
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
            $base64 = $this->downloadYCloudMedia($audioMsg->media_url);
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

            // Obtener el base64 de la imagen desde YCloud
            $base64Image = $this->downloadYCloudMedia($triggerMessage->media_url);
            if ($base64Image) {
                Log::info("Imagen descargada de YCloud.");
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
                Log::error("No se pudo obtener la imagen para mensaje ID: {$this->triggeringMessageId}");
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
            $base64Img = $this->downloadYCloudMedia($imgMsg->media_url);
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
                    return !in_array($content, ['[Image Message]', '[Audio Message]', '[Unsupported Message]', '[Empty Text Message]', '[Mensaje no disponible - Error 131060]']);
                })
                ->implode(' ');

            // Si está vacío, usamos el texto del trigger
            if (empty(trim($fullUserMessage))) {
                $fallback = $this->userMessageText;

                // CASO ESPECIAL: Archivo no soportado y sin texto adicional
                // IMPORTANTE: Solo enviamos mensaje de cortesía si realmente es un archivo no soportado
                // NO lo enviamos si es un mensaje de texto normal (evita falsos positivos)
                if ($fallback === '[Unsupported Message]') {
                    // Verificar que el tipo de media sea realmente uno no soportado (video, document, sticker, etc.)
                    // y no un mensaje de texto mal clasificado
                    $triggerMediaType = $triggerMessage ? $triggerMessage->media_type : null;

                    // Solo enviamos mensaje de cortesía si hay un tipo de medio específico que no soportamos
                    if ($triggerMediaType && !in_array($triggerMediaType, ['text', 'image', 'audio', null, 'unsupported_content'])) {
                        Log::info("Mensaje no soportado detectado (tipo: {$triggerMediaType}) sin contexto adicional. Enviando respuesta de cortesía.");

                        $politeMessage = "👋 Hola. He recibido tu archivo, pero por el momento mi sistema solo está optimizado para analizar **Texto, Audios e Imágenes**.\n\n¿Podrías por favor escribirme tu consulta o enviármela en una nota de voz? Estaré encantado de ayudarte. 😊";

                        $ycloudService->sendMessage($this->chat->remote_jid, $politeMessage);

                        Message::create([
                            'chat_id' => $this->chat->id,
                            'role' => 'assistant',
                            'content' => $politeMessage,
                        ]);

                        return; // Detener Job
                    } else {
                        Log::warning("Mensaje marcado como [Unsupported Message] pero sin media_type válido. Posible falso positivo. Procesando con IA.");
                        // Continuamos el procesamiento normal con IA
                        $fullUserMessage = ''; // Dejamos vacío para que la IA procese el historial
                    }
                }

                if (!in_array($fallback, ['[Image Message]', '[Audio Message]', '[Unsupported Message]', '[Empty Text Message]', '[Mensaje no disponible - Error 131060]'])) {
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
                        $ycloudService->sendMessage($this->chat->remote_jid, $finalContent);

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
            $handoffInstruction = "\nIMPORTANTE: Si necesitas escalar a un humano (porque el usuario lo pide, hay frustración grave, o aplica alguna regla de escalamiento como registro de negocios), DEBES incluir la etiqueta [TRANSFER_TO_HUMAN] al FINAL de tu respuesta. Puedes escribir un mensaje antes de la etiqueta. La etiqueta DEBE aparecer textualmente en tu respuesta para que el sistema funcione. Ejemplo: 'Te voy a comunicar con un asesor. [TRANSFER_TO_HUMAN]'";
            $systemInstruction .= $handoffInstruction;

            // 2. Consultar a Gemini (con o sin imagen)
            if ($imageBase64ForVision) {
                // === CONSULTA CON VISIÓN (IMAGEN) ===
                Log::info("Consultando Gemini con visión para imagen en mensaje ID: {$this->triggeringMessageId}");

                // Construir contexto de historial para incluir en el prompt de visión
                $historyContext = "";
                foreach ($history as $msg) {
                    // Excluir el mensaje actual de imagen del historial y mensajes de error internos
                    if ($msg['content'] !== '[Image Message]' && $msg['content'] !== '[Mensaje no disponible - Error 131060]') {
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
                    // Filtrar mensajes internos que no aportan valor a la conversación
                    if ($msg['content'] === '[Mensaje no disponible - Error 131060]') {
                        continue;
                    }
                    $role = $msg['role'] === 'user' ? 'Usuario' : 'Asistente';
                    $fullPrompt .= "{$role}: {$msg['content']}\n";
                }
                $fullPrompt .= "\nAsistente:";

                $aiResponse = $geminiService->askGemini($fullPrompt);
            }

            // Refresh composing después de recibir respuesta de IA
            $refreshComposing();

            // [MODIFICADO] Verificar Handoff - Detección dual (tag + palabras clave)
            Log::info("AI Response para handoff check: " . substr($aiResponse, 0, 200));

            $handoffDetected = false;

            // Método 1: Tag literal [TRANSFER_TO_HUMAN]
            if (str_contains($aiResponse, '[TRANSFER_TO_HUMAN]')) {
                $handoffDetected = true;
                Log::info("Handoff detectado por TAG [TRANSFER_TO_HUMAN]");
            }

            // Método 2: Fallback por palabras clave (si la IA no incluyó el tag)
            if (!$handoffDetected) {
                $aiResponseLower = mb_strtolower($aiResponse);
                $handoffKeywords = [
                    'comunicar con un asesor',
                    'comunicarte con un asesor',
                    'conectar con un asesor',
                    'asesor especializado',
                    'asesor humano',
                    'hablar con un asesor',
                    'poner en contacto con un asesor',
                    'comunicar con uno de nuestros asesores',
                    'transferir con un asesor',
                    'derivar a un asesor',
                ];
                foreach ($handoffKeywords as $keyword) {
                    if (str_contains($aiResponseLower, $keyword)) {
                        $handoffDetected = true;
                        Log::info("Handoff detectado por KEYWORD: '{$keyword}'");
                        break;
                    }
                }
            }

            if ($handoffDetected) {
                Log::info("Iniciando Handoff para chat: " . $this->chat->remote_jid);

                // a. Desactivar el bot
                $this->chat->update(['is_active' => false]);

                // b. Mensaje de Despedida/Transferencia - Usar la respuesta de la IA sin la etiqueta
                $handoffMessage = trim(str_replace('[TRANSFER_TO_HUMAN]', '', $aiResponse));
                if (empty($handoffMessage)) {
                    $handoffMessage = "Entendido. Voy a comunicarte con un asesor para que te ayude. 😊";
                }

                // c. Enviar a WP
                $ycloudService->sendMessage($this->chat->remote_jid, $handoffMessage);

                // d. Guardar en DB como asistente
                Message::create([
                    'chat_id' => $this->chat->id,
                    'role' => 'assistant',
                    'content' => $handoffMessage,
                ]);

                // e. Notificar al Administrador (por YCloud)
                $adminNumber = config('services.admin_whatsapp_number');
                if ($adminNumber) {
                    $adminMsg = "🔔 *ATENCIÓN - SOLICITUD DE ASESOR*\n\n" .
                        "El cliente *{$this->chat->name}* ({$this->chat->remote_jid}) necesita hablar con un asesor.\n" .
                        "Último mensaje del usuario: \"{$this->userMessageText}\"\n" .
                        "Link al Chat: " . route('chat.detail', $this->chat->id);

                    $ycloudService->sendMessage($adminNumber, $adminMsg);
                    Log::info("Notificación de Handoff enviada al administrador vía YCloud: {$adminNumber}");
                } else {
                    Log::warning("ADMIN_WHATSAPP_NUMBER no está configurado en .env");
                }

                // f. Salir
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
            Log::info("[ATTEMPTING SEND] to {$this->chat->remote_jid}, Message length: " . strlen($aiResponse));
            $ycloudService->sendPresence($this->chat->remote_jid, 'composing');
            $sent = $ycloudService->sendMessage($this->chat->remote_jid, $aiResponse);
            Log::info("[SEND RESULT] " . ($sent ? 'SUCCESS' : 'FAILED') . " for {$this->chat->remote_jid}");

            if ($sent) {
                Log::info("[JOB SUCCESS] Response sent to {$this->chat->remote_jid}");
            } else {
                Log::error("[JOB FAILED] Could not send message to {$this->chat->remote_jid}");
            }
        } catch (\Exception $e) {
            Log::error("Error crítico en ProcessWhatsappMessage: " . $e->getMessage());
            // Opcional: $this->release(10); // Reintentar en 10 segundos si falla
        }
    }

    /**
     * Descarga un medio (audio/imagen) desde YCloud y retorna su contenido en base64.
     * Soporta tanto URLs directas como IDs de media de YCloud.
     *
     * @param string|null $urlOrId URL directa o ID del media en YCloud
     * @return string|null Base64 del contenido, o null si falla
     */
    protected function downloadYCloudMedia(?string $urlOrId): ?string
    {
        if (empty($urlOrId)) {
            return null;
        }

        try {
            // Si es una URL directa, descargamos directamente
            if (filter_var($urlOrId, FILTER_VALIDATE_URL)) {
                $response = Http::timeout(30)->get($urlOrId);
                if ($response->successful()) {
                    Log::info("Media descargado de YCloud (URL directa).");
                    return base64_encode($response->body());
                }
                Log::error("Fallo al descargar media YCloud: " . $response->status());
                return null;
            }

            // Si es un ID, consultamos la API de YCloud para obtener la URL
            $apiKey = config('services.ycloud.api_key');
            $response = Http::timeout(30)
                ->withHeaders(['X-API-Key' => $apiKey])
                ->get("https://api.ycloud.com/v2/whatsapp/media/{$urlOrId}");

            if ($response->successful()) {
                $downloadUrl = $response->json('url');
                if ($downloadUrl) {
                    $fileContent = Http::timeout(30)->get($downloadUrl)->body();
                    return base64_encode($fileContent);
                }
            }

            Log::error("Fallo al obtener media por ID YCloud: {$urlOrId}");
            return null;
        } catch (\Exception $e) {
            Log::error("Error descargando media YCloud: " . $e->getMessage());
            return null;
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

            // Notificar al administrador (por YCloud)
            $adminNumber = config('services.admin_whatsapp_number');
            if ($adminNumber) {
                $ycloudService = app(YCloudService::class);
                $adminMsg = "🚨 *ERROR EN JOB DE WHATSAPP*\n\n" .
                    "Chat: *{$this->chat->name}* ({$this->chat->remote_jid})\n" .
                    "Error: {$exception->getMessage()}\n" .
                    "Link: " . route('chat.detail', $this->chat->id);

                $ycloudService->sendMessage($adminNumber, $adminMsg);
            }
        } catch (\Exception $e) {
            Log::error("Error en método failed(): " . $e->getMessage());
        }
    }
}
