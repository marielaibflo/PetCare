@extends('layouts.app')
@php($marcados = fn ($campo, $actual) => old($campo, $actual ?? []))
@section('title', 'Mi clínica')
@section('heading', 'Mi clínica')
@section('sub', 'Estos datos aparecen en tu página dentro de PetCare y en las cartillas de vacunas que firmas.')
@section('back', route('veterinaria.inicio'))
@section('acts')<a class="btn ghost" href="{{ route('veterinarias.show', $vet) }}" target="_blank"><x-icon name="eye"/>Ver mi página</a>@endsection
@section('content')
@if(! $user->matricula || ! $vet->registro)
  <div class="note warn" style="margin-bottom:14px"><x-icon name="alert"/><span>Para registrar vacunas y desparasitaciones debes completar tu <b>N.º de matrícula profesional</b> y el <b>N.º de registro de la clínica</b>.</span></div>
@endif
<form class="g2" style="align-items:start" method="POST" action="{{ route('veterinaria.clinica.update') }}" enctype="multipart/form-data" novalidate>
  @csrf @method('PUT')
  <div class="card pad f"><h2>Datos de la veterinaria</h2>
    <x-errors bag="clinica"/>
    <div class="foto-in"><span class="foto-prev sello-prev" id="logo-prev">@if($vet->logo)<img src="{{ route('archivos.logo', $vet) }}" alt="Logo">@else<b>{{ $vet->siglas() }}</b>@endif</span>
      <div><label for="vlogo">Logo o sello de la clínica</label><input class="in" id="vlogo" type="file" name="logo" accept="image/jpeg,image/png,image/webp" data-preview="logo-prev" data-max-mb="10"><div class="hint">Aparece como sello en las cartillas de vacunas y en tu página.</div></div></div>
    <div class="foto-in"><span class="foto-prev" id="qr-prev">@if($vet->qr_cobro)<img src="{{ route('archivos.qr-veterinaria', $vet) }}" alt="QR de cobro">@else<x-icon name="qr"/>@endif</span>
      <div><label for="vqr">QR de cobro de la clínica</label><input class="in" id="vqr" type="file" name="qr_cobro" accept="image/jpeg,image/png,image/webp" data-preview="qr-prev" data-max-mb="10"><div class="hint">Los dueños lo escanean para pagar sus citas. Los pagos y comprobantes te llegan en la agenda para que los confirmes.</div></div></div>
    <div><label for="vn">Nombre</label><input class="in" id="vn" name="nombre" value="{{ old('nombre', $vet->nombre) }}" required minlength="3" maxlength="100" data-msg="Escribe el nombre de la clínica."></div>
    <div class="fr"><div><label for="vreg">N.º de registro / licencia</label><input class="in" id="vreg" name="registro" value="{{ old('registro', $vet->registro) }}" placeholder="Ej.: REG-LP-0451" required maxlength="30" pattern="[A-Za-z0-9\/\-. ]+" data-msg="Escribe el N.º de registro o licencia: sin él no puedes registrar vacunas." data-msg-pattern="Usa solo letras, números, guiones o barras."></div>
      <div><label for="vmat">Tu N.º de matrícula profesional</label><input class="in" id="vmat" name="matricula" value="{{ old('matricula', $user->matricula) }}" placeholder="Ej.: MV-1023" required maxlength="30" pattern="[A-Za-z0-9\/\-. ]+" data-msg="Escribe tu N.º de matrícula profesional: sin él no puedes registrar vacunas." data-msg-pattern="Usa solo letras, números, guiones o barras."></div></div>
    <div class="fr"><div><label for="vz">Zona</label><input class="in" id="vz" name="zona" value="{{ old('zona', $vet->zona) }}" required maxlength="60" data-msg="Escribe la zona de la clínica."></div><div><label for="vt">Teléfono fijo</label><input class="in" id="vt" name="telefono" inputmode="numeric" maxlength="10" value="{{ old('telefono', $vet->telefono) }}" required pattern="\d{7,10}" data-msg="Escribe el teléfono de la clínica." data-msg-pattern="El teléfono debe tener de 7 a 10 dígitos, sin espacios."></div></div>
    <x-telefono id="vw" label="WhatsApp de la clínica" campo-pais="whatsapp_pais" campo-numero="whatsapp" :pais="old('whatsapp_pais', $vet->whatsapp_pais)" :numero="old('whatsapp', $vet->whatsapp)" :requerido="false" ayuda="Aquí te escriben los dueños para pagar y confirmar sus citas."/>
    <div><label for="vd">Dirección</label><input class="in" id="vd" name="direccion" value="{{ old('direccion', $vet->direccion) }}" required minlength="5" maxlength="150" data-msg="Escribe la dirección de la clínica."></div>
    <div><label for="vmap">Enlace de Google Maps (opcional)</label><input class="in" id="vmap" name="mapa_url" value="{{ old('mapa_url', $vet->mapa_url) }}" placeholder="https://maps.app.goo.gl/..." maxlength="500" pattern="https://(www\.)?(google\.[a-z.]+/maps|maps\.app\.goo\.gl|goo\.gl/maps|maps\.google\.[a-z.]+).*" data-msg-pattern="Pega el enlace que da Google Maps al pulsar «Compartir» (empieza con https://maps.app.goo.gl/…)."><div class="hint">En Google Maps busca tu clínica → Compartir → Copiar vínculo. Si lo dejas vacío usamos tu dirección.</div></div>
    <x-horario :valor="$vet->horario" id="vh"/>
    <div><label for="vdesc">Descripción</label><textarea class="in" id="vdesc" name="descripcion" maxlength="600" placeholder="Cuéntales a los dueños sobre tu clínica, tu equipo y tu experiencia.">{{ old('descripcion', $vet->descripcion) }}</textarea></div>
    <button class="btn" type="submit">Guardar cambios</button>
  </div>
  <div class="card pad f"><h2>Servicios y atención</h2>
    <x-servicios :seleccion="$vet->servicios" id="vs"/>
    <div><label>Servicios especiales</label><div class="cbs">@foreach(\App\Models\Veterinaria::SERVICIOS_ESPECIALES as $s)<label class="cb"><input type="checkbox" name="servicios_especiales[]" value="{{ $s }}" @checked(in_array($s, $marcados('servicios_especiales', $vet->servicios_especiales)))><span>{{ $s }}</span></label>@endforeach</div></div>
    <div><label>Métodos de pago que aceptas</label><div class="cbs">@foreach(\App\Models\Veterinaria::METODOS_PAGO as $s)<label class="cb"><input type="checkbox" name="metodos_pago[]" value="{{ $s }}" @checked(in_array($s, $marcados('metodos_pago', $vet->metodos_pago)))><span>{{ $s }}</span></label>@endforeach</div></div>
    <div><label for="vpro">Promoción del mes (opcional)</label><input class="in" id="vpro" name="promocion" maxlength="200" value="{{ old('promocion', $vet->promocion) }}" placeholder="Ej.: Campaña de vacunación antirrábica −20 % hasta fin de mes"></div>
    <button class="btn sec" type="submit">Guardar cambios</button>
  </div>
</form>

<div class="g2" style="align-items:start;margin-top:16px">
  <div class="card pad"><h2 style="margin-bottom:4px">Tarifario</h2><p class="sub" style="margin-bottom:12px">Precio de cada servicio en bolivianos. Se usa para calcular cuánto cubre el plan de la mascota y cuánto paga el dueño.</p>
    <form class="f" method="POST" action="{{ route('veterinaria.clinica.tarifario') }}" novalidate>
      @csrf @method('PUT')
      <x-errors bag="tarifario"/>
      <div class="tarifas">@foreach($vet->servicios as $s)
        <label class="tarifa"><x-svc :servicio="$s"/><span>{{ $s }}</span><span class="bs-in"><i>Bs</i><input class="in" name="precios[{{ $s }}]" inputmode="decimal" value="{{ old('precios.'.$s, $vet->precio($s)) }}" placeholder="0" data-numero min="0" max="100000" data-label="precio de {{ mb_strtolower($s) }}" data-msg-min="El precio no puede ser negativo."></span></label>
      @endforeach</div>
      <button class="btn sec" type="submit">Guardar tarifario</button>
    </form>
  </div>
  <div class="card pad"><h2 style="margin-bottom:4px">Fotos de tus instalaciones</h2><p class="sub" style="margin-bottom:12px">Se muestran en tu página para que los dueños conozcan tu clínica (hasta 12).</p>
    <x-errors bag="fotos"/>
    <div class="galeria-edit">@foreach($vet->fotos as $f)
      <div class="gal-item"><img src="{{ route('archivos.foto-veterinaria', $f) }}" alt="Foto de la clínica" loading="lazy">
        <form method="POST" action="{{ route('veterinaria.clinica.fotos.destroy', $f) }}" data-confirm="Se quitará esta foto de tu página." data-confirm-title="Eliminar foto" data-confirm-btn="Eliminar">@csrf @method('DELETE')<button class="icb bad" aria-label="Eliminar foto"><x-icon name="trash"/></button></form></div>
    @endforeach</div>
    <form class="f" method="POST" action="{{ route('veterinaria.clinica.fotos') }}" enctype="multipart/form-data" style="margin-top:12px">@csrf
      <input class="in" type="file" name="fotos[]" accept="image/jpeg,image/png,image/webp" multiple required data-max-mb="10" data-msg="Elige al menos una foto de la clínica.">
      <input class="in" name="titulo" maxlength="80" placeholder="Descripción (opcional): Ej.: Sala de espera, Quirófano">
      <button class="btn sm sec" type="submit"><x-icon name="plus"/>Subir fotos</button></form>
    <div style="margin-top:14px;display:flex;gap:8px;flex-wrap:wrap"><a class="btn sm ghost" href="{{ route('veterinaria.productos.index') }}"><x-icon name="syr"/>Catálogo de productos</a><a class="btn sm ghost" href="{{ route('veterinaria.suscripcion') }}"><x-icon name="crown"/>Mi suscripción</a></div>
  </div>
</div>
<h2 style="margin:22px 0 12px">Mi cuenta</h2>
@include('partials.perfil')
@endsection
