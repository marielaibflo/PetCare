<?php

namespace App\Services;

use App\Models\Afiliacion;
use App\Models\Atencion;
use App\Models\Mascota;
use App\Models\Plan;
use App\Models\Producto;
use App\Models\Veterinaria;
use App\Support\Catalogo;
use Illuminate\Support\Carbon;

/**
 * Cálculo de la cobertura de una atención.
 *
 *  1. Se suman el precio del servicio (tarifario de la clínica) y los productos usados.
 *  2. Cada línea está cubierta o no según el plan de la mascota:
 *       - servicio: que el plan lo incluya (cirugías solo después del periodo de espera);
 *       - vacunas y antiparasitarios: van con el servicio;
 *       - medicamentos e insumos: que la clínica los marque como cubiertos para ese plan
 *         y que el plan cubra medicamentos (todos / solo urgencias / ninguno).
 *  3. PetCare paga el porcentaje del plan sobre lo cubierto, hasta el tope mensual y anual.
 *  4. El dueño paga el resto.
 * «Oncología y crónicos» solo lo cubre el adicional Cobertura Grave (50 %, tope anual propio, espera de 180 días).
 */
class CoberturaService
{
    /**
     * @param  array<int, array{producto: Producto, cantidad: float}>  $items
     */
    public function calcular(Mascota $mascota, ?Veterinaria $vet, string $servicio, float $costoServicio, array $items, Carbon $fecha,
        bool $preexistente = false, ?int $excluirAtencion = null): array
    {
        $afil = $mascota->afiliaciones()->with(['plan', 'veterinaria'])->where('estado', 'activo')
            ->whereDate('fecha_inicio', '<=', $fecha)->whereDate('fecha_fin', '>=', $fecha)->latest('id')->first();
        $plan = $afil?->plan;
        $grave = $servicio === Catalogo::GRAVE;
        $notas = [];

        // ¿Aplica alguna cobertura?
        [$aplica, $pct] = [false, 0];
        if (! $afil) {
            $notas[] = 'La mascota no tiene un plan vigente: atención particular.';
        } elseif ($vet && ! $vet->atiendePlanes()) {
            $notas[] = 'La suscripción de la clínica no incluye atención con planes PetCare.';
        } elseif ($vet && $afil->veterinaria_id && $afil->veterinaria_id !== $vet->id) {
            $notas[] = "El plan de {$mascota->nombre} solo cubre en {$afil->veterinaria?->nombre}. En esta clínica se paga el precio completo.";
        } elseif ($afil->enCarencia($fecha)) {
            $notas[] = "Plan en periodo de carencia: {$mascota->nombre} podrá usarlo desde el ".$afil->carencia_hasta->format('d/m/Y').' (también urgencias).';
        } elseif ($preexistente) {
            $notas[] = 'Enfermedad preexistente a la afiliación: no la cubre el plan.';
        } elseif ($grave) {
            $esp = Catalogo::ADICIONAL_GRAVE['espera_dias'];
            if (! $afil->cobertura_grave) {
                $notas[] = 'Oncología y crónicos solo se cubre con el adicional Cobertura Grave.';
            } elseif ($afil->diasAfiliada($fecha) < $esp) {
                $notas[] = "Cobertura Grave en periodo de espera ({$esp} días desde la afiliación).";
            } else {
                [$aplica, $pct] = [true, Catalogo::ADICIONAL_GRAVE['porcentaje']];
            }
        } else {
            [$aplica, $pct] = [true, $plan->porcentaje];
        }

        $servicioCubierto = $aplica && ($grave || $plan->cubre($servicio));
        if ($aplica && ! $grave && ! $plan->cubre($servicio)) {
            $notas[] = in_array($servicio, Catalogo::SERVICIOS, true)
                ? "El plan {$plan->nombre} no cubre {$servicio}."
                : "«{$servicio}» no es un servicio del plan: no lo cubre, lo paga el dueño.";
        }
        if ($servicioCubierto && in_array($servicio, Catalogo::CIRUGIAS, true) && $afil->diasAfiliada($fecha) < $plan->espera_cirugia_dias) {
            $servicioCubierto = false;
            $faltan = $plan->espera_cirugia_dias - $afil->diasAfiliada($fecha);
            $notas[] = "Cirugías en periodo de espera: faltan {$faltan} días para que el plan las cubra.";
        }

        // Líneas de la cuenta
        $lineas = [['nombre' => $servicio, 'producto_id' => null, 'cantidad' => 1.0, 'precio_unitario' => round($costoServicio, 2),
            'subtotal' => round($costoServicio, 2), 'cubierto' => $servicioCubierto]];
        foreach ($items as $it) {
            $p = $it['producto'];
            $cubierto = $aplica && $this->productoCubierto($p, $plan, $servicio, $servicioCubierto, $grave);
            $lineas[] = ['nombre' => $p->nombre, 'producto_id' => $p->id, 'cantidad' => (float) $it['cantidad'],
                'precio_unitario' => $p->precio, 'subtotal' => round($p->precio * $it['cantidad'], 2), 'cubierto' => $cubierto];
        }

        $total = round(array_sum(array_column($lineas, 'subtotal')), 2);
        $baseCubierta = array_sum(array_map(fn ($l) => $l['cubierto'] ? $l['subtotal'] : 0, $lineas));
        $cubierto = round($baseCubierta * $pct / 100, 2);

        // Límite de atenciones cubiertas al año: al alcanzarlo, la clínica cobra el precio completo.
        if ($aplica && ! $grave && $plan->limite_atenciones) {
            $usadas = $this->atencionesCubiertas($mascota, $fecha, $excluirAtencion);
            if ($usadas >= $plan->limite_atenciones) {
                $notas[] = "{$mascota->nombre} ya usó las {$plan->limite_atenciones} atenciones cubiertas del año de su plan: la veterinaria cobra el precio completo.";
                foreach ($lineas as &$l) {
                    $l['cubierto'] = false;
                }
                unset($l);
                $baseCubierta = 0;
                $cubierto = 0.0;
            } elseif ($cubierto > 0 && $usadas + 1 === $plan->limite_atenciones) {
                $notas[] = "Es la última de las {$plan->limite_atenciones} atenciones cubiertas del año.";
            }
        }

        // Topes
        $disponible = $afil ? $this->disponible($mascota, $afil, $grave, $fecha, $excluirAtencion) : 0.0;
        if ($cubierto > $disponible) {
            $notas[] = $disponible <= 0
                ? 'Se alcanzó el tope de cobertura del '.($grave ? 'año' : 'periodo').': el dueño paga el 100 %.'
                : 'La cobertura se limitó al saldo disponible del tope ('.\App\Support\Formato::bs($disponible).').';
            $cubierto = max(0, round($disponible, 2));
        }

        return [
            'plan' => $plan, 'afiliacion' => $afil, 'porcentaje' => $cubierto > 0 ? $pct : 0,
            'lineas' => $lineas, 'costo_servicio' => round($costoServicio, 2), 'costo_productos' => round($total - $costoServicio, 2),
            'total' => $total, 'monto_cubierto' => $cubierto, 'paga_dueno' => round($total - $cubierto, 2),
            'disponible' => round(max(0, $disponible), 2), 'notas' => $notas,
        ];
    }

    private function productoCubierto(Producto $p, ?Plan $plan, string $servicio, bool $servicioCubierto, bool $grave): bool
    {
        // Alimentos, accesorios, higiene y juguetes son venta de la tienda: los planes cubren salud.
        if (in_array($p->categoria, Catalogo::PRODUCTOS_TIENDA, true)) {
            return false;
        }
        if (! $p->aplicaA($plan) && ! $grave) {
            return false;
        }
        if ($grave) {
            return $p->cubre_seguro;
        }
        // Vacunas y antiparasitarios forman parte del servicio preventivo.
        if (in_array($p->categoria, ['vacuna', 'antiparasitario'], true)) {
            return $servicioCubierto;
        }

        return match ($plan->medicamentos) {
            'todos' => true,
            'urgencia' => $servicio === 'Urgencia',
            default => false,
        };
    }

    /** Atenciones con cobertura en los últimos 12 meses (sin contar el adicional Grave). */
    public function atencionesCubiertas(Mascota $mascota, Carbon $fecha, ?int $excluir = null): int
    {
        return Atencion::where('mascota_id', $mascota->id)->where('monto_cubierto', '>', 0)->where('servicio', '!=', Catalogo::GRAVE)
            ->when($excluir, fn ($q) => $q->whereKeyNot($excluir))
            ->whereDate('fecha', '>', $fecha->copy()->subYear())->whereDate('fecha', '<=', $fecha)->count();
    }

    /** Saldo de cobertura que le queda a la mascota (tope mensual y anual del plan, o anual del adicional Grave). */
    public function disponible(Mascota $mascota, Afiliacion $afil, bool $grave, Carbon $fecha, ?int $excluir = null): float
    {
        $base = Atencion::where('mascota_id', $mascota->id)->where('monto_cubierto', '>', 0)
            ->when($excluir, fn ($q) => $q->whereKeyNot($excluir))
            ->when($grave, fn ($q) => $q->where('servicio', Catalogo::GRAVE), fn ($q) => $q->where('servicio', '!=', Catalogo::GRAVE));
        $anio = (clone $base)->whereDate('fecha', '>', $fecha->copy()->subYear())->whereDate('fecha', '<=', $fecha)->sum('monto_cubierto');

        if ($grave) {
            return Catalogo::ADICIONAL_GRAVE['tope_anual'] - $anio;
        }
        $mes = (clone $base)->whereBetween('fecha', [$fecha->copy()->startOfMonth()->toDateString(), $fecha->copy()->endOfMonth()->toDateString()])->sum('monto_cubierto');

        return min($afil->plan->tope_mensual - $mes, $afil->plan->tope_anual - $anio);
    }

    /** Resumen para mostrar al dueño: cuánto le queda de cobertura este mes y este año. */
    public function saldo(Mascota $mascota): ?array
    {
        $afil = $mascota->afiliacionActiva;
        if (! $afil) {
            return null;
        }
        $hoy = Carbon::today();
        $usadoMes = Atencion::where('mascota_id', $mascota->id)->where('servicio', '!=', Catalogo::GRAVE)
            ->whereBetween('fecha', [$hoy->copy()->startOfMonth()->toDateString(), $hoy->copy()->endOfMonth()->toDateString()])->sum('monto_cubierto');
        $usadoAnio = Atencion::where('mascota_id', $mascota->id)->where('servicio', '!=', Catalogo::GRAVE)
            ->whereDate('fecha', '>', $hoy->copy()->subYear())->sum('monto_cubierto');

        return ['mes_usado' => (float) $usadoMes, 'mes_tope' => $afil->plan->tope_mensual,
            'anio_usado' => (float) $usadoAnio, 'anio_tope' => $afil->plan->tope_anual,
            'atenciones_usadas' => $this->atencionesCubiertas($mascota, $hoy), 'atenciones_limite' => $afil->plan->limite_atenciones];
    }
}
