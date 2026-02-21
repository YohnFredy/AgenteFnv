<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\AutomationRule;
use App\Models\BotSetting;
use App\Models\Tag;
use App\Models\FollowupCampaign;

class AutomationRules extends Component
{
    public $automationRules;
    public $defaultTagId;
    public $office_hours_start;
    public $office_hours_end;

    public $availableTags = []; // Initialize as array
    public $availableCampaigns = []; // Initialize as array

    // Form fields
    public $showModal = false;
    public $editingRuleId = null; // Property to track editing state
    public $trigger_content;
    public $campaign_id;
    public $tag_id;
    public $remove_tag_id; // Etiqueta a eliminar
    public $followup_delay_hours = 0; // Horas de espera
    public $is_active = true;
    public $match_type = 'contains';

    public function mount()
    {
        $this->availableTags = Tag::where('is_active', true)->get();
        $this->availableCampaigns = FollowupCampaign::where('is_active', true)->get();
        $this->loadData();
    }

    public function loadData()
    {
        // Use a different property name for the collection to avoid conflict with validation rules if necessary
        // Actually, let's keep it simple.
        $this->automationRules = AutomationRule::with(['campaign', 'tag', 'removeTag'])->get();
        $this->defaultTagId = BotSetting::find('default_new_user_tag_id')?->value;
        $this->office_hours_start = BotSetting::find('office_hours_start')?->value ?? '07:00';
        $this->office_hours_end = BotSetting::find('office_hours_end')?->value ?? '19:00';
    }

    public function saveDefaultTag()
    {
        BotSetting::updateOrCreate(
            ['key' => 'default_new_user_tag_id'],
            ['value' => $this->defaultTagId]
        );
        $this->dispatch('saved-settings'); // Optional: feedback
        session()->flash('message', 'Configuración guardada.');
    }

    public function saveOfficeHours()
    {
        $this->validate([
            'office_hours_start' => 'required|date_format:H:i',
            'office_hours_end' => 'required|date_format:H:i|after:office_hours_start',
        ]);

        BotSetting::updateOrCreate(['key' => 'office_hours_start'], ['value' => $this->office_hours_start]);
        BotSetting::updateOrCreate(['key' => 'office_hours_end'], ['value' => $this->office_hours_end]);

        $this->dispatch('saved-settings');
        session()->flash('message', 'Horario de oficina actualizado.');
    }

    public function openModal()
    {
        $this->resetForm();
        $this->showModal = true;
    }

    public function editRule($id)
    {
        $this->resetForm();
        $this->editingRuleId = $id;
        $rule = AutomationRule::find($id);

        if ($rule) {
            $this->trigger_content = $rule->trigger_content;
            $this->campaign_id = $rule->campaign_id;
            $this->tag_id = $rule->tag_id;
            $this->remove_tag_id = $rule->remove_tag_id;
            $this->followup_delay_hours = $rule->followup_delay_hours;
            $this->match_type = $rule->match_type;
            $this->is_active = (bool)$rule->is_active;
            $this->showModal = true;
        }
    }

    public function saveRule()
    {
        $this->validate([
            'trigger_content' => 'required|string|max:255',
            'campaign_id' => 'nullable|exists:followup_campaigns,id',
            'tag_id' => 'nullable|exists:tags,id',
            'remove_tag_id' => 'nullable|exists:tags,id',
            'followup_delay_hours' => 'nullable|integer|min:0',
            'match_type' => 'required|in:contains,exact',
            'is_active' => 'boolean',
        ]);

        $data = [
            'trigger_content' => $this->trigger_content,
            'campaign_id' => $this->campaign_id ?: null,
            'tag_id' => $this->tag_id ?: null,
            'remove_tag_id' => $this->remove_tag_id ?: null,
            'followup_delay_hours' => $this->followup_delay_hours ?? 0,
            'match_type' => $this->match_type,
            'is_active' => $this->is_active,
        ];

        if ($this->editingRuleId) {
            $rule = AutomationRule::find($this->editingRuleId);
            if ($rule) {
                $rule->update($data);
                session()->flash('message', 'Regla actualizada correctamente.');
            }
        } else {
            AutomationRule::create($data);
            session()->flash('message', 'Regla creada correctamente.');
        }

        $this->showModal = false;
        $this->loadData();
    }

    public function deleteRule($id)
    {
        AutomationRule::destroy($id);
        $this->loadData();
        session()->flash('message', 'Regla eliminada.');
    }

    public function resetForm()
    {
        $this->reset(['trigger_content', 'campaign_id', 'tag_id', 'remove_tag_id', 'followup_delay_hours', 'match_type', 'is_active', 'editingRuleId']);
        $this->match_type = 'contains';
        $this->is_active = true;
        $this->followup_delay_hours = 0;
    }

    public function render()
    {
        return view('livewire.automation-rules', [
            'automationRules' => $this->automationRules // Pass as automationRules to view
        ]);
    }
}
