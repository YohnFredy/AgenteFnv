<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Chat;
use App\Models\Message;
use App\Models\BotRule;
use App\Models\BotRuleMessage;
use App\Jobs\ProcessWhatsappMessage;
use App\Services\GeminiService;
use App\Services\EvolutionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;

class BotRulesTest extends TestCase
{
    use RefreshDatabase;

    public function test_bot_rule_triggers_with_split_messages()
    {
        // 1. Setup Chat and Rule
        $chat = Chat::create([
            'remote_jid' => '123456789@s.whatsapp.net',
            'stage' => 'initial',
            'is_active' => true
        ]);

        $rule = BotRule::create([
            'trigger_stage' => 'initial',
            'next_stage' => 'pricing_discussed',
            'keywords' => 'quiero precio', // Rule requires "quiero" AND "precio" (or phrase)
            'is_active' => true
        ]);

        BotRuleMessage::create([
            'bot_rule_id' => $rule->id,
            'content' => 'El precio es $100',
            'delay' => 0
        ]);

        // 2. Simulate User Messages split into two parts
        // Message 1: "quiero"
        Message::create([
            'chat_id' => $chat->id,
            'role' => 'user',
            'content' => 'quiero',
            'whatsapp_id' => 'msg_1'
        ]);

        // Message 2: "precio" (This triggers the job)
        $msg2 = Message::create([
            'chat_id' => $chat->id,
            'role' => 'user',
            'content' => 'precio',
            'whatsapp_id' => 'msg_2'
        ]);

        // 3. Mock Services
        $geminiMock = Mockery::mock(GeminiService::class);
        $geminiMock->shouldNotReceive('askGemini'); // Should NOT call AI if rule triggers

        $evolutionMock = Mockery::mock(EvolutionService::class);
        $evolutionMock->shouldReceive('sendPresence')->once();
        $evolutionMock->shouldReceive('sendMessage')
            ->once()
            ->with($chat->remote_jid, Mockery::on(function ($content) {
                return str_contains($content, 'El precio es $100');
            }))
            ->andReturn(true);

        // 4. Run Job
        $job = new ProcessWhatsappMessage($chat, $msg2->content, $msg2->id);
        $job->handle($geminiMock, $evolutionMock);

        // 5. Assertions
        $this->assertEquals('pricing_discussed', $chat->fresh()->stage);

        $this->assertDatabaseHas('messages', [
            'chat_id' => $chat->id,
            'role' => 'assistant',
            'content' => 'El precio es $100' // Ensure greeting replacement didn't break things if used, but here simpler.
        ]);
    }
}
