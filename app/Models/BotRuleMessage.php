<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BotRuleMessage extends Model
{
    protected $fillable = [
        'bot_rule_id',
        'content',
        'delay',
        'sort_order',
    ];

    public function rule(): BelongsTo
    {
        return $this->belongsTo(BotRule::class, 'bot_rule_id');
    }
}
