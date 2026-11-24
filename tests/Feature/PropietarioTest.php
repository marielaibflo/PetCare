<?php

namespace Tests\Feature;

use App\Models\Cita;
use App\Models\Mascota;
use App\Models\Plan;
use App\Models\User;
use App\Models\Veterinaria;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PropietarioTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private User $maria;

    protected function setUp(): void
    {
        parent::setUp();
        $this->maria = User::where('email', 'maria@correo.com')->first();
    }

    private function diaHabil(int $dias): string
    {
        $d = Carbon::today()->addDays($dias);
        while ($d->isSunday()) {
            $d->addDay();
        }

        return $d->toDateString();
    }

    private function datosMascota(array $extra = []): array
    {
        return $extra + ['nombre' => 'Coco', 'especie' => 'Gato', 'raza' => 'Persa', 'fecha_nacimiento' => '2024-03-10',
            'peso' => '3.8', 'sexo' => 'Hembra', 'esterilizado' => 'no', 'alergias' => ''];
    }

    public function test_registrar_editar_y_eliminar_mascota(): void
    {
        $this->actingAs($this->maria)->post('/propietario/mascotas', $this->datosMascota())->assertRedirect();
        $coco = Mascota::where('nombre', 'Coco')->first();
        $this->assertSame($this->maria->id, $coco->propietario_id);

        $this->actingAs($this->maria)->put("/propietario/mascotas/{$coco->id}", $this->datosMascota(['nombre' => 'Coco Chanel', 'peso' => '4']))->assertRedirect();
        $this->assertSame('Coco Chanel', $coco->fresh()->nombre);

        $this->actingAs($this->maria)->delete("/propietario/mascotas/{$coco->id}")->assertRedirect();
        $this->assertSoftDeleted('mascotas', ['id' => $coco->id]);
    }

    public function test_mascota_con_datos_invalidos_no_se_guarda(): void
    {
        $this->actingAs($this->maria)->post('/propietario/mascotas', $this->datosMascota([
            'nombre' => '', 'especie' => 'Dragón', 'fecha_nacimiento' => Carbon::tomorrow()->toDateString(), 'peso' => '-1',
        ]))->assertSessionHasErrors(['nombre', 'especie', 'fecha_nacimiento', 'peso']);
        $this->assertSame(2, $this->maria->mascotas()->count());
    }

    public function test_no_se_puede_ver_ni_modificar_mascota_ajena(): void
    {
        $rocky = Mascota::where('nombre', 'Rocky')->first();
        $this->actingAs($this->maria)->get("/propietario/mascotas/{$rocky->id}")->assertNotFound();
        $this->actingAs($this->maria)->delete("/propietario/mascotas/{$rocky->id}")->assertNotFound();
        $this->assertNotSoftDeleted('mascotas', ['id' => $rocky->id]);
    }

    private function pagarPlan(Mascota $m, Plan $plan, string $modalidad = 'mensual'): void
    {
        $this->actingAs($this->maria)->post("/propietario/planes/{$plan->id}/solicitar", ['mascota_id' => $m->id, 'modalidad' => $modalidad,
            'facturar_a' => 'María López', 'documento' => '4785123', 'metodo' => 'tarjeta', 'tarjeta_marca' => 'visa', 'tarjeta_numero' => '4242 4242 4242 4242',
            'tarjeta_nombre' => 'MARIA LOPEZ', 'tarjeta_vence' => '12/'.(now()->year % 100 + 2), 'tarjeta_cvv' => '123', 'acepto' => '1', 'veterinaria_id' => 1, 'contrato' => '1'])->assertSessionHasNoErrors();
        $pago = \App\Models\Pago::where('mascota_id', $m->id)->where('estado', 'pendiente')->latest('id')->first();
        $admin = User::where('email', 'admin@petcare.com')->first();
        $this->actingAs($admin)->patch("/admin/pagos/{$pago->id}/aprobar")->assertSessionHasNoErrors();
    }

    public function test_una_mascota_solo_tiene_un_plan_activo(): void
    {
        $max = Mascota::where('nombre', 'Max')->first();
        $otro = Plan::where('id', '!=', $max->afiliacionActiva->plan_id)->first();
        $this->pagarPlan($max, $otro);
        $this->assertSame(1, $max->afiliaciones()->where('estado', 'activo')->count());
        $this->assertSame($otro->id, $max->fresh()->afiliacionActiva->plan_id);
    }

    public function test_cancelar_plan(): void
    {
        $max = Mascota::where('nombre', 'Max')->first();
        $this->actingAs($this->maria)->delete("/propietario/mascotas/{$max->id}/plan")->assertRedirect();
        $this->assertNull($max->fresh()->afiliacionActiva);
    }

    public function test_pagar_el_mismo_plan_lo_renueva(): void
    {
        $luna = Mascota::where('nombre', 'Luna')->first();  // Plan Básico mensual, vence en 5 días
        $fin = $luna->afiliacionActiva->fecha_fin->copy();
        $this->pagarPlan($luna, $luna->afiliacionActiva->plan);
        $this->assertTrue($luna->fresh()->afiliacionActiva->fecha_fin->equalTo($fin->addMonth()));
    }

    public function test_agendar_y_cancelar_cita(): void
    {
        $max = Mascota::where('nombre', 'Max')->first();
        $vet = Veterinaria::where('nombre', 'Veterinaria Patitas')->first();
        $fecha = $this->diaHabil(5);
        Cita::where('mascota_id', $max->id)->whereDate('fecha', $fecha)->delete();

        $this->actingAs($this->maria)->post('/propietario/citas', ['mascota_id' => $max->id, 'veterinaria_id' => $vet->id,
            'servicio' => 'Consulta', 'fecha' => $fecha, 'hora' => '16:30'])->assertSessionHasNoErrors()->assertRedirect();
        $cita = Cita::where('mascota_id', $max->id)->whereDate('fecha', $fecha)->first();
        $this->assertSame('pendiente', $cita->estado);

        $this->actingAs($this->maria)->patch("/propietario/citas/{$cita->id}/cancelar")->assertRedirect();
        $this->assertSame('cancelada', $cita->fresh()->estado);
    }

    public function test_reglas_de_negocio_de_las_citas(): void
    {
        $max = Mascota::where('nombre', 'Max')->first();
        $luna = Mascota::where('nombre', 'Luna')->first();
        $patitas = Veterinaria::where('nombre', 'Veterinaria Patitas')->first();
        $vida = Veterinaria::where('nombre', 'Centro Veterinario Vida')->first();
        $base = ['mascota_id' => $max->id, 'veterinaria_id' => $patitas->id, 'servicio' => 'Consulta', 'hora' => '08:30'];

        // fecha pasada
        $this->actingAs($this->maria)->post('/propietario/citas', $base + ['fecha' => Carbon::yesterday()->toDateString()])->assertSessionHasErrors('fecha');
        // domingo
        $this->actingAs($this->maria)->post('/propietario/citas', $base + ['fecha' => Carbon::today()->next(Carbon::SUNDAY)->toDateString()])->assertSessionHasErrors('fecha');
        // servicio que la veterinaria no ofrece
        $this->actingAs($this->maria)->post('/propietario/citas', ['servicio' => 'Urgencia', 'veterinaria_id' => $vida->id] + $base + ['fecha' => $this->diaHabil(8)])->assertSessionHasErrors('servicio');
        // horario ocupado
        $fecha = $this->diaHabil(9);
        $this->actingAs($this->maria)->post('/propietario/citas', $base + ['fecha' => $fecha])->assertSessionHasNoErrors();
        $this->actingAs($this->maria)->post('/propietario/citas', ['mascota_id' => $luna->id] + $base + ['fecha' => $fecha])->assertSessionHasErrors('hora');
        // misma mascota dos citas el mismo día
        $this->actingAs($this->maria)->post('/propietario/citas', ['hora' => '09:00'] + $base + ['fecha' => $fecha])->assertSessionHasErrors('fecha');
        // mascota ajena
        $rocky = Mascota::where('nombre', 'Rocky')->first();
        $this->actingAs($this->maria)->post('/propietario/citas', ['mascota_id' => $rocky->id] + $base + ['fecha' => $this->diaHabil(10)])->assertNotFound();
    }

    public function test_actualizar_perfil_y_contrasena(): void
    {
        $this->actingAs($this->maria)->put('/perfil', ['name' => 'María L. Torres', 'email' => 'maria@correo.com', 'telefono' => '70011224'])->assertSessionHasNoErrors();
        $this->assertSame('María L. Torres', $this->maria->fresh()->name);
        $this->actingAs($this->maria)->put('/perfil/password', ['actual' => 'mala', 'password' => 'nueva123', 'password_confirmation' => 'nueva123'])->assertSessionHasErrors();
    }
}
