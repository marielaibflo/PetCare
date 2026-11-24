@extends('layouts.auth')
@section('title', 'Bienvenida')
@section('art')@include('partials.art.hero-pets')@endsection
@section('art-title', 'Seguros y cuidado veterinario en un solo lugar')
@section('art-text', 'Propietarios, veterinarias afiliadas y administración trabajan sobre la misma información, desde la computadora o el celular.')
@section('content')
<h2 style="text-align:center;margin-bottom:14px">Selecciona tu tipo de usuario</h2>
<div class="roles three">
  <a class="role-b" href="{{ route('login', 'propietario') }}"><span class="ti c-p"><x-icon name="user"/></span><div><b>Soy propietario</b><span class="s">Mis mascotas, planes y citas</span></div><span class="arr"><x-icon name="chev"/></span></a>
  <a class="role-b" href="{{ route('login', 'veterinaria') }}"><span class="ti c-ok"><x-icon name="store"/></span><div><b>Soy veterinaria</b><span class="s">Agenda, atenciones e historiales</span></div><span class="arr"><x-icon name="chev"/></span></a>
  <a class="role-b" href="{{ route('login', 'administrador') }}"><span class="ti" style="background:#FFEDE0;color:#B23C0A"><x-icon name="grid"/></span><div><b>Soy administrador</b><span class="s">Gestión de la plataforma</span></div><span class="arr"><x-icon name="chev"/></span></a>
</div>
<p style="text-align:center;margin-top:16px;font-size:14px">¿Tienes una clínica veterinaria? <a class="lnk" href="{{ route('registro.veterinaria') }}">Afíliala a PetCare</a></p>
@php($redes = \App\Models\Ajuste::redes())
@if($redes)
<div class="redes">
  <span>Síguenos</span>
  @isset($redes['tiktok'])<a class="red tiktok" href="{{ $redes['tiktok'] }}" target="_blank" rel="noopener" aria-label="PetCare en TikTok"><x-icon name="nota"/>TikTok</a>@endisset
  @isset($redes['instagram'])<a class="red instagram" href="{{ $redes['instagram'] }}" target="_blank" rel="noopener" aria-label="PetCare en Instagram"><x-icon name="camara"/>Instagram</a>@endisset
</div>
@endif
<div class="hero-mob">@include('partials.art.hero-pets')</div>
@endsection
