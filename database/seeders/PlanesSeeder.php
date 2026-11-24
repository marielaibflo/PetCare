<?php

namespace Database\Seeders;

use App\Models\Plan;
use App\Support\Catalogo;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/** Los 3 planes de asistencia de PetCare (Básico, Estándar y Premium). */
class PlanesSeeder extends Seeder
{
    public function run(): void
    {
        if (Plan::exists()) {
            return; // ya están creados
        }
        $hoy = Carbon::today();
        $sinGrave = array_values(array_diff(Catalogo::SERVICIOS, [Catalogo::GRAVE]));
        Plan::create(['nombre' => 'Plan Básico', 'descripcion' => 'Cuidado preventivo: vacunas, desparasitación y estética.', 'precio' => 35, 'porcentaje' => 40, 'tope_mensual' => 100, 'tope_anual' => 600, 'limite_atenciones' => 4, 'espera_cirugia_dias' => 0, 'medicamentos' => 'urgencia', 'permite_grave' => false,
            'cobertura' => ['Vacuna', 'Desparasitación', 'Control de pulgas', 'Corte de pelo y baño'], 'beneficios' => ['40 % en vacunas y desparasitaciones', '40 % en corte de pelo y baño', 'Descuento en medicamentos de urgencia'], 'icono' => 'shield', 'color' => 'verde', 'vigente_desde' => $hoy->copy()->subMonths(6)]);
        Plan::create(['nombre' => 'Plan Estándar', 'descripcion' => 'Consultas, emergencias, medicamentos y cirugías.', 'precio' => 79, 'porcentaje' => 60, 'tope_mensual' => 250, 'tope_anual' => 1500, 'limite_atenciones' => 8, 'espera_cirugia_dias' => 60, 'medicamentos' => 'todos', 'permite_grave' => true,
            'cobertura' => $sinGrave, 'beneficios' => ['60 % en consultas, vacunas y emergencias', '60 % en medicamentos y tratamientos', 'Castraciones y post-operatorio (desde el día 60)'], 'icono' => 'steth', 'color' => 'azul', 'vigente_desde' => $hoy->copy()->subMonths(6)]);
        Plan::create(['nombre' => 'Plan Premium', 'descripcion' => 'La cobertura más completa para tu mascota.', 'precio' => 149, 'porcentaje' => 90, 'tope_mensual' => 800, 'tope_anual' => 5000, 'limite_atenciones' => 12, 'espera_cirugia_dias' => 30, 'medicamentos' => 'todos', 'permite_grave' => true,
            'cobertura' => $sinGrave, 'beneficios' => ['90 % en citas, vacunas, emergencias y medicamentos', '90 % en cirugías, castraciones y post-operatorio', 'Tope de hasta Bs 5.000 al año'], 'icono' => 'crown', 'color' => 'dorado', 'vigente_desde' => $hoy->copy()->subMonths(6)]);
    }
}
