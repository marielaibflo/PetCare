<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Foto de las instalaciones de una veterinaria (galería de su página). */
class VeterinariaFoto extends Model
{
    protected $table = 'veterinaria_fotos';

    protected $fillable = ['veterinaria_id', 'ruta', 'titulo'];

    public function veterinaria(): BelongsTo
    {
        return $this->belongsTo(Veterinaria::class);
    }
}
