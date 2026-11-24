<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Base REAL para trabajar con clínicas de verdad (por ejemplo en Supabase):
 * solo los 3 planes y la cuenta del administrador, sin datos de demostración.
 *   php artisan migrate --seed --seeder=ProduccionSeeder
 * El correo y la contraseña del administrador se leen de .env (PETCARE_ADMIN_EMAIL y PETCARE_ADMIN_PASSWORD).
 */
class ProduccionSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('PETCARE_ADMIN_EMAIL');
        $clave = env('PETCARE_ADMIN_PASSWORD');
        if (! $email || ! $clave || strlen($clave) < 8) {
            throw new RuntimeException('Escribe PETCARE_ADMIN_EMAIL y PETCARE_ADMIN_PASSWORD (mínimo 8 caracteres) en el archivo .env');
        }

        $this->call(PlanesSeeder::class);

        User::updateOrCreate(['email' => mb_strtolower($email)], [
            'name' => 'Administrador PetCare', 'password' => $clave, 'rol' => User::ADMINISTRADOR, 'activo' => true,
        ])->forceFill(['email_verified_at' => now(), 'telefono_verificado_at' => now()])->save();
    }
}
