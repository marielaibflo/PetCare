<?php

namespace App\Http\Controllers\Propietario;

use App\Http\Controllers\Controller;
use App\Models\Ajuste;
use App\Models\Mascota;
use App\Models\Pago;
use App\Models\Plan;
use App\Services\PagoService;
use App\Services\PlanService;
use App\Support\Archivos;
use App\Support\Catalogo;
use App\Support\ReglasPago;
use App\Support\ComprobantePdf;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Planes del propietario: comparar, solicitar y concluir el pago por el WhatsApp de PetCare.
 * El plan se activa cuando el administrador verifica el pago.
 */
class PlanController extends Controller
{
    use Concerns;

    public function __construct(private PlanService $planes, private PagoService $pagos) {}

    public function index(Request $request)
    {
        return view('propietario.planes', [
            'planes' => Plan::activos()->orderBy('precio')->get(),
            'mascotas' => $request->user()->mascotas()->with('afiliacionActiva.plan')->get(),
        ]);
    }

    /** Formulario de solicitud: mascota, modalidad, adicional, datos del comprobante y forma de pago. */
    public function solicitar(Request $request, Plan $plan)
    {
        abort_unless(Plan::activos()->whereKey($plan->id)->exists(), 404);   // activo y dentro de su vigencia
        $mascotas = $request->user()->mascotas()->with(['afiliacionActiva.plan', 'afiliacionActiva.veterinaria'])->orderBy('nombre')->get();
        // Mascotas con contrato vigente: solo pueden renovar en su misma veterinaria
        $contratos = $mascotas->mapWithKeys(function ($m) use ($plan) {
            $a = $m->afiliacionActiva;
            $fija = $a && ! $a->puedeCancelar() && $a->veterinaria_id && $this->planes->veterinariaDisponible($a);

            return [$m->id => ['vet' => $fija ? $a->veterinaria_id : null, 'hasta' => $fija ? $a->permanencia_hasta->format('d/m/Y') : null,
                'motivo' => $plan->motivoNoAdmite($m), 'otroPlan' => $a && ! $a->puedeCancelar() && $a->plan_id !== $plan->id ? $a->plan->nombre : null,
                'especie' => $m->especie]];
        });

        return view('propietario.solicitar-plan', [
            'plan' => $plan, 'mascotas' => $mascotas, 'user' => $request->user(), 'contratos' => $contratos,
            'veterinarias' => $this->planes->veterinariasParaPlanes(),
            'mascotaElegida' => $request->query('mascota'),
            'montos' => collect(Catalogo::MODALIDADES)->keys()->mapWithKeys(fn ($m) => [$m => [
                'sin' => $this->planes->monto($plan, $m, false), 'con' => $this->planes->monto($plan, $m, true)]]),
            'banco' => ['banco' => Ajuste::valor('pago_banco'), 'titular' => Ajuste::valor('pago_titular'), 'cuenta' => Ajuste::valor('pago_cuenta')],
        ]);
    }

    public function pagar(Request $request, Plan $plan)
    {
        $datos = ReglasPago::validar($request, [
            'mascota_id' => ['required', 'integer'],
            'modalidad' => ['required', Rule::in(array_keys(Catalogo::MODALIDADES))],
            'cobertura_grave' => ['nullable', 'boolean'],
            'veterinaria_id' => ['required', 'integer', 'exists:veterinarias,id'],
            'contrato' => ['accepted'],
            'facturar_a' => ['required', 'string', 'min:3', 'max:100'],
            'documento' => ['required', 'regex:/^\d{5,15}(-?[A-Za-z0-9]{1,3})?$/'],
            'acepto' => ['accepted'],
        ], [
            'mascota_id.required' => 'Elige la mascota que tendrá el plan.',
            'modalidad.required' => 'Elige si pagarás por mes o por año.',
            'facturar_a.required' => 'Escribe el nombre o razón social para la factura.',
            'facturar_a.min' => 'El nombre para la factura debe tener al menos 3 letras.',
            'documento.required' => 'Escribe tu CI o NIT para la factura.',
            'documento.regex' => 'Escribe un CI o NIT válido (solo números, con complemento opcional).',
            'acepto.accepted' => 'Lee y acepta los términos y condiciones para continuar.',
            'veterinaria_id.required' => 'Elige la veterinaria donde usarás el plan.',
            'contrato.accepted' => 'Lee y acepta el contrato de afiliación para continuar.',
        ]);
        $mascota = $this->propia($request, Mascota::findOrFail($datos['mascota_id']));
        $pago = $this->pagos->solicitarPlan($request->user(), $mascota, $plan, $datos, $request->file('comprobante'));

        return redirect()->route('propietario.pagos.index')->with('pago_whatsapp', $pago->id)
            ->with('ok', $pago->metodo === 'whatsapp' ? 'Solicitud guardada. Último paso: pide el QR por WhatsApp.' : 'Pago enviado. PetCare lo verificará y activará el plan.');
    }

    /** Contrato de afiliación en PDF. */
    public function contrato(Request $request, Mascota $mascota)
    {
        $this->propia($request, $mascota);
        $a = $mascota->afiliacionActiva;
        abort_unless($a && $a->contrato_numero, 404);

        return response(\App\Support\ContratoPdf::generar($a), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="Contrato-'.$a->contrato_numero.'.pdf"']);
    }

    public function cancelar(Request $request, Mascota $mascota)
    {
        $this->propia($request, $mascota);
        $this->planes->cancelar($mascota);

        return back()->with('ok', 'Plan cancelado');
    }

    /** Historial de pagos del propietario. */
    public function pagos(Request $request)
    {
        return view('propietario.pagos', ['pagos' => Pago::with(['mascota', 'plan'])->where('user_id', $request->user()->id)->latest()->get()]);
    }

    public function comprobantePdf(Request $request, Pago $pago)
    {
        abort_unless($pago->user_id === $request->user()->id && $pago->estado === 'aprobado', 404);

        return response(ComprobantePdf::generar($pago->load(['user', 'mascota.afiliacionActiva.plan', 'plan', 'revisor'])), 200, [
            'Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="Factura-'.$pago->numero.'.pdf"',
        ]);
    }
}
