<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * - Forma de cobro de la suscripción de cada clínica (como Spotify: mes gratis, pero la forma de pago se registra al afiliarse).
 *   De la tarjeta (simulación) solo se guardan marca, últimos 4 dígitos, titular y vencimiento.
 * - Aviso de renovación enviado y aviso al administrador cuando la clínica confirma su correo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('veterinarias', function (Blueprint $t) {
            $t->string('cobro_metodo', 12)->nullable()->after('suscripcion_hasta');    // tarjeta | qr | whatsapp
            $t->string('cobro_marca', 20)->nullable()->after('cobro_metodo');
            $t->string('cobro_ultimos', 4)->nullable()->after('cobro_marca');
            $t->string('cobro_titular', 100)->nullable()->after('cobro_ultimos');
            $t->string('cobro_vence', 5)->nullable()->after('cobro_titular');          // MM/AA
            $t->timestamp('aviso_renovacion_at')->nullable()->after('cobro_vence');
            $t->timestamp('admin_avisado_at')->nullable()->after('aviso_renovacion_at');
        });
        // Cambio de correo: el nuevo queda pendiente hasta confirmarlo; mientras tanto sigue valiendo el anterior.
        Schema::table('users', fn (Blueprint $t) => $t->string('email_nuevo', 150)->nullable()->after('email'));
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('email_nuevo'));
        Schema::table('veterinarias', fn (Blueprint $t) => $t->dropColumn(['cobro_metodo', 'cobro_marca', 'cobro_ultimos', 'cobro_titular', 'cobro_vence', 'aviso_renovacion_at', 'admin_avisado_at']));
    }
};
