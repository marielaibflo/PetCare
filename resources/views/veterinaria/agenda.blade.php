@extends('layouts.app')
@section('title', 'Agenda de citas')
@section('heading', 'Agenda de citas')
@section('back', route('veterinaria.inicio'))
@section('content')
<nav class="tabs" style="margin-bottom:14px" aria-label="Filtrar citas">
  @foreach($filtros as $k => $t)<a class="{{ $filtro === $k ? 'on' : '' }}" href="{{ route('veterinaria.agenda', $k === 'todas' ? [] : ['estado' => $k]) }}">{{ $t }}</a>@endforeach
</nav>
<div class="card">@if($citas->isEmpty())<x-empty icon="cal" title="No hay citas en esta vista" text="Cambia el filtro para ver otras citas."/>@else<div class="lst">@foreach($citas as $c)@include('partials.cita-row', ['ctx' => 'vet'])@endforeach</div>@endif</div>
@endsection
