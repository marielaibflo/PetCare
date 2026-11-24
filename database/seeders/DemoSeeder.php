<?php

namespace Database\Seeders;

use App\Models\Afiliacion;
use App\Models\Atencion;
use App\Models\Cita;
use App\Models\Mascota;
use App\Models\Resena;
use App\Services\CitaService;
use App\Models\Pago;
use App\Models\Producto;
use App\Support\Catalogo;
use App\Models\Plan;
use App\Models\User;
use App\Models\Veterinaria;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Datos de demostración COMPLETOS (los usan las pruebas automáticas; también: composer run demo-db). Las fechas son relativas al día en que se ejecuta el seeder,
 * para que la agenda y los recordatorios siempre muestren casos actuales.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $hoy = Carbon::today();
        $d = fn (int $dias) => $hoy->copy()->addDays($dias);
        $m = fn (int $meses) => $hoy->copy()->addMonths($meses);

        // Veterinarias afiliadas
        // Veterinarias afiliadas (cada una con su suscripción, su WhatsApp y su tarifario)
        $todos = Catalogo::SERVICIOS;
        $tarifa = ['Consulta' => 80, 'Vacuna' => 120, 'Desparasitación' => 60, 'Tratamiento' => 100, 'Control de pulgas' => 70, 'Urgencia' => 200,
            'Cirugía / Castración' => 600, 'Post-operatorio' => 150, 'Corte de pelo y baño' => 90, 'Oncología y crónicos' => 450];
        $v1 = Veterinaria::create(['nombre' => 'Veterinaria Patitas', 'zona' => 'San Miguel', 'direccion' => 'Calle 21 de San Miguel N.º 845', 'telefono' => '22441122', 'whatsapp' => '76541122', 'horario' => 'Lun–Sáb · 08:30 a 18:00', 'servicios' => $todos, 'precios' => $tarifa, 'plan_suscripcion' => 'plus', 'suscripcion_hasta' => $d(40), 'nit' => '4512367019', 'registro' => 'SENASAG-LP-0321', 'descripcion' => 'Clínica familiar con más de 12 años cuidando a las mascotas de San Miguel. Consultorio, quirófano y sala de recuperación.', 'servicios_especiales' => ['Rayos X', 'Laboratorio', 'Internación', 'Peluquería'], 'metodos_pago' => ['QR', 'Efectivo', 'Tarjeta'], 'promocion' => 'Noviembre: 15 % de descuento en baño y corte con cualquier vacuna.', 'activa' => true, 'color' => 0]);
        $v2 = Veterinaria::create(['nombre' => 'Centro Veterinario Vida', 'zona' => 'Sopocachi', 'direccion' => 'Av. 20 de Octubre N.º 2130', 'telefono' => '22413344', 'whatsapp' => '76543344', 'horario' => 'Lun–Vie · 09:00 a 18:00', 'servicios' => ['Consulta', 'Vacuna', 'Desparasitación', 'Tratamiento', 'Cirugía / Castración', 'Post-operatorio'], 'precios' => ['Consulta' => 70, 'Vacuna' => 110, 'Desparasitación' => 55, 'Tratamiento' => 90, 'Cirugía / Castración' => 550, 'Post-operatorio' => 130], 'plan_suscripcion' => 'profesional', 'suscripcion_hasta' => $d(5), 'nit' => '3874512011', 'registro' => 'SENASAG-LP-0517', 'descripcion' => 'Centro especializado en cirugía y medicina interna, a media cuadra de la plaza Abaroa.', 'servicios_especiales' => ['Ecografía', 'Laboratorio', 'Internación', 'Atención 24 horas'], 'metodos_pago' => ['QR', 'Efectivo', 'Transferencia'], 'activa' => true, 'color' => 1]);
        $v3 = Veterinaria::create(['nombre' => 'VetHouse', 'zona' => 'Calacoto', 'direccion' => 'Calle 15 de Calacoto N.º 7820', 'telefono' => '22795566', 'whatsapp' => '76545566', 'horario' => 'Lun–Sáb · 08:30 a 17:30', 'servicios' => ['Consulta', 'Vacuna', 'Control de pulgas', 'Urgencia', 'Corte de pelo y baño'], 'precios' => ['Consulta' => 90, 'Vacuna' => 130, 'Control de pulgas' => 75, 'Urgencia' => 220, 'Corte de pelo y baño' => 100], 'plan_suscripcion' => 'profesional', 'suscripcion_hasta' => $d(22), 'nit' => '6123498014', 'registro' => 'SENASAG-LP-0788', 'descripcion' => 'Urgencias, estética y tienda para tu mascota en la zona Sur.', 'servicios_especiales' => ['Atención a domicilio', 'Peluquería', 'Hotel / guardería'], 'metodos_pago' => ['QR', 'Tarjeta'], 'promocion' => 'Primera consulta de cachorros a Bs 60.', 'activa' => true, 'color' => 2]);
        $v4 = Veterinaria::create(['nombre' => 'Clínica Huellitas', 'zona' => 'Miraflores', 'direccion' => 'Av. Busch N.º 1450', 'telefono' => '22227788', 'horario' => 'Lun–Vie · 09:00 a 17:00', 'servicios' => ['Consulta', 'Vacuna'], 'plan_suscripcion' => 'inicial', 'registro' => 'SENASAG-LP-0902', 'activa' => false, 'color' => 3]);
        // Solicitud de afiliación en línea, esperando la revisión del administrador
        $v5 = Veterinaria::create(['nombre' => 'Clínica Veterinaria San Roque', 'zona' => 'Achumani', 'direccion' => 'Calle 17 de Achumani N.º 330', 'telefono' => '22711020', 'whatsapp' => '71230099', 'horario' => 'Lun–Sáb · 09:00 a 19:00', 'servicios' => ['Consulta', 'Vacuna', 'Desparasitación', 'Corte de pelo y baño'], 'plan_suscripcion' => 'profesional', 'estado' => 'pendiente', 'nit' => '7012345016', 'registro' => 'SENASAG-LP-1104', 'terminos_at' => now(), 'activa' => false, 'color' => 4]);

        // Fecha en que cada clínica fue aprobada (afiliada desde)
        foreach ([[$v1, -420], [$v2, -210], [$v3, -95], [$v4, -520]] as [$v, $dias]) {
            $v->update(['aprobada_at' => $d($dias), 'created_at' => $d($dias)]);
        }

        // Usuarios (contraseñas de demostración documentadas en el README)
        User::create(['name' => 'Administrador PetCare', 'email' => 'admin@petcare.com', 'password' => 'admin123', 'rol' => User::ADMINISTRADOR, 'telefono' => '70000001']);
        $maria = User::create(['name' => 'María López', 'email' => 'maria@correo.com', 'password' => '123456', 'rol' => User::PROPIETARIO, 'telefono' => '70011223', 'direccion' => 'Calle 8 de Obrajes N.º 512']);
        $carlos = User::create(['name' => 'Carlos Mamani', 'email' => 'carlos@correo.com', 'password' => '123456', 'rol' => User::PROPIETARIO, 'telefono' => '71234567', 'direccion' => 'Av. Arce N.º 2299, Sopocachi']);
        $ana = User::create(['name' => 'Ana Quispe', 'email' => 'ana@correo.com', 'password' => '123456', 'rol' => User::PROPIETARIO, 'telefono' => '76543210', 'direccion' => 'Calle 21 de Calacoto N.º 1033']);
        $claudia = User::create(['name' => 'Dra. Claudia Rojas', 'email' => 'veterinaria@clinica.com', 'password' => 'vet123', 'rol' => User::VETERINARIA, 'telefono' => '22441122', 'matricula' => 'MVZ-LP-1187', 'veterinaria_id' => $v1->id]);
        $jorge = User::create(['name' => 'Dr. Jorge Vargas', 'email' => 'vida@clinica.com', 'password' => 'vet123', 'rol' => User::VETERINARIA, 'telefono' => '22413344', 'matricula' => 'MVZ-LP-0954', 'veterinaria_id' => $v2->id]);
        $paola = User::create(['name' => 'Dra. Paola Chávez', 'email' => 'vethouse@clinica.com', 'password' => 'vet123', 'rol' => User::VETERINARIA, 'telefono' => '22795566', 'matricula' => 'MVZ-LP-1422', 'veterinaria_id' => $v3->id]);
        User::create(['name' => 'Dr. Luis Flores', 'email' => 'huellitas@clinica.com', 'password' => 'vet123', 'rol' => User::VETERINARIA, 'telefono' => '22227788', 'matricula' => 'MVZ-LP-0610', 'veterinaria_id' => $v4->id, 'activo' => false]);
        $roque = User::create(['name' => 'Dr. Roque Mendoza', 'email' => 'sanroque@clinica.com', 'password' => 'Clave#123', 'rol' => User::VETERINARIA, 'telefono' => '71230099', 'matricula' => 'MVZ-LP-1530', 'veterinaria_id' => $v5->id, 'activo' => false]);
        // Las cuentas de demostración ya tienen el correo y el celular verificados.
        User::query()->update(['codigo_pais' => 'BO', 'email_verified_at' => now(), 'telefono_verificado_at' => now()]);
        // San Roque ya confirmó su correo: su solicitud le llegó al administrador y espera aprobación.
        $roque->forceFill(['telefono_verificado_at' => null])->save();
        $v5->update(['admin_avisado_at' => now()->subHours(3), 'cobro_metodo' => 'tarjeta', 'cobro_marca' => 'visa', 'cobro_ultimos' => '1881', 'cobro_titular' => 'ROQUE MENDOZA', 'cobro_vence' => '09/29']);
        // Forma de cobro mensual de cada clínica (tarjeta = cobro automático simulado; QR/WhatsApp = aviso para pagar)
        $v1->update(['cobro_metodo' => 'tarjeta', 'cobro_marca' => 'visa', 'cobro_ultimos' => '4242', 'cobro_titular' => 'CLAUDIA ROJAS', 'cobro_vence' => '12/28']);
        $v2->update(['cobro_metodo' => 'qr']);
        $v3->update(['cobro_metodo' => 'tarjeta', 'cobro_marca' => 'mastercard', 'cobro_ultimos' => '4444', 'cobro_titular' => 'PAOLA CHAVEZ', 'cobro_vence' => '05/27']);
        $v4->update(['cobro_metodo' => 'whatsapp']);
        foreach ([$maria, $carlos, $ana, $claudia, $jorge, $paola] as $u) {
            $u->refresh();
        }

        // Planes de asistencia por porcentaje de cobertura (PetCare paga ese % de cada atención cubierta)
        $sinGrave = array_values(array_diff($todos, [Catalogo::GRAVE]));
        $basico = Plan::create(['nombre' => 'Plan Básico', 'descripcion' => 'Cuidado preventivo: vacunas, desparasitación y estética.', 'precio' => 35, 'porcentaje' => 40, 'tope_mensual' => 100, 'tope_anual' => 600, 'limite_atenciones' => 4, 'espera_cirugia_dias' => 0, 'medicamentos' => 'urgencia', 'permite_grave' => false,
            'cobertura' => ['Vacuna', 'Desparasitación', 'Control de pulgas', 'Corte de pelo y baño'], 'beneficios' => ['40 % en vacunas y desparasitaciones', '40 % en corte de pelo y baño', 'Descuento en medicamentos de urgencia'], 'icono' => 'shield', 'color' => 'verde', 'vigente_desde' => $hoy->copy()->subMonths(6)]);
        $estandar = Plan::create(['nombre' => 'Plan Estándar', 'descripcion' => 'Consultas, emergencias, medicamentos y cirugías.', 'precio' => 79, 'porcentaje' => 60, 'tope_mensual' => 250, 'tope_anual' => 1500, 'limite_atenciones' => 8, 'espera_cirugia_dias' => 60, 'medicamentos' => 'todos', 'permite_grave' => true,
            'cobertura' => $sinGrave, 'beneficios' => ['60 % en consultas, vacunas y emergencias', '60 % en medicamentos y tratamientos', 'Castraciones y post-operatorio (desde el día 60)'], 'icono' => 'steth', 'color' => 'azul', 'vigente_desde' => $hoy->copy()->subMonths(6)]);
        $premium = Plan::create(['nombre' => 'Plan Premium', 'descripcion' => 'La cobertura más completa para tu mascota.', 'precio' => 149, 'porcentaje' => 90, 'tope_mensual' => 800, 'tope_anual' => 5000, 'limite_atenciones' => 12, 'espera_cirugia_dias' => 30, 'medicamentos' => 'todos', 'permite_grave' => true,
            'cobertura' => $sinGrave, 'beneficios' => ['90 % en citas, vacunas, emergencias y medicamentos', '90 % en cirugías, castraciones y post-operatorio', 'Tope de hasta Bs 5.000 al año'], 'icono' => 'crown', 'color' => 'dorado', 'vigente_desde' => $hoy->copy()->subMonths(6)]);

        // Catálogo de productos de las clínicas (precio y qué planes los cubren)
        $prod = fn (Veterinaria $v, string $n, string $c, float $p, bool $cub = true, array $pl = [], ?string $desc = null) => Producto::create(['veterinaria_id' => $v->id, 'nombre' => $n, 'categoria' => $c, 'precio' => $p, 'cubre_seguro' => $cub && ! in_array($c, Catalogo::PRODUCTOS_TIENDA, true), 'planes' => $pl, 'activo' => true, 'descripcion' => $desc]);
        $amoxi = $prod($v1, 'Amoxicilina 500 mg (caja)', 'medicamento', 45);
        $prod($v1, 'Meloxicam inyectable', 'medicamento', 35);
        $prod($v1, 'Suero fisiológico 500 ml', 'insumo', 25);
        $antirrabica = $prod($v1, 'Vacuna antirrábica', 'vacuna', 60);
        $prod($v1, 'Vacuna séxtuple canina', 'vacuna', 95);
        $prod($v1, 'Vacuna triple felina', 'vacuna', 90);
        $prod($v1, 'Antiparasitario oral (tableta)', 'antiparasitario', 30);
        $prod($v1, 'Pipeta antipulgas', 'antiparasitario', 55);
        $prod($v1, 'Shampoo medicado', 'insumo', 40, false);
        $prod($v1, 'Collar isabelino', 'insumo', 35, true, [$estandar->id, $premium->id]);
        $prod($v1, 'Alimento Pro Plan adulto 3 kg', 'alimento', 210, false, [], 'Perros adultos de raza mediana');
        $prod($v1, 'Arena sanitaria 5 kg', 'higiene', 48, false, [], 'Aglomerante, sin olor');
        $prod($v1, 'Correa reflectiva', 'accesorio', 65, false, [], 'Talla M, varios colores');
        $prod($v1, 'Pelota de caucho', 'juguete', 25, false, [], 'Resistente a mordidas');
        $prod($v2, 'Cefalexina 500 mg', 'medicamento', 50);
        $prod($v2, 'Vacuna antirrábica', 'vacuna', 55);
        $prod($v3, 'Pipeta antipulgas', 'antiparasitario', 60);
        $prod($v3, 'Antiinflamatorio oral', 'medicamento', 40);
        $prod($v3, 'Shampoo avena 250 ml', 'higiene', 45, false, [], 'Piel sensible');
        $prod($v3, 'Cama acolchada', 'accesorio', 180, false, [], 'Lavable, 60 × 45 cm');
        $prod($v3, 'Snacks dentales', 'alimento', 38, false, [], 'Bolsa de 7 unidades');

        // Tienda: los insumos clínicos no se venden; un producto agotado de ejemplo
        Producto::where('categoria', 'insumo')->update(['en_tienda' => false]);
        Producto::where('nombre', 'Pelota de caucho')->update(['stock' => 0]);
        $v1->update(['envio_activo' => true, 'envio_costo' => 15, 'envio_zonas' => 'San Miguel, Calacoto y Obrajes']);

        // Mascotas
        $max = Mascota::create(['propietario_id' => $maria->id, 'nombre' => 'Max', 'color' => 'Dorado', 'especie' => 'Perro', 'raza' => 'Golden Retriever', 'fecha_nacimiento' => $m(-38), 'peso' => 28, 'sexo' => 'Macho', 'esterilizado' => true]);
        $luna = Mascota::create(['propietario_id' => $maria->id, 'nombre' => 'Luna', 'color' => 'Crema con puntas marrón', 'especie' => 'Gato', 'raza' => 'Siamés', 'fecha_nacimiento' => $m(-26), 'peso' => 4.2, 'sexo' => 'Hembra', 'esterilizado' => true]);
        $rocky = Mascota::create(['propietario_id' => $carlos->id, 'nombre' => 'Rocky', 'color' => 'Atigrado', 'especie' => 'Perro', 'raza' => 'Bulldog francés', 'fecha_nacimiento' => $m(-50), 'peso' => 12, 'sexo' => 'Macho', 'esterilizado' => false, 'alergias' => 'Pollo']);
        $michi = Mascota::create(['propietario_id' => $ana->id, 'nombre' => 'Michi', 'color' => 'Gris atigrado', 'especie' => 'Gato', 'raza' => 'Mestizo', 'fecha_nacimiento' => $m(-14), 'peso' => 3.6, 'sexo' => 'Hembra', 'esterilizado' => false]);
        $toby = Mascota::create(['propietario_id' => $ana->id, 'nombre' => 'Toby', 'color' => 'Tricolor', 'especie' => 'Perro', 'raza' => 'Beagle', 'fecha_nacimiento' => $m(-62), 'peso' => 14, 'sexo' => 'Macho', 'esterilizado' => true]);

        // Afiliaciones
        Afiliacion::create(['mascota_id' => $max->id, 'plan_id' => $premium->id, 'modalidad' => 'anual', 'fecha_inicio' => $d(-60), 'fecha_fin' => $d(-60)->addMonths(12), 'estado' => 'activo']);
        Afiliacion::create(['mascota_id' => $luna->id, 'plan_id' => $basico->id, 'modalidad' => 'mensual', 'fecha_inicio' => $d(-150), 'fecha_fin' => $d(5), 'estado' => 'activo']);
        Afiliacion::create(['mascota_id' => $rocky->id, 'plan_id' => $estandar->id, 'modalidad' => 'anual', 'cobertura_grave' => true, 'fecha_inicio' => $d(-100), 'fecha_fin' => $d(-100)->addMonths(12), 'estado' => 'activo']);
        Afiliacion::create(['mascota_id' => $toby->id, 'plan_id' => $premium->id, 'modalidad' => 'mensual', 'fecha_inicio' => $d(-70), 'fecha_fin' => $d(20), 'estado' => 'activo']);

        // Pagos: los aprobados activaron los planes; uno queda pendiente de verificación (Michi → Plan Estándar)
        $pago = fn (array $a) => Pago::create($a + ['metodo' => 'qr', 'estado' => 'aprobado', 'meses' => 1, 'modalidad' => 'mensual']);
        $pago(['numero' => 'PC-'.$hoy->year.'-000001', 'user_id' => $maria->id, 'concepto' => 'plan', 'mascota_id' => $max->id, 'plan_id' => $premium->id, 'modalidad' => 'anual', 'meses' => 12, 'monto' => 1490, 'facturar_a' => 'María López', 'documento' => '4785123', 'revisado_at' => $d(-60), 'created_at' => $d(-61)]);
        $pago(['numero' => 'PC-'.$hoy->year.'-000002', 'user_id' => $maria->id, 'concepto' => 'plan', 'mascota_id' => $luna->id, 'plan_id' => $basico->id, 'monto' => 35, 'facturar_a' => 'María López', 'documento' => '4785123', 'revisado_at' => $d(-25), 'created_at' => $d(-25)]);
        $pago(['numero' => 'PC-'.$hoy->year.'-000003', 'user_id' => $carlos->id, 'concepto' => 'plan', 'mascota_id' => $rocky->id, 'plan_id' => $estandar->id, 'cobertura_grave' => true, 'modalidad' => 'anual', 'meses' => 12, 'monto' => 1090, 'facturar_a' => 'Carlos Mamani', 'documento' => '5123987', 'metodo' => 'tarjeta', 'tarjeta_ultimos' => '4242', 'revisado_at' => $d(-100), 'created_at' => $d(-100)]);
        $pago(['numero' => 'PC-'.$hoy->year.'-000004', 'user_id' => $ana->id, 'concepto' => 'plan', 'mascota_id' => $toby->id, 'plan_id' => $premium->id, 'monto' => 149, 'facturar_a' => 'Ana Quispe', 'documento' => '6987412', 'revisado_at' => $d(-10), 'created_at' => $d(-10)]);
        $pago(['numero' => 'PC-'.$hoy->year.'-000005', 'user_id' => $claudia->id, 'concepto' => 'suscripcion', 'veterinaria_id' => $v1->id, 'plan_suscripcion' => 'plus', 'monto' => 400, 'facturar_a' => 'Veterinaria Patitas', 'documento' => '4512367019', 'revisado_at' => $d(-20), 'created_at' => $d(-20)]);
        Pago::create(['user_id' => $ana->id, 'concepto' => 'plan', 'mascota_id' => $michi->id, 'plan_id' => $estandar->id, 'modalidad' => 'mensual', 'meses' => 1, 'monto' => 79, 'metodo' => 'tarjeta', 'tarjeta_ultimos' => '4242', 'facturar_a' => 'Ana Quispe', 'documento' => '6987412', 'estado' => 'pendiente']);
        Pago::query()->whereNotNull('revisado_at')->update(['revisado_por' => User::where('rol', User::ADMINISTRADOR)->value('id')]);

        // Citas (si hoy es domingo, las de "hoy" se mueven al lunes)
        $citaDia = $hoy->isSunday() ? $d(1) : $hoy->copy();
        $habil = fn (Carbon $f) => $f->isSunday() ? $f->addDay() : $f;
        $c1 = Cita::create(['mascota_id' => $max->id, 'veterinaria_id' => $v1->id, 'servicio' => 'Consulta', 'fecha' => $citaDia, 'hora' => '10:00', 'estado' => 'confirmada', 'nota' => 'Control general']);
        Cita::create(['mascota_id' => $luna->id, 'veterinaria_id' => $v1->id, 'servicio' => 'Vacuna', 'fecha' => $citaDia, 'hora' => '11:30', 'estado' => 'pendiente', 'nota' => 'Refuerzo de vacuna triple felina']);
        Cita::create(['mascota_id' => $rocky->id, 'veterinaria_id' => $v1->id, 'servicio' => 'Desparasitación', 'fecha' => $citaDia, 'hora' => '14:00', 'estado' => 'confirmada']);
        Cita::create(['mascota_id' => $max->id, 'veterinaria_id' => $v1->id, 'servicio' => 'Vacuna', 'fecha' => $habil($d(23)), 'hora' => '09:30', 'estado' => 'pendiente', 'nota' => 'Refuerzo de vacuna séxtuple']);
        Cita::create(['mascota_id' => $luna->id, 'veterinaria_id' => $v2->id, 'servicio' => 'Desparasitación', 'fecha' => $habil($d(31)), 'hora' => '15:00', 'estado' => 'pendiente']);
        Cita::create(['mascota_id' => $toby->id, 'veterinaria_id' => $v3->id, 'servicio' => 'Consulta', 'fecha' => $habil($d(2)), 'hora' => '16:00', 'estado' => 'confirmada', 'nota' => 'Revisión de oído', 'respondida_at' => now()]);
        Cita::create(['mascota_id' => $toby->id, 'veterinaria_id' => $v1->id, 'servicio' => 'Urgencia', 'fecha' => $citaDia, 'hora' => '17:30', 'estado' => 'pendiente', 'urgente' => true, 'nota' => 'Vomita desde la mañana y no quiere comer']);
        $citaMax = Cita::create(['mascota_id' => $max->id, 'veterinaria_id' => $v1->id, 'servicio' => 'Consulta', 'fecha' => $d(-15), 'hora' => '10:00', 'estado' => 'atendida', 'respondida_at' => $d(-16)]);
        $citaRocky = Cita::create(['mascota_id' => $rocky->id, 'veterinaria_id' => $v2->id, 'servicio' => 'Consulta', 'fecha' => $d(-10), 'hora' => '11:00', 'estado' => 'atendida', 'respondida_at' => $d(-11)]);
        Cita::create(['mascota_id' => $rocky->id, 'veterinaria_id' => $v3->id, 'servicio' => 'Corte de pelo y baño', 'fecha' => $habil($d(3)), 'hora' => '10:30', 'estado' => 'rechazada', 'motivo_rechazo' => 'Ese día no tenemos peluquero; le sugerimos el sábado a las 09:00.', 'respondida_at' => now()]);

        // Historial clínico
        // Cada atención guarda el total, el % del plan, lo que pagó PetCare y lo que pagó el dueño.
        $at = function (array $a) {
            $total = $a['total'] ?? 0;
            $pct = $a['cubierto_por_plan'] ? ($a['pct'] ?? 0) : 0;
            $cub = round($total * $pct / 100, 2);
            unset($a['pct']);

            return Atencion::create($a + ['tratamiento' => 'Ninguno', 'costo_servicio' => $total, 'porcentaje' => $pct, 'monto_cubierto' => $cub, 'paga_dueno' => $total - $cub,
                'plan_id' => $cub > 0 ? Mascota::find($a['mascota_id'])?->afiliacionActiva?->plan_id : null]);
        };
        $at(['mascota_id' => $max->id, 'veterinaria_id' => $v1->id, 'user_id' => $claudia->id, 'cita_id' => $citaMax->id, 'fecha' => $d(-15), 'servicio' => 'Consulta', 'diagnostico' => 'Sano', 'descripcion' => 'Revisión general. Buen estado de salud.', 'peso' => 28, 'temperatura' => 38.5, 'cubierto_por_plan' => true, 'total' => 80, 'pct' => 90]);
        $at(['mascota_id' => $max->id, 'veterinaria_id' => $v1->id, 'user_id' => $claudia->id, 'fecha' => $d(-38), 'servicio' => 'Vacuna', 'producto' => 'Antirrábica', 'proxima_dosis' => $d(327), 'descripcion' => 'Vacuna antirrábica aplicada.', 'cubierto_por_plan' => true, 'total' => 120, 'pct' => 90, 'firmado_por' => 'Dra. Claudia Rojas · Veterinaria Patitas', 'matricula' => 'MVZ-LP-1187', 'registro_clinica' => 'SENASAG-LP-0321', 'lote' => 'L'.random_int(20000, 99999)]);
        $at(['mascota_id' => $max->id, 'veterinaria_id' => $v1->id, 'user_id' => $claudia->id, 'fecha' => $d(-66), 'servicio' => 'Desparasitación', 'producto' => 'Antiparasitario interno y externo', 'proxima_dosis' => $d(24), 'descripcion' => 'Desparasitación interna y externa.', 'tratamiento' => 'Antiparasitario oral, dosis única', 'cubierto_por_plan' => true, 'total' => 60, 'pct' => 90, 'firmado_por' => 'Dra. Claudia Rojas · Veterinaria Patitas', 'matricula' => 'MVZ-LP-1187', 'registro_clinica' => 'SENASAG-LP-0321', 'lote' => 'L'.random_int(20000, 99999)]);
        $at(['mascota_id' => $max->id, 'veterinaria_id' => $v2->id, 'user_id' => $jorge->id, 'fecha' => $d(-97), 'servicio' => 'Tratamiento', 'diagnostico' => 'Dermatitis leve', 'descripcion' => 'Infección leve de piel en el lomo.', 'tratamiento' => 'Antibiótico tópico por 7 días', 'cubierto_por_plan' => false, 'total' => 90]);
        $at(['mascota_id' => $max->id, 'veterinaria_id' => $v1->id, 'user_id' => $claudia->id, 'fecha' => $d(-370), 'servicio' => 'Vacuna', 'producto' => 'Séxtuple anual', 'proxima_dosis' => $d(-5), 'descripcion' => 'Vacuna séxtuple anual.', 'cubierto_por_plan' => false, 'total' => 120, 'firmado_por' => 'Dra. Claudia Rojas · Veterinaria Patitas', 'matricula' => 'MVZ-LP-1187', 'registro_clinica' => 'SENASAG-LP-0321', 'lote' => 'L'.random_int(20000, 99999)]);
        $at(['mascota_id' => $luna->id, 'veterinaria_id' => $v1->id, 'user_id' => $claudia->id, 'fecha' => $d(-20), 'servicio' => 'Vacuna', 'producto' => 'Triple felina (1.ª dosis)', 'proxima_dosis' => $d(6), 'descripcion' => 'Vacuna triple felina, primera dosis.', 'cubierto_por_plan' => true, 'total' => 120, 'pct' => 40, 'firmado_por' => 'Dra. Claudia Rojas · Veterinaria Patitas', 'matricula' => 'MVZ-LP-1187', 'registro_clinica' => 'SENASAG-LP-0321', 'lote' => 'L'.random_int(20000, 99999)]);
        $at(['mascota_id' => $rocky->id, 'veterinaria_id' => $v2->id, 'user_id' => $jorge->id, 'cita_id' => $citaRocky->id, 'fecha' => $d(-10), 'servicio' => 'Consulta', 'diagnostico' => 'Síndrome braquicefálico leve', 'descripcion' => 'Dificultad respiratoria leve, propia de la raza.', 'tratamiento' => 'Control en 30 días', 'cubierto_por_plan' => true, 'total' => 70, 'pct' => 60]);
        $at(['mascota_id' => $rocky->id, 'veterinaria_id' => $v1->id, 'user_id' => $claudia->id, 'fecha' => $d(-45), 'servicio' => 'Vacuna', 'producto' => 'Séxtuple anual', 'proxima_dosis' => $d(320), 'descripcion' => 'Vacuna séxtuple anual.', 'cubierto_por_plan' => false, 'total' => 120, 'firmado_por' => 'Dra. Claudia Rojas · Veterinaria Patitas', 'matricula' => 'MVZ-LP-1187', 'registro_clinica' => 'SENASAG-LP-0321', 'lote' => 'L'.random_int(20000, 99999)]);
        $at(['mascota_id' => $toby->id, 'veterinaria_id' => $v3->id, 'user_id' => $paola->id, 'fecha' => $d(-5), 'servicio' => 'Control de pulgas', 'producto' => 'Pipeta antipulgas', 'proxima_dosis' => $d(25), 'descripcion' => 'Presencia de pulgas, se aplicó pipeta.', 'tratamiento' => 'Pipeta antipulgas', 'cubierto_por_plan' => true, 'total' => 75, 'pct' => 90, 'firmado_por' => 'Dra. Paola Chávez · VetHouse', 'matricula' => 'MVZ-LP-1422', 'registro_clinica' => 'SENASAG-LP-0788', 'lote' => 'L'.random_int(20000, 99999)]);
        $at(['mascota_id' => $toby->id, 'veterinaria_id' => $v1->id, 'user_id' => $claudia->id, 'fecha' => $d(-25), 'servicio' => 'Consulta', 'descripcion' => 'Control de peso.', 'tratamiento' => 'Dieta controlada', 'cubierto_por_plan' => true, 'total' => 80, 'pct' => 90]);
        $at(['mascota_id' => $michi->id, 'veterinaria_id' => $v2->id, 'user_id' => $jorge->id, 'fecha' => $d(-40), 'servicio' => 'Consulta', 'descripcion' => 'Primera consulta, buen desarrollo.', 'cubierto_por_plan' => false, 'total' => 70]);

        // Calificaciones de los dueños (después de citas atendidas). Max queda sin calificar para probarlo.
        $res = fn (Veterinaria $v, User $u, int $e, ?string $c = null, ?Cita $ci = null, int $dias = -20) => Resena::create(['veterinaria_id' => $v->id, 'user_id' => $u->id, 'cita_id' => $ci?->id, 'estrellas' => $e, 'comentario' => $c, 'created_at' => $d($dias), 'updated_at' => $d($dias)]);
        $res($v1, $maria, 5, 'Muy amables con Luna, le explicaron todo con paciencia.', null, -20);
        $res($v1, $ana, 5, 'Atendieron a Toby rápido y el lugar está muy limpio.', null, -25);
        $res($v1, $carlos, 4, 'Buena atención, a veces hay que esperar un poco.', null, -45);
        $res($v2, $carlos, 5, 'El Dr. Vargas sabe mucho de bulldogs. Recomendado.', $citaRocky, -9);
        $res($v2, $ana, 4, null, null, -40);
        $res($v3, $ana, 4, 'Buen servicio de peluquería.', null, -5);
        $res($v3, $maria, 3, 'Precios un poco altos.', null, -60);
        foreach ([$v1, $v2, $v3, $v4] as $v) {
            $v->recalcularCalificacion();
        }

        // Monto estimado de las citas abiertas (precio de la clínica menos lo que cubre el plan)
        $citas = app(CitaService::class);
        Cita::whereIn('estado', ['pendiente', 'confirmada'])->get()->each(function (Cita $c) use ($citas) {
            $e = $citas->estimar($c->mascota, $c->veterinaria, $c->servicio, $c->fecha);
            $c->update(['monto_estimado' => $e['precio'], 'cubierto_estimado' => $e['cubierto']]);
        });
    }
}
