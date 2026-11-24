<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** Control de acceso por rol: cada grupo de rutas pertenece a un solo perfil. */
class EnsureRol
{
    public function handle(Request $request, Closure $next, string $rol): Response
    {
        $user = $request->user();
        if (! $user->activo) {
            Auth::logout();
            $request->session()->invalidate();

            return redirect()->route('login', $rol)->withErrors(['email' => 'La cuenta está desactivada. Comunícate con el administrador.']);
        }
        abort_unless($user->rol === $rol, 403, 'No tienes permiso para acceder a esta sección.');

        return $next($request);
    }
}
