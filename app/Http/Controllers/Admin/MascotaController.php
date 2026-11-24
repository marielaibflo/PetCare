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
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->whereLike('nombre', "%{$q}%")->orWhereLike('raza', "%{$q}%")->orWhereLike('especie', "%{$q}%")
                ->orWhereHas('propietario', fn ($p) => $p->whereLike('name', "%{$q}%"))))
            ->latest('id')->paginate(20)->withQueryString();

        return view('admin.mascotas', ['mascotas' => $lista, 'q' => $q]);
    }
}
