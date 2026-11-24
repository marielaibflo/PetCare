<?php

namespace App\Http\Controllers\Propietario;

use App\Http\Controllers\Controller;
use App\Models\Cita;
use App\Models\Mascota;
use App\Models\Veterinaria;
use App\Services\CitaService;
use App\Services\PagoService;
use App\Support\ReglasPago;
use App\Support\Catalogo;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CitaController extends Controller
{
    use Concerns;

    public function __construct(private CitaService $servicio) {}

    public function index(Request $request)
    {
        $ids = $request->user()->mascotas()->pluck('id');
        $todas = Cita::with(['mascota', 'veterinaria', 'pagos', 'resena'])->whereIn('mascota_id', $ids)->get();
        $proximas = $todas->filter(fn ($c) => $c->abierta() && $c->diasFaltantes() >= 0)->sortBy(fn ($c) => $c->fecha->format('Y-m-d').$c->hora)->values();

        return view('propietario.citas', [
            'proximas' => $proximas,
            'anteriores' => $todas->diff($proximas)->sortByDesc(fn ($c) => $c->fecha->format('Y-m-d').$c->hora)->values(),
        ]);
    }

    public function store(Request $request)
    {
        $vetElegida = Veterinaria::find($request->integer('veterinaria_id'));
        $otro = Catalogo::prepararServicioOtro($request, $vetElegida?->servicios ?? []);
        $datos = $request->validate([
            'mascota_id' => ['required', 'integer'],
            'veterinaria_id' => ['required', 'integer', 'exists:veterinarias,id'],
            'servicio' => ['required', 'string', 'max:40'],
            'fecha' => ['required', 'date', 'before_or_equal:'.today()->addMonths(3)->toDateString()],
            'hora' => ['required', Rule::in(Catalogo::HORAS)],
            'nota' => ['nullable', 'string', 'max:200'],
            'urgente' => ['nullable', 'boolean'],
        ], [
            'mascota_id.required' => 'Selecciona la mascota que llevarás a la cita.',
            'veterinaria_id.required' => 'Selecciona la veterinaria.',
            'servicio.required' => 'Selecciona el servicio que necesitas.',
            'fecha.required' => 'Elige la fecha de la cita.',
            'fecha.before_or_equal' => 'Solo se pueden agendar citas hasta 3 meses adelante.',
            'hora.required' => 'Elige la hora de la cita.',
            'hora.in' => 'Elige una hora de la lista.',
        ]);
        $mascota = $this->propia($request, Mascota::findOrFail($datos['mascota_id']));
        $cita = $this->servicio->solicitar($mascota, Veterinaria::findOrFail($datos['veterinaria_id']), $datos + ['otro' => $otro]);

        $ok = 'Cita solicitada para el '.$cita->fecha->format('d/m/Y').' a las '.$cita->hora.'.';
        // Si hay algo que pagar, el siguiente paso es el pago (tarjeta, QR de la clínica o WhatsApp).
        if ($cita->monto_estimado !== null && $cita->monto_estimado - (float) $cita->cubierto_estimado > 0) {
            return redirect()->route('propietario.citas.pago', $cita)->with('ok', $ok.' Último paso: paga tu parte.');
        }

        return redirect()->route('propietario.citas.index')->with('ok', $ok.($cita->monto_estimado !== null ? ' Tu plan cubre todo el servicio.' : ''));
    }

    /** Estimado en vivo de lo que pagará el dueño según su plan (formulario de cita). */
    public function estimar(Request $request)
    {
        $mascota = Mascota::where('propietario_id', $request->user()->id)->find($request->integer('mascota_id'));
        $vet = Veterinaria::activas()->find($request->integer('veterinaria_id'));
        if ($request->input('servicio') === Catalogo::OTRO_SERVICIO) {
            return response()->json(['ok' => true, 'precio' => null, 'cubierto' => null, 'pagar' => null, 'plan' => null, 'porcentaje' => null,
                'notas' => ['La clínica te confirmará el precio de este servicio. Los servicios «Otro» no los cubre el plan.']]);
        }
        if (! $mascota || ! $vet || ! $vet->ofrece((string) $request->input('servicio'))) {
            return response()->json(['ok' => false]);
        }

        return response()->json(['ok' => true] + $this->servicio->estimar($mascota, $vet, $request->input('servicio')));
    }

    /** Pantalla de pago de una cita: el dueño paga a la veterinaria la parte que no cubre su plan. */
    public function pago(Request $request, Cita $cita)
    {
        abort_unless($cita->mascota?->propietario_id === $request->user()->id, 404);
        $cita->load(['mascota.afiliacionActiva.plan', 'veterinaria', 'pagos']);

        return view('propietario.pagar-cita', ['cita' => $cita, 'pago' => $cita->pagos->whereIn('estado', ['pendiente', 'aprobado'])->first(),
            'monto' => round((float) $cita->monto_estimado - (float) $cita->cubierto_estimado, 2)]);
    }

    public function pagar(Request $request, Cita $cita, PagoService $pagos)
    {
        abort_unless($cita->mascota?->propietario_id === $request->user()->id, 404);
        $datos = ReglasPago::validar($request, []);
        $pago = $pagos->solicitarCita($request->user(), $cita, $datos, $request->file('comprobante'));

        return redirect()->route('propietario.citas.index')->with('pago_whatsapp', $pago->id)
            ->with('ok', $pago->metodo === 'whatsapp' ? 'Pago registrado. Último paso: pide el QR por WhatsApp.' : 'Pago enviado a '.$cita->veterinaria->nombre.' para su verificación.');
    }

    public function cancelar(Request $request, Cita $cita)
    {
        abort_unless($this->servicio->puedeGestionar($request->user(), $cita), 404);
        $this->servicio->cancelar($cita, $request->user());

        return back()->with('ok', 'Cita cancelada');
    }
}
