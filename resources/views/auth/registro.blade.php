@extends('layouts.auth')
@section('title', 'Registro')
@section('logo-sub', 'Registro de propietario')
@section('art')@include('partials.art.hero-pets')@endsection
@section('art-title', 'Crea tu cuenta de propietario')
@section('art-text', 'Registra a tus mascotas, contrata un plan de asistencia y agenda citas con veterinarias afiliadas.')
@section('content')
<form class="f" method="POST" action="{{ route('registro') }}" novalidate>
  @csrf
  <x-errors/>
  <div><label for="rn">Nombre completo</label><input class="in" id="rn" name="name" autocomplete="name" value="{{ old('name') }}" required minlength="3" maxlength="100" pattern="[\p{L}\s'.-]+" data-msg="Escribe tu nombre completo." data-msg-pattern="El nombre solo puede tener letras y espacios." data-msg-min="Escribe tu nombre y apellido (mínimo 3 letras)."></div>
  <div><label for="re">Correo electrónico</label><input class="in" id="re" name="email" type="email" autocomplete="email" placeholder="tucorreo@gmail.com" value="{{ old('email') }}" required maxlength="120" data-msg="Escribe tu correo electrónico: ahí llegará el código de verificación.">
    @include('partials.correo-existente')
    <div class="hint">Te enviaremos un correo con el botón «Confirmar mi correo».</div></div>
  <x-telefono id="rt" :pais="old('codigo_pais')" :numero="old('telefono')"/>
  <div><label for="rp">Contraseña</label><input class="in" id="rp" name="password" type="password" autocomplete="new-password" required data-fuerza data-segura data-msg="Crea una contraseña.">
    <div class="fuerza" aria-hidden="true"><span></span></div>
    <ul class="reqs" id="rp-reqs">
      <li data-r="largo">Mínimo 8 caracteres</li>
      <li data-r="may">Una mayúscula</li>
      <li data-r="min">Una minúscula</li>
      <li data-r="num">Un número</li>
      <li data-r="sim">Un símbolo (! @ # $ % …)</li>
    </ul>
  </div>
  <div><label for="rp2">Repetir contraseña</label><input class="in" id="rp2" name="password_confirmation" type="password" autocomplete="new-password" required data-igual="password" data-msg="Repite la contraseña."></div>
  <button class="btn full" type="submit">Crear cuenta</button>
  <div style="text-align:center;font-size:14px">¿Ya tienes cuenta? <a class="lnk" href="{{ route('login', 'propietario') }}">Inicia sesión</a></div>
</form>
@endsection
