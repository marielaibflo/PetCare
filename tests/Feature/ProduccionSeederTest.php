<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\User;
use App\Models\Veterinaria;
use Database\Seeders\ProduccionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** La base real (Supabase) arranca sin datos de demostración: solo planes y el administrador. */
class ProduccionSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_crea_solo_los_planes_y_el_administrador(): void
    {
        [$planes, $usuarios, $clinicas] = [Plan::count(), User::count(), Veterinaria::count()];
        putenv('PETCARE_ADMIN_EMAIL=Duenio@PetCare.bo'); putenv('PETCARE_ADMIN_PASSWORD=ClaveSegura2026!');
        $this->seed(ProduccionSeeder::class);

        // Solo agrega al administrador: ni clínicas ni dueños de ejemplo, y los planes no se duplican
        $this->assertSame(max($planes, 3), Plan::count());
        $this->assertSame($usuarios + 1, User::count());
        $this->assertSame($clinicas, Veterinaria::count());
        $this->post('/login/administrador', ['email' => 'duenio@petcare.bo', 'password' => 'ClaveSegura2026!'])->assertRedirect(route('admin.dashboard'));
        putenv('PETCARE_ADMIN_EMAIL'); putenv('PETCARE_ADMIN_PASSWORD');
    }

    public function test_sin_clave_en_env_no_crea_nada(): void
    {
        putenv('PETCARE_ADMIN_EMAIL'); putenv('PETCARE_ADMIN_PASSWORD');
        $this->expectException(\RuntimeException::class);
        $this->seed(ProduccionSeeder::class);
    }
}
