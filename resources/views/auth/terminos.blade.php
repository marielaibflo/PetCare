@extends('layouts.base')
@section('title', 'Términos y condiciones')
@section('body')
<div class="cartilla legal">
  <div class="brand" style="margin-bottom:16px"><span class="lg"><svg viewBox="0 0 24 24" aria-hidden="true">{!! \App\Support\Iconos::PATA !!}</svg></span><span>PetCare<small>Términos y condiciones</small></span></div>
  <div class="tabs-n noprint" style="margin-bottom:12px"><a class="btn {{ request('para') === 'veterinaria' ? 'ghost' : '' }}" href="?para=propietario">Propietarios</a> <a class="btn {{ request('para') === 'veterinaria' ? '' : 'ghost' }}" href="?para=veterinaria">Veterinarias</a></div>
  @include('partials.terminos-contenido', ['para' => request('para') === 'veterinaria' ? 'veterinaria' : 'propietario'])
</div>
@endsection
