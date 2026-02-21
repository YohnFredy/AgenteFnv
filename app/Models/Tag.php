<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tag extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'color',
        'description',
        'is_active'
    ];

    public function leads()
    {
        return $this->belongsToMany(Chat::class, 'lead_tag', 'tag_id', 'chat_id');
    }

    // Alias for clearer usage
    public function chats()
    {
        return $this->leads();
    }
}
