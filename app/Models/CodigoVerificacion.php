<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CodigoVerificacion extends Model
{
    protected $table = 'codigos_verificacion';

    protected $fillable = ['user_id', 'canal', 'destino', 'codigo', 'intentos', 'expira_at', 'usado_at'];

    protected $hidden = ['codigo'];

    protected function casts(): array
    {
        return ['expira_at' => 'datetime', 'usado_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
