<?php

namespace Tests\Feature;

use App\Models\Message;
use App\Models\Chat;
use App\Services\GeminiService;
use App\Services\YCloudService;
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

        // Create active chat
        Chat::create([
            'remote_jid' => '573145207814@s.whatsapp.net',
            'is_active' => true,
            'name' => 'Test User',
            'provider' => 'ycloud',
        ]);

        // Simulate YCloud webhook payload for audio
        $response = $this->postJson('/api/ycloud/webhook', [
            'type' => 'whatsapp.inbound_message.received',
            'whatsappInboundMessage' => [
                'id' => 'ABC123AUDIO',
                'from' => '573145207814',
                'to' => '+573185995909',
                'type' => 'audio',
                'audio' => [
                    'link' => 'http://example.com/audio.ogg'
                ],
                'customerProfile' => ['name' => 'Test User'],
            ]
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('messages', [
            'whatsapp_id' => 'ABC123AUDIO',
            'media_type' => 'audio',
            'content' => '[Audio Message]'
        ]);

        Queue::assertPushed(ProcessWhatsappMessage::class);
    }
}
