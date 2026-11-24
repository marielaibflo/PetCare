<?php

namespace App\Services;

use App\Models\Atencion;
use App\Models\Cita;
use App\Models\Mascota;
use App\Models\Producto;
use App\Support\Archivos;
use Illuminate\Http\UploadedFile;
use App\Models\User;
use App\Support\Catalogo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Historial clínico, cobertura y control de vacunas (próximas dosis). */
class HistorialService
{
    use ReglaNegocio;

    public function __construct(private CoberturaService $cobertura, private CitaService $citas) {}

    /**
     * Registra una atención con sus costos: el sistema calcula cuánto cubre el plan (lo paga PetCare a la clínica)
     * y cuánto paga el dueño. Si viene de una cita, la marca como atendida y avisa al dueño por WhatsApp.
     */
    public function registrar(User $veterinario, Mascota $mascota, array $datos, ?UploadedFile $foto = null): Atencion
    {
        $clinica = $veterinario->veterinaria;
        $this->exigir(! empty($datos['otro']) || $clinica->ofrece($datos['servicio']), 'Tu veterinaria no tiene habilitado ese servicio.', 'servicio');
        unset($datos['otro']);
        $preventivo = in_array($datos['servicio'], Catalogo::PREVENTIVOS, true);
        if ($preventivo) {
            // Marco legal: la vacuna o desparasitación la registra un veterinario identificado con su matrícula,
            // en una clínica con registro vigente.
            $this->exigir((bool) $veterinario->matricula, 'Para registrar vacunas y desparasitaciones, primero escribe tu N.º de matrícula profesional en «Mi clínica».', 'servicio');
            $this->exigir((bool) $clinica->registro, 'Para registrar vacunas y desparasitaciones, primero escribe el N.º de registro de la clínica en «Mi clínica».', 'servicio');
            $this->exigir(! empty($datos['producto']), 'Escribe el nombre de la vacuna o producto aplicado.', 'producto');
        }
        if ($preventivo && ! empty($datos['proxima_dosis'])) {
            $this->exigir(Carbon::parse($datos['proxima_dosis'])->gt(Carbon::parse($datos['fecha'])),
                'La próxima dosis debe ser posterior a la fecha de aplicación.', 'proxima_dosis');
        }
        $cita = null;
        if (! empty($datos['cita_id'])) {
            $cita = Cita::find($datos['cita_id']);
            $this->exigir($cita && $cita->veterinaria_id === $clinica->id && $cita->mascota_id === $mascota->id && $cita->abierta(),
                'La cita indicada no corresponde a esta atención.');
        }
        $items = $this->productosDe($clinica->id, $datos['productos'] ?? []);
        $calculo = $this->cobertura->calcular($mascota, $clinica, $datos['servicio'], (float) $datos['costo_servicio'], $items,
            Carbon::parse($datos['fecha']), (bool) ($datos['preexistente'] ?? false));

        return DB::transaction(function () use ($veterinario, $clinica, $mascota, $datos, $preventivo, $calculo, $cita, $foto) {
            $atencion = Atencion::create([
                'mascota_id' => $mascota->id, 'veterinaria_id' => $clinica->id, 'user_id' => $veterinario->id,
                'cita_id' => $cita?->id, 'plan_id' => $calculo['plan']?->id, 'fecha' => $datos['fecha'], 'servicio' => $datos['servicio'],
                'producto' => $preventivo ? ($datos['producto'] ?? null) : null, 'lote' => $preventivo ? ($datos['lote'] ?? null) : null,
                'matricula' => $preventivo ? $veterinario->matricula : null, 'registro_clinica' => $preventivo ? $clinica->registro : null,
                'proxima_dosis' => $preventivo ? ($datos['proxima_dosis'] ?? null) : null,
                'diagnostico' => $datos['diagnostico'] ?? null, 'descripcion' => $datos['descripcion'],
                'tratamiento' => ($datos['tratamiento'] ?? null) ?: 'Ninguno',
                'peso' => $datos['peso'] ?? null, 'temperatura' => $datos['temperatura'] ?? null,
                'cubierto_por_plan' => $calculo['monto_cubierto'] > 0,
                'costo_servicio' => $calculo['costo_servicio'], 'costo_productos' => $calculo['costo_productos'], 'total' => $calculo['total'],
                'porcentaje' => $calculo['porcentaje'], 'monto_cubierto' => $calculo['monto_cubierto'], 'paga_dueno' => $calculo['paga_dueno'],
                'preexistente' => (bool) ($datos['preexistente'] ?? false), 'nota_cobertura' => mb_substr(implode(' ', $calculo['notas']), 0, 250) ?: null,
                'firmado_por' => $preventivo ? $veterinario->name.' · '.$clinica->nombre : null,
                'foto_etiqueta' => $foto ? Archivos::guardar($foto, 'etiquetas') : null,
            ]);
            foreach (array_slice($calculo['lineas'], 1) as $l) {
                $atencion->productos()->create($l);
            }
            if ($cita) {
                $this->citas->marcarAtendida($cita);
            }
            if (! empty($datos['peso'])) {
                $mascota->update(['peso' => $datos['peso']]);
            }

            return $atencion;
        });
    }

    /** @return array<int, array{producto: Producto, cantidad: float}> */
    public function productosDe(int $veterinariaId, array $filas): array
    {
        $items = [];
        foreach ($filas as $f) {
            if (empty($f['id']) || empty($f['cantidad'])) {
                continue;
            }
            $p = Producto::where('veterinaria_id', $veterinariaId)->activos()->find($f['id']);
            $this->exigir((bool) $p, 'Uno de los productos no pertenece a tu catálogo.', 'productos');
            $items[] = ['producto' => $p, 'cantidad' => (float) $f['cantidad']];
        }

        return $items;
    }

    /** Mascotas que agendaron o se atendieron en la clínica. */
    public function pacientesDe(int $veterinariaId): Collection
    {
        $ids = Cita::where('veterinaria_id', $veterinariaId)->pluck('mascota_id')
            ->merge(Atencion::where('veterinaria_id', $veterinariaId)->pluck('mascota_id'))
            ->merge(\App\Models\Afiliacion::vigentes()->where('veterinaria_id', $veterinariaId)->pluck('mascota_id'))->unique();

        return Mascota::with(['propietario', 'afiliacionActiva.plan', 'afiliacionActiva.veterinaria'])->withCount('atenciones')->whereIn('id', $ids)->orderBy('nombre')->get();
    }

    public function esPaciente(int $veterinariaId, Mascota $mascota): bool
    {
        return Cita::where('veterinaria_id', $veterinariaId)->where('mascota_id', $mascota->id)->exists()
            || Atencion::where('veterinaria_id', $veterinariaId)->where('mascota_id', $mascota->id)->exists()
            || \App\Models\Afiliacion::vigentes()->where('veterinaria_id', $veterinariaId)->where('mascota_id', $mascota->id)->exists();
    }

    /** Vacunas y desparasitaciones aplicadas (carnet). */
    public function carnet(Mascota $mascota): Collection
    {
        return $mascota->atenciones()->with('veterinaria')->whereIn('servicio', Catalogo::PREVENTIVOS)->get();
    }

    /**
     * Próximas dosis: la aplicación más reciente de cada vacuna o producto.
     * «Triple felina (2.ª dosis)» reemplaza el recordatorio de «Triple felina (1.ª dosis)».
     */
    public function proximasDosis(Mascota $mascota): Collection
    {
        $vistos = [];
        $usadas = [];
        $hoy = Carbon::today();
        $citas = $mascota->citas()->proximas()->orderBy('fecha')->get();
        $out = collect();
        foreach ($this->carnet($mascota) as $a) {
            $clave = $a->servicio.'|'.mb_strtolower(trim(preg_replace('/\(.*?\)/u', '', (string) $a->producto)));
            if (isset($vistos[$clave])) {
                continue;
            }
            $vistos[$clave] = true;
            if (! $a->proxima_dosis) {
                continue;
            }
            $dias = (int) $hoy->diffInDays($a->proxima_dosis, false);
            // Una cita futura del mismo servicio cuenta para esta dosis si cae cerca de la fecha prevista.
            $cita = $citas->first(fn ($c) => $c->servicio === $a->servicio && ! in_array($c->id, $usadas, true)
                && abs($hoy->diffInDays($c->fecha, false) - $dias) <= 45);
            if ($cita) {
                $usadas[] = $cita->id;
            }
            $out->push((object) [
                'atencion' => $a, 'mascota' => $mascota, 'dias' => $dias, 'cita' => $cita,
                'estado' => $dias < 0 ? 'vencida' : ($dias <= Catalogo::DIAS_AVISO ? 'proxima' : 'al_dia'),
            ]);
        }

        return $out->sortBy(fn ($x) => $x->atencion->proxima_dosis)->values();
    }

    /** Recordatorios para el propietario: dosis vencidas o que vencen pronto. */
    public function recordatorios(User $propietario): Collection
    {
        return $propietario->mascotas()->get()
            ->flatMap(fn (Mascota $m) => $this->proximasDosis($m))
            ->filter(fn ($x) => $x->estado !== 'al_dia')->values();
    }
}
