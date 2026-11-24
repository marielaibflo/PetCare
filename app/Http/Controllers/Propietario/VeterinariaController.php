<?php

namespace App\Http\Controllers\Propietario;

use App\Http\Controllers\Controller;
use App\Models\Cita;
use App\Models\Resena;
use App\Models\User;
use App\Models\Veterinaria;
use App\Support\Catalogo;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class VeterinariaController extends Controller
{
    public const ORDENES = ['calificacion' => 'Mejor calificadas', 'solicitadas' => 'Más solicitadas', 'nombre' => 'Nombre (A-Z)'];

    public function index(Request $request)
    {
        $q = trim((string) $request->query('q'));
        $zona = (string) $request->query('zona');
        $orden = array_key_exists($request->query('orden'), self::ORDENES) ? $request->query('orden') : 'calificacion';

        // "Más solicitadas" = citas recibidas en los últimos 90 días (sin contar canceladas).
        $todas = Veterinaria::activas()->withCount(['citas as solicitudes' => fn ($c) => $c
            ->where('created_at', '>=', Carbon::now()->subDays(90))->where('estado', '!=', 'cancelada')])->get();
        $mejor = $todas->where('resenas', '>', 0)->sortByDesc(fn ($v) => [$v->valoracion, $v->resenas])->first()?->id;
        $masPedida = $todas->where('solicitudes', '>', 0)->sortByDesc('solicitudes')->first()?->id;

        $lista = $todas->filter(function ($v) use ($q, $zona) {
            $texto = mb_strtolower($v->nombre.' '.$v->zona.' '.implode(' ', $v->servicios).' '.implode(' ', $v->servicios_especiales ?? []));

            return ($q === '' || str_contains($texto, mb_strtolower($q))) && ($zona === '' || $v->zona === $zona);
        });
        $lista = match ($orden) {
            'solicitadas' => $lista->sortByDesc(fn ($v) => [$v->solicitudes, $v->valoracion]),
            'nombre' => $lista->sortBy('nombre', SORT_NATURAL | SORT_FLAG_CASE),
            default => $lista->sortByDesc(fn ($v) => [(float) $v->valoracion, $v->resenas]),
        };

        return view('propietario.veterinarias', [
            'veterinarias' => $lista->values(), 'zonas' => $todas->pluck('zona')->unique()->sort()->values(),
            'q' => $q, 'zona' => $zona, 'orden' => $orden, 'ordenes' => self::ORDENES, 'mejor' => $mejor, 'masPedida' => $masPedida,
        ]);
    }

    /** Página pública (para usuarios con sesión) de la clínica: ubicación, fotos, catálogo y calificaciones. */
    public function show(Request $request, Veterinaria $veterinaria)
    {
        $user = $request->user();
        $propia = $user->esRol(User::VETERINARIA) && $user->veterinaria_id === $veterinaria->id;
        abort_unless($veterinaria->activa || $propia || $user->esRol(User::ADMINISTRADOR), 404);

        $productos = $veterinaria->productos()->activos()->orderBy('nombre')->get()
            ->sortBy(fn ($p) => array_search($p->categoria, array_keys(Catalogo::CATEGORIAS_PRODUCTO), true))
            ->groupBy('categoria');

        return view('veterinarias.show', [
            'v' => $veterinaria,
            'fotos' => $veterinaria->fotos()->get(),
            'productos' => $productos,
            'resenas' => $veterinaria->resenasLista()->with('user')->latest()->limit(20)->get(),
            'distribucion' => $veterinaria->resenasLista()->selectRaw('estrellas, count(*) as total')->groupBy('estrellas')->pluck('total', 'estrellas'),
            'solicitudes' => $veterinaria->citas()->where('created_at', '>=', Carbon::now()->subDays(90))->where('estado', '!=', 'cancelada')->count(),
            'propia' => $propia,
            'esDueno' => $user->esRol(User::PROPIETARIO),
        ]);
    }

    /** El dueño califica una cita atendida (una sola vez por cita). */
    public function calificar(Request $request, Cita $cita)
    {
        abort_unless($cita->mascota?->propietario_id === $request->user()->id, 403);
        $datos = $request->validate([
            'estrellas' => ['required', 'integer', 'between:1,5'],
            'comentario' => ['nullable', 'string', 'max:300'],
        ], ['estrellas.required' => 'Elige de 1 a 5 estrellas.']);
        if ($cita->estado !== 'atendida') {
            throw ValidationException::withMessages(['estrellas' => 'Solo puedes calificar citas que ya fueron atendidas.']);
        }
        if (Resena::where('cita_id', $cita->id)->exists()) {
            throw ValidationException::withMessages(['estrellas' => 'Ya calificaste esta cita.']);
        }
        Resena::create($datos + ['veterinaria_id' => $cita->veterinaria_id, 'user_id' => $request->user()->id, 'cita_id' => $cita->id]);
        $cita->veterinaria->recalcularCalificacion();

        return back()->with('ok', '¡Gracias! Tu calificación ayuda a otros dueños a elegir.');
    }
}
