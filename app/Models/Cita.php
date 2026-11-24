<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class Cita extends Model
{
    protected $table = 'citas';

    protected $fillable = ['mascota_id', 'veterinaria_id', 'servicio', 'urgente', 'monto_estimado', 'cubierto_estimado', 'fecha', 'hora', 'estado',
        'nota', 'motivo_rechazo', 'respondida_at'];

    protected function casts(): array
    {
        return ['fecha' => 'date', 'urgente' => 'boolean', 'respondida_at' => 'datetime', 'monto_estimado' => 'float', 'cubierto_estimado' => 'float'];
    }

    public function mascota(): BelongsTo
    {
        return $this->belongsTo(Mascota::class)->withTrashed();
    }

    public function pagos(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Pago::class);
    }

    /** Pago vigente de la cita (pendiente de verificar o aprobado). */
    public function pagoVigente(): ?Pago
    {
        return $this->pagos->whereIn('estado', ['pendiente', 'aprobado'])->sortByDesc('id')->first();
    }

    /** Lo que le toca pagar al dueño según el estimado. */
    public function porPagar(): ?float
    {
        return $this->monto_estimado === null ? null : round((float) $this->monto_estimado - (float) $this->cubierto_estimado, 2);
    }

    public function resena(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Resena::class);
    }

    public function veterinaria(): BelongsTo
    {
        return $this->belongsTo(Veterinaria::class);
    }

    public function scopeAbiertas(Builder $q): Builder
    {
        return $q->whereIn('estado', ['pendiente', 'confirmada']);
    }

    public function scopeProximas(Builder $q): Builder
    {
        return $q->abiertas()->whereDate('fecha', '>=', Carbon::today());
    }

    public function abierta(): bool
    {
        return in_array($this->estado, ['pendiente', 'confirmada'], true);
    }

    public function diasFaltantes(): int
    {
        return (int) Carbon::today()->diffInDays($this->fecha, false);
    }
}
