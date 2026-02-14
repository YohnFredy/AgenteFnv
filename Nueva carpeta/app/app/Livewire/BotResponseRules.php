<?php

namespace App\Livewire;

use App\Models\BotRule;
use App\Models\BotRuleMessage;
use Livewire\Component;

class BotResponseRules extends Component
{
    public $rules;
    public $isEditing = false;
    public $editingRuleId = null;

    // Rule Fields
    public $trigger_stage = 0;
    public $next_stage = 0;
    public $keywords = '';
    public $is_active = true;

    // Messages Management
    public $ruleMessages = []; // Arr of ['content' => '', 'delay' => 0]

    protected $rules_validation = [
        'trigger_stage' => 'required|integer|min:0',
        'next_stage' => 'required|integer|min:0',
        'keywords' => 'required|string',
    ];

    public function mount()
    {
        $this->loadRules();
    }

    public function loadRules()
    {
        $this->rules = BotRule::with('messages')->orderBy('trigger_stage')->get();
    }

    public function create()
    {
        $this->resetInput();
        $this->isEditing = true;
        // Default message
        $this->ruleMessages[] = ['content' => '', 'delay' => 1];
    }

    public function edit($id)
    {
        $rule = BotRule::with('messages')->findOrFail($id);
        $this->editingRuleId = $id;
        $this->trigger_stage = $rule->trigger_stage;
        $this->next_stage = $rule->next_stage;
        $this->keywords = $rule->keywords;
        $this->is_active = $rule->is_active;
        
        $this->ruleMessages = $rule->messages->map(function($msg) {
            return ['content' => $msg->content, 'delay' => $msg->delay];
        })->toArray();

        $this->isEditing = true;
    }

    public function save()
    {
        $this->validate($this->rules_validation);

        if ($this->editingRuleId) {
            $rule = BotRule::find($this->editingRuleId);
            $rule->update([
                'trigger_stage' => $this->trigger_stage,
                'next_stage' => $this->next_stage,
                'keywords' => $this->keywords,
                'is_active' => $this->is_active,
            ]);
        } else {
            $rule = BotRule::create([
                'trigger_stage' => $this->trigger_stage,
                'next_stage' => $this->next_stage,
                'keywords' => $this->keywords,
                'is_active' => $this->is_active,
            ]);
        }

        // Sync messages
        $rule->messages()->delete();
        foreach ($this->ruleMessages as $index => $msgData) {
            if (!empty($msgData['content'])) {
                BotRuleMessage::create([
                    'bot_rule_id' => $rule->id,
                    'content' => $msgData['content'],
                    'delay' => $msgData['delay'] ?? 1,
                    'sort_order' => $index
                ]);
            }
        }

        $this->isEditing = false;
        $this->loadRules();
    }

    public function cancel()
    {
        $this->isEditing = false;
        $this->resetInput();
    }

    public function delete($id)
    {
        BotRule::find($id)->delete();
        $this->loadRules();
    }

    public function addMessageField()
    {
        $this->ruleMessages[] = ['content' => '', 'delay' => 1];
    }

    public function removeMessageField($index)
    {
        unset($this->ruleMessages[$index]);
        $this->ruleMessages = array_values($this->ruleMessages);
    }

    private function resetInput()
    {
        $this->editingRuleId = null;
        $this->trigger_stage = 0;
        $this->next_stage = 0;
        $this->keywords = '';
        $this->is_active = true;
        $this->ruleMessages = [];
    }

    public function render()
    {
        return view('livewire.bot-response-rules');
    }
}
