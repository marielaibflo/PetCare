<?php

namespace App\Http\Controllers\Propietario;

use App\Http\Controllers\Controller;
use App\Models\Atencion;
use App\Models\Mascota;
use App\Services\CoberturaService;
use App\Support\Archivos;
use App\Services\HistorialService;
use App\Services\MascotaService;
use App\Support\Catalogo;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MascotaController extends Controller
{
    use Concerns;

    public function __construct(private MascotaService $servicio) {}

    public function index(Request $request, HistorialService $historial)
    {
        $mascotas = $request->user()->mascotas()->with(['afiliacionActiva.plan', 'propietario'])->orderBy('id')->get();

        return view('propietario.mascotas.index', [
            'mascotas' => $mascotas,
            'carnets' => $mascotas->mapWithKeys(fn ($m) => [$m->id => $historial->carnet($m)]),
        ]);
    }

    public function show(Request $request, Mascota $mascota, HistorialService $historial, string $tab = 'info')
    {
        $this->propia($request, $mascota);
        $mascota->load(['afiliacionActiva.plan', 'afiliacionActiva.veterinaria', 'atenciones.veterinaria', 'citas.veterinaria']);

        return view('propietario.mascotas.show', [
            'mascota' => $mascota,
            'tab' => $tab,
            'dosis' => $tab === 'vacunas' ? $historial->proximasDosis($mascota) : collect(),
            'carnet' => $historial->carnet($mascota),
            'saldo' => $tab === 'plan' ? app(CoberturaService::class)->saldo($mascota) : null,
        ]);
    }

    /** Cartilla de vacunas y desparasitación lista para imprimir o guardar como PDF. */
    public function cartilla(Request $request, Mascota $mascota, HistorialService $historial)
    {
        $this->propia($request, $mascota);

        return view('cartilla', ['mascota' => $mascota->load('propietario'), 'carnet' => $historial->carnet($mascota), 'volver' => route('propietario.mascotas.show', [$mascota, 'vacunas'])]);
    }

    /**
     * El dueño adjunta a una vacuna o desparasitación el certificado físico que le dio el veterinario
     * (foto o escaneo con sellos y firma). Es lo único que el dueño puede hacer en la cartilla.
     */
    public function adjuntarDocumento(Request $request, Mascota $mascota, Atencion $atencion)
    {
        $this->propia($request, $mascota);
        abort_unless($atencion->mascota_id === $mascota->id && in_array($atencion->servicio, Catalogo::PREVENTIVOS, true), 404);
        $request->validate(['documento' => array_merge(['required'], Archivos::IMAGEN_O_PDF)],
            ['documento.required' => 'Elige la foto o el PDF del certificado.']);
        Archivos::borrar($atencion->documento_dueno);
        $atencion->update(['documento_dueno' => Archivos::guardar($request->file('documento'), 'certificados')]);

        return back()->with('ok', 'Documento adjuntado a la cartilla');
    }

    public function store(Request $request)
    {
        $mascota = $this->servicio->guardar($request->user(), null, $this->validar($request), $request->file('foto'));

        return redirect()->route('propietario.mascotas.show', $mascota)->with('ok', "{$mascota->nombre} fue registrado/a");
    }

    public function update(Request $request, Mascota $mascota)
    {
        $this->propia($request, $mascota);
        $this->servicio->guardar($request->user(), $mascota, $this->validar($request), $request->file('foto'));

        return back()->with('ok', 'Datos actualizados');
    }

    public function destroy(Request $request, Mascota $mascota)
    {
        $this->propia($request, $mascota);
        $this->servicio->eliminar($mascota);

        return redirect()->route('propietario.mascotas.index')->with('ok', 'Mascota eliminada');
    }

    private function validar(Request $request): array
    {
        $request->merge(['peso' => str_replace(',', '.', (string) $request->input('peso'))]);
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'min:2', 'max:60'],
            'especie' => ['required', Rule::in(Catalogo::ESPECIES)],
            'raza' => ['nullable', 'string', 'max:60'],
            'fecha_nacimiento' => ['required', 'date', 'before_or_equal:today'],
            'peso' => ['required', 'numeric', 'gt:0', 'max:150'],
            'sexo' => ['required', Rule::in(Catalogo::SEXOS)],
            'esterilizado' => ['required', Rule::in(['si', 'no'])],
            'alergias' => ['nullable', 'string', 'max:150'],
            'color' => ['nullable', 'string', 'max:40'],
            'especie_otro' => ['exclude_unless:especie,Otro', 'required', 'string', 'min:3', 'max:30', 'regex:/^[\pL\s]+$/u'],
            'foto' => array_merge(['nullable'], Archivos::IMAGEN),
            'quitar_foto' => ['nullable', 'boolean'],
        ], ['foto.image' => 'La foto debe ser una imagen (JPG, PNG o WEBP).', 'foto.max' => 'La foto no puede pesar más de 10 MB.',
            'especie_otro.required' => 'Escribe qué especie es tu mascota (ej.: Hámster).', 'especie_otro.regex' => 'La especie solo puede tener letras.',
            'especie_otro.min' => 'La especie debe tener al menos 3 letras.']);
        // «Otro»: se guarda la especie escrita (Hámster, Conejo…)
        if ($datos['especie'] === 'Otro') {
            $otra = mb_convert_case(preg_replace('/\s+/u', ' ', trim($datos['especie_otro'])), MB_CASE_TITLE);
            $datos['especie'] = $otra;
        }
        $datos['quitar_foto'] = ! empty($datos['quitar_foto']) && ! $request->hasFile('foto');
        unset($datos['foto'], $datos['especie_otro']);
        $datos['esterilizado'] = $datos['esterilizado'] === 'si';

        return $datos;
    }
}
