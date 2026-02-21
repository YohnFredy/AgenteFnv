<?php

namespace App\Livewire;

use App\Models\Chat;
use Livewire\Component;
use Livewire\WithPagination;

class ChatList extends Component
{
    use WithPagination;

    public $search = '';
    // Propiedades para edición
    public $sortField = 'updated_at';
    public $sortDirection = 'desc';

    // Propiedades para Selección y Etiquetas
    public $selectedChats = [];
    public $selectAll = false;
    public $selectMode = false;

    public $showTagModal = false;
    public $tagName;
    public $tagColor = '#10B981'; // Emerald-500 default
    public $availableTags = [];
    public $filterTagId = null;

    // Propiedades para edición (Restored)
    public $editingChatId = null;
    public $editingName = '';
    public $editingStage = '';

    public $selectedChatId = null;
    public $selectedChat = null;

    protected $queryString = [
        'search' => ['except' => ''],
        'sortField' => ['except' => 'updated_at'],
        'sortDirection' => ['except' => 'desc'],
        'selectedChatId' => ['except' => null],
    ];

    public function mount()
    {
        if ($this->selectedChatId) {
            $this->selectedChat = Chat::find($this->selectedChatId);
        }
        $this->loadTags();
    }

    public function loadTags()
    {
        $this->availableTags = \App\Models\Tag::where('is_active', true)->get();
    }

    public function updatedSelectAll($value)
    {
        if ($value) {
            $this->selectedChats = $this->getChatsQuery()->pluck('chats.id')->map(fn($id) => (string)$id)->toArray();
            $this->selectMode = true;
        } else {
            $this->selectedChats = [];
            $this->selectMode = false;
        }
    }

    public function updatedSelectedChats()
    {
        $this->selectMode = count($this->selectedChats) > 0;
        $this->selectAll = false;
    }

    public function toggleSelectionMode()
    {
        $this->selectMode = !$this->selectMode;
        if (!$this->selectMode) {
            $this->selectedChats = [];
            $this->selectAll = false;
        }
    }

    public function openTagModal()
    {
        $this->reset(['tagName', 'tagColor']);
        $this->loadTags();

        // If single chat selected via click (not checkbox mode), treat as selection of 1
        if (empty($this->selectedChats) && $this->selectedChatId) {
            // We don't change $selectedChats here to avoid triggering selection mode UI, 
            // but we will use logic in view or helper to check status.
        }

        $this->showTagModal = true;
    }

    public function createTag()
    {
        $this->validate([
            'tagName' => 'required|string|max:50|unique:tags,name',
            'tagColor' => 'required|string',
        ]);

        $slug = \Illuminate\Support\Str::slug($this->tagName);

        \App\Models\Tag::create([
            'name' => $this->tagName,
            'slug' => $slug,
            'color' => $this->tagColor,
        ]);

        $this->reset(['tagName']);
        $this->loadTags();
        session()->flash('message', 'Etiqueta creada correctamente.');
    }

    public function deleteTag($tagId)
    {
        \App\Models\Tag::find($tagId)?->delete();
        $this->loadTags();
        session()->flash('message', 'Etiqueta eliminada.');
    }

    public function detachTag($chatId, $tagId)
    {
        $chat = Chat::find($chatId);
        if ($chat) {
            $chat->tags()->detach($tagId);
            session()->flash('message', 'Etiqueta removida del chat.');
        }
    }

    public function toggleTag($tagId)
    {
        if (empty($this->selectedChats)) {
            // Si no hay seleccionados, actuamos sobre el seleccionado actual si existe
            if ($this->selectedChatId) {
                $this->selectedChats = [$this->selectedChatId];
            } else {
                return;
            }
        }

        // Verificar el estado actual de la etiqueta en los chats seleccionados
        // Si TODOS la tienen, la quitamos. Si NO todos la tienen, la ponemos a todos (o a los que faltan).
        // Lógica simplificada: Checkbox behavior.
        // Pero como es un toggle desde UI, necesitamos saber qué acción realizar.
        // Vamos a asumir que la UI nos manda la intención o deducirla.

        // Mejor enfoque query:
        $selectedChatsCount = count($this->selectedChats);
        $chatsWithTagCount = \Illuminate\Support\Facades\DB::table('lead_tag')
            ->whereIn('chat_id', $this->selectedChats)
            ->where('tag_id', $tagId)
            ->count();

        $action = ($chatsWithTagCount === $selectedChatsCount) ? 'detach' : 'attach';

        $chats = Chat::whereIn('id', $this->selectedChats)->get();
        foreach ($chats as $chat) {
            if ($action === 'detach') {
                $chat->tags()->detach($tagId);
            } else {
                $chat->tags()->syncWithoutDetaching([$tagId]);
            }
        }

        $this->loadTags(); // Recargar para actualizar UI si es necesario
    }

    // Deprecated or simplified assignTag (keep for single actions if needed, or alias)
    public function assignTag($tagId)
    {
        if (empty($this->selectedChats)) {
            // Si no hay seleccionados, pero hay un chat abierto, asignamos a ese
            if ($this->selectedChatId) {
                $chat = Chat::find($this->selectedChatId);
                $chat->tags()->syncWithoutDetaching([$tagId]);
                $this->dispatch('check-tags-update'); // Forzamos actualización UI si es necesario
                return;
            }
            return;
        }

        foreach ($this->selectedChats as $chatId) {
            $chat = Chat::find($chatId);
            if ($chat) {
                $chat->tags()->syncWithoutDetaching([$tagId]);
            }
        }

        $this->showTagModal = false;
        $this->selectedChats = [];
        $this->selectMode = false;
        session()->flash('message', 'Etiqueta asignada a los chats seleccionados.');
    }

    public function selectChat($chatId)
    {
        if ($this->selectMode) {
            if (in_array($chatId, $this->selectedChats)) {
                $this->selectedChats = array_diff($this->selectedChats, [$chatId]);
            } else {
                $this->selectedChats[] = (string)$chatId;
            }
            $this->updatedSelectedChats();
            return;
        }

        $this->selectedChatId = $chatId;
        $this->selectedChat = Chat::with('tags')->find($chatId);
    }

    public function resetSelection()
    {
        $this->selectedChatId = null;
        $this->selectedChat = null;
    }

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

    public function setFilterTag($tagId)
    {
        // Toggle filter: if clicking same tag, clear filter
        $this->filterTagId = ($this->filterTagId === $tagId) ? null : $tagId;
        $this->resetPage(); // Reset pagination when filtering
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

    public $filterMode = 'all'; // all, human, bot

    // ... existing properties ...

    public function setFilterMode($mode)
    {
        $this->filterMode = $mode;
        $this->resetPage();
    }

    private function getChatsQuery()
    {
        $query = Chat::query()
            ->with(['tags']) // Eager load tags
            ->where(function ($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                    ->orWhere('remote_jid', 'like', '%' . $this->search . '%');
            });

        if ($this->filterTagId) {
            $query->whereHas('tags', function ($q) {
                $q->where('tags.id', $this->filterTagId);
            });
        }

        if ($this->filterMode === 'human') {
            $query->where('is_active', false);
        } elseif ($this->filterMode === 'bot') {
            $query->where('is_active', true);
        }

        // Add the left join for last activity, as it was in the original getChatsQuery
        $query->leftJoin('messages', function ($join) {
            $join->on('chats.id', '=', 'messages.chat_id')
                ->whereRaw('messages.id = (SELECT id FROM messages WHERE chat_id = chats.id ORDER BY created_at DESC LIMIT 1)');
        })
            ->selectRaw('chats.*, COALESCE(messages.created_at, chats.updated_at) as last_activity');

        return $query;
    }

    public function render()
    {
        $chats = $this->getChatsQuery()
            ->orderBy($this->sortField === 'updated_at' ? 'last_activity' : 'chats.' . $this->sortField, $this->sortDirection)
            ->simplePaginate(10);

        return view('livewire.chat-list', [
            'chats' => $chats
        ]);
    }
}
