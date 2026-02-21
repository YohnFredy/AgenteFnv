<?php

namespace App\Livewire;

use App\Models\FollowupCampaign;
use App\Models\FollowupStep;
use Livewire\Component;
use Livewire\WithPagination;

class FollowupCampaigns extends Component
{
    use WithPagination;

    public $search = '';
    public $showModal = false;
    public $showStepsModal = false;

    // Campaign Form Data
    public $campaignId;
    public $name;
    public $description;
    public $trigger_type;
    public $is_active = true;

    // Steps Management
    public $selectedCampaign;
    public $steps = [];
    public $stepId;
    public $step_message_type = 'text';
    public $step_message_content;
    public $step_delay = 1;

    protected $rules = [
        'name' => 'required|string|max:255',
        'description' => 'nullable|string',
        'trigger_type' => 'nullable|string',
        'is_active' => 'boolean',
    ];

    public function render()
    {
        $campaigns = FollowupCampaign::where('name', 'like', '%' . $this->search . '%')
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return view('livewire.followup-campaigns', [
            'campaigns' => $campaigns
        ]);
    }

    // Campaign CRUD
    public function create()
    {
        $this->reset(['campaignId', 'name', 'description', 'trigger_type', 'is_active']);
        $this->showModal = true;
    }

    public function edit($id)
    {
        $campaign = FollowupCampaign::findOrFail($id);
        $this->campaignId = $id;
        $this->name = $campaign->name;
        $this->description = $campaign->description;
        $this->trigger_type = $campaign->trigger_type;
        $this->is_active = $campaign->is_active;
        $this->showModal = true;
    }

    public function save()
    {
        $this->validate();

        FollowupCampaign::updateOrCreate(
            ['id' => $this->campaignId],
            [
                'name' => $this->name,
                'description' => $this->description,
                'trigger_type' => $this->trigger_type,
                'is_active' => $this->is_active,
            ]
        );

        $this->showModal = false;
        $this->reset(['campaignId', 'name', 'description', 'trigger_type', 'is_active']);
    }

    public function delete($id)
    {
        FollowupCampaign::findOrFail($id)->delete();
    }

    public function toggleActive($id)
    {
        $campaign = FollowupCampaign::findOrFail($id);
        $campaign->update(['is_active' => !$campaign->is_active]);
    }

    // Steps Management
    public function manageSteps($campaignId)
    {
        $this->selectedCampaign = FollowupCampaign::with('steps')->findOrFail($campaignId);
        $this->loadSteps();
        $this->showStepsModal = true;
    }

    public function loadSteps()
    {
        $this->steps = $this->selectedCampaign->steps()->orderBy('delay')->get();
    }

    public function addStep()
    {
        $this->reset(['stepId', 'step_message_type', 'step_message_content', 'step_delay']);
    }

    public function editStep($stepId)
    {
        $step = FollowupStep::findOrFail($stepId);
        $this->stepId = $stepId;
        $this->step_message_type = $step->message_type;
        $this->step_message_content = $step->message_content;
        $this->step_delay = $step->delay;
    }

    public function saveStep()
    {
        $this->validate([
            'step_message_type' => 'required|string',
            'step_message_content' => 'required|string',
            'step_delay' => 'required|integer|min:0',
        ]);

        FollowupStep::updateOrCreate(
            ['id' => $this->stepId],
            [
                'campaign_id' => $this->selectedCampaign->id,
                'message_type' => $this->step_message_type,
                'message_content' => $this->step_message_content,
                'delay' => $this->step_delay,
            ]
        );

        $this->reset(['stepId', 'step_message_type', 'step_message_content', 'step_delay']);
        $this->loadSteps();
    }

    public function deleteStep($stepId)
    {
        FollowupStep::findOrFail($stepId)->delete();
        $this->loadSteps();
    }
}
