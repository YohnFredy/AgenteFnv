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

class SendMarketingMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $chatId;
    public $data;
    public $mode; // 'manual' or 'template'
    public $postSendTagId;

    /**
     * Create a new job instance.
     *
     * @param int $chatId
     * @param array $data ['message' => string, 'template_name' => string]
     * @param string $mode
     * @param int|null $postSendTagId
     */
    public function __construct($chatId, $data, $mode, $postSendTagId = null)
    {
        $this->chatId = $chatId;
        $this->data = $data;
        $this->mode = $mode;
        $this->postSendTagId = $postSendTagId;
    }

    /**
     * Execute the job.
     */
    public function handle(YCloudService $ycloud): void
    {
        $chat = Chat::find($this->chatId);

        if (!$chat) {
            Log::warning("SendMarketingMessage: Chat not found ID {$this->chatId}");
            return;
        }

        $remoteJid = $chat->remote_jid;
        $success = false;
        $logContent = '';

        try {
            if ($this->mode === 'manual') {
                $messageBody = $this->data['message'] ?? '';
                if (empty($messageBody)) {
                    Log::warning("SendMarketingMessage: Empty message body for chat {$this->chatId}");
                    return;
                }

                // Replace variables if needed (e.g. {name}) - Simple implementation
                // $messageBody = str_replace('{name}', $chat->name ?? 'Cliente', $messageBody);

                $success = $ycloud->sendMessage($remoteJid, $messageBody);
                $logContent = $messageBody;
            } elseif ($this->mode === 'template') {
                $templateName = $this->data['template_name'] ?? '';
                if (empty($templateName)) {
                    Log::warning("SendMarketingMessage: Empty template name for chat {$this->chatId}");
                    return;
                }

                $success = $ycloud->sendTemplate($remoteJid, $templateName);

                // Use provided context or fallback
                $context = $this->data['template_context'] ?? '';
                $logContent = "Plantilla enviada: {$templateName}";

                if (!empty($context)) {
                    $logContent = "Plantilla: {$templateName}\n\n{$context}";
                }
            }

            if ($success) {
                // Log to database as a message from assistant
                // This ensures it appears in chats-monitor
                Message::create([
                    'chat_id' => $chat->id,
                    'role' => 'assistant',
                    'content' => $logContent,
                    'type' => $this->mode === 'template' ? 'template' : 'text',
                    'status' => 'sent', // Assuming immediate success from API
                    'metadata' => json_encode(['marketing_campaign' => true])
                ]);

                // Update updated_at to bring chat to top if desired, or keep it as is?
                // Usually sending a message updates the chat timestamp.
                $chat->touch();

                // Assign Post-Send Tag if exists
                if ($this->postSendTagId) {
                    $chat->tags()->syncWithoutDetaching([$this->postSendTagId]);
                }

                Log::info("Marketing message sent to {$chat->remote_jid} (ID: {$chat->id})");
            } else {
                Log::error("Failed to send marketing message to {$chat->remote_jid} (ID: {$chat->id})");
            }
        } catch (\Exception $e) {
            Log::error("SendMarketingMessage Exception: " . $e->getMessage());
        }
    }
}
