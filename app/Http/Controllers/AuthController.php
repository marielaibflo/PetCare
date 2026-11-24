<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\VerificacionService;
use App\Support\Catalogo;
use App\Support\ReglasCuenta;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public const DEMO = [
        'propietario' => ['maria@correo.com', '123456'],
        'veterinaria' => ['veterinaria@clinica.com', 'vet123'],
        'administrador' => ['admin@petcare.com', 'admin123'],
    ];

    public function welcome()
    {
        return view('auth.welcome');
    }

    public function showLogin(string $rol)
    {
        $demo = self::DEMO[$rol];
        // Base limpia: el propietario de prueba es Juan Pérez; con la demo completa, María López
        if ($rol === 'propietario' && ! \App\Models\User::where('email', $demo[0])->exists()) {
            $demo = ['juan@correo.com', '123456'];
        }

        return view('auth.login', ['rol' => $rol, 'demo' => config('app.env') !== 'production' ? $demo : null]);
    }

    public function login(Request $request, string $rol)
    {
        $cred = $request->validate(['email' => ['required', 'email'], 'password' => ['required']]);
        $user = User::where('email', mb_strtolower($cred['email']))->first();

        // Mensaje genérico: no revela si el correo existe.
        if (! $user || ! Auth::validate(['email' => $user->email, 'password' => $cred['password']])) {
            return back()->withErrors(['email' => __('auth.failed')])->onlyInput('email');
        }
        if ($user->rol !== $rol) {
            return back()->withErrors(['email' => 'Esta cuenta pertenece al perfil de '.mb_strtolower(Catalogo::ROLES[$user->rol]).'. Selecciónalo arriba para ingresar.'])->onlyInput('email');
        }
        if ($user->rol === User::VETERINARIA && $user->veterinaria?->estado === 'pendiente') {
            if (! $user->email_verified_at) {
                $request->session()->flash('correo_pendiente', $user->email);

                return back()->withErrors(['email' => 'Primero confirma tu correo con el botón que te enviamos: así tu solicitud llega a PetCare.'])->onlyInput('email');
            }

            return back()->withErrors(['email' => 'La solicitud de afiliación de tu clínica está en revisión. Te avisaremos por correo y WhatsApp cuando sea aprobada.'])->onlyInput('email');
        }
        if ($user->rol === User::VETERINARIA && $user->veterinaria?->estado === 'rechazada') {
            return back()->withErrors(['email' => 'La solicitud de tu clínica fue rechazada. Motivo: '.$user->veterinaria->motivo_rechazo])->onlyInput('email');
        }
        if (! $user->activo) {
            return back()->withErrors(['email' => 'La cuenta está desactivada. Comunícate con el administrador.'])->onlyInput('email');
        }
        if ($user->rol === User::VETERINARIA && ! $user->veterinaria?->activa) {
            return back()->withErrors(['email' => 'La veterinaria aún no está habilitada en la plataforma.'])->onlyInput('email');
        }

        Auth::login($user, $request->boolean('recordar'));
        $request->session()->regenerate();

        return redirect()->intended($user->rutaInicio())->with('ok', 'Bienvenido/a, '.$user->primerNombre());
    }

    public function showRegistro()
    {
        return view('auth.registro');
    }

    /**
     * Registro de propietario: correo válido, celular con código de país y contraseña segura.
     * La cuenta queda pendiente hasta confirmar los códigos enviados al correo y por WhatsApp.
     */
    public function registro(Request $request, VerificacionService $verificacion)
    {
        ReglasCuenta::normalizar($request);
        $datos = $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:100', 'regex:/^[\pL\s.\'-]+$/u'],
            'email' => ReglasCuenta::correo(null, true),
            'codigo_pais' => ReglasCuenta::codigoPais(),
            'telefono' => ReglasCuenta::celular($request),
            'password' => ReglasCuenta::password(),
        ], ['name.regex' => 'El nombre solo puede tener letras y espacios.']);

        $user = User::create($datos + ['rol' => User::PROPIETARIO, 'activo' => true]);
        Auth::login($user);
        $request->session()->regenerate();

        VerificacionController::recordarDemo($request, $verificacion->enviarPendientes($user));

        return redirect()->route('verificacion.show')->with('ok', 'Cuenta creada. Confirma tu correo con el botón que te enviamos y tu celular con el código de WhatsApp.');
    }

    /** «Envíame el enlace otra vez» para un registro que quedó sin confirmar (propietario o clínica). */
    public function reenviarEnlace(Request $request, VerificacionService $verificacion)
    {
        $email = mb_strtolower(trim((string) $request->validate(['email' => ['required', 'email', 'max:150']])['email']));
        $u = User::where('email', $email)->whereNull('email_verified_at')->first();
        if ($u) {
            $codigo = $verificacion->enviar($u, 'email');
            if ($u->esRol(User::VETERINARIA)) {
                $request->session()->put('afiliacion_user', $u->id);
                VerificacionController::recordarDemo($request, ['email' => $codigo]);

                return redirect()->route('registro.veterinaria.correo')->with('ok', "Te enviamos un correo nuevo a {$email}.");
            }
        }

        // Mismo mensaje exista o no la cuenta (no revela qué correos están registrados)
        return redirect()->route('login', 'propietario')->with('ok', "Si {$email} tiene un registro pendiente, te enviamos un correo nuevo con el botón «Confirmar mi correo». Luego inicia sesión para confirmar tu celular.");
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('welcome')->with('ok', 'Sesión cerrada');
    }
}
