<?php

namespace App\Services;

use App\Models\Pago;
use App\Models\User;
use App\Models\Veterinaria;
use App\Support\Catalogo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Renovación de las suscripciones de las clínicas (modelo tipo Spotify):
 *  - El primer mes es gratis, pero la forma de pago se registra al afiliarse.
 *  - Con tarjeta: el día del vencimiento se cobra solo el mes siguiente (simulación) y se envía el comprobante.
 *  - Con QR o WhatsApp (o tarjeta vencida): 5 días antes se avisa por correo y WhatsApp para pagar desde el sistema.
 *  - Si vence sin pagar, la clínica deja de aparecer en PetCare hasta que pague.
 * Se ejecuta cada día (php artisan schedule:work → petcare:suscripciones).
 */
class SuscripcionService
{
    public const DIAS_AVISO = 5;

    public function __construct(private PagoService $pagos, private WhatsAppService $whatsapp) {}

    /** @return array{cobradas:int, avisadas:int} */
    public function procesar(?Carbon $hoy = null): array
    {
        $hoy ??= Carbon::today();
        $res = ['cobradas' => 0, 'avisadas' => 0];
        $vets = Veterinaria::where('estado', 'aprobada')->where('activa', true)->whereNull('suscripcion_cancelada_at')->whereNotNull('suscripcion_hasta')
            ->whereDate('suscripcion_hasta', '<=', $hoy->copy()->addDays(self::DIAS_AVISO))->get();

        foreach ($vets as $vet) {
            if (Pago::pendientes()->where('concepto', 'suscripcion')->where('veterinaria_id', $vet->id)->exists()) {
                continue; // ya pagó y espera verificación
            }
            if ($vet->cobro_metodo === 'tarjeta' && $vet->tarjetaVigente()) {
                if ($vet->suscripcion_hasta->lte($hoy)) {
                    $this->cobrarConTarjeta($vet);
                    $res['cobradas']++;
                }

                continue;
            }
            if (! $vet->aviso_renovacion_at) {
                $this->avisar($vet, $hoy);
                $res['avisadas']++;
            }
        }

        return $res;
    }

    /** Cobro automático del mes siguiente con la tarjeta registrada (simulación: no hay pasarela todavía). */
    public function cobrarConTarjeta(Veterinaria $vet): Pago
    {
        $u = $vet->personal()->first();
        $pago = Pago::create([
            'user_id' => $u->id, 'concepto' => 'suscripcion', 'veterinaria_id' => $vet->id, 'plan_suscripcion' => $vet->plan_suscripcion,
            'meses' => 1, 'monto' => Catalogo::SUSCRIPCIONES[$vet->plan_suscripcion]['precio'], 'metodo' => 'tarjeta',
            'tarjeta_marca' => $vet->cobro_marca, 'tarjeta_ultimos' => $vet->cobro_ultimos, 'titular' => $vet->cobro_titular,
            'facturar_a' => $vet->nombre, 'documento' => $vet->nit, 'terminos_at' => $vet->terminos_at ?? now(), 'estado' => 'pendiente',
        ]);
        $sistema = User::where('rol', User::ADMINISTRADOR)->orderBy('id')->first() ?? $u;

        return $this->pagos->aprobar($pago, $sistema);
    }

    private function avisar(Veterinaria $vet, Carbon $hoy): void
    {
        $precio = Catalogo::SUSCRIPCIONES[$vet->plan_suscripcion]['precio'];
        $cuando = $vet->suscripcion_hasta->lt($hoy) ? 'venció el '.$vet->suscripcion_hasta->format('d/m/Y')
            : ($vet->suscripcion_hasta->isSameDay($hoy) ? 'vence hoy' : 'vence el '.$vet->suscripcion_hasta->format('d/m/Y'));
        $motivo = $vet->cobro_metodo === 'tarjeta' ? ' Tu tarjeta registrada está vencida: actualízala o paga con QR.' : '';
        $texto = "La suscripción {$vet->suscripcion()['nombre']} de {$vet->nombre} {$cuando}. Paga Bs {$precio} desde PetCare → Suscripción para seguir apareciendo en el buscador y recibiendo citas.{$motivo}";
        $this->whatsapp->enviarTexto($vet->whatsappInternacional(), "PetCare 🔔 Suscripción de {$vet->nombre}: {$cuando}. Paga Bs {$precio} en PetCare → Suscripción para seguir recibiendo citas.".($motivo ? ' Tu tarjeta está vencida.' : ''));
        $u = $vet->personal()->first();
        if ($u) {
            try {
                Mail::raw("Hola {$u->primerNombre()},\n\n{$texto}\n\n".route('veterinaria.suscripcion')."\n\nEquipo PetCare",
                    fn ($m) => $m->to($u->email, $u->name)->subject("PetCare · Tu suscripción {$cuando}"));
            } catch (Throwable $e) {
                Log::warning("[Correo] No se pudo enviar el aviso de suscripción: {$e->getMessage()}");
            }
        }
        $vet->update(['aviso_renovacion_at' => now()]);
    }

    /** Registros que nunca confirmaron su correo en 48 horas: se borran para liberar el correo. */
    public function limpiarRegistrosSinConfirmar(): int
    {
        $limite = now()->subHours(48);
        $n = 0;
        User::where('rol', User::PROPIETARIO)->whereNull('email_verified_at')->where('created_at', '<', $limite)
            ->whereDoesntHave('mascotas')->get()->each(function (User $u) use (&$n) {
                $u->delete();
                $n++;
            });
        Veterinaria::where('estado', 'pendiente')->where('created_at', '<', $limite)
            ->whereDoesntHave('personal', fn ($q) => $q->whereNotNull('email_verified_at'))->get()->each(function (Veterinaria $v) use (&$n) {
                if ($v->documento) {
                    \Illuminate\Support\Facades\Storage::disk('local')->delete($v->documento);
                }
                $v->personal()->delete();
                $v->delete();
                $n++;
            });

        return $n;
    }
}
