@extends('layouts.auth')
@section('title', 'Afiliar veterinaria')
@section('logo-sub', 'Afiliación de clínicas veterinarias')
@section('art')@include('partials.art.hero-vet')@endsection
@section('art-title', 'Recibe pacientes con PetCare')
@section('art-text', 'Agenda en línea con avisos por WhatsApp, tarifario, catálogo de medicamentos, cartilla digital y pagos de cobertura de los planes. Todo desde el sistema.')
@section('content')
<form class="f" id="fafil" method="POST" action="{{ route('registro.veterinaria') }}" enctype="multipart/form-data" novalidate data-precios='@json(collect(\App\Support\Catalogo::SUSCRIPCIONES)->map(fn ($s) => $s['precio']))'>
  @csrf
  <x-errors/>
  <h2>1. Datos de la clínica</h2>
  <div><label for="rv-n">Nombre de la veterinaria</label><input class="in" id="rv-n" name="nombre" value="{{ old('nombre') }}" required minlength="3" maxlength="100" data-msg="Escribe el nombre de la veterinaria."></div>
  <div class="fr"><div><label for="rv-nit">NIT</label><input class="in" id="rv-nit" name="nit" inputmode="numeric" value="{{ old('nit') }}" required pattern="\d{6,15}" maxlength="15" data-msg="Escribe el NIT de la clínica." data-msg-pattern="El NIT debe tener solo números (6 a 15 dígitos)."></div><div><label for="rv-reg">N.º de registro / licencia de la clínica</label><input class="in" id="rv-reg" name="registro" value="{{ old('registro') }}" required maxlength="30" pattern="[A-Za-z0-9\/\-. ]+" placeholder="Ej.: SENASAG-LP-0321" data-msg="Escribe el N.º de registro o licencia de funcionamiento." data-msg-pattern="Usa solo letras, números, guiones o barras."></div></div>
  <div><label for="rv-z">Zona</label><input class="in" id="rv-z" name="zona" value="{{ old('zona') }}" placeholder="Ej.: Sopocachi" required maxlength="60" data-msg="Escribe la zona donde está la clínica."></div>
  <div><label for="rv-d">Dirección</label><input class="in" id="rv-d" name="direccion" value="{{ old('direccion') }}" required maxlength="150" minlength="5" placeholder="Calle, número y referencia" data-msg="Escribe la dirección de la clínica."></div>
  <div><label for="rv-t">Teléfono fijo</label><input class="in" id="rv-t" name="telefono" inputmode="numeric" maxlength="10" value="{{ old('telefono') }}" required pattern="\d{7,10}" placeholder="2241122" data-msg="Escribe el teléfono de la clínica." data-msg-pattern="El teléfono debe tener de 7 a 10 dígitos, sin espacios."></div>
  <x-horario id="rv-h"/>
  <x-telefono id="rv-w" label="WhatsApp de la clínica" campo-pais="whatsapp_pais" campo-numero="whatsapp" :pais="old('whatsapp_pais')" :numero="old('whatsapp')" ayuda="Aquí recibirá cada cita nueva."/>
  <x-servicios :seleccion="['Consulta', 'Vacuna']" id="rvs"/>
  <div><label for="rv-doc">Licencia de funcionamiento o documento de la clínica</label><input class="in" id="rv-doc" type="file" name="documento" accept="image/jpeg,image/png,image/webp,application/pdf" required data-max-mb="10" data-msg="Sube la licencia de funcionamiento (PDF o foto)."><div class="hint">PDF o foto, hasta 10 MB. Solo lo ve el administrador de PetCare.</div></div>

  <h2>2. Suscripción</h2>
  <div class="opciones">@foreach(\App\Support\Catalogo::SUSCRIPCIONES as $k => $x)
    <label class="opc"><input type="radio" name="plan_suscripcion" value="{{ $k }}" required data-msg="Elige un plan de suscripción." @checked(old('plan_suscripcion', 'profesional') === $k)><span><b>{{ $x['nombre'] }} · {{ \App\Support\Formato::bs($x['precio']) }}/mes</b><small>{{ implode(' · ', array_slice($x['beneficios'], 1)) }}</small></span></label>
  @endforeach</div>
  <div class="note ok"><x-icon name="check"/><span>Todas las suscripciones atienden a las mascotas con plan PetCare. <b>El primer mes es gratis</b>, pero —como en Spotify o Netflix— registras ahora tu forma de pago: el cobro empieza cuando termina el mes de prueba.</span></div>
  <h3 class="mp-h">Forma de pago después del mes gratis</h3>
  @include('partials.metodo-pago', ['registrar' => true, 'whatsapp' => true, 'qr' => '', 'banco' => null, 'destino' => 'PetCare',
    'monto' => \App\Support\Catalogo::SUSCRIPCIONES[old('plan_suscripcion', 'profesional')]['precio'] ?? 100,
    'desde' => today()->addDays(\App\Support\Catalogo::DIAS_PRUEBA_VETERINARIA)->format('d/m/Y')])

  <h2>3. Cuenta de acceso</h2>
  <div class="fr"><div><label for="rv-r">Veterinario/a responsable</label><input class="in" id="rv-r" name="responsable" value="{{ old('responsable') }}" placeholder="Dra. / Dr." required minlength="3" maxlength="100" data-msg="Escribe el nombre del veterinario/a responsable."></div>
    <div><label for="rv-mat">N.º de matrícula profesional</label><input class="in" id="rv-mat" name="matricula" value="{{ old('matricula') }}" required maxlength="30" pattern="[A-Za-z0-9\/\-. ]+" placeholder="Ej.: MVZ-LP-1187" data-msg="Escribe el N.º de matrícula profesional del veterinario." data-msg-pattern="Usa solo letras, números, guiones o barras."></div></div>
  <div><label for="rv-e">Correo</label><input class="in" id="rv-e" name="email" type="email" value="{{ old('email') }}" required maxlength="120" data-msg="Escribe el correo de la clínica: será su usuario.">
    @include('partials.correo-existente')</div>
  <div><label for="rv-p">Contraseña</label><input class="in" id="rv-p" name="password" type="password" autocomplete="new-password" data-fuerza required data-segura data-msg="Crea una contraseña."><div class="fuerza" aria-hidden="true"><span></span></div>
    <ul class="reqs"><li data-r="largo">Mínimo 8 caracteres</li><li data-r="may">Una mayúscula</li><li data-r="min">Una minúscula</li><li data-r="num">Un número</li><li data-r="sim">Un símbolo</li></ul></div>
  <div><label for="rv-p2">Repetir contraseña</label><input class="in" id="rv-p2" name="password_confirmation" type="password" autocomplete="new-password" required data-igual="password" data-msg="Repite la contraseña."></div>
  <x-terminos para="veterinaria"/>
  <button class="btn full" type="submit">Enviar solicitud</button>
  <div style="text-align:center;font-size:14px">¿Ya está afiliada? <a class="lnk" href="{{ route('login', 'veterinaria') }}">Inicia sesión</a></div>
</form>
@endsection
