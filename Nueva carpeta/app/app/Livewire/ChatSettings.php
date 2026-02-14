<?php

namespace App\Livewire;

use App\Models\Chat;
use Livewire\Component;

class ChatSettings extends Component
{
    public $chats;
    
    // Para el modal de edición
    public $editingChatId = null;
    public $editingInstruction = '';

    public function mount()
    {
        $this->loadChats();
    }

    public function loadChats()
    {
        $this->chats = Chat::latest()->get();
    }

    public function edit($chatId)
    {
        $chat = Chat::find($chatId);
        if ($chat) {
            $this->editingChatId = $chat->id;
            $this->editingInstruction = $chat->system_instruction ?? '';
        }
    }

    public function save()
    {
        if ($this->editingChatId) {
            $chat = Chat::find($this->editingChatId);
            $chat->update([
                'system_instruction' => $this->editingInstruction
            ]);
            
            $this->editingChatId = null; // Cerrar "modal"
            $this->loadChats(); // Recargar lista
        }
    }

    public function cancel()
    {
        $this->editingChatId = null;
    }

    public function render()
    {
        return view('livewire.chat-settings')
            ->layout('layouts.app'); // Asumiendo layout por defecto
    }
}
