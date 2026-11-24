<?php

namespace App\Http\Controllers\Veterinaria;

use App\Http\Controllers\Controller;
use App\Models\Atencion;
use App\Models\Cita;
use App\Services\HistorialService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class InicioController extends Controller
{
    public function __invoke(Request $request, HistorialService $historial)
    {
        $vet = $request->user()->veterinaria;
        $hoy = Carbon::today();
        $base = Cita::with(['mascota.propietario', 'pagos'])->where('veterinaria_id', $vet->id);
        $atenciones = Atencion::where('veterinaria_id', $vet->id);

        return view('veterinaria.inicio', [
            'vet' => $vet,
            'hoy' => (clone $base)->whereDate('fecha', $hoy)->where('estado', '!=', 'cancelada')->orderByRaw("CASE WHEN urgente = '1' AND estado IN ('pendiente','confirmada') THEN 0 ELSE 1 END")->orderBy('hora')->get(),
            'pendientes' => (clone $base)->where('estado', 'pendiente')->whereDate('fecha', '>', $hoy)->orderByRaw("CASE WHEN urgente = '1' AND estado IN ('pendiente','confirmada') THEN 0 ELSE 1 END")->orderBy('fecha')->orderBy('hora')->get(),
            'stats' => [
                'citas_hoy' => (clone $base)->whereDate('fecha', $hoy)->where('estado', '!=', 'cancelada')->count(),
                'atenciones_hoy' => (clone $atenciones)->whereDate('fecha', $hoy)->count(),
                'pacientes' => $historial->pacientesDe($vet->id)->count(),
                'atenciones_mes' => (clone $atenciones)->whereBetween('fecha', [$hoy->copy()->startOfMonth(), $hoy->copy()->endOfMonth()])->count(),
            ],
        ]);
    }
}
