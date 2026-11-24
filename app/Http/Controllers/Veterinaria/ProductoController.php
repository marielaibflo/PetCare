<?php

namespace App\Http\Controllers\Veterinaria;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Producto;
use App\Support\Archivos;
use App\Support\Catalogo;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Catálogo de medicamentos, vacunas e insumos de la clínica, con su precio y qué planes los cubren. */
class ProductoController extends Controller
{
    public function index(Request $request)
    {
        $vet = $request->user()->veterinaria;

        return view('veterinaria.productos', [
            'vet' => $vet,
            'productos' => $vet->productos()->orderByDesc('activo')->latest('id')->get(),
            'planes' => Plan::activos()->orderBy('precio')->get(),
            'limite' => $vet->limiteProductos(), 'enVenta' => $vet->productosEnVentaQuery()->count(), 'visibles' => $vet->idsVisiblesEnTienda(),
        ]);
    }

    public function store(Request $request)
    {
        $vet = $request->user()->veterinaria;
        $datos = $this->validar($request);
        if ($datos['en_tienda'] && $datos['stock'] > 0) {
            $this->exigirCupo($vet);
        }
        $vet->productos()->create($datos + ['activo' => true]);

        return back()->with('ok', 'Producto agregado al catálogo');
    }

    public function update(Request $request, Producto $producto)
    {
        $this->propio($request, $producto);
        $datos = $this->validar($request, $producto);
        $yaContaba = $producto->activo && $producto->en_tienda && $producto->stock > 0;
        if (! $yaContaba && $producto->activo && $datos['en_tienda'] && $datos['stock'] > 0) {
            $this->exigirCupo($request->user()->veterinaria);
        }
        $producto->update($datos);

        return back()->with('ok', 'Producto actualizado');
    }

    public function toggle(Request $request, Producto $producto)
    {
        $this->propio($request, $producto);
        if (! $producto->activo && $producto->en_tienda && $producto->stock > 0) {
            $this->exigirCupo($request->user()->veterinaria);
        }
        $producto->update(['activo' => ! $producto->activo]);

        return back()->with('ok', $producto->activo ? 'Producto activado' : 'Producto desactivado (ya no aparece al registrar atenciones)');
    }

    /** Límite de productos a la venta según la suscripción: Básico 10, Profesional 20, Clínica Plus ilimitados. */
    private function exigirCupo(\App\Models\Veterinaria $vet): void
    {
        $limite = $vet->limiteProductos();
        if ($limite && $vet->productosEnVentaQuery()->count() >= $limite) {
            $sig = $vet->plan_suscripcion === 'inicial' ? 'Profesional para vender hasta 20' : 'Clínica Plus para vender productos ilimitados';
            throw ValidationException::withMessages(['regla' => "Llegaste al límite de tu suscripción {$vet->suscripcion()['nombre']} ({$limite} productos a la venta). Mejora a {$sig}, o quita de la tienda otro producto."]);
        }
    }

    private function propio(Request $request, Producto $p): void
    {
        abort_unless($p->veterinaria_id === $request->user()->veterinaria_id, 404);
    }

    private function validar(Request $request, ?Producto $producto = null): array
    {
        $request->merge(['precio' => str_replace(',', '.', (string) $request->input('precio'))]);
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'min:2', 'max:100'],
            'categoria' => ['required', Rule::in(array_keys(Catalogo::CATEGORIAS_PRODUCTO))],
            'precio' => ['required', 'numeric', 'gt:0', 'max:100000'],
            'descripcion' => ['nullable', 'string', 'max:160'],
            'foto' => array_merge(['nullable'], Archivos::IMAGEN),
            'cubre_seguro' => ['nullable', 'boolean'],
            'planes' => ['nullable', 'array'],
            'planes.*' => ['integer', 'exists:planes,id'],
            'stock' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'en_tienda' => ['nullable', 'boolean'],
            'quitar_foto' => ['nullable', 'boolean'],
        ], ['stock.required' => 'Escribe cuántas unidades tienes (0 si está agotado).', 'stock.max' => 'El stock máximo es 9999 unidades.']);
        $quitar = ! empty($datos['quitar_foto']);
        unset($datos['foto'], $datos['quitar_foto']);
        if ($request->hasFile('foto')) {
            Archivos::borrar($producto?->foto);
            $datos['foto'] = Archivos::guardar($request->file('foto'), 'productos');
        } elseif ($quitar && $producto?->foto) {
            Archivos::borrar($producto->foto);
            $datos['foto'] = null;
        }
        $datos['en_tienda'] = (bool) ($datos['en_tienda'] ?? false);
        $datos['stock'] = (int) ($datos['stock'] ?? $producto?->stock ?? 10);
        // Los productos de tienda (alimentos, accesorios, higiene, juguetes) nunca los cubren los planes.
        if (in_array($datos['categoria'], Catalogo::PRODUCTOS_TIENDA, true)) {
            $datos['cubre_seguro'] = false;
        }
        $datos['cubre_seguro'] = (bool) ($datos['cubre_seguro'] ?? false);
        $datos['planes'] = $datos['cubre_seguro'] ? array_values(array_map('intval', $datos['planes'] ?? [])) : [];

        return $datos;
    }
}
