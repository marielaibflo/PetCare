<?php

namespace Tests\Feature;

use App\Models\Ajuste;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RedesSocialesTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_el_inicio_muestra_el_tiktok_de_petcare(): void
    {
        $this->get('/')->assertOk()->assertSee('Síguenos')
            ->assertSee('https://www.tiktok.com/@petcaresegurity', false)->assertDontSee('instagram.com', false);
    }

    public function test_el_admin_guarda_las_redes_y_aparecen_en_el_inicio(): void
    {
        $admin = User::where('email', 'admin@petcare.com')->first();
        // Acepta solo el usuario y lo convierte en enlace
        $this->actingAs($admin)->put('/admin/configuracion/redes', ['red_tiktok' => '@petcare.bo', 'red_instagram' => 'https://www.instagram.com/petcare.bo'])
            ->assertSessionHasNoErrors();
        $this->assertSame('https://www.tiktok.com/@petcare.bo', Ajuste::valor('red_tiktok'));
        $this->assertSame('https://www.instagram.com/petcare.bo', Ajuste::valor('red_instagram'));

        $this->post('/logout');
        $this->get('/')->assertOk()->assertSee('Síguenos')
            ->assertSee('https://www.tiktok.com/@petcare.bo', false)->assertSee('https://www.instagram.com/petcare.bo', false);

        // Vaciar un campo oculta su botón
        $this->actingAs($admin)->put('/admin/configuracion/redes', ['red_tiktok' => '', 'red_instagram' => 'https://www.instagram.com/petcare.bo']);
        $this->post('/logout');
        $this->get('/')->assertDontSee('tiktok.com', false)->assertSee('instagram.com/petcare.bo', false);
    }

    public function test_rechaza_enlaces_de_otro_sitio(): void
    {
        $admin = User::where('email', 'admin@petcare.com')->first();
        $this->actingAs($admin)->put('/admin/configuracion/redes', ['red_tiktok' => 'https://sitio-falso.com/petcare', 'red_instagram' => 'https://facebook.com/petcare'])
            ->assertSessionHasErrors(['red_tiktok', 'red_instagram'], null, 'redes');
        $this->assertSame('https://www.tiktok.com/@petcaresegurity', Ajuste::valor('red_tiktok'));
    }

    public function test_solo_el_administrador_cambia_las_redes(): void
    {
        $juan = User::where('rol', User::PROPIETARIO)->first();
        $this->actingAs($juan)->put('/admin/configuracion/redes', ['red_tiktok' => '@otro'])->assertForbidden();
    }
}
