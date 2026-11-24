<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GoogleLoginTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private array $cuenta = [];

    private function configurar(): void
    {
        config(['petcare.google' => ['client_id' => 'id-prueba', 'client_secret' => 'secreto-prueba', 'redirect' => null]]);
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'tok']),
            'openidconnect.googleapis.com/*' => fn () => Http::response($this->cuenta),
        ]);
    }

    /** Cuenta de Google que «responde» en la prueba. */
    private function google(string $email, string $nombre = 'Ana Google', bool $verificado = true): void
    {
        $this->cuenta = ['email' => $email, 'email_verified' => $verificado, 'name' => $nombre];
    }

    /** Va a Google y vuelve con el código, como lo haría el navegador. */
    private function entrar(string $rol)
    {
        $ida = $this->get("/login/{$rol}/google");
        $ida->assertRedirect();
        parse_str(parse_url($ida->headers->get('Location'), PHP_URL_QUERY), $q);
        $this->assertStringStartsWith('https://accounts.google.com/', $ida->headers->get('Location'));

        return $this->get('/auth/google/callback?'.http_build_query(['code' => 'abc', 'state' => $q['state']]));
    }

    public function test_sin_configurar_no_aparece_el_boton_y_el_login_normal_sigue_igual(): void
    {
        config(['petcare.google' => ['client_id' => null, 'client_secret' => null, 'redirect' => null]]);
        $this->get('/login/propietario')->assertOk()->assertDontSee('Continuar con Google')->assertSee('Iniciar sesión');
        $this->post('/login/administrador', ['email' => 'admin@petcare.com', 'password' => 'admin123'])->assertRedirect(route('admin.dashboard'));
    }

    public function test_configurado_muestra_el_boton_en_los_tres_inicios(): void
    {
        $this->configurar();
        foreach (['propietario', 'veterinaria', 'administrador'] as $rol) {
            $this->get("/login/{$rol}")->assertOk()->assertSee('Continuar con Google')->assertSee('Iniciar sesión');
        }
    }

    public function test_un_propietario_existente_entra_con_google_y_queda_recordado(): void
    {
        $this->configurar();
        $dueno = User::where('rol', User::PROPIETARIO)->first();
        $dueno->forceFill(['email_verified_at' => now(), 'telefono_verificado_at' => now(), 'telefono' => '70011223'])->save();
        $this->google($dueno->email);
        $this->entrar('propietario')->assertRedirect(route('propietario.inicio'));
        $this->assertAuthenticatedAs($dueno);
        $this->assertNotNull($dueno->fresh()->remember_token);
    }

    public function test_un_propietario_nuevo_se_crea_con_el_correo_verificado_y_solo_falta_el_whatsapp(): void
    {
        $this->configurar();
        $this->google('nueva.persona@gmail.com', 'Nueva Persona');
        $this->entrar('propietario')->assertRedirect(route('verificacion.show'));

        $u = User::where('email', 'nueva.persona@gmail.com')->first();
        $this->assertSame(User::PROPIETARIO, $u->rol);
        $this->assertNotNull($u->email_verified_at);
        $this->assertNull($u->telefono);
        $this->get('/verificar')->assertOk()->assertSee('Tu número de WhatsApp')->assertSee('Verificado');
    }

    public function test_la_veterinaria_y_el_admin_entran_con_google_si_su_correo_existe(): void
    {
        $this->configurar();
        $this->google('veterinaria@clinica.com');
        $this->entrar('veterinaria')->assertRedirect(route('veterinaria.inicio'));
        $this->post('/logout');

        $this->google('admin@petcare.com');
        $this->entrar('administrador')->assertRedirect(route('admin.dashboard'));
    }

    public function test_no_crea_veterinarias_ni_admins_y_respeta_el_perfil(): void
    {
        $this->configurar();
        $this->google('desconocida@gmail.com');
        $this->entrar('veterinaria')->assertRedirect(route('login', 'veterinaria'))->assertSessionHasErrors('email');
        $this->assertDatabaseMissing('users', ['email' => 'desconocida@gmail.com']);

        $this->google('admin@petcare.com');
        $this->entrar('propietario')->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_rechaza_un_estado_falso_o_un_correo_sin_verificar(): void
    {
        $this->configurar();
        $this->get('/login/propietario/google');
        $this->get('/auth/google/callback?code=abc&state=falso')->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->google('admin@petcare.com', 'Admin', false);
        $this->entrar('administrador')->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_si_ya_tiene_celular_sin_verificar_recibe_el_codigo_de_whatsapp(): void
    {
        $this->configurar();
        config(['petcare.demo' => true]);
        $dueno = User::where('rol', User::PROPIETARIO)->first();
        $dueno->forceFill(['telefono' => '78539749', 'telefono_verificado_at' => null])->save();
        $this->google($dueno->email);
        $this->entrar('propietario')->assertRedirect(route('verificacion.show'));
        $this->get('/verificar')->assertSee('Modo demostración')->assertSee('WhatsApp:');
    }
}
