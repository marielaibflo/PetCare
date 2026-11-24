<?php

namespace App\Support;

/**
 * Códigos de país y formato de los números de celular (necesarios para WhatsApp).
 * «movil» es el patrón del número nacional, sin el código de país ni el 0 inicial.
 */
final class Paises
{
    public const LISTA = [
        'BO' => ['nombre' => 'Bolivia', 'codigo' => '591', 'bandera' => '🇧🇴', 'movil' => '/^[67]\d{7}$/', 'ejemplo' => '71234567'],
        'AR' => ['nombre' => 'Argentina', 'codigo' => '54', 'bandera' => '🇦🇷', 'movil' => '/^9?\d{10}$/', 'ejemplo' => '91123456789'],
        'BR' => ['nombre' => 'Brasil', 'codigo' => '55', 'bandera' => '🇧🇷', 'movil' => '/^\d{2}9\d{8}$/', 'ejemplo' => '11912345678'],
        'CL' => ['nombre' => 'Chile', 'codigo' => '56', 'bandera' => '🇨🇱', 'movil' => '/^9\d{8}$/', 'ejemplo' => '912345678'],
        'CO' => ['nombre' => 'Colombia', 'codigo' => '57', 'bandera' => '🇨🇴', 'movil' => '/^3\d{9}$/', 'ejemplo' => '3001234567'],
        'EC' => ['nombre' => 'Ecuador', 'codigo' => '593', 'bandera' => '🇪🇨', 'movil' => '/^9\d{8}$/', 'ejemplo' => '991234567'],
        'ES' => ['nombre' => 'España', 'codigo' => '34', 'bandera' => '🇪🇸', 'movil' => '/^[67]\d{8}$/', 'ejemplo' => '612345678'],
        'US' => ['nombre' => 'Estados Unidos', 'codigo' => '1', 'bandera' => '🇺🇸', 'movil' => '/^[2-9]\d{9}$/', 'ejemplo' => '2015550123'],
        'MX' => ['nombre' => 'México', 'codigo' => '52', 'bandera' => '🇲🇽', 'movil' => '/^\d{10}$/', 'ejemplo' => '5512345678'],
        'PY' => ['nombre' => 'Paraguay', 'codigo' => '595', 'bandera' => '🇵🇾', 'movil' => '/^9\d{8}$/', 'ejemplo' => '981234567'],
        'PE' => ['nombre' => 'Perú', 'codigo' => '51', 'bandera' => '🇵🇪', 'movil' => '/^9\d{8}$/', 'ejemplo' => '912345678'],
        'UY' => ['nombre' => 'Uruguay', 'codigo' => '598', 'bandera' => '🇺🇾', 'movil' => '/^9\d{7}$/', 'ejemplo' => '94123456'],
    ];

    public const PREDETERMINADO = 'BO';

    /** Deja solo los dígitos y quita el 0 inicial o el código de país si el usuario lo escribió. */
    public static function normalizar(?string $pais, ?string $numero): string
    {
        $digitos = preg_replace('/\D/', '', (string) $numero);
        $codigo = self::LISTA[$pais]['codigo'] ?? '';
        if ($codigo !== '' && str_starts_with($digitos, $codigo) && strlen($digitos) > strlen(self::LISTA[$pais]['ejemplo'])) {
            $digitos = substr($digitos, strlen($codigo));
        }

        return ltrim($digitos, '0');
    }

    public static function esMovilValido(?string $pais, string $numero): bool
    {
        return isset(self::LISTA[$pais]) && preg_match(self::LISTA[$pais]['movil'], $numero) === 1;
    }

    /** Formato internacional E.164 sin el «+»: 59171234567 (el que usa WhatsApp). */
    public static function internacional(?string $pais, ?string $numero): ?string
    {
        if (! $numero || ! isset(self::LISTA[$pais])) {
            return null;
        }

        return self::LISTA[$pais]['codigo'].$numero;
    }

    /** Para mostrar: +591 71234567 */
    public static function mostrar(?string $pais, ?string $numero): string
    {
        if (! $numero) {
            return '—';
        }

        return isset(self::LISTA[$pais]) ? '+'.self::LISTA[$pais]['codigo'].' '.$numero : $numero;
    }
}
