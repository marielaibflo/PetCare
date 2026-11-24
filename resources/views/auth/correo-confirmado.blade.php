@extends('layouts.auth')
@section('title', $estado === 'invalido' ? 'Enlace no válido' : 'Correo confirmado')
@section('logo-sub', 'Confirmación de correo')
@section('art')@include('partials.art.hero-pets')@endsection
@section('art-title', $estado === 'invalido' ? 'Este enlace ya no sirve' : '¡Listo!')
@section('art-text', 'PetCare confirma el correo de cada cuenta para proteger tus datos y enviarte los avisos importantes.')
@section('content')
@php
  $vetPendiente = $user->esRol(\App\Models\User::VETERINARIA) && $user->veterinaria?->estado === 'pendiente';
@endphp
<div class="f" style="text-align:center">
  @if($estado === 'invalido')
    <div class="conf-ic bad"><x-icon name="alert"/></div>
    <h2>El enlace no es válido</h2>
    <p class="sub">El correo de la cuenta cambió o el enlace es de un mensaje anterior. Pide un enlace nuevo desde PetCare.</p>
    <a class="btn full" href="{{ route('login', $user->esRol(\App\Models\User::VETERINARIA) ? 'veterinaria' : 'propietario') }}">Ir a iniciar sesión</a>
  @else
    <div class="conf-ic"><x-icon name="check"/></div>
    <h2>{{ $estado === 'ya' ? 'Tu correo ya estaba confirmado' : '¡Correo confirmado!' }}</h2>
    <p class="sub"><b>{{ $user->email }}</b> quedó confirmado.</p>
    @if($vetPendiente)
      <div class="note ok" style="text-align:left"><x-icon name="check"/><span>La solicitud de <b>{{ $user->veterinaria->nombre }}</b> ya llegó al equipo de PetCare. Te avisaremos por correo y WhatsApp cuando sea aprobada.</span></div>
      <a class="btn full" href="{{ route('welcome') }}">Volver al inicio</a>
    @elseif(! $user->verificado())
      <p class="sub">Solo te falta confirmar tu celular con el código que te llegó por WhatsApp.</p>
      <a class="btn full" href="{{ ($sesion ?? false) ? route('verificacion.show') : route('login', 'propietario') }}">Continuar</a>
    @else
      <a class="btn full" href="{{ ($sesion ?? false) ? $user->rutaInicio() : route('login', ['propietario' => 'propietario', 'veterinaria' => 'veterinaria', 'administrador' => 'administrador'][$user->rol] ?? 'propietario') }}">{{ ($sesion ?? false) ? 'Entrar a PetCare' : 'Iniciar sesión' }}</a>
    @endif
  @endif
</div>
@endsection
