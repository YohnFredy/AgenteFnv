<?php

namespace App\Jobs;

use App\Models\Chat;
use App\Models\Message;
use App\Services\YCloudService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendCampaignMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $messageId;

    /**
     * Create a new job instance.
     */
    public function __construct(int $messageId)
    {
        $this->messageId = $messageId;
    }

    /**
     * Execute the job.
     */
    public function handle(YCloudService $ycloudService): void
    {
        $message = Message::find($this->messageId);

        if (!$message) {
            Log::error("SendCampaignMessage: Message ID {$this->messageId} not found.");
            return;
        }

        if ($message->status === 'sent') {
            Log::info("SendCampaignMessage: Message ID {$this->messageId} already sent.");
            return;
        }

        $chat = $message->chat;

        Log::info("Enviando mensaje de campaña a {$chat->remote_jid}: {$message->content}");

        try {
            // Send composing indicator
            $ycloudService->sendPresence($chat->remote_jid, 'composing');

            // Wait a bit to simulate typing (shorter than AI, fixed)
            sleep(2);

            if ($message->media_type === 'image' && $message->media_url) {
                // TODO: Implement sending image via YCloudService if needed
                // For now, assume text content contains URL or handle separately
                $ycloudService->sendMessage($chat->remote_jid, $message->content);
            } else {
                $ycloudService->sendMessage($chat->remote_jid, $message->content);
            }

            $message->update(['status' => 'sent']);
        } catch (\Exception $e) {
            Log::error("Error sending campaign message ID {$this->messageId}: " . $e->getMessage());
            $message->update(['status' => 'failed']);
            // Release job back to queue for retry
            $this->release(60);
        }
    }
}
