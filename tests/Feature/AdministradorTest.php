<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\User;
use App\Models\Veterinaria;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdministradorTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::where('email', 'admin@petcare.com')->first();
    }

    public function test_afiliar_veterinaria_crea_su_cuenta_y_puede_ingresar(): void
    {
        $this->actingAs($this->admin)->post('/admin/veterinarias', [
            'nombre' => 'Clínica San Pedro', 'zona' => 'Achumani', 'direccion' => 'Calle 10', 'telefono' => '22770011',
            'horario' => 'Lun–Vie', 'servicios' => ['Consulta', 'Vacuna'],
            'responsable' => 'Dr. Pedro Mendoza', 'email' => 'sanpedro@clinica.com', 'password' => 'Clave#123',
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('veterinarias', ['nombre' => 'Clínica San Pedro']);
        $this->assertDatabaseHas('users', ['email' => 'sanpedro@clinica.com', 'rol' => User::VETERINARIA]);
        $this->post('/logout');
        $this->post('/login/veterinaria', ['email' => 'sanpedro@clinica.com', 'password' => 'Clave#123'])->assertRedirect('/veterinaria');
    }

    public function test_desactivar_veterinaria_bloquea_su_acceso(): void
    {
        $patitas = Veterinaria::where('nombre', 'Veterinaria Patitas')->first();
        $this->actingAs($this->admin)->patch("/admin/veterinarias/{$patitas->id}/estado")->assertRedirect();
        $this->assertFalse($patitas->fresh()->activa);
        $this->post('/logout');
        $this->post('/login/veterinaria', ['email' => 'veterinaria@clinica.com', 'password' => 'vet123'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_crud_de_planes(): void
    {
        $reglas = ['porcentaje' => 50, 'tope_mensual' => '200', 'tope_anual' => '1200', 'espera_cirugia_dias' => 0, 'medicamentos' => 'ninguno', 'vigente_desde' => today()->format('Y-m-d'), 'carencia_dias' => 60, 'permanencia_meses' => 3, 'edad_min_meses' => 2, 'icono' => 'shield', 'color' => 'verde'];
        $this->actingAs($this->admin)->post('/admin/planes', $reglas + ['nombre' => 'Plan Cachorro', 'descripcion' => 'Para cachorros',
            'precio' => '90', 'cobertura' => ['Vacuna', 'Desparasitación'], 'beneficios' => "Vacunas\nDesparasitación"])->assertSessionHasNoErrors();
        $plan = Plan::where('nombre', 'Plan Cachorro')->first();
        $this->assertSame(['Vacunas', 'Desparasitación'], $plan->beneficios);

        $this->actingAs($this->admin)->put("/admin/planes/{$plan->id}", $reglas + ['nombre' => 'Plan Cachorro', 'precio' => '95',
            'cobertura' => ['Vacuna']])->assertSessionHasNoErrors();
        $this->assertEquals(95, (float) $plan->fresh()->precio);

        $this->actingAs($this->admin)->delete("/admin/planes/{$plan->id}")->assertRedirect();
        $this->assertDatabaseMissing('planes', ['id' => $plan->id]);
    }

    public function test_no_se_elimina_un_plan_con_afiliados(): void
    {
        $integral = Plan::where('nombre', 'Plan Premium')->first();
        $this->actingAs($this->admin)->delete("/admin/planes/{$integral->id}")->assertSessionHasErrors();
        $this->assertDatabaseHas('planes', ['id' => $integral->id]);
    }

    public function test_plan_invalido(): void
    {
        $this->actingAs($this->admin)->post('/admin/planes', ['nombre' => 'Plan Premium', 'precio' => '0', 'cobertura' => [], 'porcentaje' => 120, 'tope_mensual' => '500', 'tope_anual' => '100'])
            ->assertSessionHasErrors(['nombre', 'precio', 'cobertura', 'porcentaje', 'tope_anual']);
    }

    public function test_desactivar_propietario_bloquea_su_acceso(): void
    {
        $carlos = User::where('email', 'carlos@correo.com')->first();
        $this->actingAs($this->admin)->patch("/admin/propietarios/{$carlos->id}/estado")->assertRedirect();
        $this->assertFalse((bool) $carlos->fresh()->activo);
        $this->post('/logout');
        $this->post('/login/propietario', ['email' => 'carlos@correo.com', 'password' => '123456'])->assertSessionHasErrors('email');
    }

    public function test_reporte_excel_con_todas_las_hojas(): void
    {
        foreach (['dia' => today()->toDateString(), 'mes' => today()->format('Y-m'), 'anio' => (string) today()->year] as $tipo => $fecha) {
            $res = $this->actingAs($this->admin)->get("/admin/reportes/excel?periodo={$tipo}&fecha={$fecha}");
            $res->assertOk()->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
            file_put_contents($tmp, $res->getContent());
            $zip = new \ZipArchive;
            $this->assertTrue($zip->open($tmp) === true, 'El archivo debe ser un ZIP válido');
            $libro = $zip->getFromName('xl/workbook.xml');
            foreach (['Índice', 'Ventas tienda', 'Resumen', 'Estado de resultados', 'Ganancias', 'Libro diario', 'Propietarios', 'Mascotas', 'Veterinarias', 'Citas', 'Historial clínico', 'Vacunas', 'Pagos', 'Siniestralidad', 'Liquidación', 'Servicios'] as $hoja) {
                $this->assertStringContainsString('name="'.$hoja.'"', $libro);
            }
            // La hoja 1 es el índice (con enlaces a cada hoja); Propietarios es la 6.ª
            $this->assertStringContainsString('<hyperlink ref="B5" location="&apos;Resumen&apos;!A1"', $zip->getFromName('xl/worksheets/sheet1.xml'));
            $this->assertNotFalse(simplexml_load_string($zip->getFromName('xl/worksheets/sheet6.xml')), 'XML de la hoja válido');
            $this->assertStringContainsString('María López', $zip->getFromName('xl/worksheets/sheet6.xml'));
            $this->assertStringContainsString('Volver al índice', $zip->getFromName('xl/worksheets/sheet6.xml'));
            if ($tipo === 'anio') {
                $this->assertStringContainsString('outlineLevel="1"', $zip->getFromName('xl/worksheets/sheet5.xml'));   // libro diario agrupado por asiento
            }
            $zip->close();
            unlink($tmp);
        }
        $this->actingAs($this->admin)->get('/admin/reportes?periodo=anio&fecha='.today()->year)->assertOk()->assertSee('Registro de ganancias')->assertSee('Estado de resultados');
        $this->actingAs($this->admin)->get('/admin/reportes?periodo=dia&fecha='.today()->toDateString())->assertOk();
    }
}
