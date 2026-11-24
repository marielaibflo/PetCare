<?php

namespace App\Services;

use App\Models\Afiliacion;
use App\Models\Atencion;
use App\Models\Cita;
use App\Models\Mascota;
use App\Models\Pago;
use App\Models\Plan;
use App\Models\User;
use App\Models\Veterinaria;
use App\Support\Catalogo;
use Illuminate\Support\Carbon;

/**
 * Indicadores y reportes del administrador.
 *  - Siniestralidad por plan: qué parte de lo cobrado a los dueños se fue en coberturas.
 *  - Liquidación por veterinaria: cuánto le paga PetCare a cada clínica por las coberturas del mes.
 */
class ReporteService
{
    public function indicadores(): array
    {
        $hoy = Carbon::today();
        [$ini, $fin] = [$hoy->copy()->startOfMonth(), $hoy->copy()->endOfMonth()];
        $vigentes = Afiliacion::vigentes()->with('plan')->get();
        $especies = Mascota::selectRaw('especie, count(*) as total')->groupBy('especie')->pluck('total', 'especie');
        $clinicas = Atencion::where('registrado_por', 'veterinaria');
        $servicios = collect(Catalogo::SERVICIOS)->mapWithKeys(fn ($s) => [$s => 0])
            ->merge((clone $clinicas)->selectRaw('servicio, count(*) as total')->groupBy('servicio')->pluck('total', 'servicio'))
            ->sortDesc()->take(8);

        return [
            'propietarios' => User::where('rol', User::PROPIETARIO)->count(),
            'mascotas' => Mascota::count(),
            'afiliaciones' => $vigentes->count(),
            'veterinarias' => Veterinaria::activas()->count(),
            'ingreso' => (float) Pago::dePetcare()->where('estado', 'aprobado')->whereBetween('revisado_at', [$ini, $fin->copy()->endOfDay()])->sum('monto'),
            'coberturas_mes' => (float) Atencion::whereBetween('fecha', [$ini->toDateString(), $fin->toDateString()])->sum('monto_cubierto'),
            'pagos_pendientes' => Pago::dePetcare()->pendientes()->count(),
            'vets_pendientes' => Veterinaria::where('estado', 'pendiente')->count(),
            'citas_hoy' => Cita::whereDate('fecha', $hoy)->whereNotIn('estado', ['cancelada', 'rechazada'])->count(),
            'atenciones_mes' => (clone $clinicas)->whereBetween('fecha', [$ini->toDateString(), $fin->toDateString()])->count(),
            'especies' => $especies,
            'servicios' => $servicios,
            'ultimas' => (clone $clinicas)->with(['mascota', 'veterinaria'])->orderByDesc('fecha')->orderByDesc('id')->limit(5)->get(),
        ];
    }

    public const PERIODOS = ['dia' => 'Día', 'mes' => 'Mes', 'anio' => 'Año'];

    /**
     * Periodo del reporte: por día (fecha=2026-11-19), por mes (fecha=2026-11) o por año (fecha=2026).
     *
     * @return array{tipo:string, ini:Carbon, fin:Carbon, valor:string, etiqueta:string}
     */
    public static function periodo(?string $tipo, ?string $fecha): array
    {
        $tipo = array_key_exists((string) $tipo, self::PERIODOS) ? $tipo : 'mes';
        $hoy = Carbon::today();
        try {
            $base = match ($tipo) {
                'dia' => $fecha ? Carbon::createFromFormat('Y-m-d', $fecha)->startOfDay() : $hoy,
                'mes' => $fecha ? Carbon::createFromFormat('Y-m-d', substr($fecha, 0, 7).'-01')->startOfDay() : $hoy->copy()->startOfMonth(),
                'anio' => $fecha ? Carbon::create((int) substr($fecha, 0, 4), 1, 1) : $hoy->copy()->startOfYear(),
            };
        } catch (\Throwable) {
            $base = $hoy;
        }
        if ($base->gt($hoy)) {
            $base = $hoy;
        }
        [$ini, $fin, $valor, $etiqueta] = match ($tipo) {
            'dia' => [$base->copy()->startOfDay(), $base->copy()->endOfDay(), $base->format('Y-m-d'), $base->locale('es')->translatedFormat('l j \\d\\e F \\d\\e Y')],
            'mes' => [$base->copy()->startOfMonth(), $base->copy()->endOfMonth(), $base->format('Y-m'), $base->locale('es')->translatedFormat('F \\d\\e Y')],
            'anio' => [$base->copy()->startOfYear(), $base->copy()->endOfYear(), $base->format('Y'), 'Año '.$base->year],
        };

        return ['tipo' => $tipo, 'ini' => $ini, 'fin' => $fin, 'valor' => $valor, 'etiqueta' => mb_strtoupper(mb_substr($etiqueta, 0, 1)).mb_substr($etiqueta, 1)];
    }

    /** Compatibilidad: periodo de un mes ("2026-11"). */
    public static function mes(?string $mes): array
    {
        return self::periodo('mes', $mes);
    }

    /** Siniestralidad por plan en el periodo: cuotas cobradas vs. coberturas pagadas. */
    public function porPlan(array $per): array
    {
        [$ini, $fin] = [$per['ini'], $per['fin']];

        return Plan::withCount('afiliacionesVigentes')->orderBy('precio')->get()->map(function ($p) use ($ini, $fin) {
            $ingresos = (float) Pago::where('estado', 'aprobado')->where('plan_id', $p->id)->whereBetween('revisado_at', [$ini, $fin->copy()->endOfDay()])->sum('monto');
            $coberturas = (float) Atencion::where('plan_id', $p->id)->whereBetween('fecha', [$ini->toDateString(), $fin->toDateString()])->sum('monto_cubierto');

            return [$p->nombre, $p->porcentaje.' %', $p->afiliaciones_vigentes_count, $p->precio, $ingresos, $coberturas,
                $ingresos > 0 ? round($coberturas / $ingresos * 100).' %' : ($coberturas > 0 ? 'Sin ingresos' : '—')];
        })->all();
    }

    /** Liquidación del periodo: PetCare paga a cada clínica lo que cubrieron los planes. */
    public function porVeterinaria(array $per): array
    {
        [$ini, $fin] = [$per['ini']->toDateString(), $per['fin']->toDateString()];

        return Veterinaria::where('estado', 'aprobada')->orderBy('nombre')->get()->map(function ($v) use ($ini, $fin) {
            $a = $v->atenciones()->whereBetween('fecha', [$ini, $fin]);

            return [
                $v->nombre, $v->suscripcion()['nombre'], $v->suscripcion_hasta?->format('d/m/Y') ?? 'Sin vencimiento',
                (clone $a)->count(), (float) (clone $a)->sum('total'), (float) (clone $a)->sum('paga_dueno'),
                (float) (clone $a)->sum('monto_cubierto'), $v->activa ? 'Activa' : 'Inactiva',
            ];
        })->all();
    }

    public function porServicio(array $per): array
    {
        [$ini, $fin] = [$per['ini']->toDateString(), $per['fin']->toDateString()];

        return collect(Catalogo::SERVICIOS)->map(function ($s) use ($ini, $fin) {
            $q = Atencion::where('servicio', $s)->where('registrado_por', 'veterinaria')->whereBetween('fecha', [$ini, $fin]);
            $total = (clone $q)->count();
            $cub = (clone $q)->where('monto_cubierto', '>', 0)->count();

            return [$s, $total, $cub, $total ? round($cub / $total * 100).' %' : '—', (float) (clone $q)->sum('monto_cubierto')];
        })->all();
    }

    public const CABECERAS = [
        'planes' => ['Plan', 'Cobertura', 'Afiliados activos', 'Precio mensual (Bs)', 'Cobrado en el periodo (Bs)', 'Coberturas pagadas (Bs)', 'Siniestralidad'],
        'veterinarias' => ['Veterinaria', 'Suscripción', 'Vence', 'Atenciones', 'Total atendido (Bs)', 'Pagado por dueños (Bs)', 'A pagar por PetCare (Bs)', 'Estado'],
        'servicios' => ['Servicio', 'Atenciones', 'Con cobertura', '% con cobertura', 'Monto cubierto (Bs)'],
    ];

    /** Columnas con montos en bolivianos (para dar formato en pantalla). */
    public const MONTOS = ['planes' => [3, 4, 5], 'veterinarias' => [4, 5, 6], 'servicios' => [4]];

    public function tabla(string $tipo, array $per): array
    {
        return match ($tipo) {
            'planes' => $this->porPlan($per),
            'veterinarias' => $this->porVeterinaria($per),
            'servicios' => $this->porServicio($per),
        };
    }

    // ------------------------------------------------------------------ Ganancias y contabilidad básica

    /** Pagos aprobados que son ingreso de PetCare (planes y suscripciones; los pagos de citas son de cada clínica). */
    private function ingresos(array $per)
    {
        return Pago::with(['plan', 'veterinaria', 'mascota', 'user'])->where('estado', 'aprobado')->whereIn('concepto', ['plan', 'suscripcion'])
            ->whereBetween('revisado_at', [$per['ini'], $per['fin']])->orderBy('revisado_at')->get();
    }

    private function coberturas(array $per)
    {
        return Atencion::with(['veterinaria', 'mascota', 'plan'])->where('monto_cubierto', '>', 0)
            ->whereBetween('fecha', [$per['ini']->toDateString(), $per['fin']->toDateString()])->orderBy('fecha')->get();
    }

    /** Estado de resultados simplificado del periodo. */
    public function resultados(array $per): array
    {
        $ing = $this->ingresos($per);
        $planes = (float) $ing->where('concepto', 'plan')->sum('monto');
        $susc = (float) $ing->where('concepto', 'suscripcion')->sum('monto');
        $cob = (float) $this->coberturas($per)->sum('monto_cubierto');
        $total = $planes + $susc;
        $iva = round($total * 0.13, 2);   // IVA incluido en el precio (débito fiscal, referencial)
        $it = round($total * 0.03, 2);    // Impuesto a las Transacciones (referencial)
        $bruta = $total - $cob;

        return compact('planes', 'susc', 'total', 'cob', 'bruta', 'iva', 'it') + ['neta' => $bruta - $iva - $it, 'margen' => $total > 0 ? round($bruta / $total * 100, 1) : 0];
    }

    /** Ganancias desglosadas: por hora (día), por día (mes) o por mes (año). */
    public function ganancias(array $per): array
    {
        $ing = $this->ingresos($per);
        $cob = $this->coberturas($per);
        [$clave, $tramos] = match ($per['tipo']) {
            'dia' => [fn ($f) => $f->format('H').':00', collect(range(0, 23))->map(fn ($h) => sprintf('%02d:00', $h))],
            'mes' => [fn ($f) => $f->format('d/m/Y'), collect(range(1, $per['fin']->day))->map(fn ($d) => $per['ini']->copy()->day($d)->format('d/m/Y'))],
            'anio' => [fn ($f) => ucfirst($f->locale('es')->translatedFormat('F')), collect(range(1, 12))->map(fn ($m) => ucfirst(Carbon::create($per['ini']->year, $m, 1)->locale('es')->translatedFormat('F')))],
        };
        $filas = $tramos->mapWithKeys(fn ($t) => [$t => [$t, 0.0, 0.0, 0.0]])->all();
        foreach ($ing as $p) {
            $k = $clave($p->revisado_at);
            $filas[$k][$p->concepto === 'plan' ? 1 : 2] += (float) $p->monto;
        }
        foreach ($cob as $a) {
            $k = $per['tipo'] === 'dia' ? '00:00' : $clave($a->fecha);
            $filas[$k][3] += (float) $a->monto_cubierto;
        }

        return collect($filas)->map(fn ($f) => [$f[0], round($f[1], 2), round($f[2], 2), round($f[1] + $f[2], 2), round($f[3], 2), round($f[1] + $f[2] - $f[3], 2)])
            ->when($per['tipo'] === 'dia', fn ($c) => $c->filter(fn ($f) => $f[3] > 0 || $f[4] > 0))->values()->all();
    }

    /** Libro diario: cada cobro y cada cobertura con su asiento de partida doble. */
    public function libroDiario(array $per): array
    {
        $movs = [];
        foreach ($this->ingresos($per) as $p) {
            $cuenta = $p->concepto === 'plan' ? 'Ingresos por planes de asistencia' : 'Ingresos por suscripciones de clínicas';
            $caja = $p->metodo === 'tarjeta' ? 'Bancos – cobros con tarjeta' : 'Bancos – cuenta corriente';
            $glosa = 'Cobro '.($p->numero ?: 'N.º '.$p->id).' · '.$p->detalle();
            $movs[] = [$p->revisado_at, $glosa, $caja, (float) $p->monto, null];
            $movs[] = [$p->revisado_at, $glosa, $cuenta, null, (float) $p->monto];
        }
        foreach ($this->coberturas($per) as $a) {
            $glosa = "Cobertura {$a->porcentaje} % · {$a->servicio} de {$a->mascota?->nombre} en {$a->veterinaria?->nombre}";
            $movs[] = [$a->fecha, $glosa, 'Gastos por coberturas de planes', (float) $a->monto_cubierto, null];
            $movs[] = [$a->fecha, $glosa, 'Cuentas por pagar a veterinarias', null, (float) $a->monto_cubierto];
        }
        usort($movs, fn ($x, $y) => $x[0] <=> $y[0]);
        $n = 0;
        $out = [];
        foreach (array_chunk($movs, 2) as $asiento) {
            $n++;
            foreach ($asiento as $m) {
                $out[] = [$n, Carbon::parse($m[0])->startOfDay(), $m[1], $m[2], $m[3], $m[4]];
            }
        }

        return $out;
    }

    /** Un solo libro de Excel con todas las hojas de reportes del periodo. */
    public function libroExcel(array $per): \App\Support\Excel
    {
        $x = (new \App\Support\Excel('PetCare · Administración'))->conIndice();
        $sub = 'Periodo: '.$per['etiqueta'].' ('.$per['ini']->format('d/m/Y').' al '.$per['fin']->format('d/m/Y').') · Generado el '.now()->format('d/m/Y H:i');
        [$ini, $fin] = [$per['ini']->toDateString(), $per['fin']->toDateString()];
        $r = $this->resultados($per);
        $citasPer = Cita::with(['mascota.propietario', 'veterinaria'])->whereBetween('fecha', [$ini, $fin])->orderBy('fecha')->orderBy('hora')->get();
        $atPer = Atencion::with(['mascota.propietario', 'veterinaria', 'plan', 'productos'])->whereBetween('fecha', [$ini, $fin])->orderBy('fecha')->get();

        // 1. Resumen
        $x->hoja('Resumen', 'PetCare · Resumen general', ['Indicador', 'Valor'], [
            ['Propietarios registrados', User::where('rol', User::PROPIETARIO)->count()],
            ['Mascotas registradas', Mascota::count()],
            ['Mascotas con plan vigente', Afiliacion::vigentes()->count()],
            ['Veterinarias activas', Veterinaria::activas()->count()],
            ['Citas en el periodo', $citasPer->count()],
            ['Citas urgentes en el periodo', $citasPer->where('urgente', true)->count()],
            ['Atenciones en el periodo', $atPer->count()],
            ['Ingresos del periodo (Bs)', $r['total']],
            ['Coberturas pagadas (Bs)', $r['cob']],
            ['Utilidad bruta (Bs)', $r['bruta']],
            ['Margen bruto', number_format($r['margen'], 1, ',', '.').' %'],
            ['Pagos pendientes de verificar', Pago::dePetcare()->pendientes()->count()],
        ], ['anchos' => [42, 22]], $sub);

        // 2. Estado de resultados
        $x->hoja('Estado de resultados', 'Estado de resultados simplificado', ['Concepto', 'Monto (Bs)'], [
            ['INGRESOS', null],
            ['   Planes de asistencia (propietarios)', $r['planes']],
            ['   Suscripciones de veterinarias', $r['susc']],
            ['Total ingresos', $r['total']],
            ['EGRESOS', null],
            ['   Coberturas pagadas a veterinarias', $r['cob']],
            ['UTILIDAD BRUTA', $r['bruta']],
            ['   IVA débito fiscal 13 % (referencial)', $r['iva']],
            ['   Impuesto a las Transacciones 3 % (referencial)', $r['it']],
            ['UTILIDAD ESTIMADA DESPUÉS DE IMPUESTOS', $r['neta']],
        ], ['monto' => [1], 'anchos' => [52, 20]], $sub.' · Los impuestos son referenciales; confirmar con el contador.');

        // 3. Ganancias
        $x->hoja('Ganancias', 'Registro de ganancias ('.['dia' => 'por hora', 'mes' => 'por día', 'anio' => 'por mes'][$per['tipo']].')',
            [['dia' => 'Hora', 'mes' => 'Fecha', 'anio' => 'Mes'][$per['tipo']], 'Ingresos planes', 'Ingresos suscripciones', 'Total ingresos', 'Coberturas pagadas', 'Ganancia'],
            $this->ganancias($per), ['monto' => [1, 2, 3, 4, 5], 'total' => true, 'grupo' => match ($per['tipo']) {
                // Mes: los días agrupados por semana · Año: los meses por trimestre · Día: las horas por turno
                'mes' => fn ($f) => 'Semana '.(intdiv((int) substr($f[0], 0, 2) - 1, 7) + 1),
                'anio' => fn ($f) => (intdiv(array_search($f[0], array_map(fn ($m) => ucfirst(Carbon::create(2000, $m, 1)->locale('es')->translatedFormat('F')), range(1, 12))), 3) + 1).'.er trimestre',
                default => fn ($f) => (int) $f[0] < 12 ? 'Mañana' : ((int) $f[0] < 19 ? 'Tarde' : 'Noche'),
            }], $sub.' · Usa [+] / [−] a la izquierda para abrir o cerrar cada grupo.');

        // 4. Libro diario
        $x->hoja('Libro diario', 'Libro diario (partida doble)', ['Asiento', 'Fecha', 'Glosa', 'Cuenta', 'Debe (Bs)', 'Haber (Bs)'],
            $this->libroDiario($per), ['monto' => [4, 5], 'total' => true, 'anchos' => [9, 12, 60, 36, 15, 15],
                'grupo' => fn ($f) => 'Asiento N.º '.$f[0].' · '.$f[1]->format('d/m/Y').' · '.\Illuminate\Support\Str::limit($f[2], 70)], $sub.' · Cada asiento se abre y se cierra con [+] / [−].');

        // 5. Propietarios
        $x->hoja('Propietarios', 'Propietarios registrados', ['N.º', 'Nombre', 'Correo', 'Celular', 'Dirección', 'Correo verificado', 'WhatsApp verificado', 'Mascotas', 'Con plan vigente', 'Estado', 'Registrado'],
            User::where('rol', User::PROPIETARIO)->withCount('mascotas')->with('mascotas.afiliacionActiva')->orderBy('name')->get()->map(fn ($u) => [
                $u->id, $u->name, $u->email, $u->telefonoVisible(), $u->direccion, $u->email_verified_at ? 'Sí' : 'No', $u->telefono_verificado_at ? 'Sí' : 'No',
                $u->mascotas_count, $u->mascotas->filter->afiliacionActiva->count(), $u->activo ? 'Activo' : 'Inactivo', $u->created_at?->startOfDay(),
            ])->all(), [], $sub);

        // 6. Mascotas
        $x->hoja('Mascotas', 'Mascotas registradas', ['Historia', 'Nombre', 'Especie', 'Raza', 'Sexo', 'Nacimiento', 'Peso (kg)', 'Color', 'Esterilizado', 'Alergias', 'Propietario', 'Plan vigente', 'Vence'],
            Mascota::with(['propietario', 'afiliacionActiva.plan'])->orderBy('nombre')->get()->map(fn ($m) => [
                $m->numeroHistoria(), $m->nombre, $m->especie, $m->raza, $m->sexo, $m->fecha_nacimiento?->startOfDay(), $m->peso !== null ? (float) $m->peso : null, $m->color,
                $m->esterilizado ? 'Sí' : 'No', $m->alergias, $m->propietario?->name, $m->afiliacionActiva?->plan?->nombre ?? 'Sin plan', $m->afiliacionActiva?->fecha_fin?->startOfDay(),
            ])->all(), [], $sub);

        // 7. Veterinarias
        $x->hoja('Veterinarias', 'Veterinarias afiliadas', ['Nombre', 'NIT', 'N.º registro', 'Zona', 'Dirección', 'Teléfono', 'Suscripción', 'Vence', 'Estado', 'Calificación', 'Reseñas', 'Servicios'],
            Veterinaria::orderBy('nombre')->get()->map(fn ($v) => [
                $v->nombre, $v->nit, $v->registro, $v->zona, $v->direccion, $v->telefono, $v->suscripcion()['nombre'], $v->suscripcion_hasta?->startOfDay(),
                ucfirst($v->estado).($v->activa ? '' : ' (inactiva)'), (float) $v->valoracion, (int) $v->resenas, implode(', ', $v->servicios ?? []),
            ])->all(), [], $sub);

        // 8. Citas
        $x->hoja('Citas', 'Citas del periodo', ['N.º', 'Fecha', 'Hora', 'Mascota', 'Propietario', 'Veterinaria', 'Servicio', 'Urgente', 'Estado', 'Precio (Bs)', 'Cubre el plan (Bs)', 'Paga el dueño (Bs)', 'Motivo de rechazo'],
            $citasPer->map(fn ($c) => [
                $c->id, $c->fecha->copy()->startOfDay(), $c->hora, $c->mascota?->nombre, $c->mascota?->propietario?->name, $c->veterinaria?->nombre, $c->servicio,
                $c->urgente ? 'Sí' : 'No', ucfirst($c->estado), $c->monto_estimado, $c->cubierto_estimado,
                $c->monto_estimado !== null ? round($c->monto_estimado - (float) $c->cubierto_estimado, 2) : null, $c->motivo_rechazo,
            ])->all(), ['monto' => [9, 10, 11], 'total' => true, 'grupo' => 5], $sub.' · Agrupadas por veterinaria.');

        // 9. Historial clínico
        $x->hoja('Historial clínico', 'Historial clínico (atenciones del periodo)', ['Fecha', 'Historia', 'Mascota', 'Especie', 'Propietario', 'Veterinaria', 'Servicio', 'Diagnóstico', 'Tratamiento',
            'Medicamentos y productos', 'Peso (kg)', 'Temp. (°C)', 'Plan', 'Cobertura', 'Total (Bs)', 'Cubre PetCare (Bs)', 'Paga el dueño (Bs)', 'Firmado por'],
            $atPer->map(fn ($a) => [
                $a->fecha->copy()->startOfDay(), $a->mascota?->numeroHistoria(), $a->mascota?->nombre, $a->mascota?->especie, $a->mascota?->propietario?->name,
                $a->veterinaria?->nombre ?? $a->clinica_externa, $a->servicio, $a->diagnostico, $a->tratamiento,
                $a->productos->map(fn ($p) => $p->cantidad.' × '.$p->nombre)->implode('; '), $a->peso !== null ? (float) $a->peso : null,
                $a->temperatura !== null ? (float) $a->temperatura : null, $a->plan?->nombre ?? 'Sin plan', $a->porcentaje ? $a->porcentaje.' %' : '—',
                (float) $a->total, (float) $a->monto_cubierto, (float) $a->paga_dueno, $a->firmado_por,
            ])->all(), ['monto' => [14, 15, 16], 'total' => true, 'anchos' => [12, 10, 14, 10, 20, 22, 18, 30, 34, 30, 9, 9, 14, 10, 14, 14, 14, 28], 'grupo' => 5], $sub.' · Agrupado por veterinaria.');

        // 10. Vacunas y desparasitaciones
        $x->hoja('Vacunas', 'Vacunas y desparasitaciones', ['Fecha', 'Mascota', 'Propietario', 'Tipo', 'Producto', 'Lote', 'Próxima dosis', 'Veterinaria', 'Veterinario', 'Matrícula', 'Recordatorio enviado'],
            Atencion::with(['mascota.propietario', 'veterinaria'])->whereIn('servicio', ['Vacuna', 'Desparasitación'])->whereBetween('fecha', [$ini, $fin])->orderBy('fecha')->get()->map(fn ($a) => [
                $a->fecha->copy()->startOfDay(), $a->mascota?->nombre, $a->mascota?->propietario?->name, $a->servicio, $a->producto, $a->lote, $a->proxima_dosis?->copy()->startOfDay(),
                $a->veterinaria?->nombre ?? $a->clinica_externa, $a->firmado_por, $a->matricula, $a->recordatorio_at ? $a->recordatorio_at->format('d/m/Y') : 'No',
            ])->all(), [], $sub);

        // 11. Pagos
        $x->hoja('Pagos', 'Pagos del periodo', ['N.º factura', 'Fecha', 'Concepto', 'Detalle', 'Pagado por', 'Facturar a', 'CI/NIT', 'Método', 'Monto (Bs)', 'Estado', 'Revisado'],
            Pago::with(['user', 'plan', 'mascota', 'veterinaria'])->whereBetween('created_at', [$per['ini'], $per['fin']])->orderBy('created_at')->get()->map(fn ($p) => [
                $p->numero ?: '—', $p->created_at->copy()->startOfDay(), ['plan' => 'Plan', 'suscripcion' => 'Suscripción', 'cita' => 'Cita', 'pedido' => 'Tienda'][$p->concepto] ?? $p->concepto, $p->detalle(),
                $p->user?->name, $p->facturar_a, $p->documento, $p->metodoTexto(), (float) $p->monto, ucfirst($p->estado), $p->revisado_at?->format('d/m/Y H:i'),
            ])->all(), ['monto' => [8], 'total' => true], $sub);

        // 11b. Ventas de la tienda (por pedido)
        $x->hoja('Ventas tienda', 'Ventas de la tienda', ['Pedido', 'Fecha', 'Clínica', 'Cliente', 'Productos', 'Entrega', 'Subtotal (Bs)', 'Descuento plan (Bs)', 'Envío (Bs)', 'Total (Bs)', 'Estado'],
            \App\Models\Pedido::with(['veterinaria', 'user', 'items'])->whereBetween('created_at', [$per['ini'], $per['fin']])->orderBy('created_at')->get()->map(fn ($pd) => [
                $pd->numero, $pd->created_at->copy()->startOfDay(), $pd->veterinaria?->nombre, $pd->user?->name, $pd->items->map(fn ($i) => $i->cantidad.' × '.$i->nombre)->join(', '),
                $pd->entrega === 'domicilio' ? 'Domicilio' : 'Recoger', (float) $pd->subtotal, (float) $pd->descuento, (float) $pd->envio, (float) $pd->total, $pd->estadoTexto(),
            ])->all(), ['monto' => [6, 7, 8, 9], 'total' => true, 'anchos' => [16, 12, 22, 20, 40, 11, 13, 14, 11, 12, 16]], $sub.' · El descuento del plan lo cubre PetCare.');

        // 12–14. Reportes de gestión
        $x->hoja('Siniestralidad', 'Siniestralidad por plan', self::CABECERAS['planes'], $this->porPlan($per), ['monto' => [3, 4, 5], 'total' => true], $sub.' · Lo saludable es mantenerla por debajo del 65–70 %.');
        $x->hoja('Liquidación', 'Liquidación a veterinarias', self::CABECERAS['veterinarias'], $this->porVeterinaria($per), ['monto' => [4, 5, 6], 'total' => true], $sub);
        $x->hoja('Servicios', 'Uso de servicios', self::CABECERAS['servicios'], $this->porServicio($per), ['monto' => [4], 'total' => true], $sub);

        return $x;
    }
}
