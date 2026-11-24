<?php

namespace App\Http\Controllers\Veterinaria;

use App\Http\Controllers\Controller;
use App\Models\Mascota;
use App\Services\HistorialService;
use Illuminate\Http\Request;

class PacienteController extends Controller
{
    public function __construct(private HistorialService $historial) {}

    public function index(Request $request)
    {
        $q = mb_strtolower(trim((string) $request->query('q')));
        $lista = $this->historial->pacientesDe($request->user()->veterinaria_id)
            ->filter(fn ($m) => $q === '' || str_contains(mb_strtolower($m->nombre.' '.$m->raza.' '.$m->propietario->name), $q));

        $afiliados = \App\Models\Afiliacion::vigentes()->with(['mascota.propietario', 'plan'])->where('veterinaria_id', $request->user()->veterinaria_id)
            ->latest('fecha_inicio')->get();

        return view('veterinaria.pacientes', ['pacientes' => $lista, 'q' => $request->query('q'), 'afiliados' => $afiliados]);
    }

    /** Historial clínico completo del paciente (incluye atenciones de otras clínicas afiliadas). */
    public function show(Request $request, Mascota $mascota)
    {
        abort_unless($this->historial->esPaciente($request->user()->veterinaria_id, $mascota), 404);
        $mascota->load(['propietario', 'afiliacionActiva.plan', 'atenciones.veterinaria']);

        return view('veterinaria.paciente', ['mascota' => $mascota, 'dosis' => $this->historial->proximasDosis($mascota), 'carnet' => $this->historial->carnet($mascota)]);
    }

    /** Cartilla de vacunas imprimible del paciente. */
    public function cartilla(Request $request, Mascota $mascota)
    {
        abort_unless($this->historial->esPaciente($request->user()->veterinaria_id, $mascota), 404);

        return view('cartilla', ['mascota' => $mascota->load('propietario'), 'carnet' => $this->historial->carnet($mascota),
            'volver' => route('veterinaria.pacientes.show', $mascota)]);
    }
}
