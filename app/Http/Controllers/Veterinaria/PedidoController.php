<?php

namespace App\Http\Controllers\Veterinaria;

use App\Http\Controllers\Controller;
use App\Models\Pedido;
use App\Services\TiendaService;
use Illuminate\Http\Request;

/** Pedidos de la tienda que recibe la clínica. */
class PedidoController extends Controller
{
    public const FILTROS = ['abiertos' => 'Por atender', 'pendiente_pago' => 'Pago por verificar', 'entregado' => 'Entregados', 'cancelado' => 'Cancelados', 'todos' => 'Todos'];

    public function __construct(private TiendaService $tienda) {}

    public function index(Request $request)
    {
        $vet = $request->user()->veterinaria;
        $filtro = array_key_exists($request->query('estado'), self::FILTROS) ? $request->query('estado') : 'abiertos';
        $pedidos = Pedido::with(['items', 'user', 'mascota', 'pago'])->where('veterinaria_id', $vet->id)
            ->when($filtro === 'abiertos', fn ($q) => $q->whereIn('estado', Pedido::ABIERTOS))
            ->when(! in_array($filtro, ['abiertos', 'todos'], true), fn ($q) => $q->where('estado', $filtro))
            ->latest()->get();

        return view('veterinaria.pedidos', ['vet' => $vet, 'pedidos' => $pedidos, 'filtro' => $filtro, 'filtros' => self::FILTROS,
            'abiertos' => Pedido::where('veterinaria_id', $vet->id)->whereIn('estado', Pedido::ABIERTOS)->count()]);
    }

    public function aprobarPago(Request $request, Pedido $pedido)
    {
        $this->propio($request, $pedido);
        $this->tienda->revisarPago($pedido->load(['pago', 'user', 'veterinaria']), $request->user(), true);

        return back()->with('ok', "Pago del pedido {$pedido->numero} confirmado.");
    }

    public function rechazarPago(Request $request, Pedido $pedido)
    {
        $this->propio($request, $pedido);
        $d = $request->validate(['motivo' => ['required', 'string', 'min:5', 'max:200']], ['motivo.required' => 'Escribe por qué no se pudo verificar el pago.']);
        $this->tienda->revisarPago($pedido->load(['pago', 'user', 'veterinaria', 'items']), $request->user(), false, $d['motivo']);

        return back()->with('ok', "Pago rechazado: el pedido {$pedido->numero} se canceló y el stock volvió a tu catálogo.");
    }

    public function avanzar(Request $request, Pedido $pedido)
    {
        $this->propio($request, $pedido);
        $this->tienda->avanzar($pedido->load(['user', 'veterinaria']));

        return back()->with('ok', "Pedido {$pedido->numero}: {$pedido->fresh()->estadoTexto()}.");
    }

    public function cancelar(Request $request, Pedido $pedido)
    {
        $this->propio($request, $pedido);
        $d = $request->validate(['motivo' => ['required', 'string', 'min:5', 'max:200']], ['motivo.required' => 'Escribe el motivo de la cancelación.']);
        $this->tienda->cancelar($pedido->load(['items', 'pago', 'user', 'veterinaria']), $request->user(), $d['motivo']);

        return back()->with('ok', "Pedido {$pedido->numero} cancelado.");
    }

    /** Envío a domicilio: si lo ofrece, su costo y zonas. */
    public function envio(Request $request)
    {
        $request->merge(['envio_costo' => str_replace(',', '.', (string) $request->input('envio_costo', '0'))]);
        $d = $request->validate([
            'envio_activo' => ['nullable', 'boolean'],
            'envio_costo' => ['required_if:envio_activo,1', 'numeric', 'min:0', 'max:500'],
            'envio_zonas' => ['nullable', 'string', 'max:160'],
        ], ['envio_costo.required_if' => 'Escribe el costo del envío (0 si es gratis).', 'envio_costo.max' => 'El costo de envío máximo es Bs 500.']);
        $request->user()->veterinaria->update(['envio_activo' => (bool) ($d['envio_activo'] ?? false), 'envio_costo' => $d['envio_costo'] ?: 0, 'envio_zonas' => $d['envio_zonas'] ?? null]);

        return back()->with('ok', 'Entrega a domicilio actualizada.');
    }

    private function propio(Request $request, Pedido $pedido): void
    {
        abort_unless($pedido->veterinaria_id === $request->user()->veterinaria_id, 404);
    }
}
