<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** Pedido de la tienda: un pedido por clínica, se recoge en la clínica o se envía a domicilio. */
class Pedido extends Model
{
    protected $table = 'pedidos';

    protected $fillable = ['numero', 'user_id', 'veterinaria_id', 'mascota_id', 'entrega', 'direccion', 'referencia', 'subtotal', 'descuento', 'envio', 'total', 'estado', 'nota'];

    /** Estado → [texto, clase del chip] */
    public const ESTADOS = [
        'pendiente_pago' => ['Pago por verificar', 'warn'], 'pagado' => ['Pagado', 'info'], 'preparando' => ['En preparación', 'info'],
        'listo' => ['Listo para recoger', 'ok'], 'en_camino' => ['En camino', 'ok'], 'entregado' => ['Entregado', 'ok'], 'cancelado' => ['Cancelado', 'mut'],
    ];

    public const ABIERTOS = ['pendiente_pago', 'pagado', 'preparando', 'listo', 'en_camino'];

    protected function casts(): array
    {
        return ['subtotal' => 'float', 'descuento' => 'float', 'envio' => 'float', 'total' => 'float'];
    }

    public function items(): HasMany
    {
        return $this->hasMany(PedidoItem::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function veterinaria(): BelongsTo
    {
        return $this->belongsTo(Veterinaria::class);
    }

    public function mascota(): BelongsTo
    {
        return $this->belongsTo(Mascota::class)->withTrashed();
    }

    public function pago(): HasOne
    {
        return $this->hasOne(Pago::class)->latestOfMany();
    }

    public function estadoTexto(): string
    {
        return self::ESTADOS[$this->estado][0] ?? $this->estado;
    }

    public function estadoClase(): string
    {
        return self::ESTADOS[$this->estado][1] ?? 'mut';
    }

    /** Siguiente paso que puede marcar la clínica. */
    public function siguiente(): ?string
    {
        return match ($this->estado) {
            'pagado' => 'preparando',
            'preparando' => $this->entrega === 'domicilio' ? 'en_camino' : 'listo',
            'listo', 'en_camino' => 'entregado',
            default => null,
        };
    }

    /** Pasos del seguimiento según el tipo de entrega. */
    public function pasos(): array
    {
        return ['pendiente_pago', 'pagado', 'preparando', $this->entrega === 'domicilio' ? 'en_camino' : 'listo', 'entregado'];
    }
}
