<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /** Las pruebas usan los datos de demostración completos; la base de la app arranca limpia. */
    protected string $seeder = \Database\Seeders\DemoSeeder::class;
}
