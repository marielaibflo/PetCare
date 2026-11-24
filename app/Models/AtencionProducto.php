<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Producto usado en una atención (queda con el precio del día). */
class AtencionProducto extends Model
{
    protected $table = 'atencion_productos';

    protected $fillable = ['atencion_id', 'producto_id', 'nombre', 'cantidad', 'precio_unitario', 'subtotal', 'cubierto'];

    protected function casts(): array
    {
        return ['cantidad' => 'float', 'precio_unitario' => 'float', 'subtotal' => 'float', 'cubierto' => 'boolean'];
    }

    public function atencion(): BelongsTo
    {
        return $this->belongsTo(Atencion::class);
    }
}
