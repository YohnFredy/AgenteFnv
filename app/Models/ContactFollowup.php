<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContactFollowup extends Model
{
    protected $fillable = [
        'chat_id',
        'campaign_id',
        'scheduled_at',
        'last_interaction_at',
        'status',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'last_interaction_at' => 'datetime',
    ];

    public function campaign()
    {
        return $this->belongsTo(FollowupCampaign::class);
    }

    public function lead()
    {
        return $this->belongsTo(Chat::class);
    }
    public function chat()
    {
        return $this->belongsTo(Chat::class);
    }
}
