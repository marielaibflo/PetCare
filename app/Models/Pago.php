<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pago de un plan (dueño), de una suscripción (veterinaria) o de una cita (dueño → veterinaria).
 * Los planes y suscripciones los verifica el administrador; las citas, la propia veterinaria.
 */
class Pago extends Model
{
    protected $table = 'pagos';

    protected $fillable = ['numero', 'user_id', 'concepto', 'mascota_id', 'plan_id', 'cobertura_grave', 'modalidad', 'veterinaria_id',
        'plan_suscripcion', 'meses', 'monto', 'metodo', 'comprobante', 'tarjeta_ultimos', 'tarjeta_marca', 'titular', 'cita_id', 'terminos_at', 'facturar_a', 'documento', 'estado',
        'motivo_rechazo', 'revisado_por', 'revisado_at', 'pedido_id', 'afil_veterinaria_id', 'contrato_at'];

    public const ESTADOS = ['pendiente' => 'warn', 'aprobado' => 'ok', 'rechazado' => 'bad'];

    protected function casts(): array
    {
        return ['monto' => 'float', 'cobertura_grave' => 'boolean', 'revisado_at' => 'datetime', 'terminos_at' => 'datetime', 'contrato_at' => 'datetime'];
    }

    public function cita(): BelongsTo
    {
        return $this->belongsTo(Cita::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function mascota(): BelongsTo
    {
        return $this->belongsTo(Mascota::class)->withTrashed();
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class);
    }

    /** Veterinaria elegida para el plan (contrato). */
    public function afilVeterinaria(): BelongsTo
    {
        return $this->belongsTo(Veterinaria::class, 'afil_veterinaria_id');
    }

    /** Lo verifica la veterinaria (citas y pedidos de la tienda); el resto, PetCare. */
    public function loVerificaLaClinica(): bool
    {
        return in_array($this->concepto, ['cita', 'pedido'], true);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function veterinaria(): BelongsTo
    {
        return $this->belongsTo(Veterinaria::class);
    }

    public function revisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revisado_por');
    }

    /** Pagos que verifica el administrador (planes y suscripciones); los de citas los verifica cada clínica. */
    public function scopeDePetcare(Builder $q): Builder
    {
        return $q->whereIn('concepto', ['plan', 'suscripcion']);
    }

    public function scopePendientes(Builder $q): Builder
    {
        return $q->where('estado', 'pendiente');
    }

    /** Mensaje corto y directo para WhatsApp (a PetCare o, si es una cita, a la veterinaria). */
    public function mensajeWhatsapp(): string
    {
        $monto = 'Bs '.number_format($this->monto, 2, ',', '.');
        $que = match ($this->concepto) {
            'suscripcion' => 'suscripción '.(\App\Support\Catalogo::SUSCRIPCIONES[$this->plan_suscripcion]['nombre'] ?? '').' de '.$this->veterinaria?->nombre,
            'cita' => 'cita N.º '.$this->cita_id.' ('.$this->mascota?->nombre.')',
            'pedido' => 'pedido '.$this->pedido?->numero.' de la tienda',
            default => $this->detallePlan().' de '.$this->mascota?->nombre,
        };
        $hola = 'Hola,';

        return match ($this->metodo) {
            'tarjeta' => "{$hola} pagué {$monto} con tarjeta •••• {$this->tarjeta_ultimos}: {$que}. Pago N.º {$this->id}.",
            'qr' => "{$hola} pagué {$monto} por QR: {$que}. Comprobante subido. Pago N.º {$this->id}.",
            default => "{$hola} quiero pagar {$monto}: {$que}. ¿Me envían el QR? Pago N.º {$this->id}.",
        };
    }

    public function enlaceWhatsapp(): ?string
    {
        $numero = $this->loVerificaLaClinica() ? $this->veterinaria?->whatsappCitas() : config('petcare.whatsapp_petcare');

        return \App\Services\WhatsAppService::enlace($numero, $this->mensajeWhatsapp());
    }

    public function marcaTexto(): string
    {
        return \App\Support\ReglasPago::MARCAS[$this->tarjeta_marca]['nombre'] ?? '';
    }

    public function metodoTexto(): string
    {
        return match ($this->metodo) {
            'tarjeta' => trim('Tarjeta '.$this->marcaTexto()).' •••• '.$this->tarjeta_ultimos.' (simulación)',
            'qr' => 'QR con comprobante',
            default => 'QR por WhatsApp',
        };
    }

    private function detallePlan(): string
    {
        $plan = (string) $this->plan?->nombre;

        return (str_starts_with($plan, 'Plan ') ? $plan : 'Plan '.$plan).($this->cobertura_grave ? ' + Cobertura Grave' : '');
    }

    /** Texto del concepto para listas y comprobantes. */
    public function detalle(): string
    {
        if ($this->concepto === 'cita') {
            return "Cita N.º {$this->cita_id} · {$this->cita?->servicio} de {$this->mascota?->nombre} · {$this->veterinaria?->nombre}";
        }
        if ($this->concepto === 'pedido') {
            return "Pedido {$this->pedido?->numero} · tienda de {$this->veterinaria?->nombre}";
        }
        if ($this->concepto === 'suscripcion') {
            $s = \App\Support\Catalogo::SUSCRIPCIONES[$this->plan_suscripcion]['nombre'] ?? $this->plan_suscripcion;

            return "Suscripción {$s} · {$this->veterinaria?->nombre} · {$this->meses} ".($this->meses === 1 ? 'mes' : 'meses');
        }

        $plan = (string) $this->plan?->nombre;
        $plan = str_starts_with($plan, 'Plan ') ? $plan : 'Plan '.$plan;

        return $plan.($this->cobertura_grave ? ' + Cobertura Grave' : '')." · {$this->mascota?->nombre} · "
            .($this->modalidad === 'anual' ? '12 meses (pago anual)' : '1 mes');
    }
}
