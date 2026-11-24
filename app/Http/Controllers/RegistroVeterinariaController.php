<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\VerificacionService;
use App\Services\VeterinariaService;
use App\Support\Archivos;
use App\Support\Catalogo;
use App\Support\ReglasCuenta;
use App\Support\ReglasPago;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Registro en línea de clínicas veterinarias (todo desde el sistema, sin trámites presenciales).
 * 1) La clínica llena sus datos, elige suscripción y registra su forma de pago (mes gratis).
 * 2) Confirma su correo con el botón del correo o el código.
 * 3) Recién entonces la solicitud llega al administrador, que la aprueba o rechaza.
 */
class RegistroVeterinariaController extends Controller
{
    public function show()
    {
        return view('auth.registro-veterinaria');
    }

    public function store(Request $request, VeterinariaService $servicio, VerificacionService $verificacion)
    {
        $request->merge(['email' => mb_strtolower(trim((string) $request->input('email')))]);
        Catalogo::prepararServicios($request);
        \App\Support\Horario::componer($request);
        $datos = $request->validate(ReglasCuenta::whatsappClinica($request) + ReglasPago::reglasCobro() + [
            'nombre' => ['required', 'string', 'min:3', 'max:100', 'unique:veterinarias,nombre'],
            'nit' => ['required', 'regex:/^\d{6,15}$/'],
            'registro' => ['required', 'string', 'max:30', 'regex:/^[A-Za-z0-9\/\-. ]+$/'],
            'matricula' => ['required', 'string', 'max:30', 'regex:/^[A-Za-z0-9\/\-. ]+$/'],
            'zona' => ['required', 'string', 'max:60'],
            'direccion' => ['required', 'string', 'max:150'],
            'telefono' => ['required', 'regex:/^\d{7,10}$/'],
            'horario' => ['required', 'string', 'max:80'],
            'servicios' => ['required', 'array', 'min:1', 'max:20'],
            'servicios.*' => ['string', 'max:40'],
            'documento' => array_merge(['required'], Archivos::IMAGEN_O_PDF),
            'plan_suscripcion' => ['required', Rule::in(array_keys(Catalogo::SUSCRIPCIONES))],
            'responsable' => ['required', 'string', 'min:3', 'max:100'],
            'email' => ReglasCuenta::correo(null, true),
            'password' => ReglasCuenta::password(),
            'acepto' => ['accepted'],
        ], ReglasPago::mensajes() + [
            'nombre.unique' => 'Ya existe una veterinaria registrada con ese nombre.',
            'nit.regex' => 'El NIT debe tener solo números (6 a 15 dígitos).',
            'registro.required' => 'Escribe el N.º de registro o licencia de funcionamiento de la clínica.',
            'matricula.required' => 'Escribe el N.º de matrícula profesional del veterinario responsable.',
            'documento.required' => 'Sube la licencia de funcionamiento o el documento que acredite a la clínica.',
            'metodo.required' => 'Elige la forma de pago para después del mes gratis.',
            'acepto.accepted' => 'Debes aceptar los términos y condiciones de PetCare.',
        ]);
        ReglasPago::verificarTarjeta($datos);
        $this->exigirWhatsapp($request);
        $vet = $servicio->solicitar($datos, $request->file('documento'));

        $user = $vet->personal()->first();
        $request->session()->put('afiliacion_user', $user->id);
        VerificacionController::recordarDemo($request, ['email' => $verificacion->enviar($user, 'email')]);

        return redirect()->route('registro.veterinaria.correo')->with('ok', "Solicitud de {$vet->nombre} guardada. Confirma tu correo para enviarla.");
    }

    /** Paso 2: «Revisa tu correo» (botón del correo o código de 6 dígitos). */
    public function correo(Request $request)
    {
        $user = $this->pendiente($request);
        if (! $user) {
            return redirect()->route('registro.veterinaria');
        }

        return view('auth.afiliacion-correo', ['user' => $user, 'demo' => session('codigos_demo', [])]);
    }

    public function confirmarCodigo(Request $request, VerificacionService $verificacion)
    {
        $user = $this->pendiente($request) ?? abort(404);
        $datos = $request->validate(['codigo_email' => ['required', 'digits:6']],
            ['codigo_email.required' => 'Escribe el código de 6 dígitos que te enviamos.', 'codigo_email.digits' => 'El código tiene 6 dígitos.']);
        $verificacion->comprobar($user, 'email', $datos['codigo_email']);
        $request->session()->forget('codigos_demo');

        return redirect()->route('registro.veterinaria.correo')->with('ok', 'Correo confirmado. Tu solicitud ya llegó a PetCare.');
    }

    public function reenviarCodigo(Request $request, VerificacionService $verificacion)
    {
        $user = $this->pendiente($request) ?? abort(404);
        VerificacionController::recordarDemo($request, ['email' => $verificacion->enviar($user, 'email')]);

        return back()->with('ok', 'Te enviamos un correo nuevo a '.$user->email);
    }

    public function terminos()
    {
        return view('auth.terminos');
    }

    private function pendiente(Request $request): ?User
    {
        return User::where('rol', User::VETERINARIA)->with('veterinaria')->find($request->session()->get('afiliacion_user'));
    }

    private function exigirWhatsapp(Request $request): void
    {
        if (! $request->input('whatsapp')) {
            throw ValidationException::withMessages(['whatsapp' => 'Registra el celular con WhatsApp de la clínica: ahí recibirá las citas.']);
        }
    }
}
