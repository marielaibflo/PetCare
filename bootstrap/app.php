<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Detrás de un túnel o servidor (Cloudflare, ngrok, Render…) genera los enlaces con https
        $middleware->trustProxies(at: '*');
        $middleware->alias(['rol' => \App\Http\Middleware\EnsureRol::class, 'verificado' => \App\Http\Middleware\EnsureVerificado::class]);
        $middleware->redirectGuestsTo(fn () => route('welcome'));
        $middleware->redirectUsersTo(fn (Request $request) => $request->user()->rutaInicio());
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
