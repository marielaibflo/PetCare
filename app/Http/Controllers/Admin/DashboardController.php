<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ReporteService;

class DashboardController extends Controller
{
    public function __invoke(ReporteService $reportes)
    {
        return view('admin.dashboard', ['k' => $reportes->indicadores()]);
    }
}
