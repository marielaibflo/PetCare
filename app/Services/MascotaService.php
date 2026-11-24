<?php

namespace App\Services;

use App\Models\Mascota;
use App\Models\User;
use App\Support\Archivos;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class MascotaService
{
    use ReglaNegocio;

    public function guardar(User $propietario, ?Mascota $mascota, array $datos, ?UploadedFile $foto = null): Mascota
    {
        $datos['raza'] = trim($datos['raza'] ?? '') ?: 'Mestizo';
        $quitar = ! empty($datos['quitar_foto']);
        unset($datos['quitar_foto']);
        if ($foto) {
            Archivos::borrar($mascota?->foto);
            $datos['foto'] = Archivos::guardar($foto, 'mascotas');
        } elseif ($quitar && $mascota?->foto) {
            Archivos::borrar($mascota->foto);
            $datos['foto'] = null;
        }
        if ($mascota) {
            $mascota->update($datos);

            return $mascota;
        }

        return $propietario->mascotas()->create($datos);
    }

    /** La ficha se oculta al propietario; el historial clínico se conserva para las veterinarias. */
    public function eliminar(Mascota $mascota): void
    {
        $this->exigir(! $mascota->afiliacionActiva, 'La mascota tiene un plan activo. Cancela el plan antes de eliminarla.');
        DB::transaction(function () use ($mascota) {
            $mascota->citas()->abiertas()->update(['estado' => 'cancelada']);
            $mascota->delete();
        });
    }
}
