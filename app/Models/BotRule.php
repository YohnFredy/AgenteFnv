<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BotRule extends Model
{
    protected $fillable = [
        'trigger_stage',
        'next_stage',
        'keywords',
        'is_active',
    ];

    public function messages(): HasMany
    {
        return $this->hasMany(BotRuleMessage::class)->orderBy('sort_order');
    }
}
