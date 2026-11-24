<?php

namespace Tests\Feature;

use App\Models\CodigoVerificacion;
use App\Models\User;
use App\Services\WhatsAppService;
use App\Support\Paises;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Etapa 1 · Registro seguro: correo, código de país, contraseña segura y verificación por WhatsApp. */
class RegistroSeguroTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function datos(array $cambios = []): array
    {
        return $cambios + ['name' => 'Lucía Torrez', 'email' => 'lucia@correo.com', 'codigo_pais' => 'BO', 'telefono' => '72223344',
            'password' => 'Mascota#2026', 'password_confirmation' => 'Mascota#2026'];
    }

    public function test_rechaza_contrasenas_debiles(): void
    {
        foreach (['corta1!', 'sinmayuscula1!', 'SINMINUSCULA1!', 'SinNumero!!', 'SinSimbolo123'] as $clave) {
            $this->post('/registro', $this->datos(['password' => $clave, 'password_confirmation' => $clave]))->assertSessionHasErrors('password');
        }
        $this->assertGuest();
    }

    public function test_valida_el_celular_segun_el_pais(): void
    {
        // Bolivia: 8 dígitos y empieza con 6 o 7 (celular)
        $this->post('/registro', $this->datos(['telefono' => '22441122']))->assertSessionHasErrors('telefono'); // fijo
        $this->post('/registro', $this->datos(['telefono' => '7123']))->assertSessionHasErrors('telefono');
        $this->post('/registro', $this->datos(['codigo_pais' => 'XX']))->assertSessionHasErrors('codigo_pais');
        // Perú: 9 dígitos y empieza con 9
        $this->post('/registro', $this->datos(['codigo_pais' => 'PE', 'telefono' => '72223344']))->assertSessionHasErrors('telefono');
        $this->post('/registro', $this->datos(['codigo_pais' => 'PE', 'telefono' => '912345678']))->assertRedirect('/verificar');
        $this->assertDatabaseHas('users', ['email' => 'lucia@correo.com', 'codigo_pais' => 'PE', 'telefono' => '912345678']);
    }

    public function test_normaliza_el_numero_si_escribe_el_codigo_de_pais(): void
    {
        $this->assertSame('71234567', Paises::normalizar('BO', '+591 712-34567'));
        $this->assertSame('71234567', Paises::normalizar('BO', '0 7123 4567'));
        $this->assertSame('59171234567', Paises::internacional('BO', '71234567'));
    }

    public function test_no_permite_un_celular_ya_registrado(): void
    {
        // 70011223 es el celular de María (datos de demostración)
        $this->post('/registro', $this->datos(['telefono' => '70011223']))->assertSessionHasErrors('telefono');
    }

    public function test_detecta_errores_de_tipeo_en_el_correo(): void
    {
        $this->post('/registro', $this->datos(['email' => 'lucia@gmial.com']))->assertSessionHasErrors('email');
        $this->post('/registro', $this->datos(['email' => 'lucia@']))->assertSessionHasErrors('email');
    }

    public function test_cuenta_nueva_debe_verificar_correo_y_whatsapp_antes_de_entrar(): void
    {
        WhatsAppService::$enviados = [];
        $this->post('/registro', $this->datos())->assertRedirect('/verificar');
        $user = User::where('email', 'lucia@correo.com')->first();
        $this->assertFalse($user->verificado());
        $this->assertSame('59172223344', end(WhatsAppService::$enviados)['para']);
        $this->assertSame(2, CodigoVerificacion::where('user_id', $user->id)->count());

        // Sin verificar no puede usar la plataforma
        $this->get('/propietario')->assertRedirect('/verificar');
        $demo = session('codigos_demo');
        $this->get('/verificar')->assertOk()->assertSee($demo['whatsapp']);

        // Código incorrecto
        $this->post('/verificar/email', ['codigo_email' => '000000'])->assertSessionHasErrors('codigo_email');
        // Códigos correctos
        $this->post('/verificar/email', ['codigo_email' => $demo['email']])->assertSessionHasNoErrors();
        $this->post('/verificar/whatsapp', ['codigo_whatsapp' => $demo['whatsapp']])->assertRedirect('/propietario');

        $this->assertTrue($user->fresh()->verificado());
        $this->get('/propietario')->assertOk();
    }

    public function test_el_codigo_se_bloquea_tras_varios_intentos_y_vence(): void
    {
        $this->post('/registro', $this->datos());
        $codigo = session('codigos_demo')['email'];
        foreach (range(1, 5) as $i) {
            $this->post('/verificar/email', ['codigo_email' => '111111']);
        }
        $this->post('/verificar/email', ['codigo_email' => $codigo])->assertSessionHasErrors('codigo_email');

        // Un código nuevo funciona, pero no después de vencer
        $this->post('/verificar/email/reenviar');
        $nuevo = session('codigos_demo')['email'];
        $this->travel(16)->minutes();
        $this->post('/verificar/email', ['codigo_email' => $nuevo])->assertSessionHasErrors('codigo_email');
    }

    public function test_puede_corregir_su_numero_antes_de_verificar(): void
    {
        $this->post('/registro', $this->datos());
        $this->put('/verificar/telefono', ['codigo_pais' => 'BO', 'telefono' => '65554433'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('users', ['email' => 'lucia@correo.com', 'telefono' => '65554433']);
        $this->post('/verificar/whatsapp', ['codigo_whatsapp' => session('codigos_demo')['whatsapp']])->assertSessionHasNoErrors();
    }

    public function test_cambiar_el_celular_en_el_perfil_pide_verificarlo_de_nuevo(): void
    {
        $maria = User::where('email', 'maria@correo.com')->first();
        $this->actingAs($maria)->put('/perfil', ['name' => 'María López', 'email' => 'maria@correo.com', 'codigo_pais' => 'BO', 'telefono' => '79998877'])
            ->assertRedirect('/verificar');
        $this->assertNull($maria->fresh()->telefono_verificado_at);
        $this->assertNotNull($maria->fresh()->email_verified_at);
        $this->actingAs($maria)->get('/propietario')->assertRedirect('/verificar');
    }

    public function test_el_personal_de_veterinaria_puede_tener_telefono_fijo(): void
    {
        $vet = User::where('email', 'veterinaria@clinica.com')->first();
        $this->actingAs($vet)->put('/perfil', ['name' => 'Dra. Claudia Rojas', 'email' => 'veterinaria@clinica.com', 'telefono' => '22441122'])
            ->assertSessionHasNoErrors();
    }

    public function test_el_administrador_registra_propietarios_que_confirman_su_correo(): void
    {
        $admin = User::where('email', 'admin@petcare.com')->first();
        $this->actingAs($admin)->post('/admin/propietarios', ['name' => 'Rosa Choque', 'email' => 'rosa@correo.com', 'codigo_pais' => 'BO',
            'telefono' => '68887766', 'password' => 'debil'])->assertSessionHasErrors('password');
        $this->actingAs($admin)->post('/admin/propietarios', ['name' => 'Rosa Choque', 'email' => 'rosa@correo.com', 'codigo_pais' => 'BO',
            'telefono' => '68887766', 'password' => 'Rosa#2026x'])->assertSessionHasNoErrors();
        $rosa = User::where('email', 'rosa@correo.com')->first();
        // El celular lo confirmó el administrador por llamada; el correo lo confirma Rosa con el botón del correo
        $this->assertNotNull($rosa->telefono_verificado_at);
        $this->assertFalse($rosa->verificado());
        $this->get(\App\Services\VerificacionService::enlace($rosa))->assertOk();
        $this->assertTrue($rosa->fresh()->verificado());
    }
}
