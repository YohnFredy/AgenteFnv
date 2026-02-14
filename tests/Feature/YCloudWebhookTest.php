<?php

namespace Tests\Feature;

use App\Models\Chat;
use App\Models\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;
use App\Jobs\ProcessWhatsappMessage;

class YCloudWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_webhook_verification_success()
    {
        $token = 'test_token';
        config(['app.env' => 'testing']); // Ensure we are in testing
        // Mock env variable access or just match what the controller expects if it reads env directly without config
        // Controller uses env('YCLOUD_WEBHOOK_TOKEN'). In testing, we can put this in .env.testing or modify env
        // Better to use putenv or similar if config doesn't work for env() helper calls (Laravel config usually overrides env)
        // But controller uses env(). Laravel config caching might interfere if not careful.
        // Let's rely on setting the .env var via putenv for this test duration or modifying phpunit.xml
        // Safe way: Config::set wouldn't work for env() calls.

        // Actually, best practice is to use Config::get in app code. But code uses env(). 
        // We will assume the test runner allows env override via putenv.
        // Mock config access for the token
        config(['services.ycloud.webhook_token' => $token]);

        $response = $this->getJson("/api/ycloud/webhook?hub_mode=subscribe&hub_verify_token=$token&hub_challenge=12345");

        $response->assertStatus(200);
        $this->assertEquals('12345', $response->content());
    }

    public function test_webhook_verification_failure()
    {
        $token = 'correct_token';
        config(['services.ycloud.webhook_token' => $token]);

        $response = $this->getJson("/api/ycloud/webhook?hub_mode=subscribe&hub_verify_token=wrong_token&hub_challenge=12345");

        $response->assertStatus(403);
    }

    public function test_incoming_text_message_processing()
    {
        Queue::fake();

        $payload = [
            'id' => 'evt_test_123',
            'type' => 'whatsapp.inbound_message.received',
            'apiVersion' => 'v2',
            'createTime' => '2026-02-01T18:26:39.438Z',
            'whatsappInboundMessage' => [
                'id' => 'msg_123',
                'wamid' => 'wamid.HBgNM...',
                'wabaId' => '1322435169351698',
                'from' => '+1234567890',
                'customerProfile' => [
                    'name' => 'John Doe'
                ],
                'to' => '+573185995909',
                'sendTime' => '2026-02-01T18:26:38.000Z',
                'type' => 'text',
                'text' => [
                    'body' => 'Hello YCloud'
                ]
            ]
        ];

        $response = $this->postJson('/api/ycloud/webhook', $payload);

        $response->assertStatus(200);
        $response->assertJson(['status' => 'processed']);

        // Assert Chat created
        $this->assertDatabaseHas('chats', [
            'remote_jid' => '1234567890@s.whatsapp.net',
            'name' => 'John Doe',
            'provider' => 'ycloud'
        ]);

        // Assert Message created
        $this->assertDatabaseHas('messages', [
            'whatsapp_id' => 'wamid.HBgNM...',
            'content' => 'Hello YCloud',
            'role' => 'user'
        ]);

        // Assert Job pushed
        Queue::assertPushed(ProcessWhatsappMessage::class);
    }
}
