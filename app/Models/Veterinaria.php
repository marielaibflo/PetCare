<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Veterinaria extends Model
{
    protected $table = 'veterinarias';

    protected $fillable = ['nombre', 'zona', 'direccion', 'telefono', 'whatsapp_pais', 'whatsapp', 'horario', 'servicios', 'valoracion', 'resenas', 'activa', 'color',
        'precios', 'plan_suscripcion', 'suscripcion_hasta', 'estado', 'nit', 'documento', 'motivo_rechazo', 'terminos_at',
        'registro', 'logo', 'qr_cobro', 'descripcion', 'servicios_especiales', 'metodos_pago', 'promocion', 'mapa_url', 'cobro_metodo', 'cobro_marca', 'cobro_ultimos', 'cobro_titular', 'cobro_vence', 'aviso_renovacion_at', 'admin_avisado_at',
        'aprobada_at', 'envio_activo', 'envio_costo', 'envio_zonas', 'suscripcion_cancelada_at', 'motivo_cancelacion', 'baja_at'];

    public const SERVICIOS_ESPECIALES = ['Rayos X', 'Ecografía', 'Laboratorio', 'Internación', 'Atención 24 horas', 'Atención a domicilio', 'Peluquería', 'Hotel / guardería'];

    public const METODOS_PAGO = ['QR', 'Efectivo', 'Tarjeta', 'Transferencia'];

    protected function casts(): array
    {
        return ['servicios' => 'array', 'activa' => 'boolean', 'valoracion' => 'float', 'suscripcion_hasta' => 'date', 'aviso_renovacion_at' => 'datetime', 'admin_avisado_at' => 'datetime',
            'precios' => 'array', 'terminos_at' => 'datetime', 'aprobada_at' => 'date', 'envio_activo' => 'boolean', 'envio_costo' => 'float', 'suscripcion_cancelada_at' => 'datetime', 'baja_at' => 'datetime',
            'servicios_especiales' => 'array', 'metodos_pago' => 'array'];
    }

    public function personal(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function citas(): HasMany
    {
        return $this->hasMany(Cita::class);
    }

    public function atenciones(): HasMany
    {
        return $this->hasMany(Atencion::class);
    }

    public function fotos(): HasMany
    {
        return $this->hasMany(VeterinariaFoto::class);
    }

    public function resenasLista(): HasMany
    {
        return $this->hasMany(Resena::class);
    }

    /** WhatsApp al que se mandan las citas (número de muestra mientras la clínica no tenga el real). */
    public function whatsappCitas(): ?string
    {
        // El WhatsApp que registró la clínica; solo si no tiene, el número de demostración de la configuración.
        return $this->whatsappInternacional() ?: (config('petcare.whatsapp_veterinarias_demo') ?: null);
    }

    /** Enlace de Google Maps: el que cargó la clínica o una búsqueda por su dirección. */
    public function enlaceMapa(): string
    {
        return $this->mapa_url ?: 'https://www.google.com/maps/search/?api=1&query='.rawurlencode($this->busquedaMapa());
    }

    public function mapaEmbebido(): string
    {
        return 'https://maps.google.com/maps?q='.rawurlencode($this->busquedaMapa()).'&z=16&output=embed';
    }

    private function busquedaMapa(): string
    {
        return trim($this->nombre.', '.$this->direccion.', '.$this->zona.', La Paz, Bolivia', ', ');
    }

    /** Siglas para el sello de la cartilla cuando la clínica aún no subió su logo. */
    public function siglas(): string
    {
        return collect(preg_split('/\s+/', preg_replace('/\b(de|la|el|y|del)\b/iu', '', $this->nombre)))->filter()
            ->take(2)->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->join('');
    }

    /** Recalcula la calificación promedio con las reseñas registradas. */
    public function recalcularCalificacion(): void
    {
        $q = $this->resenasLista();
        $this->update(['valoracion' => round((float) $q->avg('estrellas'), 1), 'resenas' => $q->count()]);
    }

    public function productos(): HasMany
    {
        return $this->hasMany(Producto::class);
    }

    /** Visibles para los dueños: activas y con la suscripción al día. */
    public function scopeActivas(Builder $q): Builder
    {
        return $q->where('activa', true)->where(fn ($w) => $w->whereNull('suscripcion_hasta')->orWhereDate('suscripcion_hasta', '>=', now()->toDateString()));
    }

    public function precio(string $servicio): ?float
    {
        $p = $this->precios[$servicio] ?? null;

        return $p === null || $p === '' ? null : (float) $p;
    }

    public function suscripcion(): array
    {
        return \App\Support\Catalogo::SUSCRIPCIONES[$this->plan_suscripcion] ?? \App\Support\Catalogo::SUSCRIPCIONES['profesional'];
    }

    /** Solo las suscripciones Profesional y Plus atienden mascotas con plan PetCare. */
    public function atiendePlanes(): bool
    {
        return $this->suscripcion()['atiende_planes'];
    }

    /** Celular de WhatsApp de la clínica en formato internacional (59171234567). */
    public function whatsappInternacional(): ?string
    {
        return \App\Support\Paises::internacional($this->whatsapp_pais, $this->whatsapp);
    }

    /** La clínica tiene su suscripción a PetCare al día (sin fecha = cuenta antigua o creada por el administrador). */
    public function suscripcionVigente(): bool
    {
        return ! $this->suscripcion_hasta || $this->suscripcion_hasta->gte(\Illuminate\Support\Carbon::today());
    }

    /** Texto de la forma de cobro registrada: «Visa •••• 4242», «QR cada mes»… */
    public function cobroTexto(): string
    {
        return match ($this->cobro_metodo) {
            'tarjeta' => trim((\App\Support\ReglasPago::MARCAS[$this->cobro_marca]['nombre'] ?? 'Tarjeta').' •••• '.$this->cobro_ultimos),
            'qr' => 'QR desde el banco, cada mes',
            'whatsapp' => 'QR por WhatsApp, cada mes',
            default => 'Sin forma de pago registrada',
        };
    }

    /** La tarjeta registrada sigue vigente (MM/AA). */
    public function tarjetaVigente(): bool
    {
        if ($this->cobro_metodo !== 'tarjeta' || ! preg_match('/^(\d{2})\/(\d{2})$/', (string) $this->cobro_vence, $m)) {
            return false;
        }

        return now()->format('ym') <= $m[2].$m[1];
    }

    /** Período actual de la suscripción y su estado: [desde, hasta, texto, clase]. */
    public function periodoSuscripcion(): array
    {
        $hasta = $this->suscripcion_hasta;
        $prueba = $this->enPrueba();
        $ultimo = $prueba ? null : \App\Models\Pago::where('veterinaria_id', $this->id)->where('concepto', 'suscripcion')->where('estado', 'aprobado')->latest('revisado_at')->value('revisado_at');
        $desde = $ultimo ? \Illuminate\Support\Carbon::parse($ultimo)->startOfDay() : ($this->aprobada_at ?? $this->created_at?->copy()->startOfDay());
        $hoy = \Illuminate\Support\Carbon::today();
        if (! $hasta) {
            return [$desde, null, 'Sin vencimiento', 'mut'];
        }
        $d = (int) $hoy->diffInDays($hasta, false);
        [$t, $c] = match (true) {
            $d < 0 => ['Vencida', 'bad'],
            $d <= 5 => [$d === 0 ? 'Vence hoy' : "Vence en {$d} ".($d === 1 ? 'día' : 'días'), 'warn'],
            $prueba => ['Mes gratis', 'info'],
            default => ['Al día', 'ok'],
        };

        return [$desde, $hasta, $t, $c];
    }

    public function enPrueba(): bool
    {
        return ! \App\Models\Pago::where('veterinaria_id', $this->id)->where('concepto', 'suscripcion')->where('estado', 'aprobado')->exists();
    }

    /** Límite de productos a la venta según la suscripción (null = ilimitados). */
    public function limiteProductos(): ?int
    {
        return $this->suscripcion()['productos'];
    }

    /** Productos que cuentan para el límite: activos, en la tienda y con stock. */
    public function productosEnVentaQuery()
    {
        return $this->productos()->where('activo', true)->where('en_tienda', true)->where('stock', '>', 0);
    }

    /**
     * IDs de los productos visibles en la tienda: si la clínica bajó de suscripción y tiene más de los permitidos,
     * se muestran los más recientes hasta el límite; el resto queda oculto (no se borra).
     */
    public function idsVisiblesEnTienda(): array
    {
        $q = $this->productosEnVentaQuery()->latest('id');
        if ($l = $this->limiteProductos()) {
            $q->limit($l);
        }

        return $q->pluck('id')->all();
    }

    public function ofrece(string $servicio): bool
    {
        return in_array($servicio, $this->servicios ?? [], true);
    }
}
