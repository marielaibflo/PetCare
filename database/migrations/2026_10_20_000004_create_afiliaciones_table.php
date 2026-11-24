<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('afiliaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mascota_id')->constrained('mascotas')->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained('planes')->restrictOnDelete();
            $table->date('fecha_inicio');
            $table->date('fecha_fin');
            $table->string('estado', 20)->default('activo'); // activo | vencido | cancelado | cambiado
            $table->timestamps();
            $table->index(['mascota_id', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('afiliaciones');
    }
};
