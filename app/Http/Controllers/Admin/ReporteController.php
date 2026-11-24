<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ReporteService;
use Illuminate\Http\Request;

class ReporteController extends Controller
{
    public function __construct(private ReporteService $reportes) {}

    public function index(Request $request)
    {
        $per = $this->periodo($request);

        return view('admin.reportes', [
            'per' => $per,
            'tablas' => collect(ReporteService::CABECERAS)->keys()->mapWithKeys(fn ($t) => [$t => $this->reportes->tabla($t, $per)]),
            'cab' => ReporteService::CABECERAS,
            'montos' => ReporteService::MONTOS,
            'res' => $this->reportes->resultados($per),
            'ganancias' => $this->reportes->ganancias($per),
        ]);
    }

    /** Un solo archivo de Excel con todas las hojas (resumen, contabilidad, listas, historial clínico y reportes). */
    public function excel(Request $request)
    {
        $per = $this->periodo($request);

        return $this->reportes->libroExcel($per)->descargar('PetCare_reportes_'.$per['valor'].'.xlsx');
    }

    private function periodo(Request $request): array
    {
        // ?periodo=dia|mes|anio&fecha=... (también acepta el antiguo ?mes=2026-11)
        return $request->filled('mes') && ! $request->filled('periodo')
            ? ReporteService::mes($request->query('mes'))
            : ReporteService::periodo($request->query('periodo'), $request->query('fecha'));
    }
}
