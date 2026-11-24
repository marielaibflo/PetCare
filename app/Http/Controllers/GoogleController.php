<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\VerificacionService;
use App\Support\Catalogo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Inicio de sesión con la cuenta de Google (OAuth 2.0).
 * La persona escribe su contraseña en la página de Google, no en PetCare.
 * Google confirma el correo, por eso la cuenta queda con el correo verificado.
 * Se configura en .env con GOOGLE_CLIENT_ID y GOOGLE_CLIENT_SECRET; si faltan, el botón no se muestra.
 */
class GoogleController extends Controller
{
    private const AUTORIZAR = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const TOKEN = 'https://oauth2.googleapis.com/token';
    private const PERFIL = 'https://openidconnect.googleapis.com/v1/userinfo';

    public static function activo(): bool
    {
        return filled(config('petcare.google.client_id')) && filled(config('petcare.google.client_secret'));
    }

    /**
     * En Windows, PHP muchas veces no trae la lista de certificados (error cURL 60).
     * Si no está configurada en php.ini, se usa la lista de Mozilla incluida en resources/certs.
     */
    private function http()
    {
        $propia = resource_path('certs/cacert.pem');
        $sinLista = ! ini_get('curl.cainfo') && ! ini_get('openssl.cafile');

        return Http::timeout(15)->withOptions($sinLista && is_file($propia) ? ['verify' => $propia] : []);
    }

    private function urlRetorno(): string
    {
        return config('petcare.google.redirect') ?: route('google.callback');
    }

    /** Envía a la persona a la página de Google para elegir su cuenta. */
    public function redirigir(Request $request, string $rol)
    {
        if (! self::activo()) {
            return redirect()->route('login', $rol)->withErrors(['email' => 'El inicio de sesión con Google aún no está configurado.']);
        }
        $estado = Str::random(40);
        $request->session()->put('google_oauth', ['estado' => $estado, 'rol' => $rol, 'recordar' => true]);

        return redirect()->away(self::AUTORIZAR.'?'.http_build_query([
            'client_id' => config('petcare.google.client_id'),
            'redirect_uri' => $this->urlRetorno(),
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'state' => $estado,
            'prompt' => 'select_account',
        ]));
    }

    /** Google vuelve aquí con un código: se cambia por los datos de la cuenta y se inicia sesión. */
    public function volver(Request $request)
    {
        $guardado = $request->session()->pull('google_oauth');
        $rol = $guardado['rol'] ?? 'propietario';
        $error = fn (string $m) => redirect()->route('login', $rol)->withErrors(['email' => $m]);

        if (! $guardado || ! hash_equals($guardado['estado'], (string) $request->query('state'))) {
            return $error('La sesión con Google expiró. Vuelve a intentarlo.');
        }
        if ($request->filled('error') || ! $request->filled('code')) {
            return $error('No se completó el inicio de sesión con Google.');
        }

        $token = $this->http()->asForm()->post(self::TOKEN, [
            'code' => $request->query('code'),
            'client_id' => config('petcare.google.client_id'),
            'client_secret' => config('petcare.google.client_secret'),
            'redirect_uri' => $this->urlRetorno(),
            'grant_type' => 'authorization_code',
        ]);
        if (! $token->ok() || ! $token->json('access_token')) {
            return $error('Google no confirmó el inicio de sesión. Vuelve a intentarlo.');
        }
        $perfil = $this->http()->withToken($token->json('access_token'))->get(self::PERFIL);
        $email = mb_strtolower((string) $perfil->json('email'));
        if (! $perfil->ok() || $email === '' || ! $perfil->json('email_verified')) {
            return $error('Tu cuenta de Google no tiene un correo verificado.');
        }

        $user = User::where('email', $email)->first();

        if (! $user) {
            // Solo los propietarios pueden crear su cuenta con Google; las clínicas pasan por la afiliación.
            if ($rol !== User::PROPIETARIO) {
                return $error($rol === User::VETERINARIA
                    ? 'No hay una veterinaria registrada con '.$email.'. Solicita la afiliación de tu clínica.'
                    : 'No hay una cuenta de administración con '.$email.'.');
            }
            $nombre = trim((string) $perfil->json('name')) ?: Str::before($email, '@');
            $user = User::create([
                'name' => Str::limit($nombre, 100, ''), 'email' => $email, 'password' => Str::password(32),
                'rol' => User::PROPIETARIO, 'activo' => true,
            ]);
        }

        if ($user->rol !== $rol) {
            return $error('Esta cuenta pertenece al perfil de '.mb_strtolower(Catalogo::ROLES[$user->rol]).'. Selecciónalo arriba para ingresar.');
        }
        if ($user->rol === User::VETERINARIA && $user->veterinaria?->estado === 'pendiente') {
            return $error('La solicitud de afiliación de tu clínica está en revisión. Te avisaremos por correo y WhatsApp cuando sea aprobada.');
        }
        if ($user->rol === User::VETERINARIA && $user->veterinaria?->estado === 'rechazada') {
            return $error('La solicitud de tu clínica fue rechazada. Motivo: '.$user->veterinaria->motivo_rechazo);
        }
        if (! $user->activo) {
            return $error('La cuenta está desactivada. Comunícate con el administrador.');
        }
        if ($user->rol === User::VETERINARIA && ! $user->veterinaria?->activa) {
            return $error('La veterinaria aún no está habilitada en la plataforma.');
        }

        // Google ya verificó el correo
        if (! $user->email_verified_at) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        Auth::login($user, $guardado['recordar'] ?? true);
        $request->session()->regenerate();

        if (! $user->verificado()) {
            // Igual que el registro normal: se envía el código de WhatsApp (en modo demostración se muestra en pantalla)
            if (filled($user->telefono)) {
                VerificacionController::recordarDemo($request, app(VerificacionService::class)->enviarPendientes($user));
            }

            return redirect()->route('verificacion.show')->with('ok', 'Entraste con Google. Solo falta confirmar tu celular de WhatsApp.');
        }

        return redirect()->intended($user->rutaInicio())->with('ok', 'Bienvenido/a, '.$user->primerNombre());
    }
}
