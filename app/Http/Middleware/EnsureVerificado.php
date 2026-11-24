<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** El propietario debe confirmar su correo y su celular antes de usar la plataforma. */
class EnsureVerificado
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()->verificado()) {
            return redirect()->route('verificacion.show');
        }

        return $next($request);
    }
}
