<?php

namespace Tests\Feature;

use App\Models\Afiliacion;
use App\Models\Cita;
use App\Models\Mascota;
use App\Models\Pago;
use App\Models\Pedido;
use App\Models\Plan;
use App\Models\Producto;
use App\Models\User;
use App\Models\Veterinaria;
use App\Services\CoberturaService;
use App\Services\PlanService;
use App\Services\WhatsAppService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** Cambios del 23/11: especie «Otro», contrato del plan, tienda, servicio «Otro», avisos, baja de clínicas. */
class CambiosNoviembre23Test extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function u(string $email): User
    {
        return User::where('email', $email)->firstOrFail();
    }

    private function vet(string $nombre = 'Veterinaria Patitas'): Veterinaria
    {
        return Veterinaria::where('nombre', $nombre)->firstOrFail();
    }

    // ---------- 1 · Especie «Otro» ----------

    public function test_especie_otro_guarda_lo_que_se_escribe(): void
    {
        $maria = $this->u('maria@correo.com');
        $base = ['nombre' => 'Bolita', 'especie' => 'Otro', 'fecha_nacimiento' => '2025-03-01', 'peso' => '0.3', 'sexo' => 'Macho', 'esterilizado' => 'no'];
        $this->actingAs($maria)->post('/propietario/mascotas', $base)->assertSessionHasErrors('especie_otro');
        $this->actingAs($maria)->post('/propietario/mascotas', $base + ['especie_otro' => 'h4mster'])->assertSessionHasErrors('especie_otro');
        $this->actingAs($maria)->post('/propietario/mascotas', $base + ['especie_otro' => 'hámster'])->assertSessionHasNoErrors();
        $this->assertSame('Hámster', Mascota::where('nombre', 'Bolita')->value('especie'));
    }

    public function test_quitar_la_foto_guardada_de_la_mascota(): void
    {
        $maria = $this->u('maria@correo.com');
        $max = Mascota::where('nombre', 'Max')->first();
        $datos = ['nombre' => 'Max', 'especie' => 'Perro', 'fecha_nacimiento' => $max->fecha_nacimiento->toDateString(), 'peso' => '28', 'sexo' => 'Macho', 'esterilizado' => 'si'];
        $this->actingAs($maria)->put("/propietario/mascotas/{$max->id}", $datos + ['foto' => UploadedFile::fake()->image('max.jpg')])->assertSessionHasNoErrors();
        $this->assertNotNull($max->fresh()->foto);
        $this->actingAs($maria)->put("/propietario/mascotas/{$max->id}", $datos + ['quitar_foto' => 1])->assertSessionHasNoErrors();
        $this->assertNull($max->fresh()->foto);
    }

    public function test_foto_de_perfil(): void
    {
        $maria = $this->u('maria@correo.com');
        $datos = ['name' => $maria->name, 'email' => $maria->email, 'codigo_pais' => 'BO', 'telefono' => $maria->telefono];
        $this->actingAs($maria)->put('/perfil', $datos + ['foto' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf')], ['referer' => '/propietario/perfil'])->assertSessionHasErrors('foto', null, 'perfil');
        $this->actingAs($maria)->put('/perfil', $datos + ['foto' => UploadedFile::fake()->image('yo.jpg')])->assertSessionHasNoErrors();
        $this->assertNotNull($maria->fresh()->foto);
        $this->actingAs($maria->fresh())->get('/archivos/usuarios/'.$maria->id.'/foto')->assertOk();
        $this->actingAs($this->u('ana@correo.com'))->get('/archivos/usuarios/'.$maria->id.'/foto')->assertNotFound();   // otro dueño no la ve
        $this->actingAs($maria->fresh())->get('/propietario')->assertSee('con-foto', false);
        $this->actingAs($maria->fresh())->put('/perfil', $datos + ['quitar_foto' => 1])->assertSessionHasNoErrors();
        $this->assertNull($maria->fresh()->foto);
    }

    // ---------- 8 · Plan atado a una veterinaria, con contrato ----------

    private function contratar(Mascota $m, Plan $p, Veterinaria $v): Afiliacion
    {
        Pago::where('mascota_id', $m->id)->where('estado', 'pendiente')->delete();
        $r = $this->actingAs($m->propietario)->post("/propietario/planes/{$p->id}/solicitar", ['mascota_id' => $m->id, 'modalidad' => 'mensual',
            'facturar_a' => $m->propietario->name, 'documento' => '6987412', 'metodo' => 'whatsapp', 'acepto' => '1', 'veterinaria_id' => $v->id, 'contrato' => '1']);
        $r->assertSessionHasNoErrors();
        $pago = Pago::where('mascota_id', $m->id)->latest('id')->first();
        $this->actingAs($this->u('admin@petcare.com'))->patch("/admin/pagos/{$pago->id}/aprobar")->assertSessionHasNoErrors();

        return $m->fresh()->afiliacionActiva;
    }

    public function test_el_plan_se_contrata_en_una_veterinaria_con_carencia_y_permanencia(): void
    {
        $michi = Mascota::where('nombre', 'Michi')->first();   // sin plan
        $estandar = Plan::where('nombre', 'Plan Estándar')->first();
        $vida = $this->vet('Centro Veterinario Vida');
        // Sin veterinaria ni contrato no se puede
        $this->actingAs($michi->propietario)->post("/propietario/planes/{$estandar->id}/solicitar", ['mascota_id' => $michi->id, 'modalidad' => 'mensual',
            'facturar_a' => 'Ana', 'documento' => '6987412', 'metodo' => 'whatsapp', 'acepto' => '1'])->assertSessionHasErrors(['veterinaria_id', 'contrato']);

        $a = $this->contratar($michi, $estandar, $vida);
        $this->assertSame($vida->id, $a->veterinaria_id);
        $this->assertMatchesRegularExpression('/^CA-\d{4}-\d{6}$/', $a->contrato_numero);
        $this->assertTrue($a->carencia_hasta->equalTo(today()->addDays(60)));
        $this->assertTrue($a->permanencia_hasta->equalTo(today()->addMonths(3)));

        $cob = app(CoberturaService::class);
        // En carencia no cubre nada, ni urgencias
        $c = $cob->calcular($michi->fresh(), $vida, 'Urgencia', 200, [], Carbon::today());
        $this->assertEquals(0, $c['monto_cubierto']);
        $this->assertStringContainsString('carencia', implode(' ', $c['notas']));
        // Pasada la carencia (y pagando mes a mes): cubre en su veterinaria, no en otra
        $a->update(['fecha_fin' => today()->addMonths(3)]);
        $dia61 = Carbon::today()->addDays(61);
        $this->assertGreaterThan(0, $cob->calcular($michi->fresh(), $vida, 'Consulta', 100, [], $dia61)['monto_cubierto']);
        $otra = $cob->calcular($michi->fresh(), $this->vet(), 'Consulta', 100, [], $dia61);
        $this->assertEquals(0, $otra['monto_cubierto']);
        $this->assertStringContainsString('solo cubre en Centro Veterinario Vida', implode(' ', $otra['notas']));

        // Permanencia: no se cancela ni se cambia de veterinaria antes de 3 meses
        $this->actingAs($michi->propietario)->delete("/propietario/mascotas/{$michi->id}/plan")->assertSessionHasErrors();
        $this->assertNotNull($michi->fresh()->afiliacionActiva);
        $this->actingAs($michi->propietario)->post("/propietario/planes/{$estandar->id}/solicitar", ['mascota_id' => $michi->id, 'modalidad' => 'mensual',
            'facturar_a' => 'Ana', 'documento' => '6987412', 'metodo' => 'whatsapp', 'acepto' => '1', 'veterinaria_id' => $this->vet()->id, 'contrato' => '1'])->assertSessionHasErrors('veterinaria_id');
        // El contrato en PDF
        $pdf = $this->actingAs($michi->propietario)->get("/propietario/mascotas/{$michi->id}/contrato.pdf");
        $pdf->assertOk();
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
    }

    public function test_si_la_veterinaria_deja_petcare_se_puede_elegir_otra(): void
    {
        $michi = Mascota::where('nombre', 'Michi')->first();
        $estandar = Plan::where('nombre', 'Plan Estándar')->first();
        $vida = $this->vet('Centro Veterinario Vida');
        $this->contratar($michi, $estandar, $vida);
        $vida->update(['activa' => false]);
        $a = $this->contratar($michi->fresh(), $estandar, $this->vet());
        $this->assertSame($this->vet()->id, $a->veterinaria_id);
        $this->assertSame(1, $michi->afiliaciones()->where('estado', 'activo')->count());
    }

    public function test_un_plan_vencido_no_se_ofrece(): void
    {
        $basico = Plan::where('nombre', 'Plan Básico')->first();
        $basico->update(['vigente_hasta' => today()->subDay()]);
        $this->actingAs($this->u('maria@correo.com'))->get('/propietario/planes')->assertDontSee('Plan Básico');
        $this->actingAs($this->u('maria@correo.com'))->get("/propietario/planes/{$basico->id}/solicitar")->assertNotFound();
        $this->assertSame(['Vencido', 'bad'], $basico->fresh()->vigencia());
    }

    // ---------- 10 · Servicio «Otro» ----------

    public function test_servicio_otro_en_la_atencion_no_lo_cubre_el_plan(): void
    {
        $claudia = $this->u('veterinaria@clinica.com');
        $max = Mascota::where('nombre', 'Max')->first();   // Premium 90 %
        $base = ['mascota_id' => $max->id, 'servicio' => 'Otro…', 'fecha' => today()->toDateString(), 'costo_servicio' => '150', 'descripcion' => 'Colocación de microchip.'];
        $this->actingAs($claudia)->post('/veterinaria/atenciones', $base + ['servicio_otro' => 'consulta'])->assertSessionHasErrors('servicio_otro');
        $this->actingAs($claudia)->post('/veterinaria/atenciones', $base + ['servicio_otro' => 'microchip'])->assertSessionHasNoErrors();
        $a = $max->atenciones()->latest('id')->first();
        $this->assertSame('Microchip', $a->servicio);
        $this->assertEquals(0, $a->monto_cubierto);
        $this->assertEquals(150, $a->paga_dueno);
    }

    public function test_servicio_otro_al_pedir_una_cita(): void
    {
        $maria = $this->u('maria@correo.com');
        $max = Mascota::where('nombre', 'Max')->first();
        $fecha = Carbon::today()->addDays(9);
        while ($fecha->isSunday()) {
            $fecha->addDay();
        }
        $this->actingAs($maria)->post('/propietario/citas', ['mascota_id' => $max->id, 'veterinaria_id' => $this->vet()->id, 'servicio' => 'Otro…',
            'servicio_otro' => 'limpieza dental', 'fecha' => $fecha->toDateString(), 'hora' => '15:00'])->assertSessionHasNoErrors();
        $this->assertSame('Limpieza dental', Cita::latest('id')->value('servicio'));
    }

    // ---------- 5 · Avisos de pagos · 9 · Gmail ----------

    public function test_el_admin_ve_en_la_campana_los_pagos_por_verificar(): void
    {
        $admin = $this->u('admin@petcare.com');
        $html = $this->actingAs($admin)->get('/admin')->getContent();
        $this->assertStringContainsString('Pago por verificar: Plan Estándar · Michi', $html);   // pago pendiente de la demo
        $this->actingAs($admin)->get('/admin/propietarios')->assertSee('mail.google.com/mail/?view=cm&fs=1&to=maria%40correo.com', false);
    }

    public function test_las_citas_enlazan_al_whatsapp_de_la_clinica(): void
    {
        $v = $this->vet();
        $this->assertSame('59176541122', $v->whatsappCitas());
        $v->update(['whatsapp' => '71112233']);
        $this->assertSame('59171112233', $v->fresh()->whatsappCitas());
        $v->update(['whatsapp' => null]);
        $this->assertSame(config('petcare.whatsapp_veterinarias_demo'), $v->fresh()->whatsappCitas());   // solo si no registró uno
    }

    // ---------- 12 · Tienda ----------

    public function test_compra_en_la_tienda_con_envio_stock_y_seguimiento(): void
    {
        WhatsAppService::$enviados = [];
        $maria = $this->u('maria@correo.com');
        $claudia = $this->u('veterinaria@clinica.com');
        $amoxi = Producto::where('nombre', 'Amoxicilina 500 mg (caja)')->first();
        $stock = $amoxi->stock;
        $this->actingAs($maria)->post("/propietario/tienda/carrito/{$amoxi->id}", ['cantidad' => $stock + 1])->assertSessionHasErrors('cantidad');
        $this->actingAs($maria)->post("/propietario/tienda/carrito/{$amoxi->id}", ['cantidad' => 2])->assertSessionHasNoErrors();
        $this->actingAs($maria)->get('/propietario/tienda/carrito')->assertSee('Amoxicilina 500 mg (caja)');
        $this->actingAs($maria)->get('/propietario/tienda/pagar/'.$amoxi->veterinaria_id)->assertOk()->assertSee('Envío a domicilio');

        $max = Mascota::where('nombre', 'Max')->first();   // Premium 90 %, plan de la demo sin veterinaria fija
        $r = $this->actingAs($maria)->post('/propietario/tienda/pagar/'.$amoxi->veterinaria_id, ['entrega' => 'domicilio', 'direccion' => 'Calle 8 de Obrajes N.º 512',
            'mascota_id' => $max->id, 'metodo' => 'qr', 'comprobante' => UploadedFile::fake()->image('qr.jpg'), 'acepto' => '1']);
        $r->assertSessionHasNoErrors()->assertRedirect('/propietario/tienda/pedidos');
        $pedido = Pedido::latest('id')->first();
        $this->assertSame($stock - 2, $amoxi->fresh()->stock);            // stock reservado
        $this->assertEquals(90, $pedido->subtotal);
        $this->assertEquals(81, $pedido->descuento);                       // 90 % de medicamentos con su plan
        $this->assertEquals(15, $pedido->envio);
        $this->assertEquals(24, $pedido->total);
        $this->assertSame([], session('carrito'));

        $this->actingAs($claudia)->get('/veterinaria/pedidos')->assertSee($pedido->numero);
        $this->actingAs($claudia)->post("/veterinaria/pedidos/{$pedido->id}/pago/aprobar")->assertSessionHasNoErrors();
        foreach (['preparando', 'en_camino', 'entregado'] as $e) {
            $this->actingAs($claudia)->post("/veterinaria/pedidos/{$pedido->id}/avanzar")->assertSessionHasNoErrors();
            $this->assertSame($e, $pedido->fresh()->estado);
        }
        $this->assertTrue(collect(WhatsAppService::$enviados)->contains(fn ($m) => str_contains($m['mensaje'], "Pedido {$pedido->numero} entregado")));
        // Otra clínica no puede tocar el pedido
        $this->actingAs($this->u('vida@clinica.com'))->post("/veterinaria/pedidos/{$pedido->id}/avanzar")->assertNotFound();
    }

    public function test_si_la_clinica_rechaza_el_pago_vuelve_el_stock(): void
    {
        $maria = $this->u('maria@correo.com');
        $p = Producto::where('nombre', 'Correa reflectiva')->first();
        $stock = $p->stock;
        $this->actingAs($maria)->post("/propietario/tienda/carrito/{$p->id}", ['cantidad' => 1]);
        $this->actingAs($maria)->post('/propietario/tienda/pagar/'.$p->veterinaria_id, ['entrega' => 'recoger', 'metodo' => 'whatsapp', 'acepto' => '1'])->assertSessionHasNoErrors();
        $pedido = Pedido::latest('id')->first();
        $this->assertSame($stock - 1, $p->fresh()->stock);
        $this->actingAs($this->u('veterinaria@clinica.com'))->post("/veterinaria/pedidos/{$pedido->id}/pago/rechazar", ['motivo' => 'No llegó la transferencia'])->assertSessionHasNoErrors();
        $this->assertSame('cancelado', $pedido->fresh()->estado);
        $this->assertSame($stock, $p->fresh()->stock);
    }

    public function test_los_productos_agotados_no_se_pueden_comprar(): void
    {
        $pelota = Producto::where('nombre', 'Pelota de caucho')->first();   // stock 0 en la demo
        $this->actingAs($this->u('maria@correo.com'))->get('/propietario/tienda')->assertSee('Agotado');
        $this->actingAs($this->u('maria@correo.com'))->post("/propietario/tienda/carrito/{$pelota->id}", ['cantidad' => 1])->assertSessionHasErrors('cantidad');
    }

    // ---------- Clínica: cancelar suscripción y eliminar su cuenta ----------

    public function test_la_clinica_cancela_su_suscripcion_y_elimina_su_cuenta(): void
    {
        WhatsAppService::$enviados = [];
        $vida = $this->vet('Centro Veterinario Vida');
        $jorge = $this->u('vida@clinica.com');
        Pago::where('veterinaria_id', $vida->id)->where('estado', 'pendiente')->delete();
        // Sin cancelar la suscripción no se puede eliminar
        $this->actingAs($jorge)->delete('/veterinaria/cuenta', ['confirmar' => $vida->nombre, 'password' => 'vet123'])->assertSessionHasErrors();
        $this->actingAs($jorge)->post('/veterinaria/suscripcion/cancelar', ['motivo' => 'Cierre del local'])->assertSessionHasNoErrors();
        $this->assertNotNull($vida->fresh()->suscripcion_cancelada_at);
        // Cancelada: ya no se cobra ni se avisa
        $antes = Pago::where('veterinaria_id', $vida->id)->count();
        app(\App\Services\SuscripcionService::class)->procesar(Carbon::today()->addDays(30));
        $this->assertSame($antes, Pago::where('veterinaria_id', $vida->id)->count());
        $this->assertNull($vida->fresh()->aviso_renovacion_at);
        // Confirmación con el nombre y la contraseña
        $this->actingAs($jorge)->delete('/veterinaria/cuenta', ['confirmar' => 'otra', 'password' => 'vet123'])->assertSessionHasErrors('confirmar');
        $this->actingAs($jorge)->delete('/veterinaria/cuenta', ['confirmar' => $vida->nombre, 'password' => 'mal'])->assertSessionHasErrors('password');
        $abiertas = Cita::where('veterinaria_id', $vida->id)->whereIn('estado', ['pendiente', 'confirmada'])->count();
        $this->actingAs($jorge)->delete('/veterinaria/cuenta', ['confirmar' => $vida->nombre, 'password' => 'vet123'])->assertRedirect('/');

        $vida->refresh();
        $this->assertSame('baja', $vida->estado);
        $this->assertFalse($vida->activa);
        $this->assertSame(0, Cita::where('veterinaria_id', $vida->id)->whereIn('estado', ['pendiente', 'confirmada'])->count());
        $this->assertGreaterThanOrEqual($abiertas, collect(WhatsAppService::$enviados)->filter(fn ($m) => str_contains($m['mensaje'], 'dejó PetCare'))->count());
        $this->assertFalse(User::where('email', 'vida@clinica.com')->exists());   // el correo queda libre
        $this->assertGreaterThan(0, $vida->atenciones()->count());                 // el historial se conserva
        $this->post('/login/veterinaria', ['email' => 'vida@clinica.com', 'password' => 'vet123'])->assertSessionHasErrors();
    }

    public function test_una_clinica_puede_reactivar_su_suscripcion_cancelada(): void
    {
        $jorge = $this->u('vida@clinica.com');
        Pago::where('veterinaria_id', $jorge->veterinaria_id)->where('estado', 'pendiente')->delete();
        $this->actingAs($jorge)->post('/veterinaria/suscripcion/cancelar')->assertSessionHasNoErrors();
        $this->actingAs($jorge)->post('/veterinaria/suscripcion/reactivar')->assertSessionHasNoErrors();
        $this->assertNull($jorge->veterinaria->fresh()->suscripcion_cancelada_at);
    }
}
