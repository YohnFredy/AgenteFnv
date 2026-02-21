<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Chat extends Model
{
    protected $fillable = ['remote_jid', 'name', 'stage', 'status', 'system_instruction', 'is_active', 'provider'];

    // Un chat tiene muchos mensajes
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    // FUNCIÓN CLAVE: Obtener historial para la IA (últimos 10 mensajes)
    public function getHistoryForAi($limit = 10)
    {
        return $this->messages()
            ->latest()
            ->take($limit)
            ->get()
            ->reverse() // Invertimos para que queden en orden cronológico (antiguo -> nuevo)
            ->map(function ($msg) {
                return [
                    'role' => $msg->role,
                    'content' => $msg->content
                ];
            })
            ->values()
            ->toArray();
    }

    public function tags()
    {
        return $this->belongsToMany(Tag::class, 'lead_tag', 'chat_id', 'tag_id');
    }
}
