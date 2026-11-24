<?php

namespace App\Services;

use App\Models\CodigoVerificacion;
use App\Models\User;
use App\Support\Paises;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Verificación de la cuenta con códigos de 6 dígitos:
 *  - correo: confirma que el correo existe y es del usuario;
 *  - whatsapp: confirma que el celular existe, tiene WhatsApp y es del usuario.
 */
class VerificacionService
{
    use ReglaNegocio;

    public const CANALES = ['email', 'whatsapp'];

    public function __construct(private WhatsAppService $whatsapp) {}

    /** Genera y envía un código nuevo (invalida los anteriores). Devuelve el código para el modo demostración. */
    public function enviar(User $user, string $canal): string
    {
        $codigo = (string) random_int(100000, 999999);
        $destino = $canal === 'email' ? ($user->email_nuevo ?: $user->email) : (string) $user->telefonoInternacional();
        $this->exigir($destino !== '', 'Registra un número de celular para recibir el código.', 'telefono');

        CodigoVerificacion::where('user_id', $user->id)->where('canal', $canal)->whereNull('usado_at')->delete();
        CodigoVerificacion::create([
            'user_id' => $user->id, 'canal' => $canal, 'destino' => $destino, 'codigo' => Hash::make($codigo),
            'expira_at' => now()->addMinutes(config('petcare.verificacion.minutos')),
        ]);

        if ($canal === 'email') {
            $this->enviarCorreo($user, $codigo);
        } else {
            $this->whatsapp->enviarCodigo($destino, $codigo, $user);
        }

        return $codigo;
    }

    /** Envía los códigos que falten y devuelve [canal => código] para mostrarlos en modo demostración. */
    public function enviarPendientes(User $user): array
    {
        $codigos = [];
        if (! $user->email_verified_at) {
            $codigos['email'] = $this->enviar($user, 'email');
        }
        if (! $user->telefono_verificado_at && in_array('whatsapp', $user->canalesVerificacion(), true)) {
            $codigos['whatsapp'] = $this->enviar($user, 'whatsapp');
        }

        return $codigos;
    }

    public function comprobar(User $user, string $canal, string $codigo, ?string $bag = null): void
    {
        if ($bag) {
            try {
                $this->comprobar($user, $canal, $codigo);
            } catch (\Illuminate\Validation\ValidationException $e) {
                throw $e->errorBag($bag);
            }

            return;
        }
        $registro = CodigoVerificacion::where('user_id', $user->id)->where('canal', $canal)
            ->whereNull('usado_at')->latest('id')->first();
        $campo = 'codigo_'.$canal;

        $this->exigir((bool) $registro, 'No hay un código vigente. Pide uno nuevo.', $campo);
        $this->exigir($registro->expira_at->isFuture(), 'El código venció. Pide uno nuevo.', $campo);
        $this->exigir($registro->intentos < config('petcare.verificacion.intentos'), 'Superaste los intentos permitidos. Pide un código nuevo.', $campo);

        if (! Hash::check($codigo, $registro->codigo)) {
            $registro->increment('intentos');
            $quedan = config('petcare.verificacion.intentos') - $registro->intentos;
            $this->exigir(false, 'El código no es correcto.'.($quedan > 0 ? " Te quedan {$quedan} intentos." : ' Pide un código nuevo.'), $campo);
        }

        $registro->update(['usado_at' => now()]);
        if ($canal === 'email') {
            $this->correoConfirmado($user);
        } else {
            $user->forceFill(['telefono_verificado_at' => now()])->save();
        }
    }

    /** El correo quedó confirmado (por el botón del correo o por el código). */
    public function correoConfirmado(User $user): void
    {
        if ($user->email_nuevo) {
            // Cambio de correo confirmado: el nuevo reemplaza al anterior
            $user->forceFill(['email' => $user->email_nuevo, 'email_nuevo' => null, 'email_verified_at' => now()])->save();
        } elseif (! $user->email_verified_at) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }
        CodigoVerificacion::where('user_id', $user->id)->where('canal', 'email')->whereNull('usado_at')->update(['usado_at' => now()]);
        // Una clínica nueva recién llega al administrador cuando confirma su correo.
        if ($user->esRol(User::VETERINARIA) && $user->veterinaria?->estado === 'pendiente') {
            app(VeterinariaService::class)->avisarAdministrador($user->veterinaria);
        }
    }

    /** Enlace firmado para confirmar el correo desde la bandeja de entrada (vence en 24 horas). */
    public static function enlace(User $user): string
    {
        // Firma relativa: el enlace sigue siendo válido aunque se abra como localhost, 127.0.0.1 o el dominio real.
        return url(\Illuminate\Support\Facades\URL::temporarySignedRoute('verificacion.enlace', now()->addHours(24),
            ['usuario' => $user->id, 'hash' => sha1(mb_strtolower($user->email_nuevo ?: $user->email))], false));
    }

    /** Correo con el botón «Confirmar mi correo» y, por si acaso, el código de 6 dígitos. */
    private function enviarCorreo(User $user, string $codigo): void
    {
        $minutos = config('petcare.verificacion.minutos');
        $intro = match (true) {
            $user->esRol(User::VETERINARIA) && $user->veterinaria?->estado === 'pendiente' => "Recibimos la solicitud de afiliación de {$user->veterinaria->nombre}. Cuando confirmes tu correo, el equipo de PetCare la revisará.",
            $user->email_verified_at === null && $user->created_at?->gt(now()->subDay()) => 'Te damos la bienvenida a PetCare.',
            default => 'Pediste confirmar tu correo en PetCare.',
        };
        try {
            Mail::html(view('emails.confirmar-correo', ['nombre' => $user->primerNombre(), 'intro' => $intro, 'enlace' => self::enlace($user),
                'codigo' => $codigo, 'minutos' => $minutos])->render(),
                fn ($m) => $m->to($user->email_nuevo ?: $user->email, $user->name)->subject('PetCare · Confirma tu correo'));
        } catch (Throwable $e) {
            Log::warning("[Correo] No se pudo enviar el código a {$user->email}: {$e->getMessage()}");
        }
    }

    /** Texto para mostrar a dónde se envió el código. */
    public static function destinoVisible(User $user, string $canal): string
    {
        return $canal === 'email' ? $user->email : Paises::mostrar($user->codigo_pais, $user->telefono);
    }
}
