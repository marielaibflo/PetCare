<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Supabase (PostgreSQL): activa la seguridad por filas (RLS) en todas las tablas.
 * Sin políticas, nadie puede leer ni escribir por la API pública de Supabase;
 * PetCare se conecta como dueño de las tablas, así que sigue funcionando igual.
 * En SQLite o MySQL no hace nada.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }
        foreach (DB::select("select tablename from pg_tables where schemaname = 'public'") as $t) {
            DB::statement('alter table public."'.$t->tablename.'" enable row level security');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }
        foreach (DB::select("select tablename from pg_tables where schemaname = 'public'") as $t) {
            DB::statement('alter table public."'.$t->tablename.'" disable row level security');
        }
    }
};
