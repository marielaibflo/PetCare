<?php

namespace App\Http\Controllers\Veterinaria;

use App\Http\Controllers\Controller;
use App\Models\Cita;
use App\Models\Mascota;
use App\Services\CoberturaService;
use App\Services\HistorialService;
use App\Support\Archivos;
use App\Support\Catalogo;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class AtencionController extends Controller
{
    public function __construct(private HistorialService $historial) {}

    public function create(Request $request)
    {
        $vet = $request->user()->veterinaria;
        $cita = null;
        if ($request->filled('cita')) {
            $cita = Cita::where('veterinaria_id', $vet->id)->abiertas()->find($request->query('cita'));
        }
        $pacientes = $this->historial->pacientesDe($vet->id);
        $otras = Mascota::with(['propietario', 'afiliacionActiva.plan'])->whereNotIn('id', $pacientes->pluck('id'))->orderBy('nombre')->get();

        return view('veterinaria.atencion', [
            'vet' => $vet, 'cita' => $cita, 'pacientes' => $pacientes, 'otras' => $otras,
            'seleccionada' => old('mascota_id', $cita?->mascota_id ?? $request->query('mascota')),
            'productos' => $vet->productos()->activos()->orderBy('categoria')->orderBy('nombre')->get(),
            // Servicios «Otro» que la clínica ya usó: se sugieren al escribir
            'sugeridos' => \App\Models\Atencion::where('veterinaria_id', $vet->id)->whereNotIn('servicio', array_merge(\App\Support\Catalogo::SERVICIOS, $vet->servicios ?? []))
                ->select('servicio')->groupBy('servicio')->orderByRaw('count(*) desc')->limit(20)->pluck('servicio'),
        ]);
    }

    public function store(Request $request)
    {
        $vet = $request->user()->veterinaria;
        foreach (['peso', 'temperatura', 'costo_servicio'] as $k) {
            if ($request->filled($k)) {
                $request->merge([$k => str_replace(',', '.', (string) $request->input($k))]);
            }
        }
        $otro = \App\Support\Catalogo::prepararServicioOtro($request, $vet->servicios ?? []);
        $datos = $request->validate([
            'mascota_id' => ['required', 'integer', 'exists:mascotas,id'],
            'servicio' => $otro ? ['required', 'string', 'max:40'] : ['required', 'string', 'max:40', Rule::in($vet->servicios ?? [])],
            'fecha' => ['required', 'date', 'before_or_equal:today'],
            'costo_servicio' => ['required', 'numeric', 'min:0', 'max:100000'],
            'productos' => ['nullable', 'array', 'max:20'],
            'productos.*.id' => ['nullable', 'integer'],
            'productos.*.cantidad' => ['nullable', 'numeric', 'gt:0', 'max:999'],
            'preexistente' => ['nullable', 'boolean'],
            'producto' => ['nullable', 'string', 'max:100'],
            'lote' => ['nullable', 'string', 'max:40'],
            'proxima_dosis' => ['nullable', 'date', 'after:today'],
            'foto_etiqueta' => array_merge(['nullable'], Archivos::IMAGEN),
            'diagnostico' => ['nullable', 'string', 'max:150'],
            'descripcion' => ['required', 'string', 'min:5', 'max:1000'],
            'tratamiento' => ['nullable', 'string', 'max:150'],
            'peso' => ['nullable', 'numeric', 'gt:0', 'max:150'],
            'temperatura' => ['nullable', 'numeric', 'between:30,45'],
            'cita_id' => ['nullable', 'integer'],
        ], ['costo_servicio.required' => 'Escribe el precio del servicio (puedes guardarlo en tu tarifario).', 'servicio.in' => 'Ese servicio no está habilitado en tu clínica: agrégalo en «Mi clínica».']);
        $mascota = Mascota::findOrFail($datos['mascota_id']);
        $a = $this->historial->registrar($request->user(), $mascota, $datos + ['otro' => $otro], $request->file('foto_etiqueta'));

        $resumen = $a->monto_cubierto > 0
            ? ' · PetCare cubre '.\App\Support\Formato::bs($a->monto_cubierto).' y el dueño paga '.\App\Support\Formato::bs($a->paga_dueno)
            : ' · particular: el dueño paga '.\App\Support\Formato::bs($a->paga_dueno);

        return redirect()->route('veterinaria.pacientes.show', $mascota)->with('ok', 'Atención guardada'.$resumen);
    }

    /** Cálculo en vivo de la cobertura mientras la veterinaria llena el formulario. */
    public function cobertura(Request $request, CoberturaService $cobertura)
    {
        $vet = $request->user()->veterinaria;
        $mascota = Mascota::find($request->integer('mascota_id'));
        $servicio = (string) $request->input('servicio');
        if ($servicio === \App\Support\Catalogo::OTRO_SERVICIO) {
            $servicio = trim((string) $request->input('servicio_otro')) ?: 'Otro servicio';
            $request->merge(['servicio' => $servicio, 'otro' => 1]);
        }
        if (! $mascota || (! $request->boolean('otro') && ! $vet->ofrece($servicio))) {
            return response()->json(['ok' => false]);
        }
        try {
            $items = $this->historial->productosDe($vet->id, (array) $request->input('productos', []));
        } catch (\Illuminate\Validation\ValidationException) {
            $items = [];
        }
        $fecha = rescue(fn () => Carbon::parse($request->input('fecha')), Carbon::today(), false);
        $c = $cobertura->calcular($mascota, $vet, $request->input('servicio'), (float) str_replace(',', '.', (string) $request->input('costo_servicio', 0)),
            $items, $fecha, $request->boolean('preexistente'));

        return response()->json([
            'ok' => true, 'plan' => $c['plan']?->nombre, 'porcentaje' => $c['porcentaje'], 'lineas' => $c['lineas'],
            'total' => $c['total'], 'cubierto' => $c['monto_cubierto'], 'dueno' => $c['paga_dueno'], 'notas' => $c['notas'],
            'disponible' => $c['afiliacion'] ? $c['disponible'] : null,
        ]);
    }

    /** Fragmento HTML con el historial, para mostrarlo junto al formulario. */
    public function historial(Mascota $mascota)
    {
        $mascota->load('atenciones.veterinaria');

        return view('partials.historial-preview', ['mascota' => $mascota]);
    }
}
