<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MensajeWhatsapp extends Model
{
    protected $table = 'mensajes_whatsapp';

    protected $fillable = ['user_id', 'para', 'mensaje', 'estado'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
