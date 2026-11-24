<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Veterinaria;
use App\Services\VeterinariaService;
use App\Support\Catalogo;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Support\ReglasCuenta;

class VeterinariaController extends Controller
{
    public function __construct(private VeterinariaService $servicio) {}

    public function index(\Illuminate\Http\Request $request)
    {
        $orden = in_array($request->query('orden'), ['reciente', 'nombre', 'vence'], true) ? $request->query('orden') : 'reciente';
        $todas = Veterinaria::with('personal')->withCount('atenciones')->get();
        $todas = match ($orden) {
            'nombre' => $todas->sortBy('nombre', SORT_NATURAL | SORT_FLAG_CASE),
            'vence' => $todas->sortBy(fn ($v) => $v->suscripcion_hasta?->format('Y-m-d') ?? '9999'),
            default => $todas->sortByDesc(fn ($v) => ($v->aprobada_at ?? $v->created_at)->format('Y-m-d').str_pad((string) $v->id, 8, '0', STR_PAD_LEFT)),
        };

        return view('admin.veterinarias', [
            'veterinarias' => $todas->where('estado', '!=', 'pendiente')->values(),
            'solicitudes' => $todas->where('estado', 'pendiente')->sortByDesc('created_at')->values(),
            'orden' => $orden,
        ]);
    }

    /** Aprueba una clínica que se registró en línea: se activa con un mes de prueba gratis. */
    public function aprobar(Veterinaria $veterinaria)
    {
        $this->servicio->aprobarSolicitud($veterinaria);

        return back()->with('ok', "{$veterinaria->nombre} fue aprobada · se le avisó por correo y WhatsApp");
    }

    public function rechazar(Request $request, Veterinaria $veterinaria)
    {
        $datos = $request->validate(['motivo' => ['required', 'string', 'min:5', 'max:200']], ['motivo.required' => 'Escribe el motivo para la clínica.']);
        $this->servicio->rechazarSolicitud($veterinaria, $datos['motivo']);

        return back()->with('ok', 'Solicitud rechazada · se avisó a la clínica');
    }

    public function store(Request $request)
    {
        $request->merge(['email' => mb_strtolower(trim((string) $request->input('email')))]);
        \App\Support\Catalogo::prepararServicios($request);
        \App\Support\Horario::componer($request);
        $datos = $request->validate($this->reglas($request) + [
            'responsable' => ['required', 'string', 'min:3', 'max:100'],
            'email' => ReglasCuenta::correo(),
            'password' => ReglasCuenta::password(false),
        ]);
        $vet = $this->servicio->afiliar($datos);
        $u = $vet->personal()->first();
        app(\App\Services\VerificacionService::class)->enviar($u, 'email');

        return back()->with('ok', "Veterinaria afiliada. Le enviamos a {$u->email} el correo para confirmar su cuenta.");
    }

    public function update(Request $request, Veterinaria $veterinaria)
    {
        \App\Support\Catalogo::prepararServicios($request);
        \App\Support\Horario::componer($request);
        $this->servicio->actualizar($veterinaria, $request->validate($this->reglas($request)));

        return back()->with('ok', 'Veterinaria actualizada');
    }

    public function toggle(Veterinaria $veterinaria)
    {
        $this->servicio->alternar($veterinaria);

        return back()->with('ok', $veterinaria->activa ? 'Veterinaria activada' : 'Veterinaria desactivada · su cuenta queda bloqueada');
    }

    private function reglas(Request $request): array
    {
        return ReglasCuenta::whatsappClinica($request) + [
            'nombre' => ['required', 'string', 'min:3', 'max:100'],
            'zona' => ['required', 'string', 'max:60'],
            'direccion' => ['nullable', 'string', 'max:150'],
            'telefono' => ['required', 'regex:/^\d{7,10}$/'],
            'horario' => ['nullable', 'string', 'max:80'],
            'servicios' => ['required', 'array', 'min:1', 'max:20'],
            'servicios.*' => ['string', 'max:40'],
            'plan_suscripcion' => ['nullable', Rule::in(array_keys(Catalogo::SUSCRIPCIONES))],
        ];
    }
}
