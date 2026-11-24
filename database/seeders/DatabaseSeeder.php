<?php

namespace Database\Seeders;

use App\Models\Mascota;
use App\Models\Plan;
use App\Models\User;
use App\Models\Veterinaria;
use App\Support\Catalogo;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Base LIMPIA para la demostración: solo lo mínimo para empezar.
 *  - Administrador, los 3 planes de PetCare,
 *  - 1 veterinaria aprobada (mes gratis) con 10 productos en su tienda, 1 propietario (Juan Pérez) y 1 mascota (sin plan, sin citas, sin pagos).
 * Los datos de demostración completos están en DemoSeeder (composer run demo-db).
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $hoy = Carbon::today();

        // Borra los archivos subidos en pruebas anteriores (comprobantes, fotos, documentos…)
        foreach (Storage::disk('local')->directories() as $carpeta) {
            Storage::disk('local')->deleteDirectory($carpeta);
        }

        // Planes de asistencia (configuración del sistema)
        $this->call(PlanesSeeder::class);

        // 1 veterinaria aprobada, en su mes gratis (suscripción Básico, Bs 70/mes)
        $vet = Veterinaria::create(['nombre' => 'Veterinaria Patitas', 'zona' => 'San Miguel', 'direccion' => 'Calle 21 de San Miguel N.º 845', 'telefono' => '22441122', 'whatsapp' => '76541122',
            'horario' => 'Lun–Sáb · 08:30 a 18:00', 'servicios' => ['Consulta', 'Vacuna', 'Desparasitación', 'Tratamiento', 'Urgencia'],
            'precios' => ['Consulta' => 40, 'Vacuna' => 70, 'Desparasitación' => 40, 'Tratamiento' => 60, 'Urgencia' => 120],
            'plan_suscripcion' => 'inicial', 'suscripcion_hasta' => $hoy->copy()->addDays(Catalogo::DIAS_PRUEBA_VETERINARIA), 'nit' => '4512367019', 'registro' => 'SENASAG-LP-0321',
            'descripcion' => 'Clínica familiar en San Miguel: consultorio, vacunas y urgencias.', 'metodos_pago' => ['QR', 'Efectivo', 'Tarjeta'],
            'estado' => 'aprobada', 'aprobada_at' => $hoy, 'activa' => true, 'terminos_at' => now(), 'admin_avisado_at' => now(), 'color' => 0,
            'cobro_metodo' => 'qr', 'envio_activo' => true, 'envio_costo' => 10, 'envio_zonas' => 'San Miguel, Calacoto, Obrajes e Irpavi']);

        // 10 productos de la tienda, con foto (Básico permite 10 a la venta: el 11.º muestra el límite)
        $productos = [
            ['Limpiador de oídos 120 ml', 'higiene', 45, 'Solución para limpiar y secar los oídos · perros y gatos', 'limpia-oidos', false],
            ['Kit dental: cepillo y pasta', 'higiene', 38, 'Pasta sabor pollo y cepillo de doble cabezal', 'kit-dental', false],
            ['Correa reflectiva 1,5 m', 'accesorio', 65, 'Nylon resistente con costuras reflectivas para paseos de noche', 'correa', false],
            ['Desparasitante interno (4 comprimidos)', 'antiparasitario', 35, 'Para perros de hasta 10 kg · elimina parásitos intestinales', 'desparasitante', true],
            ['Pipeta antipulgas (3 dosis)', 'antiparasitario', 55, 'Protege de pulgas y garrapatas · una dosis al mes', 'pipeta', true],
            ['Collar antipulgas', 'antiparasitario', 70, 'Protección continua hasta por 8 meses', 'collar-antipulgas', true],
            ['Shampoo para piel sensible 250 ml', 'higiene', 48, 'Sin perfume, pH balanceado · alivia la picazón', 'shampoo', false],
            ['Alimento balanceado perro adulto 3 kg', 'alimento', 120, 'Croquetas con proteína de pollo y arroz', 'alimento', false],
            ['Snacks dentales (7 unidades)', 'alimento', 30, 'Ayudan a reducir el sarro y el mal aliento', 'snacks-dentales', false],
            ['Multivitamínico (60 tabletas)', 'medicamento', 42, 'Tabletas masticables para pelo, piel y defensas', 'vitaminas', true],
        ];
        foreach ($productos as $i => [$nombre, $cat, $precio, $desc, $foto, $cubre]) {
            Storage::disk('local')->put("productos/{$foto}.jpg", file_get_contents(__DIR__."/fotos/{$foto}.jpg"));
            $p = \App\Models\Producto::create(['veterinaria_id' => $vet->id, 'nombre' => $nombre, 'categoria' => $cat, 'precio' => $precio, 'descripcion' => $desc,
                'foto' => "productos/{$foto}.jpg", 'stock' => [12, 8, 6, 20, 15, 5, 10, 9, 25, 14][$i], 'en_tienda' => true, 'activo' => true, 'cubre_seguro' => $cubre]);
            $p->forceFill(['created_at' => now()->subMinutes(10 - $i)])->save();
        }

        $verificado = ['codigo_pais' => 'BO', 'email_verified_at' => now(), 'telefono_verificado_at' => now()];
        User::create(['name' => 'Administrador PetCare', 'email' => 'admin@petcare.com', 'password' => 'admin123', 'rol' => User::ADMINISTRADOR, 'telefono' => '70000001'])->forceFill($verificado)->save();
        User::create(['name' => 'Dra. Claudia Rojas', 'email' => 'veterinaria@clinica.com', 'password' => 'vet123', 'rol' => User::VETERINARIA, 'telefono' => '76541122', 'matricula' => 'MVZ-LP-1187', 'veterinaria_id' => $vet->id])->forceFill($verificado)->save();
        $juan = User::create(['name' => 'Juan Pérez', 'email' => 'juan@correo.com', 'password' => '123456', 'rol' => User::PROPIETARIO, 'telefono' => '70011223', 'direccion' => 'Calle 8 de Obrajes N.º 512']);
        $juan->forceFill($verificado)->save();

        // 1 mascota, sin plan: así se puede probar la compra del plan desde cero
        Mascota::create(['propietario_id' => $juan->id, 'nombre' => 'Max', 'color' => 'Dorado', 'especie' => 'Perro', 'raza' => 'Golden Retriever',
            'fecha_nacimiento' => $hoy->copy()->subMonths(38), 'peso' => 28, 'sexo' => 'Macho', 'esterilizado' => true]);
    }
}
