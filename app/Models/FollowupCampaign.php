<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FollowupCampaign extends Model
{
    protected $fillable = [
        'name',
        'description',
        'is_active',
        'trigger_type'
    ];

    public function steps()
    {
        return $this->hasMany(FollowupStep::class, 'campaign_id');
    }

    public function contacts()
    {
        return $this->hasMany(ContactFollowup::class, 'campaign_id');
    }
}
