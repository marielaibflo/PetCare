<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * Se ejecuta en cada arranque del servidor (Render):
 *  - si la base aún no tiene los perfiles de prueba, carga la base de demostración (DatabaseSeeder):
 *    admin@petcare.com, veterinaria@clinica.com, juan@correo.com, Veterinaria Patitas y sus productos;
 *  - vuelve a poner las fotos de los productos de prueba, porque el disco del servidor se borra al reiniciar.
 * No borra ni cambia datos que ya existan.
 */
class ArranqueSeeder extends Seeder
{
    public function run(): void
    {
        if (! User::where('email', 'juan@correo.com')->exists() && ! User::where('email', 'veterinaria@clinica.com')->exists()) {
            $this->call(DatabaseSeeder::class);
        }

        foreach (glob(database_path('seeders/fotos/*.jpg')) as $foto) {
            $ruta = 'productos/'.basename($foto);
            if (! Storage::disk('local')->exists($ruta)) {
                Storage::disk('local')->put($ruta, file_get_contents($foto));
            }
        }
    }
}
