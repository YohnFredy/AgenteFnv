<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Chat;
use App\Models\Message;
use App\Jobs\ProcessWhatsappMessage;
use App\Services\GeminiService;
use App\Services\YCloudService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;

class HandoffTest extends TestCase
{
    use RefreshDatabase;

    public function test_ai_triggering_handoff_deactivates_chat()
    {
        // 1. Setup
        $chat = Chat::create([
            'remote_jid' => '123456789@s.whatsapp.net',
            'is_active' => true,
            'stage' => 'initial',
            'provider' => 'ycloud',
        ]);

        $message = Message::create([
            'chat_id' => $chat->id,
            'role' => 'user',
            'content' => 'Quiero hablar con humano',
        ]);

        // 2. Mock Gemini to return Handoff Tag
        $geminiMock = Mockery::mock(GeminiService::class);
        $geminiMock->shouldReceive('askGemini')
            ->once()
            ->andReturn('Te voy a comunicar con un asesor. [TRANSFER_TO_HUMAN]');

        // 3. Mock YCloud
        $ycloudMock = Mockery::mock(YCloudService::class);
        $ycloudMock->shouldReceive('sendPresence')->zeroOrMoreTimes();
        $ycloudMock->shouldReceive('sendMessage')
            ->atLeast()->once()
            ->andReturn(true);

        // 4. Set admin number config
        config(['services.admin_whatsapp_number' => '573001234567']);

        // 5. Run Job
        $job = new ProcessWhatsappMessage($chat, $message->content, $message->id);
        $job->handle($geminiMock, $ycloudMock);

        // 6. Assert Chat is now inactive
        $this->assertFalse($chat->fresh()->is_active);
    }

    public function test_inactive_chat_does_not_process_ai()
    {
        // 1. Setup Inactive Chat
        $chat = Chat::create([
            'remote_jid' => '987654321@s.whatsapp.net',
            'is_active' => false,
            'name' => 'Tester',
            'provider' => 'ycloud',
        ]);

        // 2. Simulate incoming message via YCloud webhook
        $payload = [
            'type' => 'whatsapp.inbound_message.received',
            'whatsappInboundMessage' => [
                'id' => 'MSG_ID_123',
                'from' => '987654321',
                'to' => '+573185995909',
                'type' => 'text',
                'text' => ['body' => 'Hola, sigues ahi?'],
                'customerProfile' => ['name' => 'Tester'],
            ]
        ];

        // 3. Expect Job NOT to be dispatched
        \Illuminate\Support\Facades\Bus::fake();

        // 4. Call YCloud Webhook
        $response = $this->postJson('/api/ycloud/webhook', $payload);

        // 5. Assertions
        $response->assertStatus(200);

        // Ensure Job was NOT dispatched
        \Illuminate\Support\Facades\Bus::assertNotDispatched(ProcessWhatsappMessage::class);
    }
}
