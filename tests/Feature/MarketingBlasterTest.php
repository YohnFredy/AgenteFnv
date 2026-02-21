<?php

namespace Tests\Feature;

use App\Livewire\MarketingBlaster;
use App\Models\Chat;
use App\Models\Tag;
use App\Models\Message;
use App\Jobs\SendMarketingMessage;
use App\Services\YCloudService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

class MarketingBlasterTest extends TestCase
{
    use RefreshDatabase;

    public function test_renders_correctly()
    {
        Livewire::test(MarketingBlaster::class)
            ->assertStatus(200);
    }

    public function test_filtering_mode_manual_recent_users()
    {
        // Chat recent (5h ago) should appear
        $recentChat = Chat::create(['remote_jid' => '1234567890', 'is_active' => true]);
        $msg = Message::create([
            'chat_id' => $recentChat->id,
            'role' => 'user',
            'content' => 'Hello',
        ]);
        $msg->created_at = now()->subHours(5);
        $msg->save();

        // Chat old (50h ago) should NOT appear in manual (recent < 24h) mode
        $oldChat = Chat::create(['remote_jid' => '0987654321', 'is_active' => true]);
        $msg2 = Message::create([
            'chat_id' => $oldChat->id,
            'role' => 'user',
            'content' => 'Old msg',
        ]);
        $msg2->created_at = now()->subHours(50);
        $msg2->save();

        Livewire::test(MarketingBlaster::class)
            ->set('mode', 'manual')
            ->set('timeHours', 24)
            ->assertSee($recentChat->remote_jid)
            ->assertDontSee($oldChat->remote_jid);
    }

    public function test_filtering_mode_template_inactive_users()
    {
        // Chat recent (5h ago) should NOT appear in template (inactive > 24h) mode
        $recentChat = Chat::create(['remote_jid' => '1234567890', 'is_active' => true]);
        $msg = Message::create([
            'chat_id' => $recentChat->id,
            'role' => 'user',
            'content' => 'Recent msg',
        ]);
        $msg->created_at = now()->subHours(5);
        $msg->save();

        // Chat old (50h ago) should appear
        $oldChat = Chat::create(['remote_jid' => '0987654321', 'is_active' => true]);
        $msg2 = Message::create([
            'chat_id' => $oldChat->id,
            'role' => 'user',
            'content' => 'Old msg',
        ]);
        $msg2->created_at = now()->subHours(50);
        $msg2->save();

        Livewire::test(MarketingBlaster::class)
            ->set('mode', 'template')
            ->set('timeHours', 24)
            ->assertSee($oldChat->remote_jid)
            ->assertDontSee($recentChat->remote_jid);
    }

    public function test_filtering_tags_exclusion()
    {
        $tag = Tag::create(['name' => 'Excluded', 'slug' => 'excluded']);

        // Chat with tag - should be excluded
        $chatWithTag = Chat::create(['remote_jid' => '111', 'is_active' => true]);
        $chatWithTag->tags()->attach($tag);
        // Needs recent message to be eligible for manual mode
        $msg = Message::create([
            'chat_id' => $chatWithTag->id,
            'role' => 'user',
            'content' => 'Msg',
        ]);
        $msg->created_at = now()->subHours(1);
        $msg->save();

        // Chat without tag - should be included
        $chatWithoutTag = Chat::create(['remote_jid' => '222', 'is_active' => true]);
        $msg2 = Message::create([
            'chat_id' => $chatWithoutTag->id,
            'role' => 'user',
            'content' => 'Msg',
        ]);
        $msg2->created_at = now()->subHours(1);
        $msg2->save();

        Livewire::test(MarketingBlaster::class)
            ->set('mode', 'manual')
            ->set('timeHours', 24)
            ->set('selectedTags', [$tag->id])
            ->assertSee($chatWithoutTag->remote_jid)
            ->assertDontSee($chatWithTag->remote_jid);
    }

    public function test_send_manual_campaign_dispatches_jobs()
    {
        Queue::fake();

        $chat = Chat::create(['remote_jid' => '12345', 'is_active' => true]);
        // Must have recent message to be included in campaign
        $msg = Message::create([
            'chat_id' => $chat->id,
            'role' => 'user',
            'content' => 'Msg',
        ]);
        $msg->created_at = now()->subHours(1);
        $msg->save();

        Livewire::test(MarketingBlaster::class)
            ->set('mode', 'manual')
            ->set('messageBody', 'Hello World')
            ->call('sendCampaign');

        Queue::assertPushed(SendMarketingMessage::class, function ($job) use ($chat) {
            return $job->chatId === $chat->id;
        });
    }

    public function test_job_sends_message_and_logs_it()
    {
        $chat = Chat::create(['remote_jid' => '12345', 'is_active' => true]);

        $ycloudMock = Mockery::mock(YCloudService::class);
        $ycloudMock->shouldReceive('sendMessage')
            ->once()
            ->with($chat->remote_jid, 'Hello Test')
            ->andReturn(true);

        $job = new SendMarketingMessage($chat->id, ['message' => 'Hello Test'], 'manual');
        $job->handle($ycloudMock);

        $this->assertDatabaseHas('messages', [
            'chat_id' => $chat->id,
            'content' => 'Hello Test',
            'role' => 'assistant',
            'type' => 'text'
        ]);
    }
}
