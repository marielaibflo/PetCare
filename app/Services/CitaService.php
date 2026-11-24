<?php

namespace App\Services;

use App\Models\Cita;
use App\Models\Mascota;
use App\Models\User;
use App\Models\Veterinaria;
use Illuminate\Support\Carbon;

/**
 * Ciclo de una cita:
 *   pendiente ──aceptar──▶ confirmada ──atender──▶ atendida
 *       │  ▲                   │
 *       │  └─volver a pendiente┤
 *       └──────rechazar────────┴──▶ rechazada (con motivo)
 * El propietario puede cancelar mientras esté pendiente o confirmada.
 * Cada cambio se avisa por WhatsApp al propietario; las solicitudes nuevas también a la clínica.
 */
class CitaService
{
    use ReglaNegocio;

    /** Estados que ya no ocupan el horario. */
    public const LIBERAN_HORARIO = ['cancelada', 'rechazada'];

    public function __construct(private WhatsAppService $whatsapp, private CoberturaService $cobertura) {}

    /**
     * Estimado de lo que pagará el dueño: precio del servicio en esa clínica menos lo que cubre su plan
     * (con topes y periodos de espera). Los medicamentos se suman recién en la atención.
     */
    public function estimar(Mascota $mascota, Veterinaria $vet, string $servicio, ?Carbon $fecha = null): array
    {
        $precio = $vet->precio($servicio);
        if ($precio === null) {
            return ['precio' => null, 'cubierto' => null, 'pagar' => null, 'plan' => $mascota->afiliacionActiva?->plan?->nombre,
                'porcentaje' => $mascota->afiliacionActiva?->plan?->porcentaje, 'notas' => ['La clínica aún no publicó el precio de este servicio.']];
        }
        $c = $this->cobertura->calcular($mascota, $vet, $servicio, $precio, [], $fecha ?? Carbon::today());

        return ['precio' => $precio, 'cubierto' => $c['monto_cubierto'], 'pagar' => $c['paga_dueno'], 'plan' => $c['plan']?->nombre,
            'porcentaje' => $c['porcentaje'], 'notas' => $c['notas']];
    }

    /** Mensaje corto para escribir a la veterinaria por WhatsApp sobre la cita. */
    public function mensajeWhatsapp(Cita $cita): string
    {
        $pagar = $cita->porPagar();

        return 'Hola, cita N.º '.$cita->id.($cita->urgente ? ' URGENTE' : '').": {$cita->mascota->nombre} · {$cita->servicio} · {$cita->fecha->format('d/m')} {$cita->hora}."
            .($pagar > 0 ? ' Pago Bs '.number_format($pagar, 2, ',', '.').', ¿me envían el QR?' : '');
    }

    public function enlaceWhatsapp(Cita $cita): ?string
    {
        return WhatsAppService::enlace($cita->veterinaria->whatsappCitas(), $this->mensajeWhatsapp($cita));
    }

    public function solicitar(Mascota $mascota, Veterinaria $veterinaria, array $datos): Cita
    {
        $fecha = Carbon::parse($datos['fecha'])->startOfDay();
        $this->exigir($veterinaria->activa && $veterinaria->suscripcionVigente(), 'Selecciona una veterinaria afiliada.', 'veterinaria_id');
        $this->exigir(! empty($datos['otro']) || $veterinaria->ofrece($datos['servicio']), "{$veterinaria->nombre} no ofrece el servicio de ".mb_strtolower($datos['servicio']).'.', 'servicio');
        $this->exigir($fecha->gte(Carbon::today()), 'La fecha de la cita no puede ser anterior a hoy.', 'fecha');
        $this->exigir(! $fecha->isSunday(), 'Las veterinarias no atienden los domingos.', 'fecha');
        if ($fecha->isToday()) {
            $this->exigir($datos['hora'] > now()->format('H:i'), 'Ese horario de hoy ya pasó.', 'hora');
        }
        $ocupado = Cita::where('veterinaria_id', $veterinaria->id)->whereDate('fecha', $fecha)
            ->where('hora', $datos['hora'])->whereNotIn('estado', self::LIBERAN_HORARIO)->exists();
        $this->exigir(! $ocupado, 'Ese horario ya está reservado. Elige otra hora.', 'hora');
        $mismoDia = $mascota->citas()->whereDate('fecha', $fecha)->whereNotIn('estado', self::LIBERAN_HORARIO)->exists();
        $this->exigir(! $mismoDia, "{$mascota->nombre} ya tiene una cita ese día.", 'fecha');

        $estimado = $this->estimar($mascota, $veterinaria, $datos['servicio'], $fecha);
        $cita = $mascota->citas()->create([
            'veterinaria_id' => $veterinaria->id, 'servicio' => $datos['servicio'], 'fecha' => $fecha,
            'hora' => $datos['hora'], 'estado' => 'pendiente', 'nota' => $datos['nota'] ?? null,
            'urgente' => (bool) ($datos['urgente'] ?? false), 'monto_estimado' => $estimado['precio'], 'cubierto_estimado' => $estimado['cubierto'],
        ]);

        $dueno = $mascota->propietario;
        $this->avisarDueno($cita, "PetCare 🐾 Tu solicitud de cita para {$mascota->nombre} ({$cita->servicio}) el {$this->cuando($cita)} en {$veterinaria->nombre} fue enviada. Te avisaremos cuando la clínica la acepte.");
        $this->whatsapp->enviarTexto($veterinaria->whatsappInternacional(),
            'PetCare '.($cita->urgente ? '🚨 Cita URGENTE' : '📅 Nueva cita').": {$mascota->nombre} · {$cita->servicio} · {$this->cuando($cita)} · {$dueno->name} {$dueno->telefonoVisible()}. Respóndela en PetCare.");

        return $cita;
    }

    public function confirmar(Cita $cita): void
    {
        $this->exigir($cita->estado === 'pendiente', 'Solo se pueden aceptar citas pendientes.');
        $cita->update(['estado' => 'confirmada', 'motivo_rechazo' => null, 'respondida_at' => now()]);
        $v = $cita->veterinaria;
        $this->avisarDueno($cita, "PetCare ✅ Cita confirmada: {$cita->mascota->nombre} · {$this->cuando($cita)} · {$v->nombre}, {$v->direccion}.");
    }

    public function rechazar(Cita $cita, string $motivo): void
    {
        $this->exigir(in_array($cita->estado, ['pendiente', 'confirmada'], true), 'Solo se pueden rechazar citas pendientes o confirmadas.');
        $cita->update(['estado' => 'rechazada', 'motivo_rechazo' => $motivo, 'respondida_at' => now()]);
        $this->avisarDueno($cita, "PetCare ❌ Cita rechazada: {$cita->mascota->nombre} · {$this->cuando($cita)}. Motivo: {$motivo}. Elige otro horario en PetCare.");
    }

    public function volverAPendiente(Cita $cita): void
    {
        $this->exigir(in_array($cita->estado, ['confirmada', 'rechazada'], true), 'La cita ya está pendiente o fue cerrada.');
        if ($cita->estado === 'rechazada') {
            $ocupado = Cita::where('veterinaria_id', $cita->veterinaria_id)->whereDate('fecha', $cita->fecha)->where('hora', $cita->hora)
                ->whereNotIn('estado', self::LIBERAN_HORARIO)->whereKeyNot($cita->id)->exists();
            $this->exigir(! $ocupado, 'Ese horario ya fue tomado por otra cita.');
        }
        $cita->update(['estado' => 'pendiente', 'motivo_rechazo' => null]);
        $this->avisarDueno($cita, "PetCare ⏳ Cita de {$cita->mascota->nombre} ({$this->cuando($cita)}) otra vez pendiente de confirmar.");
    }

    /** La clínica marca la cita como atendida (también se marca sola al registrar la atención). */
    public function marcarAtendida(Cita $cita, bool $avisar = true): void
    {
        $this->exigir($cita->abierta(), 'Solo se pueden marcar como atendidas las citas pendientes o confirmadas.');
        $this->exigir($cita->fecha->lte(Carbon::today()), 'No se puede marcar como atendida una cita de una fecha futura.');
        $cita->update(['estado' => 'atendida']);
        if ($avisar) {
            $this->avisarDueno($cita, "PetCare 🐾 {$cita->mascota->nombre} fue atendido/a ({$cita->servicio}). El detalle está en su historial.");
        }
    }

    public function cancelar(Cita $cita, ?User $por = null): void
    {
        $this->exigir($cita->abierta(), 'Solo se pueden cancelar citas pendientes o confirmadas.');
        $cita->update(['estado' => 'cancelada']);
        if ($por?->esRol(User::PROPIETARIO)) {
            $this->whatsapp->enviarTexto($cita->veterinaria->whatsappInternacional(),
                "PetCare 🚫 Cita cancelada por el dueño: {$cita->mascota->nombre} · {$this->cuando($cita)}. Horario libre.");
        } else {
            $this->avisarDueno($cita, "PetCare 🚫 {$cita->veterinaria->nombre} canceló la cita de {$cita->mascota->nombre} ({$this->cuando($cita)}). Elige otro horario en PetCare.");
        }
    }

    /** Comprueba que el usuario tenga relación con la cita (propietario o su veterinaria). */
    public function puedeGestionar(User $user, Cita $cita): bool
    {
        return match ($user->rol) {
            User::PROPIETARIO => $cita->mascota->propietario_id === $user->id,
            User::VETERINARIA => $cita->veterinaria_id === $user->veterinaria_id,
            default => true,
        };
    }

    private function avisarDueno(Cita $cita, string $mensaje): void
    {
        $dueno = $cita->mascota->propietario;
        $this->whatsapp->enviarTexto($dueno?->telefonoInternacional(), $mensaje, $dueno);
    }

    private function cuando(Cita $cita): string
    {
        return $cita->fecha->format('d/m').' '.$cita->hora;
    }
}
