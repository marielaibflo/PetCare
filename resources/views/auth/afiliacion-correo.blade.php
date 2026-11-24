@extends('layouts.auth')
@section('title', 'Confirma tu correo')
@section('logo-sub', 'Afiliación de clínicas')
@section('art')@include('partials.art.hero-vet')@endsection
@section('art-title', 'Último paso')
@section('art-text', 'Confirma tu correo para que el equipo de PetCare reciba y revise la solicitud de tu clínica.')
@section('content')
<div class="f">
  @if($user->email_verified_at)
    <div style="text-align:center"><div class="conf-ic"><x-icon name="check"/></div>
      <h2>¡Correo confirmado!</h2>
      <p class="sub">La solicitud de <b>{{ $user->veterinaria?->nombre }}</b> ya llegó al equipo de PetCare. Te avisaremos por correo y WhatsApp cuando sea aprobada.</p></div>
    <a class="btn full" href="{{ route('welcome') }}">Volver al inicio</a>
  @else
    <div style="text-align:center"><div class="conf-ic" style="background:var(--p-l);color:var(--p)"><x-icon name="mail"/></div>
      <h2>Revisa tu correo</h2>
      <p class="sub">Enviamos un mensaje a <b>{{ $user->email }}</b>. Ábrelo y pulsa <b>«Confirmar mi correo»</b>, o escribe aquí el código de 6 dígitos.</p></div>
    @if($demo['email'] ?? null)<div class="note info"><x-icon name="info"/><span><b>Modo demostración:</b> el correo real aún no está configurado. Código: <b>{{ $demo['email'] }}</b>.</span></div>@endif
    <form class="f" method="POST" action="{{ route('registro.veterinaria.codigo') }}" novalidate>
      @csrf
      <x-errors/>
      <div><label for="ac-c">Código del correo</label>
        <input class="in codigo" id="ac-c" name="codigo_email" inputmode="numeric" autocomplete="one-time-code" maxlength="6" placeholder="000000" required pattern="\d{6}" data-msg="Escribe el código de 6 dígitos que te enviamos." data-msg-pattern="El código tiene 6 números."></div>
      <button class="btn full" type="submit">Confirmar correo</button>
    </form>
    <form method="POST" action="{{ route('registro.veterinaria.reenviar') }}" style="text-align:center">@csrf
      <button class="lnk" type="submit" style="background:none;border:0">¿No te llegó? Revisa en «Spam» o envíame otro correo</button></form>
    <p class="hint" style="text-align:center">Si no confirmas el correo en 48 horas, la solicitud se borra y puedes volver a registrarte.</p>
  @endif
</div>
@endsection
