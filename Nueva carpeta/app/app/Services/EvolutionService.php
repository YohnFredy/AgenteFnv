<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;


class EvolutionService
{
    /**
     * Envía un mensaje de texto a través de la Evolution API.
     *
     * @param string $remoteJid
     * @param string $text
     * @return bool
     */
    public function sendMessage(string $remoteJid, string $text): bool
    {
        $baseUrl = env('EVOLUTION_API_URL');
        $apiKey = env('EVOLUTION_API_KEY');
        $instance = env('EVOLUTION_INSTANCE');

        // URL para enviar texto (Evolution v2)
        $url = "{$baseUrl}/message/sendText/{$instance}";

        try {
            /** @var \Illuminate\Http\Client\Response $response */
            $response = Http::withHeaders([
                'apikey' => $apiKey,
                'Content-Type' => 'application/json',
            ])
                ->timeout(30)
                ->retry(2, 1000)
                ->post($url, [
                    "number" => $remoteJid,
                    "text" => $text
                ]);

            if ($response->failed()) {
                Log::error('Error enviando a Evolution: ' . $response->body());
                return false;
            }

            return true;
        } catch (\Exception $e) {
            Log::error('Excepción Evolution: ' . $e->getMessage());
            return false;
        }
    }
    /**
     * Envía el estado "Escribiendo..." (composing) o "Grabando audio..." (recording).
     *
     * @param string $remoteJid
     * @param string $status 'composing' | 'recording' | 'available'
     * @return bool
     */
    public function sendPresence(string $remoteJid, string $status = 'composing'): bool
    {
        $baseUrl = env('EVOLUTION_API_URL');
        $apiKey = env('EVOLUTION_API_KEY');
        $instance = env('EVOLUTION_INSTANCE');

        $url = "{$baseUrl}/chat/sendPresence/{$instance}";

        try {
            /** @var \Illuminate\Http\Client\Response $response */
            $response = Http::withHeaders([
                'apikey' => $apiKey,
                'Content-Type' => 'application/json',
            ])
                ->timeout(10)
                ->post($url, [
                    "number" => $remoteJid,
                    "presence" => $status,
                    "delay" => 5000  // 5 segundos para mantener visible más tiempo
                ]);

            return $response->successful();
        } catch (\Exception $e) {
            Log::error('Excepción Presence Evolution: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtiene el base64 de un archivo multimedia (audio, imagen, etc.) ya desencriptado.
     * WhatsApp encripta los archivos, Evolution API los desencripta.
     *
     * @param string $messageId ID del mensaje de WhatsApp
     * @param string $remoteJid JID del chat
     * @return string|null Base64 del archivo o null si falla
     */
    public function getMediaBase64(string $messageId, string $remoteJid): ?string
    {
        $baseUrl = env('EVOLUTION_API_URL');
        $apiKey = env('EVOLUTION_API_KEY');
        $instance = env('EVOLUTION_INSTANCE');

        // Endpoint para obtener media de Evolution API v2
        $url = "{$baseUrl}/chat/getBase64FromMediaMessage/{$instance}";

        try {
            /** @var \Illuminate\Http\Client\Response $response */
            $response = Http::withHeaders([
                'apikey' => $apiKey,
                'Content-Type' => 'application/json',
            ])
                ->timeout(45)
                ->retry(2, 1000)
                ->post($url, [
                    "message" => [
                        "key" => [
                            "id" => $messageId,
                            "remoteJid" => $remoteJid
                        ]
                    ],
                    "convertToMp4" => false
                ]);

            if ($response->successful()) {
                $data = $response->json();
                // Evolution devuelve el base64 en la propiedad 'base64'
                return $data['base64'] ?? null;
            }

            Log::error('Error obteniendo media de Evolution: ' . $response->body());
            return null;
        } catch (\Exception $e) {
            Log::error('Excepción getMediaBase64: ' . $e->getMessage());
            return null;
        }
    }
}
