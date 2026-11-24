<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Archivos subidos (fotos de mascotas, etiquetas de vacunas, comprobantes de pago, documentos de clínicas).
 * Se guardan en storage/app/private (no son públicos) y se muestran a través de rutas que revisan permisos.
 */
final class Archivos
{
    public const IMAGEN = ['file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'];

    public const IMAGEN_O_PDF = ['file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240'];

    public static function guardar(UploadedFile $archivo, string $carpeta): string
    {
        return $archivo->store($carpeta, 'local');
    }

    public static function borrar(?string $ruta): void
    {
        if ($ruta) {
            Storage::disk('local')->delete($ruta);
        }
    }

    public static function existe(?string $ruta): bool
    {
        return $ruta && Storage::disk('local')->exists($ruta);
    }

    public static function respuesta(?string $ruta)
    {
        abort_unless(self::existe($ruta), 404);

        return response()->file(Storage::disk('local')->path($ruta), ['Cache-Control' => 'private, max-age=86400']);
    }
}
