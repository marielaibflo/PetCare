<?php

namespace Tests\Feature;

use App\Models\Afiliacion;
use App\Models\Atencion;
use App\Models\Cita;
use App\Models\Mascota;
use App\Models\Pago;
use App\Models\Plan;
use App\Models\Producto;
use App\Models\User;
use App\Models\Veterinaria;
use App\Services\CoberturaService;
use App\Services\WhatsAppService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Etapas 2 a 7: citas con WhatsApp, tarifario, planes por porcentaje, cartilla digital, pagos y suscripciones. */
class NuevasFuncionesTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function u(string $email): User
    {
        return User::where('email', $email)->first();
    }

    private function mascota(string $n): Mascota
    {
        return Mascota::where('nombre', $n)->first();
    }

    private function diaHabil(int $dias): string
    {
        $d = Carbon::today()->addDays($dias);
        while ($d->isSunday()) {
            $d->addDay();
        }

        return $d->toDateString();
    }

    // ---------- Etapa 2 · Citas y WhatsApp ----------

    public function test_cita_nueva_avisa_por_whatsapp_al_dueno_y_a_la_clinica(): void
    {
        WhatsAppService::$enviados = [];
        $maria = $this->u('maria@correo.com');
        $patitas = Veterinaria::where('nombre', 'Veterinaria Patitas')->first();
        $this->actingAs($maria)->post('/propietario/citas', ['mascota_id' => $this->mascota('Max')->id, 'veterinaria_id' => $patitas->id,
            'servicio' => 'Consulta', 'fecha' => $this->diaHabil(6), 'hora' => '17:30'])->assertSessionHasNoErrors();
        $para = array_column(WhatsAppService::$enviados, 'para');
        $this->assertContains('59170011223', $para);      // María
        $this->assertContains('59176541122', $para);      // WhatsApp de Veterinaria Patitas
        $this->assertDatabaseHas('mensajes_whatsapp', ['para' => '59176541122']);
    }

    public function test_la_clinica_acepta_rechaza_con_motivo_devuelve_a_pendiente_y_marca_atendida(): void
    {
        $vet = $this->u('veterinaria@clinica.com');
        $cita = Cita::where('veterinaria_id', $vet->veterinaria_id)->where('estado', 'pendiente')->first();

        $this->actingAs($vet)->patch("/veterinaria/citas/{$cita->id}/rechazar", ['motivo' => ''])->assertSessionHasErrors('motivo');
        WhatsAppService::$enviados = [];
        $this->actingAs($vet)->patch("/veterinaria/citas/{$cita->id}/rechazar", ['motivo' => 'No hay disponibilidad ese día'])->assertSessionHasNoErrors();
        $this->assertSame('rechazada', $cita->fresh()->estado);
        $this->assertStringContainsString('No hay disponibilidad', end(WhatsAppService::$enviados)['mensaje']);

        $this->actingAs($vet)->patch("/veterinaria/citas/{$cita->id}/pendiente")->assertSessionHasNoErrors();
        $this->assertSame('pendiente', $cita->fresh()->estado);
        $this->actingAs($vet)->patch("/veterinaria/citas/{$cita->id}/confirmar")->assertSessionHasNoErrors();
        $this->assertSame('confirmada', $cita->fresh()->estado);

        if ($cita->fecha->lte(today())) {
            $this->actingAs($vet)->patch("/veterinaria/citas/{$cita->id}/atendida")->assertSessionHasNoErrors();
            $this->assertSame('atendida', $cita->fresh()->estado);
        }
        // El dueño ve el motivo de las citas rechazadas
        $this->actingAs($this->u('carlos@correo.com'))->get('/propietario/citas')->assertSee('le sugerimos el sábado');
    }

    public function test_una_cita_rechazada_libera_el_horario(): void
    {
        $rechazada = Cita::where('estado', 'rechazada')->first();
        $michi = $this->mascota('Michi');
        $this->actingAs($this->u('ana@correo.com'))->post('/propietario/citas', ['mascota_id' => $michi->id, 'veterinaria_id' => $rechazada->veterinaria_id,
            'servicio' => 'Consulta', 'fecha' => $rechazada->fecha->toDateString(), 'hora' => $rechazada->hora])->assertSessionHasNoErrors();
    }

    // ---------- Etapa 3 · Tarifario y medicamentos ----------

    public function test_tarifario_y_catalogo_de_productos(): void
    {
        $vet = $this->u('vida@clinica.com');
        $this->actingAs($vet)->put('/veterinaria/clinica/tarifario', ['precios' => ['Consulta' => '75,50', 'Vacuna' => '115']])->assertSessionHasNoErrors();
        $this->assertEquals(75.5, $vet->veterinaria->fresh()->precio('Consulta'));

        $estandar = Plan::where('nombre', 'Plan Estándar')->first();
        $this->actingAs($vet)->post('/veterinaria/productos', ['nombre' => 'Ivermectina', 'categoria' => 'antiparasitario', 'precio' => '30',
            'cubre_seguro' => '1', 'planes' => [$estandar->id]])->assertSessionHasNoErrors();
        $p = Producto::where('nombre', 'Ivermectina')->first();
        $this->assertSame([$estandar->id], $p->planes);
        $this->assertTrue($p->aplicaA($estandar));
        $this->assertFalse($p->aplicaA(Plan::where('nombre', 'Plan Premium')->first()));

        // Otra clínica no puede editar el producto
        $this->actingAs($this->u('veterinaria@clinica.com'))->put("/veterinaria/productos/{$p->id}", ['nombre' => 'X', 'categoria' => 'insumo', 'precio' => 1])->assertNotFound();
    }

    public function test_la_suscripcion_inicial_limita_los_productos(): void
    {
        $vida = Veterinaria::where('nombre', 'Centro Veterinario Vida')->first();
        // Básico: 10 productos a la venta · Profesional: 20 · Clínica Plus: ilimitados
        $vida->update(['plan_suscripcion' => 'inicial']);
        foreach (range(1, 10 - $vida->productosEnVentaQuery()->count()) as $i) {
            Producto::create(['veterinaria_id' => $vida->id, 'nombre' => "P{$i}", 'categoria' => 'alimento', 'precio' => 5, 'stock' => 3, 'en_tienda' => true, 'activo' => true]);
        }
        $vet = $this->u('vida@clinica.com');
        $this->actingAs($vet)->post('/veterinaria/productos', ['nombre' => 'Uno más', 'categoria' => 'alimento', 'precio' => '5', 'stock' => 4, 'en_tienda' => 1])->assertSessionHasErrors('regla');
        // Agotado o solo de uso clínico: no cuenta para el límite
        $this->actingAs($vet)->post('/veterinaria/productos', ['nombre' => 'Agotado', 'categoria' => 'alimento', 'precio' => '5', 'stock' => 0, 'en_tienda' => 1])->assertSessionHasNoErrors();
        $this->actingAs($vet)->post('/veterinaria/productos', ['nombre' => 'Suero', 'categoria' => 'insumo', 'precio' => '5', 'stock' => 9])->assertSessionHasNoErrors();
        // Al subir a Profesional se habilitan 10 más
        $vida->update(['plan_suscripcion' => 'profesional']);
        $this->actingAs($vet->fresh())->post('/veterinaria/productos', ['nombre' => 'Uno más', 'categoria' => 'alimento', 'precio' => '5', 'stock' => 4, 'en_tienda' => 1])->assertSessionHasNoErrors();
        $this->assertSame(11, $vida->fresh()->productosEnVentaQuery()->count());
        // Si baja a Básico, no se borra nada: solo se muestran los 10 más recientes
        $vida->update(['plan_suscripcion' => 'inicial']);
        $this->assertCount(10, $vida->fresh()->idsVisiblesEnTienda());
    }

    // ---------- Etapa 4 · Planes por porcentaje ----------

    private function calcular(string $mascota, string $servicio, float $costo, array $items = [], bool $pre = false, string $vet = 'Veterinaria Patitas'): array
    {
        return app(CoberturaService::class)->calcular($this->mascota($mascota), Veterinaria::where('nombre', $vet)->first(), $servicio, $costo, $items, Carbon::today(), $pre);
    }

    public function test_cada_plan_cubre_su_porcentaje(): void
    {
        $this->assertEquals(108, $this->calcular('Max', 'Vacuna', 120)['monto_cubierto']);      // Premium 90 %
        $this->assertEquals(48, $this->calcular('Luna', 'Vacuna', 120)['monto_cubierto']);      // Básico 40 %
        $this->assertEquals(48, $this->calcular('Rocky', 'Consulta', 80)['monto_cubierto']);   // Estándar 60 %
        $r = $this->calcular('Michi', 'Consulta', 80);                                           // sin plan
        $this->assertEquals(0, $r['monto_cubierto']);
        $this->assertEquals(80, $r['paga_dueno']);
    }

    public function test_el_basico_no_cubre_consultas_y_solo_medicamentos_de_urgencia(): void
    {
        $amoxi = Producto::where('nombre', 'Amoxicilina 500 mg (caja)')->first();
        $consulta = $this->calcular('Luna', 'Consulta', 80, [['producto' => $amoxi, 'cantidad' => 1]]);
        $this->assertEquals(0, $consulta['monto_cubierto']);
        $urgencia = $this->calcular('Luna', 'Urgencia', 0, [['producto' => $amoxi, 'cantidad' => 2]]);
        $this->assertEquals(36, $urgencia['monto_cubierto']);   // 40 % de Bs 90 en medicamentos de urgencia
    }

    public function test_productos_no_cubiertos_por_la_clinica_los_paga_el_dueno(): void
    {
        $shampoo = Producto::where('nombre', 'Shampoo medicado')->first();   // la clínica lo marcó sin seguro
        $r = $this->calcular('Max', 'Consulta', 80, [['producto' => $shampoo, 'cantidad' => 1]]);
        $this->assertEquals(72, $r['monto_cubierto']);
        $this->assertEquals(48, $r['paga_dueno']);
    }

    public function test_tope_mensual_espera_de_cirugia_y_preexistentes(): void
    {
        // Tope mensual del Premium: Bs 800 (Max ya usó Bs 72 este mes si hubo consulta; se calcula el saldo)
        $r = $this->calcular('Max', 'Cirugía / Castración', 2000);
        $this->assertLessThanOrEqual(800, $r['monto_cubierto']);
        $this->assertNotEmpty($r['notas']);
        // Toby se afilió hace 70 días al Premium (espera de 30): cubierto. Si la espera fuera 100 días, no.
        Plan::where('nombre', 'Plan Premium')->update(['espera_cirugia_dias' => 100]);
        $this->assertEquals(0, $this->calcular('Toby', 'Cirugía / Castración', 600)['monto_cubierto']);
        // Enfermedad preexistente
        $this->assertEquals(0, $this->calcular('Max', 'Consulta', 80, [], true)['monto_cubierto']);
    }

    public function test_cobertura_grave_y_clinica_inicial(): void
    {
        // Rocky tiene Estándar + Cobertura Grave desde hace 100 días: aún en espera (180)
        $this->assertEquals(0, $this->calcular('Rocky', 'Oncología y crónicos', 1000)['monto_cubierto']);
        Afiliacion::where('mascota_id', $this->mascota('Rocky')->id)->update(['fecha_inicio' => today()->subDays(200)]);
        $this->assertEquals(500, $this->calcular('Rocky', 'Oncología y crónicos', 1000)['monto_cubierto']);
        // Max no tiene el adicional
        $this->assertEquals(0, $this->calcular('Max', 'Oncología y crónicos', 1000)['monto_cubierto']);
        // Todas las suscripciones (incluida la Básica) atienden con planes
        Veterinaria::where('nombre', 'Veterinaria Patitas')->update(['plan_suscripcion' => 'inicial']);
        $this->assertEquals(72, $this->calcular('Max', 'Consulta', 80)['monto_cubierto']);
    }

    public function test_la_atencion_guarda_productos_y_montos(): void
    {
        $vet = $this->u('veterinaria@clinica.com');
        $max = $this->mascota('Max');
        $amoxi = Producto::where('nombre', 'Amoxicilina 500 mg (caja)')->first();
        $this->actingAs($vet)->getJson("/veterinaria/atenciones/cobertura?mascota_id={$max->id}&servicio=Tratamiento&costo_servicio=100&productos[0][id]={$amoxi->id}&productos[0][cantidad]=1")
            ->assertOk()->assertJson(['total' => 145, 'cubierto' => 130.5, 'dueno' => 14.5]);
        $this->actingAs($vet)->post('/veterinaria/atenciones', ['mascota_id' => $max->id, 'servicio' => 'Tratamiento', 'fecha' => today()->toDateString(),
            'costo_servicio' => '100', 'productos' => [['id' => $amoxi->id, 'cantidad' => '1']], 'descripcion' => 'Infección leve.'])->assertSessionHasNoErrors();
        $a = Atencion::where('mascota_id', $max->id)->latest('id')->first();
        $this->assertEquals([145.0, 130.5, 14.5, 90], [$a->total, $a->monto_cubierto, $a->paga_dueno, $a->porcentaje]);
        $this->assertSame(1, $a->productos()->count());
    }

    // ---------- Etapa 5 · Foto y cartilla digital ----------

    public function test_foto_de_mascota_y_cartilla_con_foto_de_etiqueta(): void
    {
        Storage::fake('local');
        $maria = $this->u('maria@correo.com');
        $this->actingAs($maria)->post('/propietario/mascotas', ['nombre' => 'Coco', 'especie' => 'Gato', 'raza' => 'Persa', 'fecha_nacimiento' => '2024-03-10',
            'peso' => '3.8', 'sexo' => 'Hembra', 'esterilizado' => 'no', 'foto' => UploadedFile::fake()->image('coco.jpg')])->assertSessionHasNoErrors();
        $coco = $this->mascota('Coco');
        Storage::disk('local')->assertExists($coco->foto);
        $this->actingAs($maria)->get("/archivos/mascotas/{$coco->id}/foto")->assertOk();
        $this->actingAs($this->u('carlos@correo.com'))->get("/archivos/mascotas/{$coco->id}/foto")->assertNotFound();

        // El dueño ya no registra vacunas: solo la veterinaria (la ruta antigua no existe)
        $this->actingAs($maria)->post("/propietario/mascotas/{$coco->id}/cartilla", ['servicio' => 'Vacuna', 'producto' => 'Triple felina'])->assertStatus(404);

        // La veterinaria registra una vacuna con la foto de la etiqueta y su firma
        $vet = $this->u('veterinaria@clinica.com');
        $this->actingAs($vet)->post('/veterinaria/atenciones', ['mascota_id' => $this->mascota('Max')->id, 'servicio' => 'Vacuna', 'fecha' => today()->toDateString(),
            'costo_servicio' => '120', 'producto' => 'Séxtuple anual', 'proxima_dosis' => today()->addYear()->toDateString(),
            'foto_etiqueta' => UploadedFile::fake()->image('sextuple.jpg'), 'descripcion' => 'Vacuna anual.'])->assertSessionHasNoErrors();
        $a = Atencion::where('producto', 'Séxtuple anual')->latest('id')->first();
        $this->assertNotNull($a->foto_etiqueta);
        $this->assertStringContainsString('Claudia Rojas', $a->firmado_por);
        $this->assertSame('MVZ-LP-1187', $a->matricula);
        $this->assertSame('SENASAG-LP-0321', $a->registro_clinica);

        // El dueño adjunta el certificado físico (foto o escaneo) a ese registro
        $max = $this->mascota('Max');
        $this->actingAs($this->u('carlos@correo.com'))->post("/propietario/mascotas/{$max->id}/cartilla/{$a->id}/documento",
            ['documento' => UploadedFile::fake()->image('cert.jpg')])->assertNotFound();
        $this->actingAs($maria)->post("/propietario/mascotas/{$max->id}/cartilla/{$a->id}/documento",
            ['documento' => UploadedFile::fake()->create('cert.pdf', 120, 'application/pdf')])->assertSessionHasNoErrors();
        Storage::disk('local')->assertExists($a->fresh()->documento_dueno);
        $this->actingAs($maria)->get("/archivos/certificados/{$a->id}")->assertOk();
        $this->actingAs($maria)->get("/propietario/mascotas/{$max->id}/cartilla/imprimir")->assertOk()->assertSee('Séxtuple anual')->assertSee('MVZ-LP-1187');
    }

    public function test_sin_matricula_ni_registro_la_clinica_no_puede_registrar_vacunas(): void
    {
        $vet = $this->u('veterinaria@clinica.com');
        $vet->update(['matricula' => null]);
        $this->actingAs($vet)->post('/veterinaria/atenciones', ['mascota_id' => $this->mascota('Max')->id, 'servicio' => 'Vacuna', 'fecha' => today()->toDateString(),
            'costo_servicio' => '120', 'producto' => 'Antirrábica', 'proxima_dosis' => today()->addYear()->toDateString(), 'descripcion' => 'x'])->assertSessionHasErrors();
        $this->assertSame(0, Atencion::where('producto', 'Antirrábica')->whereDate('fecha', today())->count());
    }

    public function test_recordatorio_de_vacuna_siete_dias_antes_una_sola_vez(): void
    {
        WhatsAppService::$enviados = [];
        $this->artisan('petcare:recordatorios')->assertSuccessful();
        $luna = Atencion::where('producto', 'Triple felina (1.ª dosis)')->first();
        $this->assertNotNull($luna->fresh()->recordatorio_at);   // vence en 6 días
        $this->assertTrue(collect(WhatsAppService::$enviados)->contains(fn ($m) => str_contains($m['mensaje'], 'Luna')));
        $max = Atencion::where('producto', 'Antiparasitario interno y externo')->first();
        $this->assertNull($max->fresh()->recordatorio_at);       // vence en 24 días: todavía no
        $antes = count(WhatsAppService::$enviados);
        $this->artisan('petcare:recordatorios')->assertSuccessful();
        $this->assertSame($antes, count(WhatsAppService::$enviados)); // no se repite
        // 17 días después le toca a la desparasitación de Max
        $this->assertSame(1, app(\App\Services\RecordatorioService::class)->enviarPendientes(today()->addDays(17)));
    }

    // ---------- Etapa 6 · Pagos ----------

    public function test_pago_por_qr_se_activa_cuando_el_admin_lo_aprueba_y_envia_el_comprobante(): void
    {
        Storage::fake('local');
        Event::fake([MessageSent::class]);
        $ana = $this->u('ana@correo.com');
        $michi = $this->mascota('Michi');
        $premium = Plan::where('nombre', 'Plan Premium')->first();
        Pago::where('mascota_id', $michi->id)->delete();   // quita el pago pendiente de los datos de demostración

        $base = ['mascota_id' => $michi->id, 'modalidad' => 'anual', 'cobertura_grave' => '1', 'facturar_a' => 'Ana Quispe', 'documento' => '6987412', 'metodo' => 'qr', 'acepto' => '1', 'veterinaria_id' => 1, 'contrato' => '1'];
        // Con QR el comprobante es obligatorio
        $this->actingAs($ana)->post("/propietario/planes/{$premium->id}/solicitar", $base)->assertSessionHasErrors('comprobante');
        $this->actingAs($ana)->post("/propietario/planes/{$premium->id}/solicitar", array_diff_key($base, ['acepto' => 1]) + ['comprobante' => UploadedFile::fake()->image('pago.png')])->assertSessionHasErrors('acepto');
        $r = $this->actingAs($ana)->post("/propietario/planes/{$premium->id}/solicitar", $base + ['comprobante' => UploadedFile::fake()->image('pago.png')]);
        $r->assertSessionHasNoErrors()->assertSessionHas('pago_whatsapp');
        $pago = Pago::where('mascota_id', $michi->id)->first();
        $this->assertSame('qr', $pago->metodo);
        $this->assertStringStartsWith('https://wa.me/59178539749?text=', $pago->enlaceWhatsapp());
        $this->assertStringContainsString('por QR', $pago->mensajeWhatsapp());
        $this->assertNotNull($pago->terminos_at);
        $this->actingAs($ana)->get('/propietario/pagos')->assertSee('Avisar por WhatsApp');
        $this->assertEquals((149 + 30) * 10, $pago->monto);   // anual: 12 meses pagando 10
        $this->assertNull($michi->fresh()->afiliacionActiva);    // todavía no está activo

        $admin = $this->u('admin@petcare.com');
        $this->actingAs($admin)->get("/archivos/comprobantes/{$pago->id}")->assertOk();
        $this->actingAs($this->u('maria@correo.com'))->get("/archivos/comprobantes/{$pago->id}")->assertNotFound();
        $this->actingAs($admin)->patch("/admin/pagos/{$pago->id}/aprobar")->assertSessionHasNoErrors();

        $a = $michi->fresh()->afiliacionActiva;
        $this->assertSame($premium->id, $a->plan_id);
        $this->assertTrue($a->cobertura_grave);
        $this->assertTrue($a->fecha_fin->equalTo(today()->addMonths(12)));
        $this->assertMatchesRegularExpression('/^PC-\d{4}-\d{6}$/', $pago->fresh()->numero);
        Event::assertDispatched(MessageSent::class, fn ($e) => str_contains($e->message->getSubject(), $pago->fresh()->numero)
            && count($e->message->getAttachments()) === 2);   // factura + contrato de afiliación

        $pdf = $this->actingAs($ana)->get("/propietario/pagos/{$pago->id}/comprobante.pdf");
        $pdf->assertOk();
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
    }

    public function test_pago_con_tarjeta_de_demostracion_y_rechazo(): void
    {
        $maria = $this->u('maria@correo.com');
        $luna = $this->mascota('Luna');
        $basico = Plan::where('nombre', 'Plan Básico')->first();
        $base = ['mascota_id' => $luna->id, 'modalidad' => 'mensual', 'facturar_a' => 'María López', 'documento' => '4785123', 'metodo' => 'tarjeta', 'tarjeta_marca' => 'visa',
            'tarjeta_nombre' => 'MARIA LOPEZ', 'tarjeta_vence' => '12/'.(now()->year % 100 + 2), 'tarjeta_cvv' => '123', 'acepto' => '1', 'veterinaria_id' => 1, 'contrato' => '1'];
        $this->actingAs($maria)->post("/propietario/planes/{$basico->id}/solicitar", $base + ['tarjeta_numero' => '4242 4242 4242 4241'])->assertSessionHasErrors('tarjeta_numero');
        // La marca debe coincidir con el número y el CVV con la marca
        $this->actingAs($maria)->post("/propietario/planes/{$basico->id}/solicitar", ['tarjeta_marca' => 'mastercard', 'tarjeta_numero' => '4242424242424242'] + $base)->assertSessionHasErrors('tarjeta_numero');
        $this->actingAs($maria)->post("/propietario/planes/{$basico->id}/solicitar", ['tarjeta_marca' => 'amex', 'tarjeta_numero' => '378282246310005', 'tarjeta_cvv' => '123'] + $base)->assertSessionHasErrors('tarjeta_cvv');
        $this->actingAs($maria)->post("/propietario/planes/{$basico->id}/solicitar", ['tarjeta_vence' => '01/20', 'tarjeta_numero' => '4242424242424242'] + $base)->assertSessionHasErrors('tarjeta_vence');
        $this->actingAs($maria)->post("/propietario/planes/{$basico->id}/solicitar", ['cobertura_grave' => '1', 'tarjeta_numero' => '4242424242424242'] + $base)->assertSessionHasErrors('cobertura_grave');
        $this->actingAs($maria)->post("/propietario/planes/{$basico->id}/solicitar", $base + ['tarjeta_numero' => '4242 4242 4242 4242'])->assertSessionHasNoErrors();
        $pago = Pago::where('mascota_id', $luna->id)->where('estado', 'pendiente')->first();
        $this->assertSame('4242', $pago->tarjeta_ultimos);
        $this->assertSame('visa', $pago->tarjeta_marca);
        $this->assertSame('MARIA LOPEZ', $pago->titular);
        $this->assertStringContainsString('con tarjeta •••• 4242', $pago->mensajeWhatsapp());
        $this->assertStringContainsString('Pago N.º '.$pago->id, $pago->mensajeWhatsapp());
        // No se puede enviar otro pago mientras hay uno pendiente
        $this->actingAs($maria)->post("/propietario/planes/{$basico->id}/solicitar", $base + ['tarjeta_numero' => '4242 4242 4242 4242'])->assertSessionHasErrors();

        $this->actingAs($this->u('admin@petcare.com'))->patch("/admin/pagos/{$pago->id}/rechazar", ['motivo' => 'No se recibió el pago'])->assertSessionHasNoErrors();
        $this->assertSame('rechazado', $pago->fresh()->estado);
        $this->actingAs($maria)->get('/propietario/pagos')->assertSee('No se recibió el pago');
    }

    public function test_el_admin_cambia_el_qr_de_cobro(): void
    {
        Storage::fake('local');
        $admin = $this->u('admin@petcare.com');
        $this->actingAs($admin)->put('/admin/configuracion/pago', ['pago_banco' => 'Banco Unión', 'pago_titular' => 'PETCARE SRL', 'pago_cuenta' => '1000-200300',
            'pago_qr' => UploadedFile::fake()->image('qr.png', 300, 300)])->assertSessionHasNoErrors();
        $this->actingAs($this->u('maria@correo.com'))->get('/archivos/qr-pago')->assertOk();
        $this->actingAs($this->u('maria@correo.com'))->get('/propietario/planes/'.Plan::first()->id.'/solicitar')->assertSee('Banco Unión');
    }

    // ---------- Etapa 7 · Suscripción y registro de veterinarias ----------

    public function test_registro_en_linea_de_una_veterinaria_y_aprobacion(): void
    {
        Storage::fake('local');
        $datos = ['nombre' => 'Vet Los Andes', 'nit' => '1020304050', 'registro' => 'SENASAG-LP-2001', 'matricula' => 'MVZ-LP-2002', 'zona' => 'Obrajes', 'direccion' => 'Calle 5 N.º 100', 'telefono' => '22780011',
            'whatsapp_pais' => 'BO', 'whatsapp' => '71112244', 'horario' => 'Lun–Vie', 'servicios' => ['Consulta', 'Vacuna'], 'plan_suscripcion' => 'profesional',
            'responsable' => 'Dra. Andrea Paz', 'email' => 'losandes@clinica.com', 'password' => 'Andes#2026', 'password_confirmation' => 'Andes#2026', 'acepto' => '1',
            'documento' => UploadedFile::fake()->create('licencia.pdf', 200, 'application/pdf'),
            'metodo' => 'tarjeta', 'tarjeta_marca' => 'visa', 'tarjeta_numero' => '4242 4242 4242 4242', 'tarjeta_nombre' => 'Andrea Paz', 'tarjeta_vence' => '10/29', 'tarjeta_cvv' => '123'];
        // Sin forma de pago no se puede afiliar (mes gratis, pero el cobro queda registrado)
        $this->post('/afiliar-veterinaria', ['metodo' => ''] + $datos)->assertSessionHasErrors('metodo');
        $this->post('/afiliar-veterinaria', $datos)->assertSessionHasNoErrors()->assertRedirect('/afiliar-veterinaria/confirmar-correo');
        $vet = Veterinaria::where('nombre', 'Vet Los Andes')->first();
        $this->assertSame('pendiente', $vet->estado);
        $this->assertFalse($vet->activa);
        $this->assertSame(['tarjeta', 'visa', '4242'], [$vet->cobro_metodo, $vet->cobro_marca, $vet->cobro_ultimos]);
        // Antes de confirmar el correo, la solicitud no le llega al administrador ni se puede aprobar
        $this->assertNull($vet->admin_avisado_at);
        $this->actingAs($this->u('admin@petcare.com'))->patch("/admin/veterinarias/{$vet->id}/aprobar")->assertSessionHasErrors();
        auth()->logout();
        // Confirma el correo con el botón del correo (enlace firmado)
        $u = User::where('email', 'losandes@clinica.com')->first();
        WhatsAppService::$enviados = [];
        $this->get(\App\Services\VerificacionService::enlace($u))->assertOk()->assertSee('Correo confirmado');
        $this->assertNotNull($u->fresh()->email_verified_at);
        $this->assertNotNull($vet->fresh()->admin_avisado_at);
        $this->assertTrue(collect(WhatsAppService::$enviados)->contains(fn ($m) => str_contains($m['mensaje'], 'Nueva solicitud de afiliación: Vet Los Andes')));
        // No aparece para los dueños ni puede ingresar todavía
        $this->actingAs($this->u('maria@correo.com'))->get('/propietario/perfil');   // consume el aviso de la petición anterior
        $this->actingAs($this->u('maria@correo.com'))->get('/propietario/veterinarias')->assertDontSee('Vet Los Andes');
        auth()->logout();
        $this->post('/login/veterinaria', ['email' => 'losandes@clinica.com', 'password' => 'Andes#2026'])->assertSessionHasErrors('email');

        $admin = $this->u('admin@petcare.com');
        $this->actingAs($admin)->get('/admin/veterinarias')->assertSee('Vet Los Andes');
        $this->actingAs($admin)->get("/archivos/documentos/{$vet->id}")->assertOk();
        $this->actingAs($admin)->patch("/admin/veterinarias/{$vet->id}/aprobar")->assertSessionHasNoErrors();
        $vet->refresh();
        $this->assertTrue($vet->activa);
        $this->assertTrue($vet->suscripcion_hasta->equalTo(today()->addDays(30)));
        auth()->logout();
        $this->post('/login/veterinaria', ['email' => 'losandes@clinica.com', 'password' => 'Andes#2026'])->assertRedirect('/veterinaria');
        $this->assertSame('MVZ-LP-2002', User::where('email', 'losandes@clinica.com')->value('matricula'));
    }

    public function test_pago_por_whatsapp_sin_comprobante(): void
    {
        $maria = $this->u('maria@correo.com');
        $luna = $this->mascota('Luna');
        $basico = Plan::where('nombre', 'Plan Básico')->first();
        $this->actingAs($maria)->post("/propietario/planes/{$basico->id}/solicitar", ['mascota_id' => $luna->id, 'modalidad' => 'mensual', 'facturar_a' => 'María López',
            'documento' => '4785123', 'metodo' => 'whatsapp', 'acepto' => '1', 'veterinaria_id' => 1, 'contrato' => '1'])->assertSessionHasNoErrors();
        $pago = Pago::where('mascota_id', $luna->id)->where('estado', 'pendiente')->first();
        $this->assertSame('whatsapp', $pago->metodo);
        $this->assertStringContainsString('¿Me envían el QR?', $pago->mensajeWhatsapp());
        $this->assertLessThan(160, mb_strlen($pago->mensajeWhatsapp()));   // mensaje corto
        $this->assertStringContainsString('Plan Básico', $pago->mensajeWhatsapp());
    }

    // ---------- Citas: urgente, estimado y pago por WhatsApp de la clínica ----------

    public function test_cita_urgente_con_estimado_y_whatsapp_de_la_clinica(): void
    {
        $maria = $this->u('maria@correo.com');
        $max = $this->mascota('Max');   // Plan Premium (90 %)
        $v1 = Veterinaria::where('nombre', 'Veterinaria Patitas')->first();
        $this->actingAs($maria)->getJson("/propietario/citas/estimar?mascota_id={$max->id}&veterinaria_id={$v1->id}&servicio=Consulta")
            ->assertOk()->assertJson(['ok' => true, 'precio' => 80, 'cubierto' => 72, 'pagar' => 8]);
        $this->actingAs($this->u('carlos@correo.com'))->getJson("/propietario/citas/estimar?mascota_id={$max->id}&veterinaria_id={$v1->id}&servicio=Consulta")->assertJson(['ok' => false]);

        $fecha = today()->addDays(4);
        if ($fecha->isSunday()) {
            $fecha->addDay();
        }
        $r = $this->actingAs($maria)->post('/propietario/citas', ['mascota_id' => $max->id, 'veterinaria_id' => $v1->id, 'servicio' => 'Consulta',
            'fecha' => $fecha->toDateString(), 'hora' => '08:30', 'urgente' => '1']);
        $cita = Cita::latest('id')->first();
        $r->assertSessionHasNoErrors()->assertRedirect("/propietario/citas/{$cita->id}/pago");
        $this->assertTrue($cita->urgente);
        $this->assertEquals([80.0, 72.0], [$cita->monto_estimado, $cita->cubierto_estimado]);
        $enlace = app(\App\Services\CitaService::class)->enlaceWhatsapp($cita);
        $this->assertStringStartsWith('https://wa.me/59176541122?text=', $enlace);
        $this->assertStringContainsString('URGENTE', urldecode($enlace));
        $this->actingAs($maria)->get("/propietario/citas/{$cita->id}/pago")->assertOk()->assertSee('Bs 8')->assertSee('Tarjeta');
        $this->actingAs($maria)->get('/propietario/citas')->assertSee('Pagar Bs 8');
        // En la agenda de la clínica, la urgente va primero
        $agenda = $this->actingAs($this->u('veterinaria@clinica.com'))->get('/veterinaria/agenda')->assertOk()->assertSee('URGENTE');
        $html = $agenda->getContent();
        $this->assertLessThan(strpos($html, 'Control general'), strpos($html, 'URGENTE'));
    }

    // ---------- Página de la veterinaria y calificaciones ----------

    public function test_pagina_de_la_veterinaria_y_lista_ordenada(): void
    {
        $maria = $this->u('maria@correo.com');
        $v1 = Veterinaria::where('nombre', 'Veterinaria Patitas')->first();
        $this->actingAs($maria)->get("/veterinarias/{$v1->id}")->assertOk()->assertSee('Cómo llegar')->assertSee('maps.google.com', false)
            ->assertSee('Rayos X')->assertSee('Alimento Pro Plan adulto 3 kg')->assertSee('Promoción del mes')->assertSee('Muy amables con Luna');
        $this->actingAs($maria)->get('/propietario/veterinarias')->assertOk()->assertSee('Mejor calificada')->assertSee('Más solicitada');
        $lista = $this->actingAs($maria)->get('/propietario/veterinarias?orden=calificacion')->getContent();
        $this->assertLessThan(strpos($lista, 'VetHouse'), strpos($lista, 'Veterinaria Patitas'));
        // Una clínica inactiva no se muestra a los dueños
        $v4 = Veterinaria::where('nombre', 'Clínica Huellitas')->first();
        $this->actingAs($maria)->get("/veterinarias/{$v4->id}")->assertNotFound();
    }

    public function test_el_dueno_califica_una_cita_atendida_una_sola_vez(): void
    {
        $maria = $this->u('maria@correo.com');
        $v1 = Veterinaria::where('nombre', 'Veterinaria Patitas')->first();
        $atendida = Cita::where('mascota_id', $this->mascota('Max')->id)->where('estado', 'atendida')->first();
        $pendiente = Cita::where('mascota_id', $this->mascota('Max')->id)->where('estado', 'pendiente')->first();
        $antes = $v1->resenas;

        $this->actingAs($this->u('carlos@correo.com'))->post("/propietario/citas/{$atendida->id}/calificar", ['estrellas' => 5])->assertForbidden();
        $this->actingAs($maria)->post("/propietario/citas/{$pendiente->id}/calificar", ['estrellas' => 5])->assertSessionHasErrors('estrellas');
        $this->actingAs($maria)->post("/propietario/citas/{$atendida->id}/calificar", ['estrellas' => 9])->assertSessionHasErrors('estrellas');
        $this->actingAs($maria)->post("/propietario/citas/{$atendida->id}/calificar", ['estrellas' => 2, 'comentario' => 'Esperé mucho'])->assertSessionHasNoErrors();
        $this->actingAs($maria)->post("/propietario/citas/{$atendida->id}/calificar", ['estrellas' => 5])->assertSessionHasErrors('estrellas');
        $v1->refresh();
        $this->assertSame($antes + 1, $v1->resenas);
        $this->assertEquals(4.0, $v1->valoracion);   // (5 + 5 + 4 + 2) / 4
    }

    public function test_galeria_de_la_clinica_y_producto_con_foto(): void
    {
        Storage::fake('local');
        $vet = $this->u('veterinaria@clinica.com');
        $this->actingAs($vet)->post('/veterinaria/clinica/fotos', ['fotos' => [UploadedFile::fake()->image('sala.jpg')], 'titulo' => 'Sala de espera'])->assertSessionHasNoErrors();
        $this->actingAs($vet)->post('/veterinaria/productos', ['nombre' => 'Hueso de juguete', 'categoria' => 'juguete', 'precio' => '30', 'cubre_seguro' => '1',
            'descripcion' => 'Caucho natural', 'foto' => UploadedFile::fake()->image('hueso.jpg')])->assertSessionHasNoErrors();
        $p = Producto::where('nombre', 'Hueso de juguete')->first();
        $this->assertFalse($p->cubre_seguro);   // los productos de tienda nunca los cubre el plan
        Storage::disk('local')->assertExists($p->foto);
        $v1 = $vet->veterinaria;
        $pagina = $this->actingAs($this->u('maria@correo.com'))->get("/veterinarias/{$v1->id}")->assertOk()->assertSee('Sala de espera')->assertSee('Hueso de juguete');
        $foto = $v1->fotos()->first();
        $this->actingAs($this->u('maria@correo.com'))->get("/archivos/veterinarias/fotos/{$foto->id}")->assertOk();
        $this->actingAs($this->u('maria@correo.com'))->get("/archivos/productos/{$p->id}/foto")->assertOk();
    }

    public function test_pago_de_suscripcion_extiende_la_vigencia_y_una_vencida_no_recibe_citas(): void
    {
        Storage::fake('local');
        $vida = Veterinaria::where('nombre', 'Centro Veterinario Vida')->first();   // vence en 5 días
        $hasta = $vida->suscripcion_hasta->copy();
        $this->actingAs($this->u('vida@clinica.com'))->post('/veterinaria/suscripcion', ['plan_suscripcion' => 'plus', 'meses' => '12',
            'facturar_a' => 'Centro Veterinario Vida', 'documento' => '3874512011', 'metodo' => 'qr', 'acepto' => '1', 'comprobante' => UploadedFile::fake()->image('pago.jpg')])->assertSessionHasNoErrors();
        $pago = Pago::where('veterinaria_id', $vida->id)->where('estado', 'pendiente')->first();
        $this->assertEquals(2000, $pago->monto);   // 10 meses × Bs 200
        $this->actingAs($this->u('admin@petcare.com'))->patch("/admin/pagos/{$pago->id}/aprobar")->assertSessionHasNoErrors();
        $vida->refresh();
        $this->assertSame('plus', $vida->plan_suscripcion);
        $this->assertTrue($vida->suscripcion_hasta->equalTo($hasta->addMonths(12)));

        // Suscripción vencida: no aparece y no recibe citas
        $vida->update(['suscripcion_hasta' => today()->subDay()]);
        $maria = $this->u('maria@correo.com');
        $this->actingAs($maria)->get('/propietario/veterinarias')->assertDontSee('Centro Veterinario Vida');
        $this->actingAs($maria)->post('/propietario/citas', ['mascota_id' => $this->mascota('Max')->id, 'veterinaria_id' => $vida->id,
            'servicio' => 'Consulta', 'fecha' => $this->diaHabil(4), 'hora' => '09:00'])->assertSessionHasErrors('veterinaria_id');
    }

    public function test_reportes_de_siniestralidad_y_liquidacion(): void
    {
        $admin = $this->u('admin@petcare.com');
        $this->actingAs($admin)->get('/admin/reportes')->assertOk()->assertSee('Siniestralidad por plan')->assertSee('Liquidación a veterinarias');
        $x = $this->actingAs($admin)->get('/admin/reportes/excel?periodo=mes&fecha='.today()->format('Y-m'))->assertOk()->getContent();
        $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
        file_put_contents($tmp, $x);
        $zip = new \ZipArchive;
        $zip->open($tmp);
        $this->assertStringContainsString('A pagar por PetCare', $zip->getFromName('xl/worksheets/sheet15.xml'));
        $zip->close();
    }

    // ---------- Pago de citas a la veterinaria, límite de atenciones y términos ----------

    public function test_pago_de_cita_con_qr_lo_verifica_la_veterinaria(): void
    {
        Storage::fake('local');
        $maria = $this->u('maria@correo.com');
        $max = $this->mascota('Max');
        $v1 = Veterinaria::where('nombre', 'Veterinaria Patitas')->first();
        $fecha = today()->addDays(6);
        if ($fecha->isSunday()) {
            $fecha->addDay();
        }
        $this->actingAs($maria)->post('/propietario/citas', ['mascota_id' => $max->id, 'veterinaria_id' => $v1->id, 'servicio' => 'Consulta',
            'fecha' => $fecha->toDateString(), 'hora' => '09:30'])->assertSessionHasNoErrors();
        $cita = Cita::latest('id')->first();

        // QR sin comprobante → error específico del campo
        $this->actingAs($maria)->post("/propietario/citas/{$cita->id}/pago", ['metodo' => 'qr'])->assertSessionHasErrors('comprobante');
        $this->actingAs($this->u('carlos@correo.com'))->post("/propietario/citas/{$cita->id}/pago", ['metodo' => 'whatsapp'])->assertNotFound();
        $this->actingAs($maria)->post("/propietario/citas/{$cita->id}/pago", ['metodo' => 'qr', 'comprobante' => UploadedFile::fake()->image('qr.jpg')])
            ->assertSessionHasNoErrors()->assertSessionHas('pago_whatsapp');
        $pago = Pago::where('cita_id', $cita->id)->first();
        $this->assertSame('cita', $pago->concepto);
        $this->assertEquals(8.0, $pago->monto);   // Consulta Bs 80, Premium cubre 90 %
        $this->assertStringStartsWith('https://wa.me/59176541122', $pago->enlaceWhatsapp());   // al WhatsApp que registró la clínica, no a PetCare
        // No se puede pagar dos veces
        $this->actingAs($maria)->post("/propietario/citas/{$cita->id}/pago", ['metodo' => 'whatsapp'])->assertSessionHasErrors();

        // El administrador no lo ve en su lista; la veterinaria sí, con el comprobante
        $this->actingAs($this->u('admin@petcare.com'))->get('/admin/pagos')->assertDontSee('Cita N.º '.$cita->id);
        $vet = $this->u('veterinaria@clinica.com');
        $this->actingAs($vet)->get('/veterinaria/agenda')->assertSee('Pago por verificar')->assertSee('Confirmar pago');
        $this->actingAs($vet)->get("/archivos/comprobantes/{$pago->id}")->assertOk();
        $this->actingAs($this->u('admin@petcare.com'))->patch("/admin/pagos/{$pago->id}/aprobar")->assertSessionHasErrors();
        $this->actingAs($vet)->patch("/veterinaria/pagos/{$pago->id}/aprobar")->assertSessionHasNoErrors();
        $this->assertSame('aprobado', $pago->fresh()->estado);
        $this->assertSame('confirmada', $cita->fresh()->estado);   // al confirmar el pago se confirma la cita
        $this->actingAs($maria)->get('/propietario/citas')->assertSee('Pagado');
    }

    public function test_al_llegar_al_limite_de_atenciones_la_clinica_cobra_el_total(): void
    {
        $max = $this->mascota('Max');   // Premium: 12 atenciones cubiertas al año
        $premium = Plan::where('nombre', 'Plan Premium')->first();
        $v1 = Veterinaria::where('nombre', 'Veterinaria Patitas')->first();
        $cob = app(\App\Services\CoberturaService::class);
        $usadas = $cob->atencionesCubiertas($max, today());
        $this->assertGreaterThan(0, $usadas);
        $premium->update(['limite_atenciones' => $usadas + 1]);
        $c = $cob->calcular($max->fresh(), $v1, 'Consulta', 80, [], today());
        $this->assertEquals(72, $c['monto_cubierto']);
        $this->assertStringContainsString('última', implode(' ', $c['notas']));
        $premium->update(['limite_atenciones' => $usadas]);
        $c = $cob->calcular($max->fresh(), $v1, 'Consulta', 80, [], today());
        $this->assertEquals(0, $c['monto_cubierto']);
        $this->assertEquals(80, $c['paga_dueno']);
        $this->assertStringContainsString('la veterinaria cobra el precio completo', implode(' ', $c['notas']));
        $this->actingAs($this->u('maria@correo.com'))->get("/propietario/mascotas/{$max->id}/plan")->assertSee('Atenciones cubiertas');
    }

    public function test_terminos_y_condiciones_en_ventana_flotante(): void
    {
        $this->get('/afiliar-veterinaria')->assertOk()->assertSee('dlg-terminos-veterinaria', false);
        $this->get('/terminos?para=veterinaria')->assertOk()->assertSee('Obligaciones de la clínica');
        $maria = $this->u('maria@correo.com');
        $plan = Plan::where('nombre', 'Plan Estándar')->first();
        $this->actingAs($maria)->get("/propietario/planes/{$plan->id}/solicitar")->assertOk()
            ->assertSee('dlg-terminos-propietario', false)->assertSee('Atenciones cubiertas')->assertSee('la veterinaria cobra el precio completo');
        $this->actingAs($this->u('veterinaria@clinica.com'))->get('/veterinaria/suscripcion')->assertOk()->assertSee('dlg-terminos-veterinaria', false);
    }

    public function test_horario_estructurado_y_otros_servicios_de_la_clinica(): void
    {
        $vet = $this->u('veterinaria@clinica.com');
        $v = $vet->veterinaria;
        $base = ['nombre' => $v->nombre, 'registro' => 'SENASAG-LP-0321', 'matricula' => 'MVZ-LP-1187', 'zona' => $v->zona, 'direccion' => $v->direccion,
            'telefono' => $v->telefono, 'whatsapp_pais' => 'BO', 'whatsapp' => '71234567', 'servicios' => ['Consulta', 'Vacuna', 'Otros'],
            'horario_dia_desde' => 'lun', 'horario_dia_hasta' => 'sab', 'horario_hora_desde' => '08:30', 'horario_hora_hasta' => '18:00'];

        // La hora de cierre debe ser posterior a la de apertura
        $this->actingAs($vet)->put('/veterinaria/clinica', ['horario_hora_hasta' => '08:00', 'servicios_otros' => 'Fisioterapia'] + $base)
            ->assertSessionHasErrorsIn('clinica', ['horario_hora_hasta']);
        // El día final no puede ser anterior al inicial
        $this->actingAs($vet)->put('/veterinaria/clinica', ['horario_dia_desde' => 'vie', 'horario_dia_hasta' => 'mar', 'servicios_otros' => 'Fisioterapia'] + $base)
            ->assertSessionHasErrorsIn('clinica', ['horario_dia_hasta']);
        // «Otros» marcado sin escribir cuáles
        $this->actingAs($vet)->put('/veterinaria/clinica', ['servicios_otros' => ''] + $base)->assertSessionHasErrorsIn('clinica', ['servicios_otros']);

        $this->actingAs($vet)->put('/veterinaria/clinica', ['servicios_otros' => 'fisioterapia, Odontología, consulta'] + $base)->assertSessionHasNoErrors();
        $v->refresh();
        $this->assertSame('Lun–Sáb · 08:30 a 18:00', $v->horario);
        $this->assertSame(['Consulta', 'Vacuna', 'Fisioterapia', 'Odontología'], $v->servicios);

        // 24 horas
        $this->actingAs($vet)->put('/veterinaria/clinica', ['horario_dia_hasta' => 'dom', 'horario_24' => '1', 'servicios_otros' => 'Fisioterapia'] + $base)->assertSessionHasNoErrors();
        $this->assertSame('Lun–Dom · 24 horas', $v->fresh()->horario);
        $this->actingAs($vet)->get('/veterinaria/clinica')->assertOk()->assertSee('Fisioterapia');
    }

    // ---------- Confirmación de correo en todos los registros ----------

    public function test_correo_ya_registrado_sin_confirmar_y_reenvio_del_enlace(): void
    {
        $datos = ['name' => 'Pedro Pérez', 'email' => 'pedro@gmail.com', 'codigo_pais' => 'BO', 'telefono' => '71112233',
            'password' => 'Secreto#2026', 'password_confirmation' => 'Secreto#2026'];
        $this->post('/registro', $datos)->assertRedirect('/verificar');
        $this->post('/logout');
        // Vuelve a registrarse con el mismo correo sin haberlo confirmado
        $this->post('/registro', $datos)->assertSessionHasErrors(['email' => 'Ya empezaste un registro con este correo, pero todavía no lo confirmaste. Pide que te enviemos el enlace otra vez.'])
            ->assertSessionHas('correo_pendiente', 'pedro@gmail.com');
        $this->assertSame(1, User::where('email', 'pedro@gmail.com')->count());
        $this->post('/registro/reenviar-enlace', ['email' => 'pedro@gmail.com'])->assertRedirect('/login/propietario');
        // Un correo ya confirmado
        $this->post('/registro', ['email' => 'maria@correo.com'] + $datos)->assertSessionHasErrors(['email' => 'Este correo ya tiene una cuenta en PetCare. Inicia sesión o usa otro correo.']);
        // Enlace con un hash que no corresponde
        $u = User::where('email', 'pedro@gmail.com')->first();
        $this->get(\Illuminate\Support\Facades\URL::temporarySignedRoute('verificacion.enlace', now()->addHour(), ['usuario' => $u->id, 'hash' => sha1('otro@correo.com')], false))->assertStatus(410);
        // Enlace sin firma válida
        $this->get('/confirmar-correo/'.$u->id.'/'.sha1('pedro@gmail.com'))->assertStatus(403);
        $this->assertNull($u->fresh()->email_verified_at);
    }

    public function test_cambio_de_correo_queda_pendiente_hasta_confirmarlo(): void
    {
        $maria = $this->u('maria@correo.com');
        $this->actingAs($maria)->put('/perfil', ['name' => $maria->name, 'email' => 'maria.nueva@gmail.com', 'codigo_pais' => 'BO', 'telefono' => $maria->telefono])->assertSessionHasNoErrors();
        $maria->refresh();
        $this->assertSame('maria@correo.com', $maria->email);          // sigue entrando con el anterior
        $this->assertSame('maria.nueva@gmail.com', $maria->email_nuevo);
        $this->assertTrue($maria->verificado());
        $this->actingAs($maria)->get('/propietario/perfil')->assertSee('Cambio de correo pendiente');
        $this->get(\App\Services\VerificacionService::enlace($maria))->assertOk();
        $maria->refresh();
        $this->assertSame('maria.nueva@gmail.com', $maria->email);
        $this->assertNull($maria->email_nuevo);
    }

    public function test_clinica_confirma_su_correo_con_el_codigo_y_el_admin_recibe_el_aviso(): void
    {
        Storage::fake('local');
        $this->post('/afiliar-veterinaria', ['nombre' => 'Vet Illimani', 'nit' => '1020304099', 'registro' => 'SENASAG-LP-3001', 'matricula' => 'MVZ-LP-3002', 'zona' => 'Sopocachi',
            'direccion' => 'Calle 8 N.º 22', 'telefono' => '22780099', 'whatsapp_pais' => 'BO', 'whatsapp' => '71119988', 'servicios' => ['Consulta'],
            'horario_dia_desde' => 'lun', 'horario_dia_hasta' => 'vie', 'horario_hora_desde' => '09:00', 'horario_hora_hasta' => '18:00',
            'plan_suscripcion' => 'inicial', 'responsable' => 'Dr. Mario Quispe', 'email' => 'illimani@clinica.com', 'password' => 'Illi#2026x', 'password_confirmation' => 'Illi#2026x',
            'acepto' => '1', 'metodo' => 'qr', 'documento' => UploadedFile::fake()->create('licencia.pdf', 100, 'application/pdf')])->assertSessionHasNoErrors();
        $vet = Veterinaria::where('nombre', 'Vet Illimani')->first();
        $this->assertSame('Lun–Vie · 09:00 a 18:00', $vet->horario);
        $this->get('/afiliar-veterinaria/confirmar-correo')->assertOk()->assertSee('Revisa tu correo');
        $codigo = session('codigos_demo.email');
        $this->post('/afiliar-veterinaria/confirmar-correo', ['codigo_email' => '000000'])->assertSessionHasErrors('codigo_email');
        $this->post('/afiliar-veterinaria/confirmar-correo', ['codigo_email' => $codigo])->assertSessionHasNoErrors();
        $this->assertNotNull($vet->fresh()->admin_avisado_at);
        $this->actingAs($this->u('admin@petcare.com'))->get('/admin/veterinarias')->assertSee('Correo confirmado')->assertSee('Nueva solicitud de afiliación: Vet Illimani');
    }

    // ---------- Suscripciones: mes gratis, cobro automático con tarjeta o aviso con QR ----------

    public function test_renovacion_de_suscripciones_y_limpieza_de_registros(): void
    {
        $patitas = Veterinaria::where('nombre', 'Veterinaria Patitas')->first();   // tarjeta Visa 4242, plan Plus
        $vida = Veterinaria::where('nombre', 'Centro Veterinario Vida')->first();     // QR, vence en 5 días
        $patitas->update(['suscripcion_hasta' => today()]);
        WhatsAppService::$enviados = [];
        $r = app(\App\Services\SuscripcionService::class)->procesar();
        $this->assertSame(1, $r['cobradas']);
        $pago = Pago::where('veterinaria_id', $patitas->id)->where('concepto', 'suscripcion')->latest('id')->first();
        $this->assertSame(['aprobado', 'tarjeta', '4242'], [$pago->estado, $pago->metodo, $pago->tarjeta_ultimos]);
        $this->assertEquals(200, $pago->monto);
        $this->assertTrue($patitas->fresh()->suscripcion_hasta->equalTo(today()->addMonth()));
        // Vida paga con QR: recibe un aviso (una sola vez)
        $this->assertNotNull($vida->fresh()->aviso_renovacion_at);
        $this->assertTrue(collect(WhatsAppService::$enviados)->contains(fn ($m) => str_contains($m['mensaje'], 'Centro Veterinario Vida') && str_contains($m['mensaje'], 'Bs 100')));
        $this->assertSame(0, app(\App\Services\SuscripcionService::class)->procesar()['avisadas']);
        // Registros sin confirmar por más de 48 horas se borran
        $viejo = User::create(['name' => 'Sin Confirmar', 'email' => 'sinconfirmar@gmail.com', 'password' => 'Clave#2026', 'rol' => User::PROPIETARIO, 'telefono' => '71234500', 'codigo_pais' => 'BO']);
        $viejo->forceFill(['created_at' => now()->subHours(50)])->save();
        $this->artisan('petcare:suscripciones')->assertSuccessful();
        $this->assertNull(User::find($viejo->id));
        $this->assertNotNull(User::where('email', 'maria@correo.com')->first());
    }

    public function test_la_clinica_cambia_su_forma_de_cobro(): void
    {
        $vet = $this->u('vida@clinica.com');
        $this->actingAs($vet)->get('/veterinaria/suscripcion')->assertOk()->assertSee('Forma de cobro mensual');
        $this->actingAs($vet)->put('/veterinaria/suscripcion/cobro', ['metodo' => 'tarjeta', 'tarjeta_marca' => 'mastercard', 'tarjeta_numero' => '4242 4242 4242 4242',
            'tarjeta_nombre' => 'Jorge Vargas', 'tarjeta_vence' => '10/29', 'tarjeta_cvv' => '123'])->assertSessionHasErrors('tarjeta_numero');
        $this->actingAs($vet)->put('/veterinaria/suscripcion/cobro', ['metodo' => 'tarjeta', 'tarjeta_marca' => 'mastercard', 'tarjeta_numero' => '5555 5555 5555 4444',
            'tarjeta_nombre' => 'Jorge Vargas', 'tarjeta_vence' => '10/29', 'tarjeta_cvv' => '123'])->assertSessionHasNoErrors();
        $this->assertSame('Mastercard •••• 4444', $vet->veterinaria->fresh()->cobroTexto());
    }

    public function test_la_clinica_ve_su_propia_pagina_publica(): void
    {
        $vet = $this->u('veterinaria@clinica.com');
        $this->actingAs($vet)->get('/veterinarias/'.$vet->veterinaria_id)->assertOk()->assertSee('Editar mi página');
        $this->actingAs($this->u('admin@petcare.com'))->get('/veterinarias/'.$vet->veterinaria_id)->assertOk();
    }
}
