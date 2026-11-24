<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class Afiliacion extends Model
{
    protected $table = 'afiliaciones';

    protected $fillable = ['mascota_id', 'plan_id', 'cobertura_grave', 'modalidad', 'fecha_inicio', 'fecha_fin', 'estado',
        'veterinaria_id', 'contrato_numero', 'contrato_at', 'permanencia_hasta', 'carencia_hasta'];

    protected function casts(): array
    {
        return ['fecha_inicio' => 'date', 'fecha_fin' => 'date', 'cobertura_grave' => 'boolean', 'contrato_at' => 'datetime',
            'permanencia_hasta' => 'date', 'carencia_hasta' => 'date'];
    }

    /** Veterinaria elegida: el plan solo cubre ahí. */
    public function veterinaria(): BelongsTo
    {
        return $this->belongsTo(Veterinaria::class);
    }

    /** Periodo de carencia: el plan todavía no se puede usar (60 días desde el contrato). */
    public function enCarencia(?Carbon $fecha = null): bool
    {
        return $this->carencia_hasta && ($fecha ?? Carbon::today())->lt($this->carencia_hasta);
    }

    /** Permanencia mínima: no se puede cancelar antes de esta fecha. */
    public function puedeCancelar(): bool
    {
        return ! $this->permanencia_hasta || Carbon::today()->gte($this->permanencia_hasta);
    }

    /** Meses del contrato mínimo que vencieron sin pagarse (deuda). */
    public function mesesAdeudados(): int
    {
        if (! $this->permanencia_hasta || $this->estado !== 'activo' || $this->fecha_fin->gte($this->permanencia_hasta)) {
            return 0;
        }
        $hasta = Carbon::today()->lt($this->permanencia_hasta) ? Carbon::today() : $this->permanencia_hasta;
        if ($hasta->lte($this->fecha_fin)) {
            return 0;
        }

        return (int) ceil($this->fecha_fin->diffInDays($hasta) / 30);
    }

    /** Estado de la afiliación para las listas: [texto, clase] */
    public function estadoTexto(): array
    {
        if ($this->estado !== 'activo') {
            return [ucfirst($this->estado), 'mut'];
        }
        $d = $this->diasRestantes();
        if ($d < 0) {
            return ['Vencido', 'bad'];
        }
        if ($this->porVencer()) {
            return ["Vence en {$d} ".($d === 1 ? 'día' : 'días'), 'warn'];
        }

        return [$this->enCarencia() ? 'En carencia' : 'Vigente', $this->enCarencia() ? 'info' : 'ok'];
    }

    public function mascota(): BelongsTo
    {
        return $this->belongsTo(Mascota::class)->withTrashed();
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /** Activas y con fecha de fin no vencida. */
    public function scopeVigentes(Builder $q): Builder
    {
        return $q->where('afiliaciones.estado', 'activo')->whereDate('afiliaciones.fecha_fin', '>=', Carbon::today());
    }

    public function diasRestantes(): int
    {
        return (int) Carbon::today()->diffInDays($this->fecha_fin, false);
    }

    /** Días desde que la mascota está afiliada (para los periodos de espera). */
    public function diasAfiliada(?Carbon $fecha = null): int
    {
        return (int) $this->fecha_inicio->diffInDays($fecha ?? Carbon::today(), false);
    }

    public function porVencer(): bool
    {
        return $this->diasRestantes() <= \App\Support\Catalogo::DIAS_AVISO_PLAN;
    }
}
