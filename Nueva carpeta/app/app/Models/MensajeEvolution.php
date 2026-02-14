<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MensajeEvolution extends Model
{
    protected $fillable = ['payload'];

    // Casteamos el payload a array automáticamente al leerlo
    protected function casts(): array
    {
        return [
            'payload' => 'array',
        ];
    }
}
