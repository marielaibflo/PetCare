<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('veterinarias', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('zona');
            $table->string('direccion')->nullable();
            $table->string('telefono', 20);
            $table->string('horario')->nullable();
            $table->json('servicios');
            $table->decimal('valoracion', 2, 1)->default(0);
            $table->unsignedInteger('resenas')->default(0);
            $table->boolean('activa')->default(true);
            $table->unsignedTinyInteger('color')->default(0);
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('veterinaria_id')->nullable()->after('telefono')
                ->constrained('veterinarias')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('veterinaria_id');
        });
        Schema::dropIfExists('veterinarias');
    }
};
