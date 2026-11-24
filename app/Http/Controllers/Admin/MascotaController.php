<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Mascota;
use Illuminate\Http\Request;

class MascotaController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q'));
        $lista = Mascota::with(['propietario', 'afiliacionActiva.plan', 'afiliacionActiva.veterinaria'])
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('nombre', 'like', "%{$q}%")->orWhere('raza', 'like', "%{$q}%")->orWhere('especie', 'like', "%{$q}%")
                ->orWhereHas('propietario', fn ($p) => $p->where('name', 'like', "%{$q}%"))))
            ->latest('id')->paginate(20)->withQueryString();

        return view('admin.mascotas', ['mascotas' => $lista, 'q' => $q]);
    }
}
