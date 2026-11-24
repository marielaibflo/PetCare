@extends('layouts.app')
@php($gr = ['linear-gradient(135deg,#9A88F2,#5B3FD6)', 'linear-gradient(135deg,#7DD3B5,#2FA987)', 'linear-gradient(135deg,#F6C378,#EE9B3C)', 'linear-gradient(135deg,#7FB6F2,#4F8FE0)', 'linear-gradient(135deg,#F59EA8,#E05D6F)'])
@section('title', 'Veterinarias afiliadas')
@section('heading', 'Veterinarias afiliadas')
@section('sub', 'Clínicas que atienden con los planes de PetCare.')
@section('back', route('propietario.inicio'))
@section('content')
<form class="flt" method="GET" action="{{ route('propietario.veterinarias.index') }}">
  <label class="search"><x-icon name="search"/><span class="sr">Buscar</span><input name="q" value="{{ $q }}" placeholder="Buscar por nombre, zona o servicio..."></label>
  <select class="in" name="zona" aria-label="Zona" data-autosubmit><option value="">Todas las zonas</option>@foreach($zonas as $z)<option @selected($zona === $z)>{{ $z }}</option>@endforeach</select>
  <select class="in" name="orden" aria-label="Ordenar" data-autosubmit>@foreach($ordenes as $k => $t)<option value="{{ $k }}" @selected($orden === $k)>{{ $t }}</option>@endforeach</select>
  <button class="btn sec" type="submit">Buscar</button>
</form>
<div class="vets">
  @forelse($veterinarias as $v)
    <a class="card vet-c" href="{{ route('veterinarias.show', $v) }}">
      @if($v->logo)<div class="vimg vlogo"><x-sello :v="$v" :s="64"/></div>@else<div class="vimg" style="background:{{ $gr[$v->color % 5] }}"><x-icon name="store"/></div>@endif
      <div><b>{{ $v->nombre }}</b>
        <div class="vbadges">
          @if($v->id === $mejor)<span class="chip warn">⭐ Mejor calificada</span>@endif
          @if($v->id === $masPedida)<span class="chip bad">🔥 Más solicitada</span>@endif
          <span class="chip p dest">{{ $v->plan_suscripcion === 'plus' ? 'Clínica Plus' : 'Acepta planes PetCare' }}</span>
        </div>
        <div class="rate">@if($v->resenas)<span class="estrellas" style="--v:{{ $v->valoracion }}" aria-hidden="true"></span>{{ number_format($v->valoracion, 1) }} <span>({{ $v->resenas }})</span>@else<x-icon name="star"/>Nueva @endif
          @if($v->solicitudes)<span class="sol">· {{ $v->solicitudes }} {{ $v->solicitudes === 1 ? 'cita' : 'citas' }} en 90 días</span>@endif</div>
        <div class="loc"><x-icon name="pin"/>{{ $v->direccion }}, {{ $v->zona }}</div>
        @if($v->servicios_especiales)<div class="vesp">{{ implode(' · ', array_slice($v->servicios_especiales, 0, 3)) }}{{ count($v->servicios_especiales) > 3 ? ' · +'.(count($v->servicios_especiales) - 3) : '' }}</div>@endif
      </div>
    </a>
  @empty
    <div class="card" style="grid-column:1/-1"><x-empty icon="search" title="Sin resultados" text="Prueba con otro nombre, zona o servicio."/></div>
  @endforelse
</div>
@endsection
