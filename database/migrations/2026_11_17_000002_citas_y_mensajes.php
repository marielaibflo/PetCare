<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Etapa 2 · Citas: la clínica acepta, rechaza (con motivo), devuelve a pendiente o marca atendida; avisos por WhatsApp. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('citas', function (Blueprint $table) {
            $table->string('motivo_rechazo', 200)->nullable()->after('nota');
            $table->timestamp('respondida_at')->nullable()->after('motivo_rechazo');
        });

        Schema::table('veterinarias', function (Blueprint $table) {
            // Celular con WhatsApp de la clínica: recibe el aviso de cada cita nueva.
            $table->string('whatsapp_pais', 2)->default('BO')->after('telefono');
            $table->string('whatsapp', 15)->nullable()->after('whatsapp_pais');
        });

        // Historial de mensajes de WhatsApp (en modo demostración se puede ver lo que se habría enviado).
        Schema::create('mensajes_whatsapp', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('para', 20);
            $table->text('mensaje');
            $table->string('estado', 10);   // demo | enviado | fallido
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mensajes_whatsapp');
        Schema::table('veterinarias', fn (Blueprint $t) => $t->dropColumn(['whatsapp_pais', 'whatsapp']));
        Schema::table('citas', fn (Blueprint $t) => $t->dropColumn(['motivo_rechazo', 'respondida_at']));
    }
};
