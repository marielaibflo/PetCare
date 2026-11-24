@extends('layouts.app')
@php
  $fmt = function ($tipo, $filas) use ($montos) {
      return array_map(fn ($f) => array_map(fn ($c, $i) => in_array($i, $montos[$tipo], true) ? \App\Support\Formato::bs($c) : $c, $f, array_keys($f)), $filas);
  };
  $tabla = fn ($cab, $filas) => view('admin.tabla-reporte', compact('cab', 'filas'));
  $q = ['periodo' => $per['tipo'], 'fecha' => $per['valor']];
  $bs = fn ($n) => \App\Support\Formato::bs($n);
  $planes = collect($tablas['planes']);
  $col = ['dia' => 'Hora', 'mes' => 'Fecha', 'anio' => 'Mes'][$per['tipo']];
@endphp
@section('title', 'Reportes')
@section('heading', 'Reportes')
@section('sub', $per['etiqueta'].' · del '.$per['ini']->format('d/m/Y').' al '.$per['fin']->format('d/m/Y'))
@section('acts')
  <a class="btn" href="{{ route('admin.reportes.excel', $q) }}"><x-icon name="down"/>Descargar Excel</a>
  <button class="btn ghost" type="button" data-print><x-icon name="print"/>Imprimir</button>
@endsection
@section('content')
<form method="GET" class="card pad periodo noprint" id="fper">
  <div class="seg" role="radiogroup" aria-label="Tipo de reporte">
    @foreach(\App\Services\ReporteService::PERIODOS as $k => $t)
      <label class="{{ $per['tipo'] === $k ? 'on' : '' }}"><input type="radio" name="periodo" value="{{ $k }}" @checked($per['tipo'] === $k)><span>{{ $t === 'Día' ? 'Diario' : ($t === 'Mes' ? 'Mensual' : 'Anual') }}</span></label>
    @endforeach
  </div>
  <div class="per-in">
    <input class="in" type="date" name="fecha" value="{{ $per['tipo'] === 'dia' ? $per['valor'] : '' }}" max="{{ today()->toDateString() }}" data-per="dia" aria-label="Día" @disabled($per['tipo'] !== 'dia') @if($per['tipo'] !== 'dia') hidden @endif>
    <input class="in" type="month" name="fecha" value="{{ $per['tipo'] === 'mes' ? $per['valor'] : '' }}" max="{{ today()->format('Y-m') }}" data-per="mes" aria-label="Mes" @disabled($per['tipo'] !== 'mes') @if($per['tipo'] !== 'mes') hidden @endif>
    <select class="in" name="fecha" data-per="anio" aria-label="Año" @disabled($per['tipo'] !== 'anio') @if($per['tipo'] !== 'anio') hidden @endif>
      @for($y = today()->year; $y >= 2024; $y--)<option value="{{ $y }}" @selected($per['tipo'] === 'anio' && $per['valor'] == $y)>{{ $y }}</option>@endfor
    </select>
    <button class="btn sec" type="submit">Ver reporte</button>
  </div>
  <p class="hint" style="margin:0">El Excel trae <b>14 hojas</b>: resumen, estado de resultados, ganancias, libro diario, propietarios, mascotas, veterinarias, citas, historial clínico, vacunas, pagos, siniestralidad, liquidación y servicios.</p>
</form>

<div class="stats" style="margin:14px 0">
  @foreach([[$bs($res['total']), 'Ingresos', 'doc', 'c-ok'], [$bs($res['cob']), 'Coberturas pagadas', 'shield', 'c-w'],
    [$bs($res['bruta']), 'Utilidad bruta', 'chart', $res['bruta'] >= 0 ? 'c-p' : 'c-bad'], [$res['margen'].' %', 'Margen bruto', 'store', 'c-i']] as [$n, $t, $ic, $cl])
    <div class="card stat"><span class="ti {{ $cl }}"><x-icon :name="$ic"/></span><div><b>{{ $n }}</b><span>{{ $t }}</span></div></div>
  @endforeach
</div>

<div class="grid g2-1">
  <section class="card pad"><div class="sec"><h2>Registro de ganancias</h2></div>
    <p class="sub" style="margin-bottom:10px">Ingresos por planes y suscripciones menos lo pagado en coberturas, {{ ['dia' => 'por hora', 'mes' => 'día por día', 'anio' => 'mes por mes'][$per['tipo']] }}.</p>
    @php($g = collect($ganancias)->filter(fn ($f) => $f[3] || $f[4]))
    @if($g->isEmpty())<x-empty icon="chart" title="Sin movimientos" text="No hubo cobros ni coberturas en este periodo."/>
    @else
    <div class="tw tw-max"><table><thead><tr><th>{{ $col }}</th><th>Planes</th><th>Suscripciones</th><th>Coberturas</th><th>Ganancia</th></tr></thead><tbody>
      @foreach($g as $f)<tr><td>{{ $f[0] }}</td><td data-l="Planes">{{ $bs($f[1]) }}</td><td data-l="Suscripciones">{{ $bs($f[2]) }}</td><td data-l="Coberturas">{{ $bs($f[4]) }}</td><td data-l="Ganancia"><b class="{{ $f[5] < 0 ? 't-bad' : 't-ok' }}">{{ $bs($f[5]) }}</b></td></tr>@endforeach
    </tbody><tfoot><tr><th>Total</th><th>{{ $bs($g->sum(1)) }}</th><th>{{ $bs($g->sum(2)) }}</th><th>{{ $bs($g->sum(4)) }}</th><th>{{ $bs($g->sum(5)) }}</th></tr></tfoot></table></div>
    @endif
  </section>
  <section class="card pad"><div class="sec"><h2>Estado de resultados</h2></div>
    <table class="eerr">
      <tr class="t"><td>Ingresos</td><td></td></tr>
      <tr><td>Planes de asistencia</td><td>{{ $bs($res['planes']) }}</td></tr>
      <tr><td>Suscripciones de clínicas</td><td>{{ $bs($res['susc']) }}</td></tr>
      <tr class="s"><td>Total ingresos</td><td>{{ $bs($res['total']) }}</td></tr>
      <tr class="t"><td>Egresos</td><td></td></tr>
      <tr><td>Coberturas pagadas a clínicas</td><td>({{ $bs($res['cob']) }})</td></tr>
      <tr class="s"><td>Utilidad bruta</td><td>{{ $bs($res['bruta']) }}</td></tr>
      <tr><td>IVA 13 % (referencial)</td><td>({{ $bs($res['iva']) }})</td></tr>
      <tr><td>IT 3 % (referencial)</td><td>({{ $bs($res['it']) }})</td></tr>
      <tr class="f"><td>Utilidad estimada</td><td>{{ $bs($res['neta']) }}</td></tr>
    </table>
    <p class="hint">Impuestos referenciales; el libro diario completo está en el Excel.</p>
  </section>
</div>

<div class="grid" style="margin-top:14px">
  <section class="card pad"><div class="sec"><h2>Siniestralidad por plan</h2></div>
    <p class="sub" style="margin-bottom:10px">Qué parte de lo cobrado a los dueños se pagó en coberturas. Lo saludable es mantenerla por debajo del 65–70 %.</p>
    {!! $tabla($cab['planes'], $fmt('planes', $tablas['planes'])) !!}</section>
  <section class="card pad"><div class="sec"><h2>Liquidación a veterinarias</h2></div>
    <p class="sub" style="margin-bottom:10px">PetCare paga a cada clínica la parte cubierta por los planes; el dueño paga el resto directamente en la clínica.</p>
    {!! $tabla($cab['veterinarias'], $fmt('veterinarias', $tablas['veterinarias'])) !!}</section>
  <section class="card pad"><div class="sec"><h2>Uso de servicios</h2></div>{!! $tabla($cab['servicios'], $fmt('servicios', $tablas['servicios'])) !!}</section>
</div>
@endsection
