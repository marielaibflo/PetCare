<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\VerificacionService;
use App\Support\ReglasCuenta;
use Illuminate\Http\Request;

/** Pantalla para confirmar el correo y el celular (WhatsApp) con los códigos recibidos. */
class VerificacionController extends Controller
{
    public function __construct(private VerificacionService $verificacion) {}

    public function show(Request $request)
    {
        $user = $request->user();
        if ($user->verificado()) {
            return redirect($user->rutaInicio());
        }

        return view('auth.verificar', ['user' => $user, 'demo' => session('codigos_demo', [])]);
    }

    public function verificar(Request $request, string $canal)
    {
        $campo = 'codigo_'.$canal;
        $datos = $request->validate([$campo => ['required', 'digits:6']], ["{$campo}.digits" => 'El código tiene 6 dígitos.', "{$campo}.required" => 'Escribe el código de 6 dígitos.']);
        $this->verificacion->comprobar($request->user(), $canal, $datos[$campo]);
        $user = $request->user()->fresh();

        if ($user->verificado()) {
            $request->session()->forget('codigos_demo');

            return redirect($user->rutaInicio())->with('ok', $user->esRol(User::PROPIETARIO) ? '¡Cuenta verificada! Ahora agrega a tu mascota.' : '¡Correo confirmado!');
        }

        return back()->with('ok', $canal === 'email' ? 'Correo verificado' : 'Celular verificado');
    }

    public function reenviar(Request $request, string $canal)
    {
        $codigo = $this->verificacion->enviar($request->user(), $canal);
        $this->recordarDemo($request, [$canal => $codigo]);

        return back()->with('ok', 'Te enviamos un código nuevo a '.VerificacionService::destinoVisible($request->user(), $canal));
    }

    /** Botón «Confirmar mi correo» del correo (enlace firmado). Funciona aunque no haya sesión iniciada. */
    public function enlace(Request $request, User $usuario, string $hash)
    {
        if (! hash_equals(sha1(mb_strtolower($usuario->email_nuevo ?: $usuario->email)), $hash)) {
            return response()->view('auth.correo-confirmado', ['estado' => 'invalido', 'user' => $usuario], 410);
        }
        $ya = $usuario->email_verified_at !== null && ! $usuario->email_nuevo;
        $this->verificacion->correoConfirmado($usuario);
        $usuario->refresh();

        return view('auth.correo-confirmado', ['estado' => $ya ? 'ya' : 'ok', 'user' => $usuario,
            'sesion' => $request->user()?->is($usuario) ?? false]);
    }

    /** Si el usuario escribió mal su número, puede corregirlo antes de verificar. */
    public function cambiarTelefono(Request $request)
    {
        $user = $request->user();
        abort_if($user->telefono_verificado_at, 403);
        ReglasCuenta::normalizar($request->merge(['email' => $user->email]));
        $datos = $request->validate(['codigo_pais' => ReglasCuenta::codigoPais(), 'telefono' => ReglasCuenta::celular($request, $user)]);
        $user->update($datos);
        $codigo = $this->verificacion->enviar($user, 'whatsapp');
        $this->recordarDemo($request, ['whatsapp' => $codigo]);

        return back()->with('ok', 'Número actualizado. Te enviamos un código por WhatsApp.');
    }

    /** En modo demostración los códigos se muestran en pantalla (en producción nunca). */
    public static function recordarDemo(Request $request, array $codigos): void
    {
        if (config('petcare.demo')) {
            $request->session()->put('codigos_demo', $codigos + $request->session()->get('codigos_demo', []));
        }
    }
}
