<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Reglas compartidas del formulario de pago (planes, suscripciones y citas).
 * Métodos: tarjeta (simulación, sin pasarela), QR con comprobante obligatorio y WhatsApp.
 */
class ReglasPago
{
    public const MARCAS = [
        'visa' => ['nombre' => 'Visa', 'largo' => [13, 16, 19], 'cvv' => 3, 'patron' => '/^4/'],
        'mastercard' => ['nombre' => 'Mastercard', 'largo' => [16], 'cvv' => 3, 'patron' => '/^(5[1-5]|2(2[2-9][1-9]|2[3-9]\d|[3-6]\d\d|7[01]\d|720))/'],
        'amex' => ['nombre' => 'American Express', 'largo' => [15], 'cvv' => 4, 'patron' => '/^3[47]/'],
    ];

    public const METODOS = ['tarjeta' => 'Tarjeta', 'qr' => 'QR', 'whatsapp' => 'WhatsApp'];

    public static function reglas(array $metodos = ['tarjeta', 'qr', 'whatsapp']): array
    {
        return [
            'metodo' => ['required', Rule::in($metodos)],
            'tarjeta_marca' => ['required_if:metodo,tarjeta', 'nullable', Rule::in(array_keys(self::MARCAS))],
            'tarjeta_numero' => ['required_if:metodo,tarjeta', 'nullable', 'string', 'max:23'],
            'tarjeta_nombre' => ['required_if:metodo,tarjeta', 'nullable', 'string', 'min:3', 'max:80', 'regex:/^[\pL\s.\'-]+$/u'],
            'tarjeta_vence' => ['required_if:metodo,tarjeta', 'nullable', 'regex:/^(0[1-9]|1[0-2])\/\d{2}$/'],
            'tarjeta_cvv' => ['required_if:metodo,tarjeta', 'nullable', 'digits_between:3,4'],
            'comprobante' => array_merge(['required_if:metodo,qr', 'nullable'], Archivos::IMAGEN_O_PDF),
        ];
    }

    public static function mensajes(): array
    {
        return [
            'metodo.required' => 'Elige cómo vas a pagar: tarjeta, QR o WhatsApp.',
            'tarjeta_marca.required_if' => 'Elige la marca de tu tarjeta (Visa, Mastercard o American Express).',
            'tarjeta_numero.required_if' => 'Escribe el número de la tarjeta.',
            'tarjeta_nombre.required_if' => 'Escribe el nombre tal como aparece en la tarjeta.',
            'tarjeta_nombre.regex' => 'El nombre del titular solo puede tener letras y espacios.',
            'tarjeta_nombre.min' => 'El nombre del titular debe tener al menos 3 letras.',
            'tarjeta_vence.required_if' => 'Escribe la fecha de vencimiento (MM/AA).',
            'tarjeta_vence.regex' => 'La fecha de vencimiento debe tener el formato MM/AA (por ejemplo 08/28).',
            'tarjeta_cvv.required_if' => 'Escribe el código de seguridad (CVV) que está atrás de la tarjeta.',
            'tarjeta_cvv.digits_between' => 'El CVV debe tener 3 dígitos (4 en American Express).',
            'comprobante.required_if' => 'Sube la foto o el PDF del comprobante del pago con QR.',
            'comprobante.mimes' => 'El comprobante debe ser una imagen (JPG, PNG o WEBP) o un PDF.',
            'comprobante.max' => 'El comprobante no debe pesar más de 10 MB.',
        ];
    }

    /** Validaciones que dependen de varios campos: marca vs. número, Luhn, CVV y vencimiento. */
    public static function verificarTarjeta(array $datos): void
    {
        if (($datos['metodo'] ?? null) !== 'tarjeta') {
            return;
        }
        $m = self::MARCAS[$datos['tarjeta_marca']];
        $num = preg_replace('/\D/', '', (string) $datos['tarjeta_numero']);
        $err = [];
        if (! in_array(strlen($num), $m['largo'], true)) {
            $err['tarjeta_numero'] = "Una tarjeta {$m['nombre']} tiene ".implode(' o ', $m['largo']).' dígitos.';
        } elseif (! preg_match($m['patron'], $num)) {
            $err['tarjeta_numero'] = "Ese número no corresponde a una tarjeta {$m['nombre']}. Revisa la marca elegida.";
        } elseif (! self::luhn($num)) {
            $err['tarjeta_numero'] = 'El número de tarjeta no es válido. Revisa que esté bien escrito.';
        }
        if (strlen((string) $datos['tarjeta_cvv']) !== $m['cvv']) {
            $err['tarjeta_cvv'] = "El CVV de {$m['nombre']} tiene {$m['cvv']} dígitos.";
        }
        [$mm, $aa] = explode('/', $datos['tarjeta_vence']);
        if (now()->format('ym') > $aa.$mm) {
            $err['tarjeta_vence'] = 'La tarjeta está vencida.';
        } elseif ((int) $aa > (int) now()->format('y') + 20) {
            $err['tarjeta_vence'] = 'Revisa el año de vencimiento.';
        }
        if ($err) {
            throw ValidationException::withMessages($err);
        }
    }

    public static function validar(Request $request, array $reglas, array $mensajes = [], array $metodos = ['tarjeta', 'qr', 'whatsapp']): array
    {
        $datos = $request->validate($reglas + self::reglas($metodos), $mensajes + self::mensajes());
        self::verificarTarjeta($datos);

        return $datos;
    }

    /** Reglas para registrar una forma de cobro (sin comprobante: todavía no se paga). */
    public static function reglasCobro(): array
    {
        return array_diff_key(self::reglas(), ['comprobante' => 1]);
    }

    /** Datos que se guardan de la forma de cobro: nunca el número completo ni el CVV. */
    public static function datosCobro(array $datos): array
    {
        $tarjeta = ($datos['metodo'] ?? null) === 'tarjeta';

        return [
            'cobro_metodo' => $datos['metodo'] ?? null,
            'cobro_marca' => $tarjeta ? $datos['tarjeta_marca'] : null,
            'cobro_ultimos' => $tarjeta ? substr(preg_replace('/\D/', '', (string) $datos['tarjeta_numero']), -4) : null,
            'cobro_titular' => $tarjeta ? mb_strtoupper(trim($datos['tarjeta_nombre'])) : null,
            'cobro_vence' => $tarjeta ? $datos['tarjeta_vence'] : null,
        ];
    }

    public static function luhn(string $num): bool
    {
        if (! preg_match('/^\d{12,19}$/', $num)) {
            return false;
        }
        $suma = 0;
        foreach (array_reverse(str_split($num)) as $i => $d) {
            $d = (int) $d;
            if ($i % 2 === 1 && ($d *= 2) > 9) {
                $d -= 9;
            }
            $suma += $d;
        }

        return $suma % 10 === 0;
    }
}
