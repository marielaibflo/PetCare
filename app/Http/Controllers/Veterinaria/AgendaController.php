<?php

namespace App\Http\Controllers\Veterinaria;

use App\Http\Controllers\Controller;
use App\Models\Cita;
use App\Services\CitaService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AgendaController extends Controller
{
    public const FILTROS = ['todas' => 'Todas', 'hoy' => 'Hoy', 'pendiente' => 'Por confirmar', 'confirmada' => 'Confirmadas', 'atendida' => 'Atendidas', 'rechazada' => 'Rechazadas', 'cancelada' => 'Canceladas'];

    public function __construct(private CitaService $servicio) {}

    public function index(Request $request)
    {
        $filtro = array_key_exists($request->query('estado'), self::FILTROS) ? $request->query('estado') : 'todas';
        $q = Cita::with(['mascota.propietario', 'pagos.user'])->where('veterinaria_id', $request->user()->veterinaria_id);
        if ($filtro === 'hoy') {
            $q->whereDate('fecha', Carbon::today());
        } elseif ($filtro !== 'todas') {
            $q->where('estado', $filtro);
        }

        return view('veterinaria.agenda', ['citas' => $q->orderByRaw("CASE WHEN urgente = 1 AND estado IN ('pendiente','confirmada') THEN 0 ELSE 1 END")->orderBy('fecha')->orderBy('hora')->get(), 'filtro' => $filtro, 'filtros' => self::FILTROS]);
    }

    public function confirmar(Request $request, Cita $cita)
    {
        abort_unless($this->servicio->puedeGestionar($request->user(), $cita), 404);
        $this->servicio->confirmar($cita);

        return back()->with('ok', 'Cita aceptada · se avisó al propietario por WhatsApp');
    }

    public function rechazar(Request $request, Cita $cita)
    {
        abort_unless($this->servicio->puedeGestionar($request->user(), $cita), 404);
        $datos = $request->validate(['motivo' => ['required', 'string', 'min:5', 'max:200']], ['motivo.required' => 'Escribe el motivo del rechazo para el propietario.']);
        $this->servicio->rechazar($cita, $datos['motivo']);

        return back()->with('ok', 'Cita rechazada · se avisó al propietario por WhatsApp');
    }

    public function pendiente(Request $request, Cita $cita)
    {
        abort_unless($this->servicio->puedeGestionar($request->user(), $cita), 404);
        $this->servicio->volverAPendiente($cita);

        return back()->with('ok', 'La cita volvió a estar pendiente');
    }

    public function atendida(Request $request, Cita $cita)
    {
        abort_unless($this->servicio->puedeGestionar($request->user(), $cita), 404);
        $this->servicio->marcarAtendida($cita);

        return back()->with('ok', 'Cita marcada como atendida');
    }

    public function cancelar(Request $request, Cita $cita)
    {
        abort_unless($this->servicio->puedeGestionar($request->user(), $cita), 404);
        $this->servicio->cancelar($cita, $request->user());

        return back()->with('ok', 'Cita cancelada');
    }

    public function aprobarPago(Request $request, \App\Models\Pago $pago, \App\Services\PagoService $pagos)
    {
        abort_unless($pago->concepto === 'cita' && $pago->veterinaria_id === $request->user()->veterinaria_id, 404);
        $pagos->revisarCita($pago, $request->user(), true);

        return back()->with('ok', 'Pago confirmado. La cita quedó confirmada y se avisó al dueño.');
    }

    public function rechazarPago(Request $request, \App\Models\Pago $pago, \App\Services\PagoService $pagos)
    {
        abort_unless($pago->concepto === 'cita' && $pago->veterinaria_id === $request->user()->veterinaria_id, 404);
        $datos = $request->validate(['motivo' => ['required', 'string', 'min:5', 'max:200']],
            ['motivo.required' => 'Escribe por qué no se pudo verificar el pago.', 'motivo.min' => 'Explica el motivo en al menos 5 caracteres.']);
        $pagos->revisarCita($pago, $request->user(), false, $datos['motivo']);

        return back()->with('ok', 'Pago rechazado. Se avisó al dueño por WhatsApp.');
    }
}
