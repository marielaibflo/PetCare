<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Veterinaria;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutenticacionTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_las_pantallas_publicas_cargan(): void
    {
        $this->get('/')->assertOk()->assertSee('PetCare');
        $this->get('/login/propietario')->assertOk();
        $this->get('/login/veterinaria')->assertOk();
        $this->get('/login/administrador')->assertOk();
        $this->get('/registro')->assertOk();
    }

    public function test_login_correcto_redirige_al_panel_de_cada_rol(): void
    {
        $this->post('/login/propietario', ['email' => 'maria@correo.com', 'password' => '123456'])->assertRedirect('/propietario');
        $this->post('/logout');
        $this->post('/login/veterinaria', ['email' => 'veterinaria@clinica.com', 'password' => 'vet123'])->assertRedirect('/veterinaria');
        $this->post('/logout');
        $this->post('/login/administrador', ['email' => 'admin@petcare.com', 'password' => 'admin123'])->assertRedirect('/admin');
    }

    public function test_login_con_clave_incorrecta_muestra_error(): void
    {
        $this->from('/login/propietario')->post('/login/propietario', ['email' => 'maria@correo.com', 'password' => 'mala'])
            ->assertRedirect('/login/propietario')->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_no_se_puede_ingresar_con_el_perfil_equivocado(): void
    {
        $this->post('/login/administrador', ['email' => 'maria@correo.com', 'password' => '123456'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_registro_crea_un_propietario_y_valida_los_datos(): void
    {
        $this->post('/registro', ['name' => 'Pedro Pérez', 'email' => 'pedro@correo.com', 'codigo_pais' => 'BO', 'telefono' => '71112233',
            'password' => 'Secreto#2026', 'password_confirmation' => 'Secreto#2026'])->assertRedirect('/verificar');
        $this->assertDatabaseHas('users', ['email' => 'pedro@correo.com', 'rol' => User::PROPIETARIO, 'codigo_pais' => 'BO']);
        $this->post('/logout');

        $this->post('/registro', ['name' => 'X', 'email' => 'maria@correo.com', 'codigo_pais' => 'BO', 'telefono' => '12',
            'password' => '1', 'password_confirmation' => '2'])->assertSessionHasErrors(['name', 'email', 'telefono', 'password']);
    }

    public function test_rutas_protegidas_por_rol(): void
    {
        $this->get('/propietario')->assertRedirect('/');
        $maria = User::where('email', 'maria@correo.com')->first();
        $this->actingAs($maria)->get('/admin')->assertForbidden();
        $this->actingAs($maria)->get('/veterinaria')->assertForbidden();
        $vet = User::where('email', 'veterinaria@clinica.com')->first();
        $this->actingAs($vet)->get('/propietario')->assertForbidden();
    }

    public function test_cuenta_de_veterinaria_inactiva_no_puede_ingresar(): void
    {
        $this->post('/login/veterinaria', ['email' => 'huellitas@clinica.com', 'password' => 'vet123'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertFalse(Veterinaria::where('nombre', 'Clínica Huellitas')->first()->activa);
    }

    public function test_todas_las_pantallas_de_cada_rol_responden(): void
    {
        $maria = User::where('email', 'maria@correo.com')->first();
        $max = $maria->mascotas()->first();
        foreach (['/propietario', '/propietario/mascotas', "/propietario/mascotas/{$max->id}", "/propietario/mascotas/{$max->id}/vacunas",
            "/propietario/mascotas/{$max->id}/historial", "/propietario/mascotas/{$max->id}/plan", "/propietario/mascotas/{$max->id}/citas",
            '/propietario/planes', '/propietario/veterinarias', '/propietario/citas', '/propietario/perfil'] as $url) {
            $this->actingAs($maria)->get($url)->assertOk();
        }
        $vet = User::where('email', 'veterinaria@clinica.com')->first();
        foreach (['/veterinaria', '/veterinaria/agenda', '/veterinaria/pacientes', "/veterinaria/pacientes/{$max->id}",
            '/veterinaria/clinica', '/veterinaria/atenciones/nueva'] as $url) {
            $this->actingAs($vet)->get($url)->assertOk();
        }
        $admin = User::where('email', 'admin@petcare.com')->first();
        foreach (['/admin', '/admin/propietarios', '/admin/mascotas', '/admin/veterinarias', '/admin/planes', '/admin/servicios',
            '/admin/servicios?tab=citas', '/admin/reportes', '/admin/configuracion'] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
    }
}
