<?php

namespace App\Http\Controllers;

use App\Models\Ajuste;
use App\Models\Atencion;
use App\Models\Mascota;
use App\Models\Pago;
use App\Models\User;
use App\Models\Veterinaria;
use App\Support\Archivos;
use Illuminate\Http\Request;

/** Muestra los archivos privados solo a quien corresponde. */
class ArchivoController extends Controller
{
    /** Foto de la mascota: su dueño, las veterinarias y el administrador. */
    /** Foto de perfil: el propio usuario, el administrador y el personal de las veterinarias. */
    public function usuario(Request $request, User $usuario)
    {
        $u = $request->user();
        abort_unless($u->id === $usuario->id || $u->rol !== User::PROPIETARIO, 404);

        return Archivos::respuesta($usuario->foto);
    }

    public function mascota(Request $request, Mascota $mascota)
    {
        $u = $request->user();
        abort_unless($u->rol !== User::PROPIETARIO || $mascota->propietario_id === $u->id, 404);

        return Archivos::respuesta($mascota->foto);
    }

    /** Foto de la etiqueta de una vacuna: el dueño de la mascota, las veterinarias y el administrador. */
    public function etiqueta(Request $request, Atencion $atencion)
    {
        $u = $request->user();
        abort_unless($u->rol !== User::PROPIETARIO || $atencion->mascota?->propietario_id === $u->id, 404);

        return Archivos::respuesta($atencion->foto_etiqueta);
    }

    /** Certificado físico de una vacuna que adjuntó el dueño. */
    public function certificado(Request $request, Atencion $atencion)
    {
        $u = $request->user();
        abort_unless($u->rol !== User::PROPIETARIO || $atencion->mascota?->propietario_id === $u->id, 404);

        return Archivos::respuesta($atencion->documento_dueno);
    }

    /** Logo de una veterinaria (sello en la cartilla y página pública). */
    public function logo(Veterinaria $veterinaria)
    {
        return Archivos::respuesta($veterinaria->logo);
    }

    public function fotoVeterinaria(\App\Models\VeterinariaFoto $foto)
    {
        return Archivos::respuesta($foto->ruta);
    }

    public function fotoProducto(\App\Models\Producto $producto)
    {
        return Archivos::respuesta($producto->foto);
    }

    /** Comprobante subido por el cliente: quien pagó y el administrador. */
    public function comprobante(Request $request, Pago $pago)
    {
        $u = $request->user();
        abort_unless($u->esRol(User::ADMINISTRADOR) || $pago->user_id === $u->id
            || ($pago->loVerificaLaClinica() && $u->esRol(User::VETERINARIA) && $pago->veterinaria_id === $u->veterinaria_id), 404);

        return Archivos::respuesta($pago->comprobante);
    }

    /** Licencia o documento de la clínica: solo el administrador. */
    public function documento(Request $request, Veterinaria $veterinaria)
    {
        abort_unless($request->user()->esRol(User::ADMINISTRADOR), 404);

        return Archivos::respuesta($veterinaria->documento);
    }

    /** QR de cobro vigente (el que subió el administrador o el incluido en public/img). */
    /** QR de cobro de una clínica (para pagar citas); si no subió uno, el de muestra. */
    public function qrVeterinaria(Veterinaria $veterinaria)
    {
        return Archivos::existe($veterinaria->qr_cobro) ? Archivos::respuesta($veterinaria->qr_cobro) : $this->qr();
    }

    public function qr()
    {
        $ruta = Ajuste::valor('pago_qr');
        if (Archivos::existe($ruta)) {
            return Archivos::respuesta($ruta);
        }

        return response()->file(public_path('img/qr-pago.png'));
    }
}
