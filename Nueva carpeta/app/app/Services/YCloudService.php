<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class YCloudService
{
    /**
     * Envía un mensaje de texto a través de YCloud.
     *
     * @param string $remoteJid
     * @param string $text
     * @return bool
     */
    public function sendMessage(string $remoteJid, string $text): bool
    {
        $apiKey = config('services.ycloud.api_key');
        $fromNumber = config('services.ycloud.from_number');

        // YCloud expects the 'to' number in E.164 format (e.g. +1234567890)
        // Our system stores it as 1234567890@s.whatsapp.net
        $to = str_replace('@s.whatsapp.net', '', $remoteJid);
        if (!str_starts_with($to, '+')) {
            $to = '+' . $to;
        }

        $url = 'https://api.ycloud.com/v2/whatsapp/messages';

        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'X-API-Key' => $apiKey,
                    'Content-Type' => 'application/json',
                ])
                ->post($url, [
                    'from' => $fromNumber,
                    'to' => $to,
                    'type' => 'text',
                    'text' => [
                        'body' => $text
                    ]
                ]);

            if ($response->failed()) {
                Log::error('Error enviando a YCloud: ' . $response->body());
                return false;
            }

            return true;
        } catch (\Exception $e) {
            Log::error('Excepción YCloud: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Envía estado de presencia (escribiendo...)
     */
    public function sendPresence(string $remoteJid, string $status): bool
    {
        // $status options: 'composing' (typing), 'paused' (stop typing)

        $apiKey = env('YCLOUD_API_KEY');
        $url = 'https://api.ycloud.com/v2/whatsapp/messages';

        // Format JID
        $to = str_replace('@s.whatsapp.net', '', $remoteJid);
        if (!str_starts_with($to, '+')) {
            $to = '+' . $to;
        }

        // Map internal status to YCloud/Meta status
        // 'composing' -> 'typing' (Standard Cloud API)
        // 'available' -> ignore or handled differently?
        // Let's assume standard "messaging_product": "whatsapp" style might be needed or just simple fields.
        // YCloud v2 simple format:
        // { "to": "+123", "type": "chat_state", "chat_state": "typing" } (Hypothesis based on wrapper behavior)

        // Let's try the standard Cloud API payload structure which YCloud usually mimics or accepts.
        // But since YCloud v2 has its own 'simple' structure for text, maybe it has for chat_state too.
        // Common pattern for others:

        /*
          POST /v2/whatsapp/messages
          {
            "to": "+123456",
            "type": "chat_state", 
            "chat_state": "typing"
          }
        */

        // Only handle composing for now
        if ($status !== 'composing') {
            return false; // usage mostly for composing
        }

        // YCloud API (v2/messages) rejects 'chat_state' with "Invalid parameter: type".
        // Currently, YCloud does not support sending typing indicators via the standard messages endpoint.
        // We log it for debugging/future implementation if the API updates.
        Log::debug("YCloud Presence Simulation: {$remoteJid} -> {$status}");

        return true;

        /* 
        // DISABLED UNTIL SUPPORTED
        try {
            // ... (Previous API call code)
        } catch ...
        */
    }

    /**
     * Envía plantilla (opcional, por si se necesita más adelante).
     */
    public function sendTemplate(string $remoteJid, string $templateName, string $language = 'en'): bool
    {
        // Implementation for templates if needed
        return false;
    }
}
