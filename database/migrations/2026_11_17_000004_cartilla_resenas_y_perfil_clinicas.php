<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cartilla digital con matrícula y sello de la clínica, recordatorios de vacunas,
 * citas urgentes con monto estimado, calificaciones y página pública de cada veterinaria.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('matricula', 30)->nullable()->after('veterinaria_id');   // matrícula profesional del veterinario
            $table->string('direccion', 150)->nullable()->after('matricula');       // dirección del propietario (cartilla)
        });

        Schema::table('mascotas', function (Blueprint $table) {
            $table->string('color', 40)->nullable()->after('raza');
        });

        Schema::table('veterinarias', function (Blueprint $table) {
            $table->string('registro', 30)->nullable()->after('nit');               // N.º de registro / licencia de la clínica
            $table->string('logo')->nullable()->after('registro');                  // logo o sello que aparece en la cartilla
            $table->text('descripcion')->nullable()->after('logo');
            $table->json('servicios_especiales')->nullable()->after('descripcion'); // rayos X, laboratorio, 24 h, domicilio...
            $table->json('metodos_pago')->nullable()->after('servicios_especiales');
            $table->string('promocion', 200)->nullable()->after('metodos_pago');
            $table->string('mapa_url', 500)->nullable()->after('promocion');        // enlace de Google Maps (opcional)
        });

        Schema::create('veterinaria_fotos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('veterinaria_id')->constrained()->cascadeOnDelete();
            $table->string('ruta');
            $table->string('titulo', 80)->nullable();
            $table->timestamps();
        });

        Schema::table('productos', function (Blueprint $table) {
            $table->string('descripcion', 200)->nullable()->after('nombre');
            $table->string('foto')->nullable()->after('descripcion');
        });

        Schema::table('atenciones', function (Blueprint $table) {
            $table->string('lote', 40)->nullable()->after('producto');
            $table->string('matricula', 30)->nullable()->after('firmado_por');         // matrícula de quien aplicó
            $table->string('registro_clinica', 30)->nullable()->after('matricula');    // registro de la clínica
            $table->string('documento_dueno')->nullable()->after('registro_clinica');  // certificado físico escaneado por el dueño
            $table->timestamp('recordatorio_at')->nullable()->after('documento_dueno'); // aviso de la próxima dosis enviado
        });

        Schema::table('citas', function (Blueprint $table) {
            $table->boolean('urgente')->default(false)->after('servicio');
            $table->decimal('monto_estimado', 10, 2)->nullable()->after('urgente');    // precio del servicio en esa clínica
            $table->decimal('cubierto_estimado', 10, 2)->nullable()->after('monto_estimado');
        });

        Schema::create('resenas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('veterinaria_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cita_id')->nullable()->unique()->constrained()->nullOnDelete();   // una calificación por cita
            $table->unsignedTinyInteger('estrellas');
            $table->string('comentario', 300)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resenas');
        Schema::table('citas', fn (Blueprint $t) => $t->dropColumn(['urgente', 'monto_estimado', 'cubierto_estimado']));
        Schema::table('atenciones', fn (Blueprint $t) => $t->dropColumn(['lote', 'matricula', 'registro_clinica', 'documento_dueno', 'recordatorio_at']));
        Schema::table('productos', fn (Blueprint $t) => $t->dropColumn(['descripcion', 'foto']));
        Schema::dropIfExists('veterinaria_fotos');
        Schema::table('veterinarias', fn (Blueprint $t) => $t->dropColumn(['registro', 'logo', 'descripcion', 'servicios_especiales', 'metodos_pago', 'promocion', 'mapa_url']));
        Schema::table('mascotas', fn (Blueprint $t) => $t->dropColumn('color'));
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['matricula', 'direccion']));
    }
};
