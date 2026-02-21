<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FollowupStep extends Model
{
      protected $fillable = [
        'campaign_id',
        'message_type',
        'message_content',
        'delay',
    ];

    public function campaign()
    {
        return $this->belongsTo(FollowupCampaign::class);
    }
}
