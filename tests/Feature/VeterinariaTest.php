<?php

namespace Tests\Feature;

use App\Models\Cita;
use App\Models\Mascota;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class VeterinariaTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private User $vet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->vet = User::where('email', 'veterinaria@clinica.com')->first();
    }

    public function test_confirmar_cita_pendiente(): void
    {
        $cita = Cita::where('veterinaria_id', $this->vet->veterinaria_id)->where('estado', 'pendiente')->first();
        $this->actingAs($this->vet)->patch("/veterinaria/citas/{$cita->id}/confirmar")->assertRedirect();
        $this->assertSame('confirmada', $cita->fresh()->estado);
    }

    public function test_no_gestiona_citas_de_otra_clinica(): void
    {
        $ajena = Cita::where('veterinaria_id', '!=', $this->vet->veterinaria_id)->where('estado', 'pendiente')->first();
        $res = $this->actingAs($this->vet)->patch("/veterinaria/citas/{$ajena->id}/confirmar");
        $this->assertContains($res->status(), [403, 404]);
        $this->assertSame('pendiente', $ajena->fresh()->estado);
    }

    public function test_registrar_atencion_desde_cita_marca_atendida_y_calcula_cobertura(): void
    {
        $cita = Cita::where('veterinaria_id', $this->vet->veterinaria_id)->where('servicio', 'Consulta')->where('estado', 'confirmada')->first();
        $this->actingAs($this->vet)->post('/veterinaria/atenciones', [
            'mascota_id' => $cita->mascota_id, 'cita_id' => $cita->id, 'servicio' => 'Consulta', 'fecha' => Carbon::today()->toDateString(),
            'costo_servicio' => '80', 'descripcion' => 'Control general sin hallazgos.', 'peso' => '28.4', 'temperatura' => '38.5',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertSame('atendida', $cita->fresh()->estado);
        $atencion = $cita->mascota->atenciones()->first();
        $this->assertSame('Control general sin hallazgos.', $atencion->descripcion);
        $this->assertTrue((bool) $atencion->cubierto_por_plan);   // Max tiene el Plan Premium (90 %)
        $this->assertEquals(72, $atencion->monto_cubierto);
        $this->assertEquals(8, $atencion->paga_dueno);
        $this->assertEquals(28.4, (float) $cita->mascota->fresh()->peso);
    }

    public function test_vacuna_con_proxima_dosis_aparece_en_el_carnet_del_propietario(): void
    {
        $luna = Mascota::where('nombre', 'Luna')->first();
        $proxima = Carbon::today()->addYear()->toDateString();
        $this->actingAs($this->vet)->post('/veterinaria/atenciones', [
            'mascota_id' => $luna->id, 'servicio' => 'Vacuna', 'fecha' => Carbon::today()->toDateString(),
            'producto' => 'Triple felina (2.ª dosis)', 'proxima_dosis' => $proxima, 'descripcion' => 'Segunda dosis aplicada.', 'costo_servicio' => '120',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('atenciones', ['mascota_id' => $luna->id, 'producto' => 'Triple felina (2.ª dosis)']);
        $maria = User::where('email', 'maria@correo.com')->first();
        $this->actingAs($maria)->get("/propietario/mascotas/{$luna->id}/vacunas")->assertOk()->assertSee('Triple felina (2.ª dosis)');
    }

    public function test_atencion_invalida_no_se_guarda(): void
    {
        $max = Mascota::where('nombre', 'Max')->first();
        $antes = $max->atenciones()->count();
        $this->actingAs($this->vet)->post('/veterinaria/atenciones', [
            'mascota_id' => $max->id, 'servicio' => 'Magia', 'fecha' => Carbon::tomorrow()->toDateString(),
            'descripcion' => '', 'temperatura' => '60',
        ])->assertSessionHasErrors(['servicio', 'fecha', 'descripcion', 'temperatura']);
        $this->assertSame($antes, $max->atenciones()->count());
    }

    public function test_editar_datos_de_la_clinica(): void
    {
        $this->actingAs($this->vet)->put('/veterinaria/clinica', [
            'nombre' => 'Veterinaria Patitas', 'zona' => 'San Miguel', 'direccion' => 'Calle 21 N.º 900', 'telefono' => '22441199',
            'horario' => 'Lun–Sáb · 08:30 a 19:00', 'servicios' => ['Consulta', 'Vacuna'],
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('veterinarias', ['id' => $this->vet->veterinaria_id, 'telefono' => '22441199']);
    }
}
