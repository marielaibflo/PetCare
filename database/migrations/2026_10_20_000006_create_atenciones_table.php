<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Historial clínico: cada fila es una atención registrada por una veterinaria. */
    public function up(): void
    {
        Schema::create('atenciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mascota_id')->constrained('mascotas')->cascadeOnDelete();
            $table->foreignId('veterinaria_id')->constrained('veterinarias')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete(); // quién la registró
            $table->foreignId('cita_id')->nullable()->constrained('citas')->nullOnDelete();
            $table->date('fecha');
            $table->string('servicio', 40);
            $table->string('producto')->nullable();      // vacuna o producto aplicado
            $table->string('diagnostico')->nullable();
            $table->text('descripcion');
            $table->string('tratamiento')->nullable();
            $table->decimal('peso', 5, 1)->nullable();
            $table->decimal('temperatura', 4, 1)->nullable();
            $table->date('proxima_dosis')->nullable();   // genera los recordatorios de vacunas
            $table->boolean('cubierto_por_plan')->default(false);
            $table->timestamps();
            $table->index(['mascota_id', 'fecha']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('atenciones');
    }
};
