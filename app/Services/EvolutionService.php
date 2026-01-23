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
            ])->post($url, [
                "number" => $remoteJid, // El ID de usuario (ej: 57300...@s.whatsapp.net)
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
            ])->post($url, [
                "number" => $remoteJid,
                "presence" => $status,
                "delay" => 1200 // Milisegundos que dura el estado (opcional)
            ]);

            return $response->successful();

        } catch (\Exception $e) {
            Log::error('Excepción Presence Evolution: ' . $e->getMessage());
            return false;
        }
    }
}