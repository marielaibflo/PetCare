<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Services\PlanService;
use App\Support\Catalogo;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PlanController extends Controller
{
    public function __construct(private PlanService $servicio) {}

    public function index()
    {
        return view('admin.planes', ['planes' => Plan::withCount('afiliacionesVigentes')
            ->with(['afiliaciones' => fn ($q) => $q->with(['mascota.propietario', 'veterinaria'])->latest('fecha_inicio')])
            ->latest('id')->get()]);
    }

    /** Excel con los afiliados del plan: inicio, vencimiento y días restantes. */
    public function afiliadosExcel(Plan $plan)
    {
        $filas = $plan->afiliaciones()->with(['mascota.propietario', 'veterinaria'])->latest('fecha_inicio')->get()->map(fn ($a) => [
            $a->mascota?->nombre, $a->mascota?->especie, $a->mascota?->propietario?->name, $a->mascota?->propietario?->email, $a->veterinaria?->nombre ?? '—',
            ucfirst($a->modalidad), $a->fecha_inicio->copy()->startOfDay(), $a->fecha_fin->copy()->startOfDay(), $a->estado === 'activo' ? max(0, $a->diasRestantes()) : 0, $a->estadoTexto()[0],
        ])->all();
        $x = new \App\Support\Excel;
        $x->hoja('Afiliados', "Afiliados del {$plan->nombre}", ['Mascota', 'Especie', 'Dueño', 'Correo', 'Veterinaria', 'Modalidad', 'Inicio', 'Vence', 'Días restantes', 'Estado'],
            $filas, ['anchos' => [16, 12, 24, 28, 24, 12, 13, 13, 14, 16]], 'Generado el '.now()->format('d/m/Y H:i'));

        return $x->descargar('PetCare-afiliados-'.\Illuminate\Support\Str::slug($plan->nombre).'.xlsx');
    }

    public function store(Request $request)
    {
        $this->servicio->guardar(null, $this->validar($request, null));

        return back()->with('ok', 'Plan creado');
    }

    public function update(Request $request, Plan $plan)
    {
        $this->servicio->guardar($plan, $this->validar($request, $plan));

        return back()->with('ok', 'Plan actualizado');
    }

    public function toggle(Plan $plan)
    {
        $plan->update(['activo' => ! $plan->activo]);

        return back()->with('ok', $plan->activo ? 'Plan activado' : 'Plan desactivado');
    }

    public function destroy(Plan $plan)
    {
        $this->servicio->eliminar($plan);

        return back()->with('ok', 'Plan eliminado');
    }

    private function validar(Request $request, ?Plan $plan): array
    {
        foreach (['precio', 'precio_anual', 'tope_mensual', 'tope_anual'] as $k) {
            $request->merge([$k => str_replace(',', '.', (string) $request->input($k))]);
        }

        return $request->validate([
            'vigente_desde' => ['required', 'date'],
            'vigente_hasta' => ['nullable', 'date', 'after:vigente_desde'],
            'precio_anual' => ['nullable', 'numeric', 'gt:0', 'max:1000000'],
            'carencia_dias' => ['required', 'integer', 'between:0,365'],
            'permanencia_meses' => ['required', 'integer', 'between:1,24'],
            'edad_min_meses' => ['required', 'integer', 'between:0,240'],
            'edad_max_anios' => ['nullable', 'integer', 'between:1,40'],
            'especies' => ['nullable', 'array'],
            'especies.*' => [Rule::in(Catalogo::ESPECIES)],
            'icono' => ['required', Rule::in(array_keys(Plan::ICONOS))],
            'color' => ['required', Rule::in(array_keys(Plan::COLORES))],
            'nombre' => ['required', 'string', 'min:3', 'max:80', Rule::unique('planes', 'nombre')->ignore($plan?->id)],
            'descripcion' => ['nullable', 'string', 'max:160'],
            'precio' => ['required', 'numeric', 'gt:0', 'max:100000'],
            'porcentaje' => ['required', 'integer', 'between:1,100'],
            'tope_mensual' => ['required', 'numeric', 'gt:0', 'max:1000000'],
            'tope_anual' => ['required', 'numeric', 'gte:tope_mensual', 'max:10000000'],
            'limite_atenciones' => ['nullable', 'integer', 'between:1,365'],
            'espera_cirugia_dias' => ['required', 'integer', 'between:0,365'],
            'medicamentos' => ['required', Rule::in(array_keys(Catalogo::REGLA_MEDICAMENTOS))],
            'permite_grave' => ['nullable', 'boolean'],
            'cobertura' => ['required', 'array', 'min:1'],
            'cobertura.*' => [Rule::in(Catalogo::SERVICIOS)],
            'beneficios' => ['nullable', 'string', 'max:600'],
        ], [
            'vigente_desde.required' => 'Indica desde qué fecha se ofrece el plan.',
            'vigente_hasta.after' => 'La fecha de fin debe ser posterior a la de inicio.',
            'tope_anual.gte' => 'El tope anual no puede ser menor al tope mensual.',
            'precio_anual.gt' => 'El precio anual debe ser mayor a 0.',
        ]);
    }
}
