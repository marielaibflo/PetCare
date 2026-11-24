<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Calificación (1 a 5 estrellas) que deja un propietario después de una cita atendida. */
class Resena extends Model
{
    protected $table = 'resenas';

    protected $fillable = ['veterinaria_id', 'user_id', 'cita_id', 'estrellas', 'comentario'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function veterinaria(): BelongsTo
    {
        return $this->belongsTo(Veterinaria::class);
    }

    public function cita(): BelongsTo
    {
        return $this->belongsTo(Cita::class);
    }
}
