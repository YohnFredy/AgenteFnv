<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\FollowupCampaign;
use App\Models\Tag;

class AutomationRule extends Model
{
    protected $fillable = [
        'trigger_content',
        'campaign_id',
        'tag_id',
        'remove_tag_id',
        'followup_delay_hours',
        'is_active',
        'match_type'
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function campaign()
    {
        return $this->belongsTo(FollowupCampaign::class);
    }

    public function tag()
    {
        return $this->belongsTo(Tag::class);
    }

    public function removeTag()
    {
        return $this->belongsTo(Tag::class, 'remove_tag_id');
    }
}
