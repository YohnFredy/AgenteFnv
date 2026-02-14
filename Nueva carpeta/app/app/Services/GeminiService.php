<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiService
{
    /**
     * Envía un prompt a Gemini y obtiene la respuesta.
     *
     * @param string $prompt
     * @param array $history
     * @return string
     */
    public function askGemini(string $prompt, array $history = []): string
    {
        $apiKey = config('services.gemini.api_key');
        $model = config('services.gemini.model', 'gemini-2.0-flash');

        // URL dinámica basada en el modelo
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";
        // Estructurar el cuerpo para Gemini
        // Nota: Para un chatbot real, aquí deberías formatear $history para darle contexto
        $payload = [
            "contents" => [
                [
                    "parts" => [
                        ["text" => $prompt]
                    ]
                ]
            ],
            // Opcional: Configuración de seguridad o temperatura
            "generationConfig" => [
                "temperature" => 0.7
            ]
        ];

        try {
            /** @var \Illuminate\Http\Client\Response $response */
            $response = Http::withHeaders(['Content-Type' => 'application/json'])
                ->timeout(60)
                ->retry(2, 1000)
                ->post($url, $payload);

            if ($response->successful()) {
                $data = $response->json();
                // Extraer el texto de la respuesta de Gemini
                return $data['candidates'][0]['content']['parts'][0]['text'] ?? 'Lo siento, no pude procesar tu solicitud.';
            }

            Log::error('Error Gemini API: ' . $response->body());
            return "Tuvimos un inconveniente con el asistente virtual al intentar responder tu mensaje.
¿Podrías enviarlo nuevamente, por favor? En cuanto lo recibamos, el asistente te responderá de inmediato.";
        } catch (\Exception $e) {
            Log::error('Excepción Gemini: ' . $e->getMessage());

            if (str_contains($e->getMessage(), '429')) {
                return "He excedido mi cuota de mensajes por el momento. Por favor espera unos minutos.";
            }

            if (str_contains($e->getMessage(), '503')) {
                return "El servidor de inteligencia artificial está un poco saturado en este momento. Por favor intenta de nuevo en unos segundos.";
            }

            return "Error interno del servidor de IA.";
        }
    }
    /**
     * Transcribe un archivo de audio usando Gemini.
     *
     * @param string $base64Audio Audio en formato base64 (ya desencriptado por Evolution)
     * @param string $mimeType Tipo MIME del audio (default: audio/ogg)
     * @return string Texto transcrito
     */
    public function transcribeAudio(string $base64Audio, string $mimeType = 'audio/ogg'): string
    {
        $apiKey = config('services.gemini.api_key');
        $model = config('services.gemini.model', 'gemini-2.0-flash');

        try {
            $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

            $payload = [
                "contents" => [
                    [
                        "parts" => [
                            [
                                "inline_data" => [
                                    "mime_type" => $mimeType,
                                    "data" => $base64Audio
                                ]
                            ],
                            ["text" => "Transcribe este audio de voz. Responde ÚNICAMENTE con el texto transcrito, sin agregar ninguna introducción, explicación o comentario adicional. Si no se entiende nada, responde solo: (Audio ininteligible)"]
                        ]
                    ]
                ]
            ];

            $response = Http::withHeaders(['Content-Type' => 'application/json'])
                ->timeout(90)
                ->retry(2, 1000)
                ->post($url, $payload);

            if ($response->successful()) {
                $data = $response->json();
                return $data['candidates'][0]['content']['parts'][0]['text'] ?? '(No se pudo transcribir)';
            }

            Log::error('Error Gemini Audio: ' . $response->body());
            return "(Error en transcripción)";
        } catch (\Exception $e) {
            Log::error('Excepción Gemini Audio: ' . $e->getMessage());
            return "(Error técnico al procesar audio)";
        }
    }

    /**
     * Analiza una imagen junto con un prompt de texto usando Gemini Vision.
     * 
     * @param string $base64Image Imagen en formato base64
     * @param string $textPrompt Pregunta o contexto del usuario sobre la imagen
     * @param string $systemInstruction Instrucción del sistema para el bot
     * @param string $mimeType Tipo MIME de la imagen (default: image/jpeg)
     * @return string Respuesta de la IA sobre la imagen
     */
    public function analyzeImageWithText(
        string $base64Image,
        string $textPrompt,
        string $systemInstruction = '',
        string $mimeType = 'image/jpeg'
    ): string {
        $apiKey = config('services.gemini.api_key');
        $model = config('services.gemini.model', 'gemini-2.0-flash');
        Log::info("GeminiService: Usando modelo: {$model}");

        try {
            $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

            // Construir el prompt completo
            $fullPrompt = "";
            if (!empty($systemInstruction)) {
                $fullPrompt .= "Instrucciones del sistema: {$systemInstruction}\n\n";
            }
            if (!empty($textPrompt) && $textPrompt !== '[Image Message]') {
                $fullPrompt .= "Pregunta/mensaje del usuario: {$textPrompt}";
            } else {
                $fullPrompt .= "El usuario ha enviado una imagen. Describe brevemente qué ves y pregunta en qué puedes ayudar con respecto a la imagen.";
            }

            $payload = [
                "contents" => [
                    [
                        "parts" => [
                            [
                                "inline_data" => [
                                    "mime_type" => $mimeType,
                                    "data" => $base64Image
                                ]
                            ],
                            ["text" => $fullPrompt]
                        ]
                    ]
                ],
                "generationConfig" => [
                    "temperature" => 0.7
                ]
            ];

            /** @var \Illuminate\Http\Client\Response $response */
            $response = Http::withHeaders(['Content-Type' => 'application/json'])
                ->timeout(90)
                ->retry(2, 1000)
                ->post($url, $payload);

            if ($response->successful()) {
                $data = $response->json();
                return $data['candidates'][0]['content']['parts'][0]['text'] ?? 'No pude analizar la imagen.';
            }

            // Verificar si es error de cuota
            $body = $response->body();
            Log::error('Error Gemini Vision: ' . $body);

            return "Hubo un error al analizar la imagen. Por favor, intenta nuevamente.";
        } catch (\Exception $e) {
            Log::error('Excepción Gemini Vision: ' . $e->getMessage());
            return "(Error técnico al procesar imagen)";
        }
    }
}
