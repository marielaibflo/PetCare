<?php

namespace Tests\Feature;

use App\Models\Mascota;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Los buscadores no distinguen mayúsculas, ni en SQLite ni en PostgreSQL (Supabase). */
class BusquedaTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_buscar_sin_importar_mayusculas(): void
    {
        $admin = User::where('email', 'admin@petcare.com')->first();
        $mascota = Mascota::first();
        $this->actingAs($admin)->get('/admin/mascotas?q='.urlencode(mb_strtoupper($mascota->nombre)))->assertOk()->assertSee($mascota->nombre);
        $dueno = User::where('rol', User::PROPIETARIO)->first();
        $this->actingAs($admin)->get('/admin/propietarios?q='.urlencode(mb_strtolower($dueno->name)))->assertOk()->assertSee($dueno->name);
    }
}
