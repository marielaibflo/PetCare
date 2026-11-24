<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Medicamento, vacuna o insumo del catálogo de una veterinaria, con su precio y cobertura. */
class Producto extends Model
{
    protected $table = 'productos';

    protected $fillable = ['veterinaria_id', 'nombre', 'descripcion', 'foto', 'categoria', 'precio', 'cubre_seguro', 'planes', 'activo', 'stock', 'en_tienda'];

    protected function casts(): array
    {
        return ['precio' => 'float', 'cubre_seguro' => 'boolean', 'planes' => 'array', 'activo' => 'boolean', 'stock' => 'integer', 'en_tienda' => 'boolean'];
    }

    public function veterinaria(): BelongsTo
    {
        return $this->belongsTo(Veterinaria::class);
    }

    public function scopeActivos(Builder $q): Builder
    {
        return $q->where('activo', true);
    }

    /** La clínica marcó el producto como cubierto para ese plan (sin planes elegidos = todos los planes). */
    public function aplicaA(?Plan $plan): bool
    {
        return $plan && $this->cubre_seguro && (empty($this->planes) || in_array($plan->id, array_map('intval', $this->planes), true));
    }
}
