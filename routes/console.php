<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Recordatorios de vacunas y desparasitaciones (7 días antes, una vez por dosis).
// Para que se ejecuten solos deja corriendo:  php artisan schedule:work
Artisan::command('petcare:recordatorios', function (\App\Services\RecordatorioService $servicio) {
    $n = $servicio->enviarPendientes();
    $this->info("Recordatorios enviados: {$n}");
})->purpose('Envía por correo y WhatsApp los recordatorios de vacunas y desparasitaciones');

\Illuminate\Support\Facades\Schedule::command('petcare:recordatorios')->dailyAt('08:00');

// Suscripciones de las clínicas: cobro automático con tarjeta (simulación) o aviso para pagar con QR, y
// limpieza de registros que no confirmaron su correo en 48 horas.
Artisan::command('petcare:suscripciones', function (\App\Services\SuscripcionService $servicio) {
    $r = $servicio->procesar();
    $b = $servicio->limpiarRegistrosSinConfirmar();
    $this->info("Suscripciones cobradas: {$r['cobradas']} · avisos enviados: {$r['avisadas']} · registros sin confirmar borrados: {$b}");
})->purpose('Renueva las suscripciones de las clínicas y limpia registros sin confirmar');

\Illuminate\Support\Facades\Schedule::command('petcare:suscripciones')->dailyAt('08:10');
