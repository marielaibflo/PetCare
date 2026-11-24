<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mascotas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('propietario_id')->constrained('users')->cascadeOnDelete();
            $table->string('nombre');
            $table->string('especie', 20);
            $table->string('raza')->default('Mestizo');
            $table->date('fecha_nacimiento');
            $table->decimal('peso', 5, 1);
            $table->string('sexo', 10);
            $table->boolean('esterilizado')->default(false);
            $table->string('alergias')->nullable();
            $table->softDeletes(); // el historial clínico se conserva aunque el propietario elimine la ficha
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mascotas');
    }
};
