<?php

namespace App\Http\Controllers\Propietario;

use App\Http\Controllers\Controller;
use App\Models\Cita;
use App\Services\HistorialService;
use Illuminate\Http\Request;

class InicioController extends Controller
{
    public function __invoke(Request $request, HistorialService $historial)
    {
        $user = $request->user();
        $mascotas = $user->mascotas()->with('afiliacionActiva.plan')->orderBy('id')->get();

        return view('propietario.inicio', [
            'user' => $user,
            'mascotas' => $mascotas,
            'afiliaciones' => $mascotas->filter->afiliacionActiva,
            'citas' => Cita::with(['mascota', 'veterinaria'])->whereIn('mascota_id', $mascotas->pluck('id'))->proximas()->orderBy('fecha')->orderBy('hora')->limit(4)->get(),
            'recordatorios' => $historial->recordatorios($user),
        ]);
    }
}
