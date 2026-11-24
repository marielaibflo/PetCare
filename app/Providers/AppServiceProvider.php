<?php

namespace App\Providers;

use App\Models\Cita;
use App\Models\Mascota;
use App\Models\Plan;
use App\Models\User;
use App\Models\Veterinaria;
use App\Services\NotificacionService;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Rutas en español: /mascotas/{mascota}, /admin/propietarios/{usuario}, etc.
        Route::model('usuario', User::class);
        Route::model('veterinaria', Veterinaria::class);
        Route::model('plan', Plan::class);
        Route::model('mascota', Mascota::class);
        Route::model('cita', Cita::class);
        Route::model('atencion', \App\Models\Atencion::class);
        Route::model('pago', \App\Models\Pago::class);
        Route::model('producto', \App\Models\Producto::class);
        Route::model('foto', \App\Models\VeterinariaFoto::class);
        Route::model('resena', \App\Models\Resena::class);

        // Contraseña segura en toda la plataforma. En producción además se rechazan
        // contraseñas filtradas en internet (consulta haveibeenpwned.com).
        Password::defaults(function () {
            $regla = Password::min(8)->letters()->mixedCase()->numbers()->symbols();

            return $this->app->isProduction() ? $regla->uncompromised() : $regla;
        });

        // Avisos de la campana, disponibles en el diseño principal de los tres roles.
        View::composer('layouts.app', function ($view) {
            $user = auth()->user();
            $view->with('notificaciones', $user ? app(NotificacionService::class)->para($user) : []);
        });
    }
}
