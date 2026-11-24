@extends('layouts.auth')
@php
  $art = ['propietario' => ['hero-pets', 'Cuida a tus mascotas', 'Consulta sus datos, su plan de asistencia, sus vacunas y agenda citas con veterinarias afiliadas.'],
          'veterinaria' => ['hero-vet', 'Gestiona la salud de tus pacientes', 'Registra atenciones, revisa historiales y organiza tu agenda desde cualquier dispositivo.'],
          'administrador' => ['hero-admin', 'Panel de administración', 'Supervisa propietarios, mascotas, planes y veterinarias afiliadas en un solo lugar.']][$rol];
@endphp
@section('title', 'Iniciar sesión')
@section('logo-sub', 'Iniciar sesión')
@section('art')@include('partials.art.'.$art[0])@endsection
@section('art-title', $art[1])
@section('art-text', $art[2])
@section('content')
<div class="rsel" role="tablist">
  @foreach(\App\Support\Catalogo::ROLES as $r => $n)<a class="{{ $r === $rol ? 'on' : '' }}" href="{{ route('login', $r) }}" role="tab" aria-selected="{{ $r === $rol ? 'true' : 'false' }}">{{ $n }}</a>@endforeach
</div>
<form class="f" method="POST" action="{{ url('/login/'.$rol) }}" novalidate>
  @csrf
  <x-errors/>
  <div><label for="em">Correo electrónico</label><input class="in" id="em" name="email" type="email" autocomplete="username" value="{{ old('email') }}" placeholder="tucorreo@ejemplo.com" required data-msg="Escribe tu correo electrónico.">@include('partials.correo-existente')</div>
  <div><label for="pw">Contraseña</label><input class="in" id="pw" name="password" type="password" autocomplete="current-password" placeholder="••••••" required data-msg="Escribe tu contraseña."></div>
  <label class="cb" style="border:0;padding:0"><input type="checkbox" name="recordar" value="1"> Mantener la sesión iniciada</label>
  <button class="btn full" type="submit">Iniciar sesión</button>
  @if($rol === 'propietario')
    <div style="text-align:center;font-size:14px">¿No tienes cuenta? <a class="lnk" href="{{ route('registro') }}">Regístrate</a></div>
  @else
    @if($rol === 'veterinaria')
      <div style="text-align:center;font-size:14px">¿Tu clínica aún no está en PetCare? <a class="lnk" href="{{ route('registro.veterinaria') }}">Solicita la afiliación</a></div>
    @else
      <div class="hint" style="text-align:center">Las cuentas de administración se asignan internamente.</div>
    @endif
  @endif
</form>
@if($demo)
<div class="demo">Cuenta de demostración: <b>{{ $demo[0] }}</b> · <b>{{ $demo[1] }}</b>
  <button type="button" class="btn sm sec" style="margin-left:6px" data-demo="{{ $demo[0] }}|{{ $demo[1] }}">Usar</button></div>
@endif
<div style="text-align:center;margin-top:14px"><a class="lnk" href="{{ route('welcome') }}">← Volver a la selección de perfil</a></div>
@endsection
