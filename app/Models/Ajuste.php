<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Ajustes que el administrador puede cambiar sin tocar el código (QR de cobro, datos bancarios). */
class Ajuste extends Model
{
    protected $table = 'ajustes';

    protected $primaryKey = 'clave';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['clave', 'valor'];

    public const PREDETERMINADOS = [
        'pago_banco' => 'Banco Económico',
        'pago_titular' => 'PETCARE (DATOS DE EJEMPLO)',   // los datos reales se cargan en Administrador → Configuración
        'pago_cuenta' => '1000000001',
        'pago_qr' => null,   // ruta del QR subido; si está vacío se usa public/img/qr-pago.png
        'red_tiktok' => 'https://www.tiktok.com/@petcaresegurity',   // cuenta oficial de PetCare en TikTok
        'red_instagram' => null,  // enlace de la cuenta de Instagram de PetCare
    ];

    public static function valor(string $clave): ?string
    {
        try {
            return static::find($clave)?->valor ?? self::PREDETERMINADOS[$clave] ?? null;
        } catch (\Throwable) {
            return self::PREDETERMINADOS[$clave] ?? null;
        }
    }

    /** Redes sociales de PetCare con enlace guardado: ['tiktok' => url, 'instagram' => url]. */
    public static function redes(): array
    {
        return array_filter(['tiktok' => static::valor('red_tiktok'), 'instagram' => static::valor('red_instagram')]);
    }

    public static function guardar(string $clave, ?string $valor): void
    {
        static::updateOrCreate(['clave' => $clave], ['valor' => $valor]);
    }
}
