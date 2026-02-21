<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\ContactFollowup;
use App\Models\FollowupCampaign;
use App\Models\Chat;

class ContactFollowups extends Component
{
    use WithPagination;

    public $search = '';
    public $filterStatus = '';
    public $filterCampaign = '';

    // Form properties
    public $showModal = false;
    public $editingId = null;
    public $chat_id;
    public $campaign_id;
    public $scheduled_at;
    public $status = 'scheduled';

    // Selections
    public $chats = []; // Will be loaded conditionally or limited
    public $campaigns = [];

    protected $listeners = ['refreshComponent' => '$refresh'];

    public function mount()
    {
        $this->campaigns = FollowupCampaign::where('is_active', true)->get();
        // Limit initial load or use a search-select component in frontend
        $this->chats = Chat::where('is_active', true)->orderBy('name')->limit(200)->get();
        $this->scheduled_at = now()->addDay()->format('Y-m-d\TH:i');
    }

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function render()
    {
        $query = ContactFollowup::query()
            ->with(['chat', 'campaign'])
            ->when($this->search, function ($q) {
                $q->whereHas('chat', function ($subQ) {
                    $subQ->where('name', 'like', '%' . $this->search . '%')
                        ->orWhere('remote_jid', 'like', '%' . $this->search . '%');
                });
            })
            ->when($this->filterStatus, function ($q) {
                $q->where('status', $this->filterStatus);
            })
            ->when($this->filterCampaign, function ($q) {
                $q->where('campaign_id', $this->filterCampaign);
            })
            ->orderBy('scheduled_at', 'asc');

        return view('livewire.contact-followups', [
            'followups' => $query->paginate(10)
        ]);
    }

    public function create()
    {
        $this->resetForm();
        $this->showModal = true;
    }

    public function edit($id)
    {
        $this->resetForm();
        $this->editingId = $id;
        $followup = ContactFollowup::findOrFail($id);

        $this->chat_id = $followup->chat_id;
        $this->campaign_id = $followup->campaign_id;
        $this->scheduled_at = $followup->scheduled_at ? $followup->scheduled_at->format('Y-m-d\TH:i') : null;
        $this->status = $followup->status;

        $this->showModal = true;
    }

    public function save()
    {
        $this->validate([
            'chat_id' => 'required|exists:chats,id',
            'campaign_id' => 'required|exists:followup_campaigns,id',
            'scheduled_at' => 'required|date',
            'status' => 'required|in:scheduled,pending,completed,canceled',
        ]);

        $data = [
            'chat_id' => $this->chat_id,
            'campaign_id' => $this->campaign_id,
            'scheduled_at' => $this->scheduled_at,
            'status' => $this->status,
        ];

        if ($this->editingId) {
            $followup = ContactFollowup::findOrFail($this->editingId);
            $followup->update($data);
            session()->flash('message', 'Seguimiento actualizado correctamente.');
        } else {
            // Logic for manual creation: Check for existing active followup first
            $existingFollowup = ContactFollowup::where('chat_id', $this->chat_id)
                ->whereIn('status', ['scheduled', 'pending'])
                ->first();

            // Set last interaction to the actual last user message
            $chat = Chat::find($this->chat_id);
            $lastUserMessage = $chat->messages()->where('role', 'user')->latest()->first();
            $data['last_interaction_at'] = $lastUserMessage ? $lastUserMessage->created_at : now();

            if ($existingFollowup) {
                // Update existing instead of creating new
                $existingFollowup->update($data);
                session()->flash('message', 'Seguimiento existente actualizado con la nueva campaña.');
            } else {
                ContactFollowup::create($data);
                session()->flash('message', 'Seguimiento programado correctamente.');
            }
        }

        $this->showModal = false;
        $this->resetForm();
    }

    public function delete($id)
    {
        ContactFollowup::destroy($id);
        session()->flash('message', 'Seguimiento eliminado.');
    }

    public function markAsCompleted($id)
    {
        $followup = ContactFollowup::findOrFail($id);
        $followup->update(['status' => 'completed']);
        session()->flash('message', 'Seguimiento marcado como completado.');
    }

    public function markAsCanceled($id)
    {
        $followup = ContactFollowup::findOrFail($id);
        $followup->update(['status' => 'canceled']);
        session()->flash('message', 'Seguimiento cancelado.');
    }

    public function resetForm()
    {
        // Keep chats and campaigns loaded
        $this->chat_id = null;
        $this->campaign_id = null;
        $this->editingId = null;
        $this->status = 'scheduled';
        $this->scheduled_at = now()->addDay()->format('Y-m-d\TH:i');
    }
}
