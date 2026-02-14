<?php

namespace Tests\Feature;

use App\Models\Message;
use App\Models\Chat;
use App\Services\GeminiService;
use App\Services\EvolutionService;
use App\Jobs\ProcessWhatsappMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use Mockery;

class AudioMessageTest extends TestCase
{
    use RefreshDatabase;
    use \Illuminate\Foundation\Testing\WithoutMiddleware;

    public function test_webhook_buffers_audio_message()
    {
        Queue::fake();

        // Mock environment variable
        config(['app.env' => 'testing']);
        $apiKey = 'test-api-key';
        config(['env.EVOLUTION_API_KEY' => $apiKey]); // This might not work if env() is used directly.
        // Better: Mock env() call? No, hard in Laravel.
        // Instead, just pass what we expect specifically if we can control the server side, 
        // but here we are testing the middleware too.

        // Actually, if the middleware uses env(), we can use Config::set if config cache is not issue,
        // BUT env() calls inside code are NOT overridden by Config::set in recent Laravel versions if config is cached or if directly using env().
        // However, CheckEvolutionKey uses `env('EVOLUTION_API_KEY')`.

        // Let's rely on the fact that we can't easily mock env() dependent code without proper config.
        // We will TRY setting the env var via putenv for the test process.
        putenv("EVOLUTION_API_KEY=test-api-key");

        // Create active chat
        Chat::create([
            'remote_jid' => '573145207814@s.whatsapp.net',
            'is_active' => true,
            'name' => 'Test User'
        ]);

        $response = $this->withHeaders(['apikey' => 'test-api-key'])
            ->postJson('/api/evolution/webhook', [
                'event' => 'messages.upsert',
                'data' => [
                    'key' => [
                        'remoteJid' => '573145207814@s.whatsapp.net',
                        'fromMe' => false,
                        'id' => 'ABC123AUDIO'
                    ],
                    'pushName' => 'Test User',
                    'message' => [
                        'audioMessage' => [
                            'url' => 'http://example.com/audio.ogg'
                        ]
                    ]
                ]
            ]);

        $response->assertStatus(200);
        $response->assertJson(['status' => 'buffered']);

        $this->assertDatabaseHas('messages', [
            'whatsapp_id' => 'ABC123AUDIO',
            'media_type' => 'audio',
            'media_url' => 'http://example.com/audio.ogg',
            'content' => '[Audio Message]'
        ]);

        Queue::assertPushed(ProcessWhatsappMessage::class);
    }
}
