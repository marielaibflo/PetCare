@extends('layouts.auth')
@section('title', 'Verifica tu cuenta')
@section('logo-sub', 'Verificación de cuenta')
@section('art')@include('partials.art.hero-pets')@endsection
@section('art-title', 'Un último paso')
@section('art-text', 'Confirmamos tu correo y tu celular para proteger tu cuenta y enviarte los avisos de tus citas y vacunas.')
@section('content')
<div class="f">
  <p class="sub" style="text-align:center">Hola {{ $user->primerNombre() }}, abre el correo que te enviamos y pulsa <b>«Confirmar mi correo»</b>, o escribe aquí el código de 6 dígitos.@if(in_array('whatsapp', $user->canalesVerificacion(), true)) Para el celular, escribe el código que te llegó por WhatsApp.@endif</p>

  @if($demo)
    <div class="note info"><x-icon name="info"/><span><b>Modo demostración:</b> como el envío real aún no está configurado, los códigos se muestran aquí.
      @isset($demo['email']) Correo: <b>{{ $demo['email'] }}</b>.@endisset
      @isset($demo['whatsapp']) WhatsApp: <b>{{ $demo['whatsapp'] }}</b>.@endisset</span></div>
  @endif

  @foreach(collect(['email' => ['Correo electrónico', 'mail', $user->email_verified_at], 'whatsapp' => ['Celular (WhatsApp)', 'phone', $user->telefono_verificado_at]])->only($user->canalesVerificacion()) as $canal => [$titulo, $icono, $hecho])
    <div class="card pad verif {{ $hecho ? 'ok' : '' }}">
      <div class="verif-h"><x-icon :name="$hecho ? 'check' : $icono"/><div><b>{{ $titulo }}</b><div class="sub">{{ \App\Services\VerificacionService::destinoVisible($user, $canal) }}</div></div>
        @if($hecho)<span class="chip ok">Verificado</span>@endif</div>
      @if(! $hecho && $canal === 'whatsapp' && blank($user->telefono))
        {{-- Cuenta creada con Google: aún no tiene celular --}}
        <form class="f" method="POST" action="{{ route('verificacion.telefono') }}" novalidate>
          @csrf @method('PUT')
          @if($errors->hasAny(['telefono', 'codigo_pais']))<div class="note bad" role="alert"><x-icon name="alert"/><span>{{ $errors->first('telefono') ?: $errors->first('codigo_pais') }}</span></div>@endif
          <x-telefono id="vt" :pais="$user->codigo_pais" :numero="$user->telefono" label="Tu número de WhatsApp"/>
          <button class="btn" type="submit">Enviarme el código</button>
        </form>
      @elseif(! $hecho)
        <form class="f" method="POST" action="{{ route('verificacion.verificar', $canal) }}" novalidate>
          @csrf
          @error('codigo_'.$canal)<div class="note bad" role="alert"><x-icon name="alert"/><span>{{ $message }}</span></div>@enderror
          <input class="in codigo" name="codigo_{{ $canal }}" inputmode="numeric" autocomplete="one-time-code" maxlength="6" placeholder="000000" required pattern="\d{6}" data-msg="Escribe el código de 6 dígitos que te enviamos." data-msg-pattern="El código tiene 6 números." data-label="código" aria-label="Código de {{ $titulo }}" value="{{ old('codigo_'.$canal) }}">
          <button class="btn" type="submit">Verificar</button>
        </form>
        <form method="POST" action="{{ route('verificacion.reenviar', $canal) }}" style="margin-top:8px">@csrf
          <button class="lnk" type="submit" style="background:none;border:0;padding:0">¿No te llegó? Enviar otro código</button></form>
        @if($canal === 'whatsapp')
          <details style="margin-top:10px" @if($errors->hasAny(['telefono', 'codigo_pais'])) open @endif><summary class="lnk" style="cursor:pointer">¿Escribiste mal tu número?</summary>
            <form class="f" method="POST" action="{{ route('verificacion.telefono') }}" style="margin-top:10px" novalidate>
              @csrf @method('PUT')
              @if($errors->hasAny(['telefono', 'codigo_pais']))<div class="note bad" role="alert"><x-icon name="alert"/><span>{{ $errors->first('telefono') ?: $errors->first('codigo_pais') }}</span></div>@endif
              <x-telefono id="vt" :pais="$user->codigo_pais" :numero="$user->telefono" label="Número correcto"/>
              <button class="btn sec" type="submit">Guardar y enviar código</button>
            </form>
          </details>
        @endif
      @endif
    </div>
  @endforeach

  <form method="POST" action="{{ route('logout') }}" style="text-align:center">@csrf<button class="lnk" style="background:none;border:0">Cerrar sesión</button></form>
</div>
@endsection
