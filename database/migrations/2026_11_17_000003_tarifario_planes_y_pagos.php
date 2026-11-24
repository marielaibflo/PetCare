<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Etapas 3 a 7:
 *  - tarifario y catálogo de productos de cada veterinaria;
 *  - planes por porcentaje de cobertura, con topes y periodo de espera;
 *  - costos y cobertura de cada atención (con detalle de productos usados);
 *  - foto de la mascota y foto de la etiqueta en la cartilla de vacunas;
 *  - pagos (planes y suscripciones) verificados por el administrador;
 *  - suscripción y registro en línea de las veterinarias.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Etapa 3 · Tarifario y suscripción de la veterinaria
        Schema::table('veterinarias', function (Blueprint $table) {
            $table->json('precios')->nullable()->after('servicios');                         // {"Consulta": 80, ...}
            $table->string('plan_suscripcion', 12)->default('profesional')->after('precios'); // inicial | profesional | plus
            $table->date('suscripcion_hasta')->nullable()->after('plan_suscripcion');
            $table->string('estado', 12)->default('aprobada')->after('suscripcion_hasta');    // pendiente | aprobada | rechazada
            $table->string('nit', 20)->nullable()->after('estado');
            $table->string('documento')->nullable()->after('nit');                            // licencia de funcionamiento
            $table->string('motivo_rechazo', 200)->nullable()->after('documento');
            $table->timestamp('terminos_at')->nullable()->after('motivo_rechazo');
        });

        Schema::create('productos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('veterinaria_id')->constrained()->cascadeOnDelete();
            $table->string('nombre', 100);
            $table->string('categoria', 20);                  // medicamento | vacuna | antiparasitario | insumo
            $table->decimal('precio', 10, 2);
            $table->boolean('cubre_seguro')->default(true);
            $table->json('planes')->nullable();               // ids de planes con descuento (vacío = todos)
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        // Etapa 4 · Planes por porcentaje
        Schema::table('planes', function (Blueprint $table) {
            $table->unsignedTinyInteger('porcentaje')->default(0)->after('precio');
            $table->decimal('tope_mensual', 10, 2)->default(0)->after('porcentaje');
            $table->decimal('tope_anual', 10, 2)->default(0)->after('tope_mensual');
            $table->unsignedSmallInteger('espera_cirugia_dias')->default(0)->after('tope_anual');
            $table->string('medicamentos', 10)->default('todos')->after('espera_cirugia_dias'); // todos | urgencia | ninguno
            $table->boolean('permite_grave')->default(false)->after('medicamentos');
        });

        Schema::table('afiliaciones', function (Blueprint $table) {
            $table->boolean('cobertura_grave')->default(false)->after('plan_id');
            $table->string('modalidad', 10)->default('mensual')->after('cobertura_grave');
        });

        Schema::table('atenciones', function (Blueprint $table) {
            $table->foreignId('veterinaria_id')->nullable()->change();   // los registros que sube el dueño no tienen clínica
            $table->foreignId('plan_id')->nullable()->after('cita_id')->constrained('planes')->nullOnDelete();
            $table->decimal('costo_servicio', 10, 2)->default(0)->after('cubierto_por_plan');
            $table->decimal('costo_productos', 10, 2)->default(0)->after('costo_servicio');
            $table->decimal('total', 10, 2)->default(0)->after('costo_productos');
            $table->unsignedTinyInteger('porcentaje')->default(0)->after('total');
            $table->decimal('monto_cubierto', 10, 2)->default(0)->after('porcentaje');   // lo paga PetCare a la clínica
            $table->decimal('paga_dueno', 10, 2)->default(0)->after('monto_cubierto');
            $table->boolean('preexistente')->default(false)->after('paga_dueno');
            $table->string('nota_cobertura', 250)->nullable()->after('preexistente');
            // Etapa 5 · Cartilla digital
            $table->string('foto_etiqueta')->nullable()->after('nota_cobertura');
            $table->string('registrado_por', 12)->default('veterinaria')->after('foto_etiqueta');   // veterinaria | propietario
            $table->string('clinica_externa', 100)->nullable()->after('registrado_por');
            $table->string('firmado_por', 100)->nullable()->after('clinica_externa');
        });

        Schema::create('atencion_productos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('atencion_id')->constrained('atenciones')->cascadeOnDelete();
            $table->foreignId('producto_id')->nullable()->constrained()->nullOnDelete();
            $table->string('nombre', 100);
            $table->decimal('cantidad', 8, 2);
            $table->decimal('precio_unitario', 10, 2);
            $table->decimal('subtotal', 10, 2);
            $table->boolean('cubierto')->default(false);
            $table->timestamps();
        });

        // Etapa 5 · Foto de la mascota
        Schema::table('mascotas', function (Blueprint $table) {
            $table->string('foto')->nullable()->after('alergias');
        });

        // Etapa 6 · Pagos (planes de los dueños y suscripciones de las veterinarias)
        Schema::create('pagos', function (Blueprint $table) {
            $table->id();
            $table->string('numero', 20)->nullable()->unique();           // número de comprobante (al aprobar)
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('concepto', 12);                               // plan | suscripcion
            $table->foreignId('mascota_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('plan_id')->nullable()->constrained('planes')->nullOnDelete();
            $table->boolean('cobertura_grave')->default(false);
            $table->string('modalidad', 10)->default('mensual');
            $table->foreignId('veterinaria_id')->nullable()->constrained()->nullOnDelete();
            $table->string('plan_suscripcion', 12)->nullable();
            $table->unsignedTinyInteger('meses')->default(1);
            $table->decimal('monto', 10, 2);
            $table->string('metodo', 10);                                 // qr | tarjeta
            $table->string('comprobante')->nullable();                    // imagen o PDF subido por el cliente
            $table->string('tarjeta_ultimos', 4)->nullable();             // nunca se guarda el número completo
            $table->string('facturar_a', 100);
            $table->string('documento', 20);                              // CI o NIT
            $table->string('estado', 10)->default('pendiente');           // pendiente | aprobado | rechazado
            $table->string('motivo_rechazo', 200)->nullable();
            $table->foreignId('revisado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revisado_at')->nullable();
            $table->timestamps();
        });

        // Ajustes editables por el administrador (QR de cobro, datos bancarios)
        Schema::create('ajustes', function (Blueprint $table) {
            $table->string('clave', 60)->primary();
            $table->text('valor')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ajustes');
        Schema::dropIfExists('pagos');
        Schema::table('mascotas', fn (Blueprint $t) => $t->dropColumn('foto'));
        Schema::dropIfExists('atencion_productos');
        Schema::table('atenciones', function (Blueprint $t) {
            $t->dropConstrainedForeignId('plan_id');
            $t->dropColumn(['costo_servicio', 'costo_productos', 'total', 'porcentaje', 'monto_cubierto', 'paga_dueno', 'preexistente',
                'nota_cobertura', 'foto_etiqueta', 'registrado_por', 'clinica_externa', 'firmado_por']);
        });
        Schema::table('afiliaciones', fn (Blueprint $t) => $t->dropColumn(['cobertura_grave', 'modalidad']));
        Schema::table('planes', fn (Blueprint $t) => $t->dropColumn(['porcentaje', 'tope_mensual', 'tope_anual', 'espera_cirugia_dias', 'medicamentos', 'permite_grave']));
        Schema::dropIfExists('productos');
        Schema::table('veterinarias', fn (Blueprint $t) => $t->dropColumn(['precios', 'plan_suscripcion', 'suscripcion_hasta', 'estado', 'nit', 'documento', 'motivo_rechazo', 'terminos_at']));
    }
};
