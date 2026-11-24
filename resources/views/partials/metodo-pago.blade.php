{{--
  Forma de pago reutilizable (planes, suscripciones y citas).
  $qr: URL de la imagen del QR · $banco: ['banco','titular','cuenta'] o null · $destino: a quién se paga
  $monto: total a pagar · $whatsapp: si se ofrece la opción de WhatsApp
  $registrar: solo se registra la forma de cobro (afiliación con mes gratis): no se sube comprobante ni se cobra ahora.
  $desde: fecha del primer cobro (con $registrar)
--}}
@php
  $met = old('metodo', 'tarjeta');
  $marca = old('tarjeta_marca', 'visa');
  $whatsapp ??= true;
  $registrar ??= false;
  $pid ??= 'mp';
@endphp
<div class="mp" data-mp>
  <div class="mp-tabs" role="radiogroup" aria-label="Forma de pago">
    <label class="mp-tab"><input type="radio" name="metodo" value="tarjeta" required data-msg="Elige cómo vas a pagar." @checked($met === 'tarjeta')><span><x-icon name="card"/><b>Tarjeta</b><small>{{ $registrar ? 'Cobro automático mensual' : 'Débito o crédito' }}</small></span></label>
    <label class="mp-tab"><input type="radio" name="metodo" value="qr" @checked($met === 'qr')><span><x-icon name="qr"/><b>QR</b><small>{{ $registrar ? 'Pagas cada mes' : 'Desde tu banco' }}</small></span></label>
    @if($whatsapp)<label class="mp-tab"><input type="radio" name="metodo" value="whatsapp" @checked($met === 'whatsapp')><span><x-icon name="phone"/><b>WhatsApp</b><small>Te enviamos el QR</small></span></label>@endif
  </div>
  @error('metodo')<p class="err-campo">{{ $message }}</p>@enderror

  {{-- Tarjeta --}}
  <div class="mp-panel" data-panel="tarjeta" @if($met !== 'tarjeta') hidden @endif>
    <div class="mp-marcas" role="radiogroup" aria-label="Marca de la tarjeta">
      @foreach(\App\Support\ReglasPago::MARCAS as $k => $m)
        <label class="mp-marca"><input type="radio" name="tarjeta_marca" value="{{ $k }}" @checked($marca === $k)><span class="mk mk-{{ $k }}">{{ $m['nombre'] }}</span></label>
      @endforeach
    </div>
    <div class="mp-card-wrap">
      <div class="tcard mk-{{ $marca }}" data-tcard aria-hidden="true">
        <div class="tc-front">
          <div class="tc-top"><span class="tc-chip"></span><span class="tc-marca" data-tc-marca>{{ \App\Support\ReglasPago::MARCAS[$marca]['nombre'] }}</span></div>
          <div class="tc-num" data-tc-num>•••• •••• •••• ••••</div>
          <div class="tc-bot"><div><small>Titular</small><span data-tc-nom>NOMBRE APELLIDO</span></div><div><small>Vence</small><span data-tc-ven>MM/AA</span></div></div>
        </div>
        <div class="tc-back"><div class="tc-band"></div><div class="tc-cvv"><small>CVV</small><span data-tc-cvv>•••</span></div></div>
      </div>
      <div class="f mp-campos">
        <div><label for="{{ $pid }}-num" class="req">Número de tarjeta</label><input class="in" id="{{ $pid }}-num" name="tarjeta_numero" inputmode="numeric" autocomplete="off" maxlength="23" placeholder="0000 0000 0000 0000" data-tarjeta required data-msg="Escribe el número de la tarjeta."></div>
        <div><label for="{{ $pid }}-nom" class="req">Nombre en la tarjeta</label><input class="in" id="{{ $pid }}-nom" name="tarjeta_nombre" autocomplete="off" value="{{ old('tarjeta_nombre') }}" placeholder="Como aparece en la tarjeta" required minlength="3" maxlength="60" pattern="[\p{L}\s.'-]+" data-msg="Escribe el nombre tal como aparece en la tarjeta." data-msg-pattern="El nombre del titular solo puede tener letras."></div>
        <div class="fr">
          <div><label for="{{ $pid }}-ven" class="req">Vencimiento</label><input class="in" id="{{ $pid }}-ven" name="tarjeta_vence" placeholder="MM/AA" maxlength="5" inputmode="numeric" autocomplete="off" value="{{ old('tarjeta_vence') }}" required data-msg="Escribe el vencimiento (MM/AA)."></div>
          <div><label for="{{ $pid }}-cvv" class="req">CVV</label><input class="in" id="{{ $pid }}-cvv" name="tarjeta_cvv" inputmode="numeric" maxlength="4" autocomplete="off" placeholder="123" required data-msg="Escribe el código de seguridad (CVV) del reverso."></div>
        </div>
      </div>
    </div>
    @if($registrar)<div class="note ok"><x-icon name="check"/><span><b>Hoy no se cobra nada.</b> El primer mes es gratis; desde el <b>{{ $desde ?? '' }}</b> se cobrará automáticamente <b data-mp-monto>{{ isset($monto) ? \App\Support\Formato::bs($monto) : '' }}</b> cada mes a esta tarjeta. Puedes cambiarla o pasar a QR en «Suscripción».</span></div>@endif
    <div class="note warn"><x-icon name="alert"/><span><b>Pago simulado:</b> aún no hay pasarela de pago, no se cobra nada y solo se guardan los 4 últimos dígitos. Para probar: Visa 4242 4242 4242 4242 · Mastercard 5555 5555 5555 4444 · Amex 3782 822463 10005.</span></div>
  </div>

  {{-- QR --}}
  @if($registrar)
  <div class="mp-panel" data-panel="qr" @if($met !== 'qr') hidden @endif>
    <div class="note info"><x-icon name="qr"/><span><b>Pago mes a mes con QR.</b> No pagas nada ahora: el primer mes es gratis. 5 días antes de cada vencimiento te avisamos por correo y WhatsApp para que pagues con el QR de PetCare desde el sistema y subas el comprobante. Si no pagas, la clínica deja de aparecer en PetCare hasta que pagues.</span></div>
  </div>
  @else
  <div class="mp-panel" data-panel="qr" @if($met !== 'qr') hidden @endif>
    <div class="pago-qr">
      <img src="{{ $qr }}" alt="Código QR de pago de {{ $destino }}" width="180" height="180">
      <div class="grid" style="gap:10px">
        <ol class="mp-pasos"><li>Escanea el QR desde la app de tu banco.</li><li>Paga <b data-mp-monto>{{ isset($monto) ? \App\Support\Formato::bs($monto) : '' }}</b> a {{ $destino }}.</li><li>Sube aquí la captura o el PDF del comprobante.</li></ol>
        @if($banco)<div class="kv" style="padding:0"><div><span>Banco</span><b>{{ $banco['banco'] }}</b></div><div><span>Titular</span><b>{{ $banco['titular'] }}</b></div><div><span>Cuenta</span><b>{{ $banco['cuenta'] }}</b></div></div>@endif
      </div>
    </div>
    <div class="mp-subir" data-subir>
      <label for="{{ $pid }}-comp" class="req">Comprobante del pago</label>
      <label class="drop" for="{{ $pid }}-comp"><x-icon name="up"/><span data-drop-txt><b>Elige o arrastra</b> la foto o el PDF del comprobante<small>JPG, PNG, WEBP o PDF · máx. 10 MB</small></span><img data-drop-img alt="" hidden></label>
      <input class="sr" id="{{ $pid }}-comp" type="file" name="comprobante" accept="image/jpeg,image/png,image/webp,application/pdf" required data-max-mb="10" data-msg="Sube la foto o el PDF del comprobante del pago QR.">
    </div>
  </div>

  @endif

  {{-- WhatsApp --}}
  @if($whatsapp)
  <div class="mp-panel" data-panel="whatsapp" @if($met !== 'whatsapp') hidden @endif>
    <div class="pasos-wa">
      <div><span>1</span>Al confirmar se abre <b>WhatsApp de {{ $destino }}</b> con un mensaje corto y listo.</div>
      <div><span>2</span>Te responden con el <b>QR</b> para pagar desde tu banco.</div>
      <div><span>3</span>Verifican el pago y te llega la confirmación.</div>
    </div>
  </div>
  @endif
</div>
