<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

/** Utilidad para que la capa de lógica de negocio rechace operaciones con un mensaje claro. */
trait ReglaNegocio
{
    protected function exigir(bool $condicion, string $mensaje, string $campo = 'regla'): void
    {
        if (! $condicion) {
            throw ValidationException::withMessages([$campo => $mensaje]);
        }
    }
}
