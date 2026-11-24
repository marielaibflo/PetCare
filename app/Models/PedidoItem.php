<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PedidoItem extends Model
{
    protected $table = 'pedido_items';

    public $timestamps = false;

    protected $fillable = ['pedido_id', 'producto_id', 'nombre', 'categoria', 'cantidad', 'precio', 'descuento', 'subtotal'];

    protected function casts(): array
    {
        return ['precio' => 'float', 'descuento' => 'float', 'subtotal' => 'float', 'cantidad' => 'integer'];
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }
}
