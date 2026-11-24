<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 23/11 · Planes con vigencia y reglas completas, afiliación atada a una veterinaria con contrato
 * (permanencia mínima de 3 meses y carencia de 60 días) y tienda con carrito y pedidos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('planes', function (Blueprint $t) {
            $t->date('vigente_desde')->nullable();
            $t->date('vigente_hasta')->nullable();
            $t->decimal('precio_anual', 10, 2)->nullable();       // vacío = 10 meses (2 gratis)
            $t->unsignedSmallInteger('edad_min_meses')->default(2);
            $t->unsignedTinyInteger('edad_max_anios')->nullable();
            $t->json('especies')->nullable();                     // vacío = todas
            $t->string('color', 20)->default('violeta');
            $t->unsignedTinyInteger('permanencia_meses')->default(3);
            $t->unsignedSmallInteger('carencia_dias')->default(60);
        });

        // Foto de perfil del usuario
        Schema::table('users', fn (Blueprint $t) => $t->string('foto')->nullable());

        Schema::table('afiliaciones', function (Blueprint $t) {
            $t->foreignId('veterinaria_id')->nullable()->constrained('veterinarias')->nullOnDelete();
            $t->string('contrato_numero', 30)->nullable();
            $t->timestamp('contrato_at')->nullable();
            $t->date('permanencia_hasta')->nullable();
            $t->date('carencia_hasta')->nullable();
        });

        Schema::table('veterinarias', function (Blueprint $t) {
            $t->date('aprobada_at')->nullable();
            $t->boolean('envio_activo')->default(false);
            $t->decimal('envio_costo', 8, 2)->default(0);
            $t->string('envio_zonas', 160)->nullable();
            $t->timestamp('suscripcion_cancelada_at')->nullable();   // canceló: sigue hasta fin del período, sin cobros
            $t->string('motivo_cancelacion', 200)->nullable();
            $t->timestamp('baja_at')->nullable();                    // eliminó su cuenta
        });

        Schema::table('productos', function (Blueprint $t) {
            $t->unsignedInteger('stock')->default(10);
            $t->boolean('en_tienda')->default(true);
        });

        Schema::create('pedidos', function (Blueprint $t) {
            $t->id();
            $t->string('numero', 20)->nullable()->unique();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->foreignId('veterinaria_id')->constrained('veterinarias')->cascadeOnDelete();
            $t->foreignId('mascota_id')->nullable()->constrained('mascotas')->nullOnDelete();
            $t->string('entrega', 12)->default('recoger');      // recoger | domicilio
            $t->string('direccion', 200)->nullable();
            $t->string('referencia', 160)->nullable();
            $t->decimal('subtotal', 10, 2);
            $t->decimal('descuento', 10, 2)->default(0);        // lo que cubre el plan en medicamentos y vacunas
            $t->decimal('envio', 10, 2)->default(0);
            $t->decimal('total', 10, 2);
            // pendiente_pago → pagado → preparando → listo (recoger) / en_camino (domicilio) → entregado | cancelado
            $t->string('estado', 16)->default('pendiente_pago');
            $t->string('nota', 200)->nullable();
            $t->timestamps();
        });

        Schema::create('pedido_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('pedido_id')->constrained('pedidos')->cascadeOnDelete();
            $t->foreignId('producto_id')->nullable()->constrained('productos')->nullOnDelete();
            $t->string('nombre', 120);
            $t->string('categoria', 30);
            $t->unsignedInteger('cantidad');
            $t->decimal('precio', 10, 2);
            $t->decimal('descuento', 10, 2)->default(0);
            $t->decimal('subtotal', 10, 2);
        });

        Schema::table('pagos', function (Blueprint $t) {
            $t->foreignId('pedido_id')->nullable()->constrained('pedidos')->nullOnDelete();
            $t->foreignId('afil_veterinaria_id')->nullable()->constrained('veterinarias')->nullOnDelete();
            $t->timestamp('contrato_at')->nullable();
        });

        // Datos existentes: fecha de aprobación de las clínicas y contrato de las afiliaciones vigentes
        DB::table('veterinarias')->where('estado', 'aprobada')->update(['aprobada_at' => DB::raw('date(created_at)')]);
        DB::table('planes')->update(['vigente_desde' => DB::raw('date(created_at)')]);
    }

    public function down(): void
    {
        Schema::table('pagos', function (Blueprint $t) {
            $t->dropConstrainedForeignId('pedido_id');
            $t->dropConstrainedForeignId('afil_veterinaria_id');
            $t->dropColumn('contrato_at');
        });
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('foto'));
        Schema::dropIfExists('pedido_items');
        Schema::dropIfExists('pedidos');
        Schema::table('productos', fn (Blueprint $t) => $t->dropColumn(['stock', 'en_tienda']));
        Schema::table('veterinarias', fn (Blueprint $t) => $t->dropColumn(['aprobada_at', 'envio_activo', 'envio_costo', 'envio_zonas', 'suscripcion_cancelada_at', 'motivo_cancelacion', 'baja_at']));
        Schema::table('afiliaciones', function (Blueprint $t) {
            $t->dropConstrainedForeignId('veterinaria_id');
            $t->dropColumn(['contrato_numero', 'contrato_at', 'permanencia_hasta', 'carencia_hasta']);
        });
        Schema::table('planes', fn (Blueprint $t) => $t->dropColumn(['vigente_desde', 'vigente_hasta', 'precio_anual', 'edad_min_meses', 'edad_max_anios',
            'especies', 'color', 'permanencia_meses', 'carencia_dias']));
    }
};
