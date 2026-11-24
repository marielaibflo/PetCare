<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('citas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mascota_id')->constrained('mascotas')->cascadeOnDelete();
            $table->foreignId('veterinaria_id')->constrained('veterinarias')->cascadeOnDelete();
            $table->string('servicio', 40);
            $table->date('fecha');
            $table->string('hora', 5);
            $table->string('estado', 20)->default('pendiente'); // pendiente | confirmada | atendida | cancelada
            $table->string('nota')->nullable();
            $table->timestamps();
            $table->index(['veterinaria_id', 'fecha', 'hora']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('citas');
    }
};
