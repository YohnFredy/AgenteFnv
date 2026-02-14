<?php

namespace App\Livewire;

use App\Models\Chat;
use Livewire\Component;
use Livewire\WithPagination;

class ChatList extends Component
{
    use WithPagination;

    public $search = '';
    public $sortField = 'updated_at';
    public $sortDirection = 'desc';

    // Propiedades para edición
    public $editingChatId = null;
    public $editingName = '';
    public $editingStage = '';

    protected $queryString = [
        'search' => ['except' => ''],
        'sortField' => ['except' => 'updated_at'],
        'sortDirection' => ['except' => 'desc'],
    ];

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function sortBy($field)
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }
    }

    public function toggleActive($chatId)
    {
        $chat = Chat::find($chatId);
        if ($chat) {
            $chat->update(['is_active' => !$chat->is_active]);
        }
    }

    public function editChat($chatId)
    {
        $chat = Chat::findOrFail($chatId);
        $this->editingChatId = $chatId;
        $this->editingName = $chat->name;
        $this->editingStage = $chat->stage;
    }

    public function saveChat()
    {
        $this->validate([
            'editingName' => 'required|string|max:255',
            'editingStage' => 'required|integer|min:0',
        ]);

        $chat = Chat::findOrFail($this->editingChatId);
        $chat->update([
            'name' => $this->editingName,
            'stage' => $this->editingStage,
        ]);

        $this->editingChatId = null;
        session()->flash('message', 'Chat actualizado correctamente.');
    }

    public function cancelEdit()
    {
        $this->editingChatId = null;
    }

    public function render()
    {
        $chats = Chat::query()
            ->leftJoin('messages', function ($join) {
                $join->on('chats.id', '=', 'messages.chat_id')
                    ->whereRaw('messages.id = (SELECT id FROM messages WHERE chat_id = chats.id ORDER BY created_at DESC LIMIT 1)');
            })
            ->selectRaw('chats.*, COALESCE(messages.created_at, chats.updated_at) as last_activity')
            ->where(function ($query) {
                $query->where('chats.name', 'like', '%' . $this->search . '%')
                    ->orWhere('chats.remote_jid', 'like', '%' . $this->search . '%');
            })
            ->orderBy($this->sortField === 'updated_at' ? 'last_activity' : 'chats.' . $this->sortField, $this->sortDirection)
            ->paginate(10);

        return view('livewire.chat-list', [
            'chats' => $chats
        ]);
    }
}
