<?php

namespace App\Http\Controllers\Propietario;

use App\Http\Controllers\Controller;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\Veterinaria;
use App\Services\TiendaService;
use App\Support\Catalogo;
use App\Support\ReglasPago;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Tienda del propietario: vitrina, carrito, pago por clínica y seguimiento de pedidos. */
class TiendaController extends Controller
{
    public function __construct(private TiendaService $tienda) {}

    public function index(Request $request)
    {
        $f = $request->only(['q', 'categoria', 'veterinaria', 'max', 'orden']);

        return view('propietario.tienda.index', [
            'productos' => $this->tienda->vitrina($f), 'f' => $f,
            'clinicas' => Veterinaria::activas()->where('estado', 'aprobada')->orderBy('nombre')->get(['id', 'nombre']),
            'enCarrito' => $this->tienda->carrito(),
        ]);
    }

    public function agregar(Request $request, Producto $producto)
    {
        $d = $request->validate(['cantidad' => ['required', 'integer', 'between:1,99']], ['cantidad.between' => 'Elige entre 1 y 99 unidades.']);
        $this->tienda->agregar($producto->load('veterinaria'), $d['cantidad']);

        return back()->with('ok', "Agregaste {$d['cantidad']} × {$producto->nombre} al carrito.");
    }

    public function carrito()
    {
        return view('propietario.tienda.carrito', ['grupos' => $this->tienda->porClinica()]);
    }

    public function cambiar(Request $request, Producto $producto)
    {
        $d = $request->validate(['cantidad' => ['required', 'integer', 'between:0,99']]);
        $this->tienda->cambiar($producto, $d['cantidad']);

        return back()->with('ok', $d['cantidad'] ? 'Cantidad actualizada.' : 'Producto quitado del carrito.');
    }

    /** Pago de los productos de UNA clínica. */
    public function checkout(Request $request, Veterinaria $veterinaria)
    {
        $grupo = $this->tienda->porClinica()->firstWhere('vet.id', $veterinaria->id);
        if (! $grupo) {
            return redirect()->route('propietario.tienda.carrito')->with('ok', 'Tu carrito no tiene productos de esa clínica.');
        }
        $mascotas = $request->user()->mascotas()->with(['afiliacionActiva.plan', 'afiliacionActiva.veterinaria'])->orderBy('nombre')->get();
        // Descuento posible con cada mascota (para mostrarlo al elegirla)
        $descuentos = $mascotas->mapWithKeys(fn ($m) => [$m->id => $this->tienda->descuento($m, $veterinaria, $grupo['lineas'])]);

        return view('propietario.tienda.pagar', ['vet' => $veterinaria, 'grupo' => $grupo, 'mascotas' => $mascotas, 'descuentos' => $descuentos, 'user' => $request->user()]);
    }

    public function comprar(Request $request, Veterinaria $veterinaria)
    {
        $datos = ReglasPago::validar($request, [
            'entrega' => ['required', Rule::in(['recoger', 'domicilio'])],
            'direccion' => ['required_if:entrega,domicilio', 'nullable', 'string', 'min:8', 'max:200'],
            'referencia' => ['nullable', 'string', 'max:160'],
            'mascota_id' => ['nullable', 'integer'],
            'nota' => ['nullable', 'string', 'max:200'],
            'acepto' => ['accepted'],
        ], [
            'entrega.required' => 'Elige cómo recibirás tu pedido.',
            'direccion.required_if' => 'Escribe la dirección de entrega (calle, número y zona).',
            'direccion.min' => 'Escribe la dirección completa (calle, número y zona).',
            'acepto.accepted' => 'Lee y acepta los términos y condiciones para continuar.',
        ]);
        $pedido = $this->tienda->comprar($request->user(), $veterinaria, $datos, $request->file('comprobante'));

        return redirect()->route('propietario.tienda.pedidos')->with('pago_whatsapp', $pedido->pago->id)
            ->with('ok', "Pedido {$pedido->numero} enviado. {$veterinaria->nombre} verificará el pago.");
    }

    public function pedidos(Request $request)
    {
        return view('propietario.tienda.pedidos', ['pedidos' => Pedido::with(['items', 'veterinaria', 'pago'])->where('user_id', $request->user()->id)->latest()->get()]);
    }

    public function cancelar(Request $request, Pedido $pedido)
    {
        abort_unless($pedido->user_id === $request->user()->id, 404);
        $this->tienda->cancelar($pedido, $request->user());

        return back()->with('ok', "Pedido {$pedido->numero} cancelado.");
    }

    /** Categorías para el filtro. */
    public static function categorias(): array
    {
        return array_diff_key(Catalogo::CATEGORIAS_PRODUCTO, ['insumo' => 1]);
    }
}
