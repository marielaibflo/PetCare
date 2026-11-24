<?php

namespace App\Services;

use App\Models\Afiliacion;
use App\Models\Mascota;
use App\Models\Plan;
use App\Support\Catalogo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Planes de asistencia por porcentaje de cobertura.
 * El dueño solicita y paga; el administrador verifica el pago y recién ahí se activa (o se extiende) la afiliación.
 * Regla: una mascota solo puede tener un plan activo.
 */
class PlanService
{
    use ReglaNegocio;

    /** Monto a pagar: (precio del plan + adicional Grave) × meses cobrados (en la modalidad anual se cobran 10 de 12). */
    public function monto(Plan $plan, string $modalidad, bool $grave): float
    {
        $adicional = $grave ? Catalogo::ADICIONAL_GRAVE['precio'] : 0;
        if ($modalidad === 'anual') {
            return round($plan->precioAnual() + $adicional * Catalogo::MODALIDADES['anual']['cobra'], 2);
        }

        return round(($plan->precio + $adicional) * Catalogo::MODALIDADES[$modalidad]['cobra'], 2);
    }

    public function validarSolicitud(Mascota $mascota, Plan $plan, bool $grave, ?\App\Models\Veterinaria $vet = null): void
    {
        $this->exigir($plan->activo && Plan::activos()->whereKey($plan->id)->exists(), 'El plan no está disponible.');
        $this->exigir(! $grave || $plan->permite_grave, "El adicional Cobertura Grave no está disponible con el plan {$plan->nombre}.", 'cobertura_grave');
        $motivo = $plan->motivoNoAdmite($mascota);
        $this->exigir(! $motivo, (string) $motivo, 'mascota_id');
        if (! $vet) {
            return;
        }
        $this->exigir($vet->activa && $vet->suscripcionVigente() && $vet->atiendePlanes(), 'Elige una veterinaria afiliada que atienda planes PetCare.', 'veterinaria_id');
        // Contrato vigente: mientras dure la permanencia no se cambia de plan ni de veterinaria
        $actual = $mascota->afiliacionActiva;
        if ($actual && ! $actual->puedeCancelar()) {
            $fin = $actual->permanencia_hasta->format('d/m/Y');
            $this->exigir($actual->plan_id === $plan->id, "{$mascota->nombre} tiene un contrato con el {$actual->plan->nombre} hasta el {$fin}: solo puedes renovarlo. Podrás cambiar de plan después de esa fecha.", 'mascota_id');
            $this->exigir($actual->veterinaria_id === null || $actual->veterinaria_id === $vet->id || ! $this->veterinariaDisponible($actual),
                "El plan de {$mascota->nombre} está contratado en {$actual->veterinaria?->nombre} hasta el {$fin}. Podrás elegir otra veterinaria en tu próximo plan.", 'veterinaria_id');
        }
    }

    /** La veterinaria del contrato sigue en PetCare (si no, el dueño puede elegir otra sin penalidad). */
    public function veterinariaDisponible(Afiliacion $a): bool
    {
        $v = $a->veterinaria;

        return (bool) ($v && $v->activa && $v->suscripcionVigente() && $v->atiendePlanes());
    }

    /** Veterinarias donde se puede contratar un plan. */
    public function veterinariasParaPlanes(): \Illuminate\Support\Collection
    {
        return \App\Models\Veterinaria::activas()->where('estado', 'aprobada')->orderByDesc('valoracion')->orderBy('nombre')->get()
            ->filter(fn ($v) => $v->atiendePlanes())->values();
    }

    /**
     * Activa el plan pagado. Si la mascota ya tiene ese mismo plan, se extiende (renovación);
     * si tiene otro, se reemplaza desde hoy.
     */
    /**
     * Activa el plan pagado (contrato).
     *  - Mismo plan y misma veterinaria: se renueva (se extiende la fecha de fin).
     *  - Si la veterinaria del contrato dejó PetCare: se cambia a la nueva sin penalidad.
     *  - Otro plan u otra veterinaria (ya cumplida la permanencia): nuevo contrato.
     * Contrato nuevo: permanencia mínima (3 meses) y carencia (60 días) del plan; si ya tenía un plan, la carencia no se repite.
     */
    public function activar(Mascota $mascota, Plan $plan, string $modalidad, bool $grave, ?int $vetId = null, ?Carbon $contratoAt = null): Afiliacion
    {
        $meses = Catalogo::MODALIDADES[$modalidad]['meses'];
        $actual = $mascota->afiliacionActiva;
        $vetId ??= $actual?->veterinaria_id;

        return DB::transaction(function () use ($mascota, $plan, $modalidad, $grave, $meses, $actual, $vetId, $contratoAt) {
            $mismaVet = ! $actual || $actual->veterinaria_id === null || $actual->veterinaria_id === $vetId;
            if ($actual && $actual->plan_id === $plan->id && ($mismaVet || ! $this->veterinariaDisponible($actual))) {
                $actual->update(['fecha_fin' => $actual->fecha_fin->copy()->addMonths($meses), 'modalidad' => $modalidad,
                    'cobertura_grave' => $grave || $actual->cobertura_grave, 'veterinaria_id' => $vetId]);

                return $actual->fresh();
            }
            $actual?->update(['estado' => 'cambiado', 'fecha_fin' => Carbon::today()]);
            $hoy = Carbon::today();
            $a = Afiliacion::create([
                'mascota_id' => $mascota->id, 'plan_id' => $plan->id, 'cobertura_grave' => $grave, 'modalidad' => $modalidad,
                'estado' => 'activo', 'fecha_inicio' => $hoy, 'fecha_fin' => $hoy->copy()->addMonths($meses), 'veterinaria_id' => $vetId,
                'contrato_at' => $contratoAt ?? now(), 'permanencia_hasta' => $hoy->copy()->addMonths($plan->permanencia_meses ?: 3),
                'carencia_hasta' => $actual ? ($actual->carencia_hasta && $actual->carencia_hasta->gt($hoy) ? $actual->carencia_hasta : null) : $hoy->copy()->addDays($plan->carencia_dias),
            ]);
            $a->update(['contrato_numero' => 'CA-'.$hoy->year.'-'.str_pad((string) $a->id, 6, '0', STR_PAD_LEFT)]);

            return $a->fresh();
        });
    }

    public function cancelar(Mascota $mascota): void
    {
        $actual = $mascota->afiliacionActiva;
        $this->exigir((bool) $actual, 'La mascota no tiene un plan activo.');
        $this->exigir($actual->puedeCancelar(), "El contrato tiene una permanencia mínima: podrás cancelar el plan desde el {$actual->permanencia_hasta?->format('d/m/Y')}.");
        $actual->update(['estado' => 'cancelado', 'fecha_fin' => Carbon::today()]);
    }

    public function guardar(?Plan $plan, array $datos): Plan
    {
        $datos['beneficios'] = collect(preg_split('/\r?\n/', $datos['beneficios'] ?? ''))->map('trim')->filter()->values()->all();
        $datos['permite_grave'] = (bool) ($datos['permite_grave'] ?? false);
        $datos['especies'] = array_values($datos['especies'] ?? []) ?: null;
        $datos['precio_anual'] = ($datos['precio_anual'] ?? '') === '' ? null : $datos['precio_anual'];
        if ($plan) {
            $plan->update($datos);

            return $plan;
        }

        return Plan::create($datos + ['activo' => true]);
    }

    public function eliminar(Plan $plan): void
    {
        $this->exigir(! $plan->afiliaciones()->exists(), 'El plan tiene o tuvo mascotas afiliadas. Desactívalo para que no se ofrezca a nuevos clientes.');
        $plan->delete();
    }
}
