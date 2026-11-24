<?php

namespace App\Services;

use App\Models\Afiliacion;
use App\Models\Cita;
use App\Models\Pago;
use App\Support\Formato;
use App\Models\User;
use App\Models\Veterinaria;
use App\Support\Catalogo;
use Illuminate\Support\Carbon;

/** Avisos que se muestran en la campana de la barra superior según el rol. */
class NotificacionService
{
    public function __construct(private HistorialService $historial) {}

    public function para(User $u): array
    {
        $hoy = Carbon::today();
        $out = [];
        if ($u->esRol(User::PROPIETARIO)) {
            $ids = $u->mascotas()->pluck('id');
            foreach (Cita::with(['mascota', 'veterinaria'])->whereIn('mascota_id', $ids)->proximas()->whereDate('fecha', '<=', $hoy->copy()->addDays(7))->orderBy('fecha')->get() as $c) {
                $cuando = $c->fecha->isToday() ? 'hoy' : ($c->fecha->isTomorrow() ? 'mañana' : 'el '.$c->fecha->format('d/m/Y'));
                $out[] = ['icono' => 'cal', 'color' => 'c-i', 'titulo' => "Cita de {$c->mascota->nombre} {$cuando} a las {$c->hora}", 'detalle' => "{$c->servicio} · {$c->veterinaria->nombre} · {$c->estado}", 'url' => route('propietario.citas.index')];
            }
            foreach (Cita::with(['mascota', 'veterinaria'])->whereIn('mascota_id', $ids)->where('estado', 'rechazada')
                ->whereDate('fecha', '>=', $hoy)->where('respondida_at', '>=', now()->subDays(7))->get() as $c) {
                $out[] = ['icono' => 'x', 'color' => 'c-b', 'titulo' => "{$c->veterinaria->nombre} rechazó la cita de {$c->mascota->nombre}", 'detalle' => 'Motivo: '.($c->motivo_rechazo ?: '—'), 'url' => route('propietario.citas.index')];
            }
            foreach ($this->historial->recordatorios($u) as $x) {
                $venc = $x->estado === 'vencida';
                $out[] = ['icono' => 'shield', 'color' => $venc ? 'c-b' : 'c-w', 'titulo' => ($venc ? 'Dosis vencida' : 'Próxima dosis').': '.($x->atencion->producto ?: $x->atencion->servicio)." de {$x->mascota->nombre}", 'detalle' => ($venc ? 'Debía aplicarse el ' : 'Programada para el ').$x->atencion->proxima_dosis->format('d/m/Y').($x->cita ? ' · cita agendada' : ''), 'url' => route('propietario.mascotas.show', [$x->mascota, 'vacunas'])];
            }
            // Resultado de la verificación de sus pagos (últimos 7 días)
            foreach (Pago::with(['mascota', 'plan', 'cita.mascota'])->where('user_id', $u->id)->whereIn('estado', ['aprobado', 'rechazado'])
                ->where('revisado_at', '>=', now()->subDays(7))->latest('revisado_at')->get() as $p) {
                $ok = $p->estado === 'aprobado';
                $out[] = ['icono' => $ok ? 'check' : 'x', 'color' => $ok ? 'c-ok' : 'c-b', 'titulo' => ($ok ? 'Pago aprobado: ' : 'Pago rechazado: ').$p->detalle(),
                    'detalle' => Formato::bs($p->monto).' · '.($ok ? 'Factura '.$p->numero : 'Motivo: '.($p->motivo_rechazo ?: '—')).' · '.$p->revisado_at->diffForHumans(),
                    'url' => match ($p->concepto) { 'cita' => route('propietario.citas.index'), 'pedido' => route('propietario.tienda.pedidos'), default => route('propietario.pagos.index') }];
            }
            // Pedidos de la tienda listos para recoger o en camino
            foreach (\App\Models\Pedido::with('veterinaria')->where('user_id', $u->id)->whereIn('estado', ['listo', 'en_camino'])->get() as $pd) {
                $out[] = ['icono' => 'cart', 'color' => 'c-ok', 'titulo' => "Pedido {$pd->numero}: {$pd->estadoTexto()}",
                    'detalle' => $pd->estado === 'listo' ? "Recógelo en {$pd->veterinaria->nombre}." : "Va a {$pd->direccion}.", 'url' => route('propietario.tienda.pedidos')];
            }
            foreach ($u->mascotas()->with('afiliacionActiva.plan')->get() as $m) {
                if ($m->afiliacionActiva && ($deuda = $m->afiliacionActiva->mesesAdeudados())) {
                    $out[] = ['icono' => 'alert', 'color' => 'c-b', 'titulo' => "Contrato de {$m->nombre}: {$deuda} ".($deuda === 1 ? 'mes' : 'meses').' sin pagar',
                        'detalle' => 'La permanencia mínima es hasta el '.$m->afiliacionActiva->permanencia_hasta->format('d/m/Y').'. Paga para no perder la cobertura.', 'url' => route('propietario.mascotas.show', [$m, 'plan'])];
                }
                if ($m->afiliacionActiva && $m->afiliacionActiva->porVencer()) {
                    $out[] = ['icono' => 'alert', 'color' => 'c-w', 'titulo' => "El plan de {$m->nombre} vence el ".$m->afiliacionActiva->fecha_fin->format('d/m/Y'), 'detalle' => 'Renueva para mantener la cobertura.', 'url' => route('propietario.mascotas.show', [$m, 'plan'])];
                } elseif (! $m->afiliacionActiva) {
                    $out[] = ['icono' => 'shield', 'color' => 'c-p', 'titulo' => "{$m->nombre} no tiene plan de asistencia", 'detalle' => 'Compara los planes disponibles.', 'url' => route('propietario.planes.index')];
                }
            }
        } elseif ($u->esRol(User::VETERINARIA)) {
            foreach (Cita::with('mascota')->where('veterinaria_id', $u->veterinaria_id)->where('estado', 'pendiente')->whereDate('fecha', '>=', $hoy)->orderBy('fecha')->get() as $c) {
                $out[] = ['icono' => 'clock', 'color' => 'c-w', 'titulo' => "Cita por confirmar: {$c->mascota->nombre}", 'detalle' => "{$c->servicio} · {$c->fecha->format('d/m/Y')} {$c->hora}", 'url' => route('veterinaria.agenda', ['estado' => 'pendiente'])];
            }
            // Pedidos de la tienda: pago por verificar o por preparar
            foreach (\App\Models\Pedido::with('user')->where('veterinaria_id', $u->veterinaria_id)->whereIn('estado', ['pendiente_pago', 'pagado'])->latest()->get() as $pd) {
                $out[] = ['icono' => 'cart', 'color' => $pd->estado === 'pendiente_pago' ? 'c-w' : 'c-p', 'titulo' => "🛒 Pedido {$pd->numero}: ".($pd->estado === 'pendiente_pago' ? 'pago por verificar' : 'por preparar'),
                    'detalle' => Formato::bs($pd->total).' · '.$pd->user->name.' · '.($pd->entrega === 'domicilio' ? 'envío a domicilio' : 'recoge en la clínica').' · '.$pd->created_at->diffForHumans(),
                    'url' => route('veterinaria.pedidos')];
            }
            // Pagos de citas que esperan su verificación
            foreach (Pago::with(['cita.mascota', 'user'])->where('concepto', 'cita')->where('veterinaria_id', $u->veterinaria_id)->pendientes()->latest()->get() as $p) {
                $out[] = ['icono' => 'card', 'color' => 'c-p', 'titulo' => '💳 Pago por verificar: cita de '.($p->cita?->mascota?->nombre ?? '—'),
                    'detalle' => Formato::bs($p->monto).' · '.$p->metodoTexto().' · '.$p->user->name.' · '.$p->created_at->diffForHumans(), 'url' => route('veterinaria.agenda', ['estado' => 'pendiente'])];
            }
        } else {
            // Pagos de planes y suscripciones que esperan verificación
            foreach (Pago::dePetcare()->pendientes()->with(['user', 'mascota', 'plan', 'veterinaria'])->latest()->get() as $p) {
                $out[] = ['icono' => 'card', 'color' => 'c-p', 'titulo' => '💳 Pago por verificar: '.$p->detalle(),
                    'detalle' => Formato::bs($p->monto).' · '.$p->metodoTexto().' · '.$p->user->name.' · '.$p->created_at->diffForHumans(),
                    'url' => route('admin.pagos.index', ['estado' => 'pendiente', 'ver' => $p->id]).'#pago-'.$p->id];
            }
            // Solicitudes nuevas de clínicas que ya confirmaron su correo
            foreach (Veterinaria::where('estado', 'pendiente')->whereNotNull('admin_avisado_at')->latest('admin_avisado_at')->get() as $v) {
                $out[] = ['icono' => 'store', 'color' => 'c-p', 'titulo' => "Nueva solicitud de afiliación: {$v->nombre}", 'detalle' => "NIT {$v->nit} · {$v->zona} · ".$v->admin_avisado_at->diffForHumans(), 'url' => route('admin.veterinarias.index')];
            }
            foreach (Veterinaria::where('activa', false)->where('estado', 'aprobada')->get() as $v) {
                $out[] = ['icono' => 'store', 'color' => 'c-w', 'titulo' => "{$v->nombre} está inactiva", 'detalle' => 'Revisa su habilitación.', 'url' => route('admin.veterinarias.index')];
            }
            foreach (Afiliacion::vigentes()->with('mascota')->whereDate('fecha_fin', '<=', $hoy->copy()->addDays(Catalogo::DIAS_AVISO))->get() as $a) {
                $out[] = ['icono' => 'doc', 'color' => 'c-i', 'titulo' => "Plan por vencer: {$a->mascota->nombre}", 'detalle' => 'Vence el '.$a->fecha_fin->format('d/m/Y'), 'url' => route('admin.mascotas.index')];
            }
        }

        return $out;
    }
}
