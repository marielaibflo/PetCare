@extends('layouts.app')
@section('title', 'Inicio')
@section('heading', $vet->nombre)
@section('sub', 'Bienvenida, '.auth()->user()->name)
@section('acts')<a class="btn" href="{{ route('veterinaria.atenciones.create') }}"><x-icon name="plus"/>Registrar atención</a>@endsection
@section('content')
<div class="grid" style="gap:20px">
  @if(! $vet->suscripcionVigente())
    <div class="note bad"><x-icon name="alert"/><span>Tu suscripción a PetCare venció: no apareces en el buscador ni recibes citas nuevas. <a class="lnk" href="{{ route('veterinaria.suscripcion') }}">Renovar ahora</a></span></div>
  @elseif($vet->suscripcion_hasta && $vet->suscripcion_hasta->lte(today()->addDays(7)))
    <div class="note warn"><x-icon name="clock"/><span>Tu suscripción vence el {{ $vet->suscripcion_hasta->format('d/m/Y') }}. <a class="lnk" href="{{ route('veterinaria.suscripcion') }}">Renovar</a></span></div>
  @endif
  @if(empty(array_filter($vet->precios ?? [])))
    <div class="note info"><x-icon name="info"/><span>Registra los precios de tus servicios en el <a class="lnk" href="{{ route('veterinaria.clinica') }}">tarifario</a> para calcular la cobertura de los planes.</span></div>
  @endif
  <div class="stats">
    @foreach([[$stats['citas_hoy'], 'Citas de hoy', 'cal', 'c-i'], [$stats['atenciones_hoy'], 'Atenciones hoy', 'clip', 'c-ok'], [$stats['pacientes'], 'Pacientes', 'pets', 'c-p'], [$stats['atenciones_mes'], 'Atenciones del mes', 'heart', 'c-w']] as [$n, $t, $ic, $cl])
      <div class="card stat"><span class="ti {{ $cl }}"><x-icon :name="$ic"/></span><div><b>{{ $n }}</b><span>{{ $t }}</span></div></div>
    @endforeach
  </div>
  <div class="cols">
    <section><div class="sec"><h2>Citas de hoy · {{ today()->format('d/m/Y') }}</h2><a class="lnk" style="font-size:14px" href="{{ route('veterinaria.agenda') }}">Agenda completa</a></div>
      <div class="card">@if($hoy->isEmpty())<x-empty icon="cal" title="No hay citas para hoy" text="Puedes registrar una atención sin cita."><a class="btn sec" href="{{ route('veterinaria.atenciones.create') }}">Registrar atención</a></x-empty>@else<div class="lst">@foreach($hoy as $c)@include('partials.cita-row', ['ctx' => 'vet'])@endforeach</div>@endif</div>
    </section>
    <section><h2 style="margin-bottom:10px">Solicitudes por confirmar</h2>
      <div class="card">@if($pendientes->isEmpty())<x-empty icon="check" title="Todo al día" text="No hay solicitudes pendientes."/>@else<div class="lst">@foreach($pendientes as $c)@include('partials.cita-row', ['ctx' => 'vet'])@endforeach</div>@endif</div>
    </section>
  </div>
</div>
@endsection
