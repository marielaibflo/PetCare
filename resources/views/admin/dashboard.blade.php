@extends('layouts.app')
@php
  $tot = max(1, $k['especies']->sum());
  $cols = ['#5B3FD6', '#A99AF2', '#F2A65A'];
  $off = 0;
  $mx = max(1, $k['servicios']->max());
@endphp
@section('title', 'Dashboard')
@section('heading', 'Dashboard')
@section('sub', 'Resumen general de la plataforma')
@section('acts')<x-exportar/>@endsection
@section('content')
<div class="stats">
  @foreach([[$k['propietarios'], 'Propietarios', 'user', 'c-p'], [$k['mascotas'], 'Mascotas', 'pets', 'c-i'], [$k['afiliaciones'], 'Planes activos', 'doc', 'c-ok'], [$k['veterinarias'], 'Veterinarias activas', 'store', 'c-w']] as [$n, $t, $ic, $cl])
    <div class="card stat"><span class="ti {{ $cl }}"><x-icon :name="$ic"/></span><div><b>{{ $n }}</b><span>{{ $t }}</span></div></div>
  @endforeach
</div>
@if($k['pagos_pendientes'] || $k['vets_pendientes'])
<div class="g2" style="margin-top:14px">
  @if($k['pagos_pendientes'])<a class="card row" href="{{ route('admin.pagos.index') }}"><span class="ti c-w"><x-icon name="doc"/></span><div class="tx"><b>{{ $k['pagos_pendientes'] }} {{ $k['pagos_pendientes'] === 1 ? 'pago' : 'pagos' }} por verificar</b><span>Revisa los comprobantes y activa los planes.</span></div><x-icon name="chev"/></a>@endif
  @if($k['vets_pendientes'])<a class="card row" href="{{ route('admin.veterinarias.index') }}"><span class="ti c-p"><x-icon name="store"/></span><div class="tx"><b>{{ $k['vets_pendientes'] }} {{ $k['vets_pendientes'] === 1 ? 'clínica quiere' : 'clínicas quieren' }} afiliarse</b><span>Revisa sus documentos y aprueba la solicitud.</span></div><x-icon name="chev"/></a>@endif
</div>
@endif
<div class="g3" style="margin-top:14px">
  <div class="card pad"><h2>Cobrado este mes</h2><div class="money" style="margin-top:8px">{{ \App\Support\Formato::bs($k['ingreso']) }}</div><div class="sub">pagos aprobados · {{ $k['afiliaciones'] }} planes activos</div></div>
  <div class="card pad"><h2>Coberturas del mes</h2><div class="money" style="margin-top:8px">{{ \App\Support\Formato::bs($k['coberturas_mes']) }}</div><div class="sub">a pagar a las clínicas · <a class="lnk" href="{{ route('admin.reportes') }}">ver liquidación</a></div></div>
  <div class="card pad"><h2>Citas de hoy</h2><div class="money" style="margin-top:8px">{{ $k['citas_hoy'] }}</div><div class="sub">{{ $k['atenciones_mes'] }} atenciones en el mes</div></div>
</div>
<div class="g2" style="margin-top:14px">
  <div class="card pad"><h2 style="margin-bottom:12px">Mascotas por especie</h2>
    <div class="donut"><svg viewBox="0 0 42 42" role="img" aria-label="Distribución de mascotas por especie"><circle cx="21" cy="21" r="15.915" fill="none" stroke="#EEEBFA" stroke-width="7"/>
      @foreach($k['especies'] as $esp => $n)@php($pc = $n / $tot * 100)<circle cx="21" cy="21" r="15.915" fill="none" stroke="{{ $cols[$loop->index % 3] }}" stroke-width="7" stroke-dasharray="{{ $pc }} {{ 100 - $pc }}" stroke-dashoffset="{{ -$off }}"/>@php($off += $pc)@endforeach
    </svg>
    <div>@foreach($k['especies'] as $esp => $n)<div class="lg-i"><i style="background:{{ $cols[$loop->index % 3] }}"></i>{{ $esp }}: {{ $n }} ({{ round($n / $tot * 100) }}%)</div>@endforeach</div></div>
  </div>
  <div class="card pad"><h2 style="margin-bottom:12px">Servicios más utilizados</h2>
    <div class="bars">@foreach($k['servicios'] as $s => $n)<div class="bar"><span>{{ $s }}</span><div><i style="width:{{ $n / $mx * 100 }}%"></i></div><b>{{ $n }}</b></div>@endforeach</div>
  </div>
</div>
<section style="margin-top:14px"><div class="sec"><h2>Últimas atenciones registradas</h2><a class="lnk" style="font-size:14px" href="{{ route('admin.servicios') }}">Ver todas</a></div>
  <div class="card lst">@forelse($k['ultimas'] as $a)<div class="row"><x-svc :servicio="$a->servicio"/><div class="tx"><b>{{ $a->servicio }} · {{ $a->mascota->nombre }}</b><span>{{ $a->fecha->format('d/m/Y') }} · {{ $a->lugar() }}{{ $a->total > 0 ? ' · '.\App\Support\Formato::bs($a->total) : '' }}</span></div>@if($a->monto_cubierto > 0)<span class="chip ok">Cubierto {{ \App\Support\Formato::bs($a->monto_cubierto) }}</span>@else<span class="chip mut">Particular</span>@endif</div>@empty<x-empty icon="doc" title="Sin atenciones" text="Aún no hay atenciones registradas."/>@endforelse</div>
</section>
@endsection
