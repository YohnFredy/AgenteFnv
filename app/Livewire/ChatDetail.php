<?php

namespace App\Livewire;

use App\Models\Chat;
use App\Models\Message;
use App\Services\YCloudService;
use Livewire\Component;

class ChatDetail extends Component
{
    public Chat $chat;

    public string $memoryInput = '';

    /** 'note' = nota interna (sin enviar) | 'whatsapp' = enviar al usuario */
    public string $sendMode = 'whatsapp';

    public function mount(Chat $chat)
    {
        $this->chat = $chat;
    }

    public function toggleActive()
    {
        $this->chat->update(['is_active' => !$this->chat->is_active]);
    }

    public function setSendMode(string $mode)
    {
        $this->sendMode = $mode;
    }

    /**
     * Guarda una nota interna visible en el chat (no se envía al usuario).
     */
    public function addMemory()
    {
        $this->validate(['memoryInput' => 'required|string|min:1']);

        Message::create([
            'chat_id'      => $this->chat->id,
            'role'         => 'system',
            'content'      => '[NOTA DEL ADMIN/MEMORIA]: ' . $this->memoryInput,
            'whatsapp_id'  => null,
        ]);

        $this->memoryInput = '';
    }

    /**
     * Envía el mensaje al usuario por WhatsApp y lo guarda en el historial.
     */
    public function sendWhatsApp()
    {
        $this->validate(['memoryInput' => 'required|string|min:1']);

        $text = $this->memoryInput;

        /** @var YCloudService $ycloud */
        $ycloud = app(YCloudService::class);
        $sent   = $ycloud->sendMessage($this->chat->remote_jid, $text);

        if ($sent) {
            Message::create([
                'chat_id'      => $this->chat->id,
                'role'         => 'assistant',
                'content'      => $text,
                'whatsapp_id'  => null,
            ]);

            $this->memoryInput = '';
        } else {
            session()->flash('error', 'No se pudo enviar el mensaje por WhatsApp. Inténtalo de nuevo.');
        }
    }

    /**
     * Determine cuál método ejecutar según el modo activo.
     */
    public function submitMessage()
    {
        if ($this->sendMode === 'whatsapp') {
            $this->sendWhatsApp();
        } else {
            $this->addMemory();
        }
    }

    public function render()
    {
        $messages = $this->chat->messages()->orderBy('created_at', 'asc')->get();

        return view('livewire.chat-detail', [
            'messages' => $messages,
        ]);
    }
}
