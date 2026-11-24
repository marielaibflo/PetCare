<?php

namespace App\Services;

use App\Models\Mascota;
use App\Models\Pago;
use App\Models\Plan;
use App\Models\User;
use App\Models\Veterinaria;
use App\Support\Archivos;
use App\Support\Catalogo;
use App\Support\ComprobantePdf;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Pagos por QR coordinados por el WhatsApp de PetCare.
 * Flujo: el cliente llena sus datos → se abre WhatsApp con un mensaje pregrabado → PetCare cobra con QR,
 * verifica el dinero en su cuenta y aprueba el pago en el sistema →
 * se activa el plan o la suscripción → se envía el comprobante en PDF por correo y un aviso por WhatsApp.
 */
class PagoService
{
    use ReglaNegocio;

    public function __construct(private PlanService $planes, private WhatsAppService $whatsapp) {}

    public function solicitarPlan(User $dueno, Mascota $mascota, Plan $plan, array $datos, ?UploadedFile $comprobante): Pago
    {
        $grave = (bool) ($datos['cobertura_grave'] ?? false);
        $vet = isset($datos['veterinaria_id']) ? Veterinaria::find($datos['veterinaria_id']) : null;
        $this->planes->validarSolicitud($mascota, $plan, $grave, $vet);
        $this->exigir(! Pago::pendientes()->where('concepto', 'plan')->where('mascota_id', $mascota->id)->exists(),
            "Ya hay un pago pendiente de verificación para {$mascota->nombre}. Espera la confirmación del administrador.");

        $pago = $this->crear($dueno, $datos, $comprobante, [
            'concepto' => 'plan', 'mascota_id' => $mascota->id, 'plan_id' => $plan->id, 'cobertura_grave' => $grave,
            'afil_veterinaria_id' => $vet?->id, 'contrato_at' => ! empty($datos['contrato']) ? now() : null,
            'modalidad' => $datos['modalidad'], 'meses' => Catalogo::MODALIDADES[$datos['modalidad']]['meses'],
            'monto' => $this->planes->monto($plan, $datos['modalidad'], $grave),
        ]);
        return $pago;
    }

    public function solicitarSuscripcion(User $usuario, Veterinaria $vet, array $datos, ?UploadedFile $comprobante): Pago
    {
        $this->exigir(! Pago::pendientes()->where('concepto', 'suscripcion')->where('veterinaria_id', $vet->id)->exists(), 'Ya hay un pago de suscripción pendiente de verificación.');
        $meses = $datos['meses'] == 12 ? 12 : 1;
        $cobra = $meses === 12 ? 10 : 1;

        return $this->crear($usuario, $datos, $comprobante, [
            'concepto' => 'suscripcion', 'veterinaria_id' => $vet->id, 'plan_suscripcion' => $datos['plan_suscripcion'],
            'meses' => $meses, 'modalidad' => $meses === 12 ? 'anual' : 'mensual',
            'monto' => Catalogo::SUSCRIPCIONES[$datos['plan_suscripcion']]['precio'] * $cobra,
        ]);
    }

    /**
     * Pago de una cita: el dueño paga a la veterinaria la parte que no cubre su plan.
     * Lo verifica la propia veterinaria (comprobante de QR, tarjeta simulada o WhatsApp).
     */
    public function solicitarCita(User $dueno, \App\Models\Cita $cita, array $datos, ?UploadedFile $comprobante): Pago
    {
        $this->exigir($cita->abierta(), 'Solo se pueden pagar citas pendientes o confirmadas.');
        $monto = round((float) $cita->monto_estimado - (float) $cita->cubierto_estimado, 2);
        $this->exigir($cita->monto_estimado !== null, 'La clínica aún no publicó el precio de este servicio: paga en la clínica.');
        $this->exigir($monto > 0, 'Tu plan cubre todo el servicio: no tienes nada que pagar.');
        $this->exigir(! Pago::where('cita_id', $cita->id)->whereIn('estado', ['pendiente', 'aprobado'])->exists(), 'Esta cita ya tiene un pago registrado.');

        $pago = $this->crear($dueno, $datos + ['facturar_a' => $dueno->name, 'documento' => null], $comprobante, [
            'concepto' => 'cita', 'cita_id' => $cita->id, 'mascota_id' => $cita->mascota_id, 'veterinaria_id' => $cita->veterinaria_id,
            'meses' => 0, 'monto' => $monto,
        ]);
        $this->whatsapp->enviarTexto($cita->veterinaria->whatsappInternacional(),
            "PetCare 💳 Pago de cita N.º {$cita->id}: Bs ".number_format($monto, 2, ',', '.')." ({$pago->metodo}) de {$dueno->name}. Verifícalo en tu agenda.");

        return $pago;
    }

    /** Pago de un pedido de la tienda: lo verifica la clínica que vende. */
    public function solicitarPedido(User $dueno, \App\Models\Pedido $pedido, array $datos, ?UploadedFile $comprobante): Pago
    {
        return $this->crear($dueno, $datos + ['facturar_a' => $datos['facturar_a'] ?? $dueno->name, 'documento' => $datos['documento'] ?? null], $comprobante, [
            'concepto' => 'pedido', 'pedido_id' => $pedido->id, 'veterinaria_id' => $pedido->veterinaria_id, 'mascota_id' => $pedido->mascota_id,
            'meses' => 0, 'monto' => $pedido->total,
        ]);
    }

    /** La veterinaria confirma o rechaza el pago de una cita. */
    public function revisarCita(Pago $pago, User $vet, bool $aprobar, ?string $motivo = null): void
    {
        $this->exigir($pago->concepto === 'cita' && $pago->estado === 'pendiente', 'Este pago ya fue revisado.');
        $pago->update(['estado' => $aprobar ? 'aprobado' : 'rechazado', 'motivo_rechazo' => $aprobar ? null : $motivo,
            'revisado_por' => $vet->id, 'revisado_at' => now(), 'numero' => $aprobar ? 'CT-'.now()->format('Y').'-'.str_pad((string) $pago->id, 6, '0', STR_PAD_LEFT) : null]);
        $cita = $pago->cita;
        if ($aprobar && $cita->estado === 'pendiente') {
            app(CitaService::class)->confirmar($cita);
        }
        $this->whatsapp->enviarTexto($pago->user->telefonoInternacional(), $aprobar
            ? "PetCare ✅ Pago confirmado: Bs ".number_format($pago->monto, 2, ',', '.')." · cita de {$cita->mascota->nombre} {$cita->fecha->format('d/m')} {$cita->hora}."
            : "PetCare ❌ Pago no verificado (cita de {$cita->mascota->nombre}). Motivo: {$motivo}. Vuelve a pagar en PetCare.", $pago->user);
    }

    /** Crea la solicitud de pago con el método elegido: tarjeta (simulada), QR con comprobante o WhatsApp. */
    private function crear(User $u, array $datos, ?UploadedFile $comprobante, array $extra): Pago
    {
        $metodo = $datos['metodo'] ?? 'whatsapp';
        $tarjeta = $metodo === 'tarjeta' ? substr(preg_replace('/\D/', '', (string) $datos['tarjeta_numero']), -4) : null;

        return Pago::create($extra + [
            'user_id' => $u->id, 'metodo' => $metodo, 'tarjeta_ultimos' => $tarjeta,
            'tarjeta_marca' => $tarjeta ? $datos['tarjeta_marca'] : null,
            'titular' => $tarjeta ? mb_strtoupper(trim($datos['tarjeta_nombre'])) : null,
            'comprobante' => $comprobante ? Archivos::guardar($comprobante, 'comprobantes') : null,
            'facturar_a' => $datos['facturar_a'], 'documento' => $datos['documento'], 'estado' => 'pendiente',
            'terminos_at' => ! empty($datos['acepto']) ? now() : null,
        ]);
    }

    /** El administrador verificó el pago: se activa lo comprado y se envía el comprobante. */
    public function aprobar(Pago $pago, User $admin): Pago
    {
        $this->exigir($pago->estado === 'pendiente', 'Este pago ya fue revisado.');
        $this->exigir(! $pago->loVerificaLaClinica(), 'Los pagos de citas y pedidos los verifica la veterinaria.');

        DB::transaction(function () use ($pago, $admin) {
            $pago->update(['estado' => 'aprobado', 'revisado_por' => $admin->id, 'revisado_at' => now(),
                'numero' => 'PC-'.now()->format('Y').'-'.str_pad((string) $pago->id, 6, '0', STR_PAD_LEFT)]);
            if ($pago->concepto === 'plan') {
                $this->planes->activar($pago->mascota, $pago->plan, $pago->modalidad, $pago->cobertura_grave, $pago->afil_veterinaria_id, $pago->contrato_at);
            } else {
                $vet = $pago->veterinaria;
                $desde = $vet->suscripcion_hasta && $vet->suscripcion_hasta->gte(Carbon::today()) ? $vet->suscripcion_hasta->copy() : Carbon::today();
                $vet->update(['plan_suscripcion' => $pago->plan_suscripcion, 'suscripcion_hasta' => $desde->addMonths($pago->meses), 'aviso_renovacion_at' => null]);
            }
        });
        $pago->refresh()->load(['user', 'mascota.afiliacionActiva.plan', 'plan', 'veterinaria', 'revisor']);

        $this->enviarComprobante($pago);
        $msg = $pago->concepto === 'plan'
            ? "PetCare ✅ {$pago->plan->nombre} activo para {$pago->mascota->nombre} hasta el "
                .$pago->mascota->afiliacionActiva->fecha_fin->format('d/m/Y').". Factura {$pago->numero} en tu correo."
            : "PetCare ✅ Suscripción de {$pago->veterinaria->nombre} pagada (Bs ".number_format($pago->monto, 0, ',', '.').'), vigente hasta el '
                .$pago->veterinaria->suscripcion_hasta->format('d/m/Y').". Factura {$pago->numero} en tu correo.";
        $this->whatsapp->enviarTexto($pago->user->telefonoInternacional() ?? $pago->veterinaria?->whatsappInternacional(), $msg, $pago->user);

        return $pago;
    }

    public function rechazar(Pago $pago, User $admin, string $motivo): void
    {
        $this->exigir($pago->estado === 'pendiente', 'Este pago ya fue revisado.');
        $pago->update(['estado' => 'rechazado', 'motivo_rechazo' => $motivo, 'revisado_por' => $admin->id, 'revisado_at' => now()]);
        $this->whatsapp->enviarTexto($pago->user->telefonoInternacional() ?? $pago->veterinaria?->whatsappInternacional(),
            "PetCare ❌ Pago de Bs ".number_format($pago->monto, 2, ',', '.')." no verificado. Motivo: {$motivo}. Envíalo de nuevo en PetCare.", $pago->user);
    }

    public function enviarComprobante(Pago $pago): void
    {
        try {
            $pdf = ComprobantePdf::generar($pago);
            $cuerpo = "Hola {$pago->user->primerNombre()},\n\nVerificamos tu pago y ya está activo:\n\n  {$pago->detalle()}\n  Monto: Bs "
                .number_format($pago->monto, 2, ',', '.')."\n  Factura: {$pago->numero}\n\n"
                ."Adjuntamos la factura en PDF. También puedes descargarla desde PetCare.\n\nGracias por confiar en PetCare 🐾";
            $afil = $pago->concepto === 'plan' ? $pago->mascota?->afiliacionActiva : null;
            $contrato = $afil?->contrato_numero ? \App\Support\ContratoPdf::generar($afil) : null;
            if ($contrato) {
                $cuerpo = str_replace('Adjuntamos la factura en PDF.', "Adjuntamos la factura y el contrato de afiliación {$afil->contrato_numero} en PDF.", $cuerpo);
            }
            Mail::raw($cuerpo, function ($m) use ($pago, $pdf, $contrato, $afil) {
                $m->to($pago->user->email, $pago->user->name)
                    ->subject("PetCare · Pago confirmado · Factura {$pago->numero}")
                    ->attachData($pdf, "Factura-{$pago->numero}.pdf", ['mime' => 'application/pdf']);
                if ($contrato) {
                    $m->attachData($contrato, "Contrato-{$afil->contrato_numero}.pdf", ['mime' => 'application/pdf']);
                }
            });
        } catch (Throwable $e) {
            Log::warning("[Correo] No se pudo enviar el comprobante {$pago->numero}: {$e->getMessage()}");
        }
    }

    /** Validación del número de tarjeta (algoritmo de Luhn). */
    public static function luhn(string $num): bool
    {
        return \App\Support\ReglasPago::luhn($num);
    }
}
