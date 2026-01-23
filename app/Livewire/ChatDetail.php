<?php

namespace App\Livewire;

use App\Models\Chat;
use Livewire\Component;

class ChatDetail extends Component
{
    public Chat $chat;

    public $memoryInput = '';

    public function mount(Chat $chat)
    {
        $this->chat = $chat;
    }

    public function toggleActive()
    {
        $this->chat->update(['is_active' => !$this->chat->is_active]);
    }

    public function addMemory()
    {
        $this->validate(['memoryInput' => 'required|string|min:3']);

        // Agregar mensaje de sistema
        \App\Models\Message::create([
            'chat_id' => $this->chat->id,
            'role' => 'system', // O 'assistant' pero con nota especial, 'system' es mejor si tu prompt lo soporta.
            // Si el prompt actual itera roles 'user'/'assistant', tal vez sea mejor 'assistant' con prefijo.
            // PERO el prompt actual hace: $role = $msg['role'] === 'user' ? 'Usuario' : 'Asistente';
            // Entonces si ponemos 'system', se verá como 'Asistente' (por el else).
            // Lo mejor es forzar un contenido explicito.
            'content' => "[NOTA DEL ADMIN/MEMORIA]: " . $this->memoryInput,
            'whatsapp_id' => null
        ]);

        $this->memoryInput = '';
    }

    public function render()
    {
        // Sort messages by ID (chronological) usually works, or created_at
        $messages = $this->chat->messages()->orderBy('created_at', 'asc')->get();

        return view('livewire.chat-detail', [
            'messages' => $messages
        ]);
    }
}
