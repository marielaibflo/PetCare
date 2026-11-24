<?php

namespace App\Services;

use App\Models\Mascota;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Recordatorio de vacunas y desparasitaciones: 7 días antes de la próxima dosis se envía
 * un correo y un WhatsApp al dueño (una sola vez por dosis; en vacunas anuales es un aviso al año).
 * Además el aviso aparece en la campana de su perfil.
 * Se ejecuta todos los días con el programador de Laravel (php artisan schedule:work).
 */
class RecordatorioService
{
    public function __construct(private HistorialService $historial, private WhatsAppService $whatsapp) {}

    /** @return int cantidad de recordatorios enviados */
    public function enviarPendientes(?Carbon $hoy = null): int
    {
        $hoy ??= Carbon::today();
        $limite = $hoy->copy()->addDays((int) config('petcare.dias_recordatorio', 7));
        $enviados = 0;

        Mascota::with('propietario')->whereHas('propietario', fn ($q) => $q->where('activo', true))->get()
            ->each(function (Mascota $m) use ($hoy, $limite, &$enviados) {
                foreach ($this->historial->proximasDosis($m) as $x) {
                    $a = $x->atencion;
                    if ($a->recordatorio_at || $a->proxima_dosis->lt($hoy) || $a->proxima_dosis->gt($limite)) {
                        continue;
                    }
                    $this->avisar($m, $a, $x->cita);
                    $a->update(['recordatorio_at' => now()]);
                    $enviados++;
                }
            });

        return $enviados;
    }

    private function avisar(Mascota $m, $a, $cita): void
    {
        $dueno = $m->propietario;
        $que = $a->servicio === 'Vacuna' ? 'la vacuna '.($a->producto ?: '') : mb_strtolower($a->servicio).' ('.($a->producto ?: $a->servicio).')';
        $fecha = $a->proxima_dosis->format('d/m/Y');
        $cuando = $a->proxima_dosis->isToday() ? 'hoy' : 'el '.$fecha;
        $extra = $cita
            ? " Ya tienes una cita el {$cita->fecha->format('d/m/Y')} a las {$cita->hora} en {$cita->veterinaria->nombre}."
            : ' Agenda tu cita desde PetCare.';
        $texto = "PetCare 💉 {$m->nombre}: ".trim($que)." {$cuando}.".($cita ? " Cita: {$cita->fecha->format('d/m')} {$cita->hora}, {$cita->veterinaria->nombre}." : ' Agenda su cita en PetCare.').' Lleva su cartilla.';

        $this->whatsapp->enviarTexto($dueno->telefonoInternacional(), $texto, $dueno);
        try {
            Mail::raw("Hola {$dueno->primerNombre()},\n\nTe recordamos que {$m->nombre} debe recibir {$que} {$cuando}.{$extra}\n\n"
                ."No olvides llevar su cartilla de vacunación.\n\nEquipo PetCare 🐾",
                fn ($msg) => $msg->to($dueno->email, $dueno->name)->subject("PetCare · Recordatorio de {$a->servicio} de {$m->nombre} ({$fecha})"));
        } catch (Throwable $e) {
            Log::warning("[Correo] No se pudo enviar el recordatorio a {$dueno->email}: {$e->getMessage()}");
        }
    }
}
