<?php

namespace App\Support;

use App\Models\User;
use App\Rules\CelularValido;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/** Reglas de validación de cuentas, compartidas por el registro, el perfil y el administrador. */
final class ReglasCuenta
{
    /** Errores de tipeo frecuentes en dominios de correo. */
    private const TYPOS = [
        'gmial.com' => 'gmail.com', 'gmal.com' => 'gmail.com', 'gmai.com' => 'gmail.com', 'gamil.com' => 'gmail.com',
        'gmail.co' => 'gmail.com', 'gmail.con' => 'gmail.com', 'gmail.cm' => 'gmail.com', 'hotmial.com' => 'hotmail.com',
        'hotmal.com' => 'hotmail.com', 'hotmail.con' => 'hotmail.com', 'hotmai.com' => 'hotmail.com',
        'outlok.com' => 'outlook.com', 'outloo.com' => 'outlook.com', 'yahooo.com' => 'yahoo.com', 'yaho.com' => 'yahoo.com',
    ];

    /** Deja el correo en minúsculas y el celular solo con dígitos antes de validar. */
    public static function normalizar(Request $request): void
    {
        $pais = $request->input('codigo_pais', Paises::PREDETERMINADO);
        $request->merge([
            'email' => mb_strtolower(trim((string) $request->input('email'))),
            'codigo_pais' => $pais,
            'telefono' => $request->filled('telefono') ? Paises::normalizar($pais, $request->input('telefono')) : null,
        ]);
    }

    public static function correo(?User $ignorar = null, bool $explicar = false): array
    {
        return [
            'required', 'max:150',
            self::hayDns() ? 'email:rfc,dns' : 'email:rfc',
            function (string $attr, mixed $valor, Closure $fail) {
                $dominio = mb_strtolower((string) substr(strrchr((string) $valor, '@') ?: '', 1));
                if (isset(self::TYPOS[$dominio])) {
                    $fail('Revisa el correo: ¿quisiste escribir @'.self::TYPOS[$dominio].'?');
                }
            },
            $explicar ? function (string $attr, mixed $valor, Closure $fail) {
                $u = User::where('email', mb_strtolower(trim((string) $valor)))->first();
                if (! $u) {
                    return;
                }
                if (! $u->email_verified_at) {
                    session()->flash('correo_pendiente', $u->email);
                    $fail('Ya empezaste un registro con este correo, pero todavía no lo confirmaste. Pide que te enviemos el enlace otra vez.');
                } else {
                    session()->flash('correo_registrado', $u->rol);
                    $fail('Este correo ya tiene una cuenta en PetCare. Inicia sesión o usa otro correo.');
                }
            } : Rule::unique('users', 'email')->ignore($ignorar?->id),
        ];
    }

    /**
     * La comprobación del dominio necesita internet. Si la computadora no tiene conexión
     * (por ejemplo, en una presentación sin red) se omite para no bloquear los registros.
     */
    private static function hayDns(): bool
    {
        if (! config('petcare.validar_dns')) {
            return false;
        }

        return Cache::remember('petcare.dns_disponible', now()->addMinutes(10), fn () => @checkdnsrr('gmail.com', 'MX'));
    }

    public static function codigoPais(): array
    {
        return ['required', Rule::in(array_keys(Paises::LISTA))];
    }

    /** Celular válido para el país y que no esté registrado por otro propietario. */
    public static function celular(Request $request, ?User $ignorar = null, bool $obligatorio = true): array
    {
        return [
            $obligatorio ? 'required' : 'nullable',
            new CelularValido($request->input('codigo_pais')),
            Rule::unique('users', 'telefono')->where('codigo_pais', $request->input('codigo_pais'))
                ->where('rol', User::PROPIETARIO)->ignore($ignorar?->id),
        ];
    }

    /** Celular de WhatsApp de una clínica (opcional): recibe el aviso de cada cita nueva. */
    public static function whatsappClinica(Request $request): array
    {
        $pais = $request->input('whatsapp_pais', Paises::PREDETERMINADO);
        $request->merge(['whatsapp_pais' => $pais,
            'whatsapp' => $request->filled('whatsapp') ? Paises::normalizar($pais, $request->input('whatsapp')) : null]);

        return [
            'whatsapp_pais' => ['required', Rule::in(array_keys(Paises::LISTA))],
            'whatsapp' => ['nullable', new CelularValido($pais)],
        ];
    }

    /** Contraseña segura: 8+ caracteres, mayúscula, minúscula, número y símbolo. */
    public static function password(bool $confirmar = true): array
    {
        return array_values(array_filter(['required', $confirmar ? 'confirmed' : null, Password::defaults()]));
    }
}
