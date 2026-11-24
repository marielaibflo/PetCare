<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Atencion;
use App\Models\Cita;
use App\Models\Veterinaria;
use Illuminate\Http\Request;

class ServicioController extends Controller
{
    public function __invoke(Request $request)
    {
        $tab = $request->query('tab') === 'citas' ? 'citas' : 'atenciones';
        $vet = $request->query('veterinaria');
        $modelo = $tab === 'citas' ? Cita::query() : Atencion::query();
        $lista = $modelo->with(['mascota', 'veterinaria'])
            ->when($vet, fn ($q) => $q->where('veterinaria_id', $vet))
            ->orderByDesc('fecha')->orderByDesc('id')->paginate(25)->withQueryString();

        return view('admin.servicios', ['tab' => $tab, 'lista' => $lista, 'vet' => $vet, 'veterinarias' => Veterinaria::orderBy('nombre')->get()]);
    }
}
