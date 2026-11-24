@extends('layouts.app')
@section('title', 'Configuración')
@section('heading', 'Configuración')
@section('content')
<div class="g2" style="align-items:start">
  <div class="grid">
    <div class="card pad"><h2 style="margin-bottom:6px">Datos de cobro (QR)</h2><p class="sub" style="margin-bottom:12px">Se muestran a los dueños y a las veterinarias al pagar. Los QR de los bancos suelen tener fecha de vencimiento: súbelo de nuevo cuando generes uno nuevo.</p>
      <form class="f" method="POST" action="{{ route('admin.configuracion.pago') }}" enctype="multipart/form-data" novalidate>
        @csrf @method('PUT')
        <x-errors bag="pago"/>
        <div class="pago-qr"><img src="{{ route('archivos.qr') }}?v={{ now()->timestamp }}" alt="QR de cobro vigente" width="150" height="150">
          <div class="grid" style="gap:10px;flex:1"><div><label>Banco</label><input class="in" name="pago_banco" value="{{ old('pago_banco', $banco['banco']) }}" required maxlength="60" data-msg="Escribe el nombre del banco."></div>
            <div><label>Titular</label><input class="in" name="pago_titular" value="{{ old('pago_titular', $banco['titular']) }}" required maxlength="100" data-msg="Escribe el titular de la cuenta."></div>
            <div><label>N.º de cuenta</label><input class="in" name="pago_cuenta" value="{{ old('pago_cuenta', $banco['cuenta']) }}" required maxlength="25" pattern="[\d-]{6,25}" data-msg="Escribe el número de cuenta." data-msg-pattern="El número de cuenta solo puede tener números y guiones."></div></div></div>
        <div><label>Nuevo QR (opcional)</label><input class="in" type="file" name="pago_qr" accept="image/jpeg,image/png,image/webp" data-max-mb="10"></div>
        <button class="btn sec" type="submit">Guardar datos de cobro</button>
      </form>
    </div>
    <div class="card pad"><h2 style="margin-bottom:6px">Redes sociales</h2><p class="sub" style="margin-bottom:12px">Se muestran como botones en la pantalla de inicio de PetCare. Pega el enlace de la cuenta o solo el usuario; deja el campo vacío para ocultar el botón.</p>
      <form class="f" method="POST" action="{{ route('admin.configuracion.redes') }}" novalidate>
        @csrf @method('PUT')
        <x-errors bag="redes"/>
        <div><label><x-icon name="nota" style="width:16px;height:16px;vertical-align:-3px"/> TikTok</label><input class="in" name="red_tiktok" value="{{ old('red_tiktok', $redes['tiktok']) }}" maxlength="200" placeholder="https://www.tiktok.com/@petcare  o  @petcare"></div>
        <div><label><x-icon name="camara" style="width:16px;height:16px;vertical-align:-3px"/> Instagram</label><input class="in" name="red_instagram" value="{{ old('red_instagram', $redes['instagram']) }}" maxlength="200" placeholder="https://www.instagram.com/petcare  o  @petcare"></div>
        <button class="btn sec" type="submit">Guardar redes sociales</button>
      </form>
    </div>
    <div class="card pad"><h2 style="margin-bottom:10px">Plataforma</h2><div class="kv" style="padding:0"><div><span>Nombre</span><b>PetCare</b></div><div><span>Ciudad</span><b>La Paz, Bolivia</b></div><div><span>Moneda</span><b>Bolivianos (Bs)</b></div><div><span>Usuarios registrados</span><b>{{ $usuarios }}</b></div><div><span>WhatsApp</span><b>{{ $driver === 'meta' ? 'API de WhatsApp Business (envío real)' : 'Modo demostración' }}</b></div><div><span>Correo</span><b>{{ config('mail.default') === 'log' ? 'Modo demostración (storage/logs)' : strtoupper(config('mail.default')) }}</b></div><div><span>Base de datos</span><b>{{ strtoupper(config('database.default')) }}</b></div></div></div>
    <div class="card pad"><h2 style="margin-bottom:6px">Respaldo de datos</h2><p class="sub" style="margin-bottom:12px">Descarga una copia de toda la información en formato JSON (sin contraseñas).</p><a class="btn sec" href="{{ route('admin.respaldo') }}"><x-icon name="down"/>Descargar respaldo</a></div>
  </div>
  <div class="card pad"><h2 style="margin-bottom:6px">Mensajes de WhatsApp</h2><p class="sub" style="margin-bottom:10px">Últimos avisos enviados por la plataforma{{ $driver === 'meta' ? '' : ' (en modo demostración no salen del sistema)' }}. Los códigos de verificación se muestran ocultos.</p>
    @if($mensajes->isEmpty())<x-empty icon="phone" title="Sin mensajes" text="Aparecerán al registrar cuentas, agendar citas o aprobar pagos."/>
    @else<div class="lst mensajes">@foreach($mensajes as $m)<div class="row"><div class="tx"><b>+{{ $m->para }}{{ $m->user ? ' · '.$m->user->name : '' }}</b><span>{{ $m->mensaje }}</span><span class="hint">{{ $m->created_at->format('d/m/Y H:i') }}</span></div><span class="chip {{ ['demo' => 'info', 'enviado' => 'ok', 'fallido' => 'bad'][$m->estado] ?? 'mut' }}">{{ ucfirst($m->estado) }}</span></div>@endforeach</div>@endif
  </div>
</div>
<h2 style="margin:22px 0 12px">Mi cuenta</h2>
@include('partials.perfil')
@endsection
