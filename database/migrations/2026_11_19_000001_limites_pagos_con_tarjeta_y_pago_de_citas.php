<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * - Límite de atenciones cubiertas por año en cada plan (después la clínica cobra el precio completo).
 * - Pagos: marca y titular de la tarjeta (simulación), aceptación de términos y pago de citas a la veterinaria.
 * - Veterinarias: QR propio para cobrar las citas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('planes', function (Blueprint $t) {
            $t->unsignedSmallInteger('limite_atenciones')->nullable()->after('tope_anual');
        });
        Schema::table('pagos', function (Blueprint $t) {
            $t->foreignId('cita_id')->nullable()->after('veterinaria_id')->constrained('citas')->nullOnDelete();
            $t->string('tarjeta_marca', 20)->nullable()->after('tarjeta_ultimos');
            $t->string('titular', 100)->nullable()->after('tarjeta_marca');
            $t->timestamp('terminos_at')->nullable()->after('documento');
            // Los pagos de citas no piden datos de facturación
            $t->string('documento', 20)->nullable()->change();
            $t->string('facturar_a', 100)->nullable()->change();
        });
        Schema::table('veterinarias', function (Blueprint $t) {
            $t->string('qr_cobro')->nullable()->after('logo');
        });
        DB::table('planes')->where('porcentaje', 40)->update(['limite_atenciones' => 4]);
        DB::table('planes')->where('porcentaje', 60)->update(['limite_atenciones' => 8]);
        DB::table('planes')->where('porcentaje', 90)->update(['limite_atenciones' => 12]);
    }

    public function down(): void
    {
        Schema::table('veterinarias', fn (Blueprint $t) => $t->dropColumn('qr_cobro'));
        Schema::table('pagos', function (Blueprint $t) {
            $t->dropConstrainedForeignId('cita_id');
            $t->dropColumn(['tarjeta_marca', 'titular', 'terminos_at']);
        });
        Schema::table('planes', fn (Blueprint $t) => $t->dropColumn('limite_atenciones'));
    }
};
