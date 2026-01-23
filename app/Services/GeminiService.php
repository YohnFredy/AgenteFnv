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
        $apiKey = env('GEMINI_API_KEY');
        // Usamos la variable de entorno, si no existe usa 'gemini-2.5-flash-lite' por defecto
        $model = env('GEMINI_MODEL', 'gemini-2.5-flash-lite');

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
            return "Error interno del servidor de IA.";
        }
    }
}
