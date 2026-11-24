<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    protected $table = 'planes';

    protected $fillable = ['nombre', 'descripcion', 'precio', 'porcentaje', 'tope_mensual', 'tope_anual', 'limite_atenciones', 'espera_cirugia_dias',
        'medicamentos', 'permite_grave', 'cobertura', 'beneficios', 'icono', 'activo', 'vigente_desde', 'vigente_hasta', 'precio_anual',
        'edad_min_meses', 'edad_max_anios', 'especies', 'color', 'permanencia_meses', 'carencia_dias'];

    /** Colores para la tarjeta del plan. */
    public const COLORES = ['violeta' => '#5B3FD6', 'verde' => '#0E9F6E', 'azul' => '#2463C9', 'naranja' => '#C2410C', 'dorado' => '#B7791F', 'rosa' => '#C026D3'];

    public const ICONOS = ['shield' => 'Escudo', 'steth' => 'Estetoscopio', 'crown' => 'Corona', 'heart' => 'Corazón', 'pets' => 'Huella'];

    protected function casts(): array
    {
        return ['cobertura' => 'array', 'beneficios' => 'array', 'activo' => 'boolean', 'precio' => 'float', 'porcentaje' => 'integer',
            'tope_mensual' => 'float', 'tope_anual' => 'float', 'limite_atenciones' => 'integer', 'espera_cirugia_dias' => 'integer', 'permite_grave' => 'boolean',
            'vigente_desde' => 'date', 'vigente_hasta' => 'date', 'precio_anual' => 'float', 'edad_min_meses' => 'integer', 'edad_max_anios' => 'integer',
            'especies' => 'array', 'permanencia_meses' => 'integer', 'carencia_dias' => 'integer'];
    }

    public function afiliaciones(): HasMany
    {
        return $this->hasMany(Afiliacion::class);
    }

    public function afiliacionesVigentes(): HasMany
    {
        return $this->afiliaciones()->vigentes();
    }

    /** Se ofrece a clientes nuevos: activo y dentro de su vigencia. */
    public function scopeActivos(Builder $q): Builder
    {
        $hoy = \Illuminate\Support\Carbon::today();

        return $q->where('activo', true)
            ->where(fn ($w) => $w->whereNull('vigente_desde')->orWhereDate('vigente_desde', '<=', $hoy))
            ->where(fn ($w) => $w->whereNull('vigente_hasta')->orWhereDate('vigente_hasta', '>=', $hoy));
    }

    /** Precio del pago anual (si no se definió, 12 meses pagando 10). */
    public function precioAnual(): float
    {
        return (float) ($this->precio_anual ?: $this->precio * 10);
    }

    public function colorHex(): string
    {
        return self::COLORES[$this->color] ?? self::COLORES['violeta'];
    }

    /** Estado de la vigencia: [texto, clase de chip] */
    public function vigencia(): array
    {
        $hoy = \Illuminate\Support\Carbon::today();
        if (! $this->activo) {
            return ['Inactivo', 'mut'];
        }
        if ($this->vigente_desde && $this->vigente_desde->gt($hoy)) {
            return ['Empieza el '.$this->vigente_desde->format('d/m/Y'), 'info'];
        }
        if ($this->vigente_hasta && $this->vigente_hasta->lt($hoy)) {
            return ['Vencido', 'bad'];
        }
        if ($this->vigente_hasta && $hoy->diffInDays($this->vigente_hasta) <= 30) {
            $d = (int) $hoy->diffInDays($this->vigente_hasta);

            return [$d === 0 ? 'Vence hoy' : "Vence en {$d} ".($d === 1 ? 'día' : 'días'), 'warn'];
        }

        return ['Activo', 'ok'];
    }

    /** ¿La mascota puede contratar este plan? Devuelve el motivo si no puede. */
    public function motivoNoAdmite(Mascota $m): ?string
    {
        if (! empty($this->especies) && ! in_array($m->especie, $this->especies, true)
            && ! (in_array('Otro', $this->especies, true) && ! in_array($m->especie, ['Perro', 'Gato'], true))) {
            return "El {$this->nombre} es solo para: ".implode(', ', $this->especies).'.';
        }
        $meses = (int) $m->fecha_nacimiento->diffInMonths(\Illuminate\Support\Carbon::today());
        if ($meses < $this->edad_min_meses) {
            return "{$m->nombre} debe tener al menos {$this->edad_min_meses} meses para este plan.";
        }
        if ($this->edad_max_anios && $meses >= $this->edad_max_anios * 12) {
            return "Este plan admite mascotas de hasta {$this->edad_max_anios} años.";
        }

        return null;
    }

    public function cubre(string $servicio): bool
    {
        return in_array($servicio, $this->cobertura ?? [], true);
    }
}
