@extends('layouts.app')
@section('title', 'Inicio')
@section('content')
<div class="grid" style="gap:20px">
  <div class="hello"><x-avatar :u="$user" clase="owner" style="width:50px;height:50px;font-size:18px"/><div><h1>Hola, {{ $user->primerNombre() }}</h1><div class="sub">Bienvenida a PetCare</div></div></div>
  <div class="card qa">
    <a href="#" data-open="dlg-cita"><span class="ti c-i"><x-icon name="cal"/></span>Agendar cita</a>
    <a href="#" data-open="dlg-mascota-nueva"><span class="ti c-p"><x-icon name="plus"/></span>Agregar mascota</a>
    <a href="{{ route('propietario.planes.index') }}"><span class="ti c-ok"><x-icon name="shield"/></span>Ver planes</a>
  </div>
  <div class="cols">
    <div class="grid" style="gap:20px">
      <section><div class="sec"><h2>Mis mascotas</h2><a class="lnk" style="font-size:14px" href="{{ route('propietario.mascotas.index') }}">Ver todas</a></div>
        @if($mascotas->isEmpty())
          <div class="card"><x-empty icon="pets" title="Aún no registraste mascotas" text="Agrega a tu mascota para contratar un plan y agendar citas."><button class="btn" data-open="dlg-mascota-nueva"><x-icon name="plus"/>Agregar mascota</button></x-empty></div>
        @else
          <div class="pets">@foreach($mascotas->take(3) as $m)<a class="card pet-c" href="{{ route('propietario.mascotas.show', $m) }}"><x-pet :m="$m"/><b>{{ $m->nombre }}</b><span>{{ $m->resumen() }}</span><x-plan-chip :m="$m"/></a>@endforeach</div>
        @endif
      </section>
      <section><h2 style="margin-bottom:10px">Planes activos</h2>
        @if($afiliaciones->isEmpty())
          <a class="card row" href="{{ route('propietario.planes.index') }}"><span class="ti c-p"><x-icon name="shield"/></span><div class="tx"><b>Sin planes activos</b><span>Compara las coberturas y protege a tu mascota.</span></div><x-icon name="chev"/></a>
        @else
          <div class="card lst">@foreach($afiliaciones as $m)@php($a = $m->afiliacionActiva)<a class="row" href="{{ route('propietario.mascotas.show', [$m, 'plan']) }}"><span class="ti c-ok"><x-icon name="crown"/></span><div class="tx"><b>{{ $a->plan->nombre }}</b><span>{{ $m->nombre }} · Vigente hasta: {{ $a->fecha_fin->format('d/m/Y') }}</span></div>@if($a->porVencer())<span class="chip warn">Por vencer</span>@endif<x-icon name="chev"/></a>@endforeach</div>
        @endif
      </section>
    </div>
    <section><div class="sec"><h2>Próximas citas y recordatorios</h2><a class="lnk" style="font-size:14px" href="{{ route('propietario.citas.index') }}">Ver todas</a></div>
      @if($recordatorios->isNotEmpty())<div class="card lst" style="margin-bottom:14px">@foreach($recordatorios as $x)@include('partials.dosis-row')@endforeach</div>@endif
      <div class="card">
        @if($citas->isEmpty())<x-empty icon="cal" title="No tienes citas próximas" text="Agenda una cita con una veterinaria afiliada."><button class="btn sec" data-open="dlg-cita">Agendar cita</button></x-empty>
        @else<div class="lst">@foreach($citas as $c)@include('partials.cita-row', ['ctx' => 'owner'])@endforeach</div>@endif
      </div>
    </section>
  </div>
</div>
@endsection
