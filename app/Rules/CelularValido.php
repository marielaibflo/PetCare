<?php

namespace App\Rules;

use App\Support\Paises;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** El número debe ser un celular con el formato del país elegido (WhatsApp solo funciona con celulares). */
class CelularValido implements ValidationRule
{
    public function __construct(private ?string $pais) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $pais = Paises::LISTA[$this->pais] ?? null;
        if (! $pais) {
            $fail('Selecciona el código de país.');

            return;
        }
        if (! Paises::esMovilValido($this->pais, (string) $value)) {
            $fail("El número no es un celular válido de {$pais['nombre']}. Ejemplo: {$pais['ejemplo']}.");
        }
    }
}
