<?php

namespace App\Http\Controllers\Propietario;

use App\Models\Mascota;
use Illuminate\Http\Request;

trait Concerns
{
    /** El propietario solo puede ver o modificar sus propias mascotas. */
    protected function propia(Request $request, Mascota $mascota): Mascota
    {
        abort_unless($mascota->propietario_id === $request->user()->id, 404);

        return $mascota;
    }
}
