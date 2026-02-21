<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    protected $fillable = ['chat_id', 'role', 'content', 'whatsapp_id', 'media_url', 'media_type', 'media_path', 'type', 'status', 'metadata'];

    public function chat()
    {
        return $this->belongsTo(Chat::class);
    }
}
