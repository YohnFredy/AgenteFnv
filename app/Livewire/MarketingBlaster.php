<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\Chat;
use App\Models\Tag;
use App\Jobs\SendMarketingMessage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;

class MarketingBlaster extends Component
{
    use WithPagination;

    // Modes
    public $mode = 'manual'; // 'manual' or 'template'

    // Filters
    public $selectedTags = []; // Tags to EXCLUDE
    public $timeHours = 24;

    // Messages
    public $messageBody = '';
    public $templateName = '';
    public $templateContext = ''; // For saving context in chat history

    // Actions
    public $postSendTagId = null;

    // State
    public $isSending = false;
    public $successMessage = '';

    protected $queryString = ['mode'];

    public function updatedMode()
    {
        $this->resetPage();
        $this->successMessage = '';
    }

    public function updatedSelectedTags()
    {
        $this->resetPage();
    }

    public function updatedTimeHours()
    {
        $this->resetPage();
    }

    public function getTargetChatsQuery(): Builder
    {
        $query = Chat::query()
            ->where('is_active', true) // Assuming we only want active chats? Or all? User said "users", usually implies leads/chats.
            ->whereNotNull('remote_jid');

        // Add subquery for last user message timestamp
        $query->addSelect([
            'last_user_message_at' => \App\Models\Message::select('created_at')
                ->whereColumn('chat_id', 'chats.id')
                ->where('role', 'user')
                ->latest()
                ->take(1)
        ])->withCasts(['last_user_message_at' => 'datetime']);

        // Filter 1: Exclude Tags
        if (!empty($this->selectedTags)) {
            $query->whereDoesntHave('tags', function ($q) {
                $q->whereIn('tags.id', $this->selectedTags);
            });
        }

        // Filter 2: Time Logic
        $cutoffTime = now()->subHours($this->timeHours);

        if ($this->mode === 'manual') {
            // Recent users: written LESS than X hours ago
            // Must have a user message >= cutoffTime
            $query->whereHas('messages', function ($q) use ($cutoffTime) {
                $q->where('role', 'user')
                    ->where('created_at', '>=', $cutoffTime);
            });
        } else {
            // Inactive users: written MORE than X hours ago OR never written
            // Must NOT have a user message >= cutoffTime
            $query->whereDoesntHave('messages', function ($q) use ($cutoffTime) {
                $q->where('role', 'user')
                    ->where('created_at', '>=', $cutoffTime);
            });
        }

        // Order by the computed column
        return $query->orderByDesc('last_user_message_at');
    }

    public function getPreviewUsersProperty()
    {
        return $this->getTargetChatsQuery()->paginate(10);
    }

    public function getTotalUsersProperty()
    {
        return $this->getTargetChatsQuery()->count();
    }

    public function sendCampaign()
    {
        $this->validate([
            'mode' => 'required|in:manual,template',
            'messageBody' => 'required_if:mode,manual',
            'templateName' => 'required_if:mode,template',
            'timeHours' => 'required|integer|min:1',
        ]);

        $this->isSending = true;
        $this->successMessage = '';

        $count = 0;
        $delaySeconds = 0;
        $batchSize = 50; // Chunk for safer query processing

        // Execute query in chunks to avoid memory issues
        $this->getTargetChatsQuery()->chunk($batchSize, function ($chats) use (&$count, &$delaySeconds) {
            foreach ($chats as $chat) {
                $data = [];
                if ($this->mode === 'manual') {
                    $data['message'] = $this->messageBody;
                } else {
                    $data['template_name'] = $this->templateName;
                    $data['template_context'] = $this->templateContext;
                }

                // Dispatch Job with delay
                // Stagger: 2 seconds per user
                SendMarketingMessage::dispatch(
                    $chat->id,
                    $data,
                    $this->mode,
                    $this->postSendTagId
                )->delay(now()->addSeconds($delaySeconds));

                $delaySeconds += 2;
                $count++;
            }
        });

        $this->isSending = false;
        $this->successMessage = "🚀 Campaña iniciada. Se enviarán {$count} mensajes en cola (aprox. " . gmdate("H:i:s", $delaySeconds) . " de duración).";

        // Clear inputs after send? Maybe keep them for review.
    }

    public function render()
    {
        return view('livewire.marketing-blaster', [
            'users' => $this->getPreviewUsersProperty(),
            'total' => $this->getTotalUsersProperty(),
            'availableTags' => Tag::all(),
        ]);
    }
}
