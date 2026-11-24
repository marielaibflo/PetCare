<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Pago;
use App\Services\PagoService;
use App\Support\ComprobantePdf;
use Illuminate\Http\Request;

/** El administrador verifica los pagos (QR por WhatsApp) y activa los planes y suscripciones. */
class PagoController extends Controller
{
    public const FILTROS = ['pendiente' => 'Por verificar', 'aprobado' => 'Aprobados', 'rechazado' => 'Rechazados', 'todos' => 'Todos'];

    public function __construct(private PagoService $pagos) {}

    public function index(Request $request)
    {
        $filtro = array_key_exists($request->query('estado'), self::FILTROS) ? $request->query('estado') : 'pendiente';
        $lista = Pago::dePetcare()->with(['user', 'mascota', 'plan', 'veterinaria'])
            ->when($filtro !== 'todos', fn ($q) => $q->where('estado', $filtro))
            ->latest()->paginate(20)->withQueryString();

        return view('admin.pagos', ['pagos' => $lista, 'filtro' => $filtro, 'filtros' => self::FILTROS, 'pendientes' => Pago::dePetcare()->pendientes()->count()]);
    }

    public function aprobar(Request $request, Pago $pago)
    {
        $pago = $this->pagos->aprobar($pago, $request->user());

        return back()->with('ok', "Pago aprobado · factura {$pago->numero} enviada por correo");
    }

    public function rechazar(Request $request, Pago $pago)
    {
        $datos = $request->validate(['motivo' => ['required', 'string', 'min:5', 'max:200']], ['motivo.required' => 'Escribe el motivo para el cliente.']);
        $this->pagos->rechazar($pago, $request->user(), $datos['motivo']);

        return back()->with('ok', 'Pago rechazado · se avisó al cliente');
    }

    public function comprobantePdf(Pago $pago)
    {
        abort_unless($pago->estado === 'aprobado', 404);

        return response(ComprobantePdf::generar($pago->load(['user', 'mascota.afiliacionActiva.plan', 'plan', 'veterinaria', 'revisor'])), 200, [
            'Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="Factura-'.$pago->numero.'.pdf"',
        ]);
    }

    public function reenviar(Pago $pago)
    {
        abort_unless($pago->estado === 'aprobado', 404);
        $this->pagos->enviarComprobante($pago->load(['user', 'mascota.afiliacionActiva.plan', 'plan', 'veterinaria', 'revisor']));

        return back()->with('ok', 'Factura reenviada a '.$pago->user->email);
    }
}
