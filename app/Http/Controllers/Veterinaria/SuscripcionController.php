<?php

namespace App\Http\Controllers\Veterinaria;

use App\Http\Controllers\Controller;
use App\Models\Ajuste;
use App\Models\Pago;
use App\Services\PagoService;
use App\Support\Archivos;
use App\Support\Catalogo;
use App\Support\ReglasPago;
use App\Support\ComprobantePdf;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Suscripción de la clínica a PetCare: ver estado, renovar o cambiar de plan pagando con QR. */
class SuscripcionController extends Controller
{
    public function show(Request $request)
    {
        $vet = $request->user()->veterinaria;

        return view('veterinaria.suscripcion', [
            'vet' => $vet,
            'pagos' => Pago::where('veterinaria_id', $vet->id)->where('concepto', 'suscripcion')->latest()->get(),
            'banco' => ['banco' => Ajuste::valor('pago_banco'), 'titular' => Ajuste::valor('pago_titular'), 'cuenta' => Ajuste::valor('pago_cuenta')],
        ]);
    }

    public function cancelar(Request $request, \App\Services\VeterinariaService $servicio)
    {
        $datos = $request->validate(['motivo' => ['nullable', 'string', 'max:200']]);
        $servicio->cancelarSuscripcion($request->user()->veterinaria, $datos['motivo'] ?? null);

        return back()->with('ok', 'Suscripción cancelada. Tu clínica sigue activa hasta el fin del período.');
    }

    public function reactivar(Request $request, \App\Services\VeterinariaService $servicio)
    {
        $servicio->reactivarSuscripcion($request->user()->veterinaria);

        return back()->with('ok', 'Suscripción reactivada.');
    }

    /** Eliminar la cuenta: se confirma con el nombre de la clínica y la contraseña. */
    public function eliminarCuenta(Request $request, \App\Services\VeterinariaService $servicio)
    {
        $vet = $request->user()->veterinaria;
        $request->validate(['confirmar' => ['required', 'string'], 'password' => ['required', 'current_password']],
            ['password.current_password' => 'La contraseña no es correcta.', 'confirmar.required' => 'Escribe el nombre de tu clínica para confirmar.']);
        if (mb_strtolower(trim($request->input('confirmar'))) !== mb_strtolower(trim($vet->nombre))) {
            throw \Illuminate\Validation\ValidationException::withMessages(['confirmar' => "Escribe exactamente «{$vet->nombre}»."]);
        }
        $servicio->eliminarCuenta($vet, $request->user());
        \Illuminate\Support\Facades\Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/')->with('ok', 'Tu cuenta fue eliminada. Gracias por haber sido parte de PetCare.');
    }

    public function pagar(Request $request, PagoService $pagos)
    {
        $datos = ReglasPago::validar($request, [
            'plan_suscripcion' => ['required', Rule::in(array_keys(Catalogo::SUSCRIPCIONES))],
            'meses' => ['required', Rule::in(['1', '12'])],
            'facturar_a' => ['required', 'string', 'min:3', 'max:100'],
            'documento' => ['required', 'regex:/^\d{5,15}(-?[A-Za-z0-9]{1,3})?$/'],
            'acepto' => ['accepted'],
        ], [
            'plan_suscripcion.required' => 'Elige la suscripción.',
            'meses.required' => 'Elige si pagarás 1 mes o 12 meses.',
            'facturar_a.required' => 'Escribe la razón social para la factura.',
            'documento.required' => 'Escribe el NIT de la clínica.',
            'documento.regex' => 'Escribe un NIT o CI válido (solo números).',
            'acepto.accepted' => 'Lee y acepta los términos y condiciones para continuar.',
        ]);
        $pago = $pagos->solicitarSuscripcion($request->user(), $request->user()->veterinaria, $datos, $request->file('comprobante'));

        return back()->with('pago_whatsapp', $pago->id)
            ->with('ok', $pago->metodo === 'whatsapp' ? 'Solicitud guardada. Último paso: pide el QR por WhatsApp.' : 'Pago enviado. PetCare lo verificará y activará tu suscripción.');
    }

    /** Cambiar la forma de cobro mensual (tarjeta para cobro automático, o QR/WhatsApp con aviso). */
    public function cobro(Request $request)
    {
        $datos = $request->validate(ReglasPago::reglasCobro(), ReglasPago::mensajes());
        ReglasPago::verificarTarjeta($datos);
        $vet = $request->user()->veterinaria;
        $vet->update(ReglasPago::datosCobro($datos) + ['aviso_renovacion_at' => null]);

        return back()->with('ok', 'Forma de cobro actualizada: '.$vet->fresh()->cobroTexto());
    }

    public function comprobantePdf(Request $request, Pago $pago)
    {
        abort_unless($pago->veterinaria_id === $request->user()->veterinaria_id && $pago->estado === 'aprobado', 404);

        return response(ComprobantePdf::generar($pago->load(['user', 'veterinaria', 'revisor'])), 200, [
            'Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="Factura-'.$pago->numero.'.pdf"',
        ]);
    }
}
