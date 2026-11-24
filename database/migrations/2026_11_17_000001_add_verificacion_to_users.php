<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Etapa 1 · Registro seguro: código de país, verificación de correo y celular (WhatsApp). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('codigo_pais', 2)->default('BO')->after('telefono');
            $table->timestamp('telefono_verificado_at')->nullable()->after('codigo_pais');
        });

        // Las cuentas que ya existían quedan verificadas para no bloquear a nadie.
        DB::table('users')->update(['email_verified_at' => now(), 'telefono_verificado_at' => now()]);

        Schema::create('codigos_verificacion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('canal', 10);          // email | whatsapp
            $table->string('destino', 150);       // correo o número internacional
            $table->string('codigo');             // guardado cifrado (hash), nunca en texto plano
            $table->unsignedTinyInteger('intentos')->default(0);
            $table->timestamp('expira_at');
            $table->timestamp('usado_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'canal']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('codigos_verificacion');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['codigo_pais', 'telefono_verificado_at']);
        });
    }
};
