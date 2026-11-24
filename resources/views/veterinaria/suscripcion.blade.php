@extends('layouts.app')
@php($s = $vet->suscripcion())
@php($pendiente = $pagos->firstWhere('estado', 'pendiente'))
@section('title', 'Mi suscripción')
@section('heading', 'Mi suscripción a PetCare')
@section('back', route('veterinaria.inicio'))
@section('content')
@include('partials.pago-whatsapp')
@push('dialogs')
<x-dialog id="dlg-cancelar-sus" title="Cancelar suscripción" icon="x">
  <form class="f" method="POST" action="{{ route('veterinaria.suscripcion.cancelar') }}" novalidate>
    @csrf
    <x-form-errors for="dlg-cancelar-sus"/>
    <p class="sub" style="margin:0">Tu clínica seguirá apareciendo y recibiendo citas hasta el <b>{{ $vet->suscripcion_hasta?->format('d/m/Y') ?? 'fin del período' }}</b>. Después dejará de aparecer en PetCare. No se cobrará más.</p>
    <div><label for="cs-m">¿Por qué cancelas? (opcional)</label><textarea class="in" id="cs-m" name="motivo" maxlength="200" placeholder="Nos ayuda a mejorar"></textarea></div>
    <div class="md-f"><button type="button" class="btn ghost" data-close>Volver</button><button class="btn danger" type="submit">Cancelar suscripción</button></div>
  </form>
</x-dialog>
<x-dialog id="dlg-eliminar-cuenta" title="Eliminar mi cuenta" icon="trash">
  <form class="f" method="POST" action="{{ route('veterinaria.cuenta.eliminar') }}" novalidate>
    @csrf @method('DELETE')
    <x-form-errors for="dlg-eliminar-cuenta"/>
    <div class="note bad"><x-icon name="alert"/><span>Esto no se puede deshacer:</span></div>
    <ul class="lista-baja"><li>Se cancelan tus citas pendientes y avisamos a cada dueño.</li><li>Los dueños con plan en tu clínica podrán elegir otra veterinaria.</li>
      <li>Tu clínica y tu catálogo dejan de aparecer y ya no podrás ingresar.</li><li>El historial clínico de las mascotas que atendiste se conserva.</li></ul>
    <div><label for="ec-n">Escribe el nombre de tu clínica para confirmar</label><input class="in" id="ec-n" name="confirmar" required autocomplete="off" placeholder="{{ $vet->nombre }}" data-msg="Escribe el nombre de tu clínica para confirmar."></div>
    <div><label for="ec-p">Tu contraseña</label><input class="in" id="ec-p" type="password" name="password" required autocomplete="current-password" data-msg="Escribe tu contraseña."></div>
    <div class="md-f"><button type="button" class="btn ghost" data-close>Volver</button><button class="btn danger" type="submit">Eliminar mi cuenta</button></div>
  </form>
</x-dialog>
<x-dialog id="dlg-cobro" title="Forma de cobro mensual" icon="card">
  <form class="f" method="POST" action="{{ route('veterinaria.suscripcion.cobro') }}" novalidate>
    @csrf @method('PUT')
    <x-form-errors for="dlg-cobro"/>
    <p class="sub" style="margin:0">Elige cómo pagarás tu suscripción cada mes. Hoy no se cobra nada.</p>
    @include('partials.metodo-pago', ['registrar' => true, 'pid' => 'cb', 'qr' => '', 'banco' => null, 'destino' => 'PetCare', 'monto' => $s['precio'], 'desde' => $vet->suscripcion_hasta?->format('d/m/Y')])
    <div class="md-f"><button type="button" class="btn ghost" data-close>Cancelar</button><button class="btn" type="submit">Guardar forma de cobro</button></div>
  </form>
</x-dialog>
@endpush
<div class="g2" style="align-items:start;margin-top:14px">
  <div class="grid">
    <div class="card pad grid">
      <div style="display:flex;gap:12px;align-items:center"><span class="ti c-p"><x-icon name="crown"/></span><div><b style="font-size:17px">Plan {{ $s['nombre'] }} · {{ \App\Support\Formato::bs($s['precio']) }}/mes</b>
        <div class="sub">@if($vet->suscripcion_hasta)Vigente hasta el {{ $vet->suscripcion_hasta->format('d/m/Y') }}@else Sin fecha de vencimiento @endif</div></div></div>
      @if(! $vet->suscripcionVigente())<div class="note bad"><x-icon name="alert"/><span>Tu suscripción venció: tu clínica no aparece en el buscador y no recibe citas nuevas. Renuévala abajo.</span></div>
      @elseif($vet->suscripcion_hasta && $vet->suscripcion_hasta->diffInDays(today()) <= 7)<div class="note warn"><x-icon name="alert"/><span>Tu suscripción vence pronto. Renuévala para seguir recibiendo citas.</span></div>@endif
      @if($pendiente)<div class="note info"><x-icon name="clock"/><span>Tienes un pago de {{ \App\Support\Formato::bs($pendiente->monto) }} pendiente. <a class="lnk" href="{{ $pendiente->enlaceWhatsapp() }}" target="_blank" rel="noopener">Avisar por WhatsApp</a></span></div>@endif
      <ul class="lista-ok">@foreach($s['beneficios'] as $b)<li><x-icon name="check"/>{{ $b }}</li>@endforeach</ul>
    </div>
    @php($precio = \App\Support\Formato::bs($s['precio']))
    <div class="card pad grid" style="gap:10px">
      <div style="display:flex;gap:12px;align-items:center"><span class="ti c-i"><x-icon name="card"/></span><div style="flex:1"><b>Forma de cobro mensual</b>
        <div class="sub">{{ $vet->cobroTexto() }}@if($vet->cobro_metodo === 'tarjeta' && $vet->cobro_vence) · vence {{ $vet->cobro_vence }}@endif</div></div>
        <button type="button" class="btn sm sec" data-open="dlg-cobro">Cambiar</button></div>
      @if($vet->enPrueba() && $vet->suscripcion_hasta)<div class="note ok"><x-icon name="check"/><span>Estás en tu <b>mes gratis</b> hasta el {{ $vet->suscripcion_hasta->format('d/m/Y') }}.</span></div>@endif
      @if($vet->cobro_metodo === 'tarjeta' && $vet->tarjetaVigente())
        <p class="hint" style="margin:0">El {{ $vet->suscripcion_hasta?->format('d/m/Y') }} se cobrará automáticamente {{ $precio }} a tu tarjeta (simulación) y te llegará la factura por correo.</p>
      @elseif($vet->cobro_metodo === 'tarjeta')
        <div class="note warn"><x-icon name="alert"/><span>Tu tarjeta registrada está vencida: actualízala o paga con QR antes del vencimiento.</span></div>
      @else
        <p class="hint" style="margin:0">5 días antes de cada vencimiento te avisaremos por correo y WhatsApp para que pagues {{ $precio }} con QR aquí mismo.</p>
      @endif
    </div>
    {{-- Cancelar la suscripción y, después, eliminar la cuenta --}}
    <div class="card pad grid zona-baja" style="gap:10px">
      @if($vet->suscripcion_cancelada_at)
        <div class="note warn"><x-icon name="alert"/><span><b>Cancelaste tu suscripción</b> el {{ $vet->suscripcion_cancelada_at->format('d/m/Y') }}. Tu clínica sigue activa hasta el {{ $vet->suscripcion_hasta?->format('d/m/Y') ?? 'fin del período' }}; no se cobrará ni se avisará más.</span></div>
        <div style="display:flex;gap:10px;flex-wrap:wrap">
          <form method="POST" action="{{ route('veterinaria.suscripcion.reactivar') }}">@csrf<button class="btn sec"><x-icon name="check"/>Reactivar suscripción</button></form>
          <button type="button" class="btn danger" data-open="dlg-eliminar-cuenta"><x-icon name="trash"/>Eliminar mi cuenta</button>
        </div>
      @else
        <div style="display:flex;gap:12px;align-items:center"><span class="ti c-b"><x-icon name="x"/></span><div style="flex:1"><b>Cancelar suscripción</b>
          <div class="sub">Tu clínica seguirá activa hasta el {{ $vet->suscripcion_hasta?->format('d/m/Y') ?? 'fin del período' }}. Después podrás eliminar tu cuenta.</div></div>
          <button type="button" class="btn sm danger" data-open="dlg-cancelar-sus">Cancelar</button></div>
      @endif
    </div>
    <section><h2 style="margin-bottom:10px">Historial de pagos</h2>
      <div class="card">@if($pagos->isEmpty())<x-empty icon="doc" title="Sin pagos" text="Aquí verás tus pagos de suscripción y sus facturas."/>@else
        <div class="lst">@foreach($pagos as $p)<div class="row"><span class="ti c-p"><x-icon name="doc"/></span><div class="tx"><b>{{ $p->detalle() }}</b><span>{{ \App\Support\Formato::bs($p->monto) }} · {{ $p->created_at->format('d/m/Y') }}{{ $p->numero ? ' · '.$p->numero : '' }}</span>@if($p->estado === 'rechazado')<span class="motivo">Motivo: {{ $p->motivo_rechazo }}</span>@endif</div>
          <div class="ra"><span class="chip {{ \App\Models\Pago::ESTADOS[$p->estado] }}">{{ ucfirst($p->estado) }}</span>@if($p->estado === 'aprobado')<a class="btn sm sec" href="{{ route('veterinaria.suscripcion.pdf', $p) }}" target="_blank"><x-icon name="down"/>Descargar factura</a>@endif</div></div>@endforeach</div>@endif</div>
    </section>
  </div>
  <form class="card pad f" id="fsus" data-validar method="POST" action="{{ route('veterinaria.suscripcion.pagar') }}" enctype="multipart/form-data" novalidate data-precios='@json(collect(\App\Support\Catalogo::SUSCRIPCIONES)->map(fn ($x) => $x['precio']))'>
    @csrf
    <h2>Renovar o cambiar de plan</h2>
    <x-errors/>
    <div class="opciones">@foreach(\App\Support\Catalogo::SUSCRIPCIONES as $k => $x)
      <label class="opc"><input type="radio" name="plan_suscripcion" value="{{ $k }}" @checked(old('plan_suscripcion', $vet->plan_suscripcion) === $k)><span><b>{{ $x['nombre'] }} · {{ \App\Support\Formato::bs($x['precio']) }}/mes</b><small>{{ $x['beneficios'][1] }}</small></span></label>
    @endforeach</div>
    <div class="opciones">
      <label class="opc"><input type="radio" name="meses" value="1" @checked(old('meses', '1') === '1')><span><b>1 mes</b></span></label>
      <label class="opc"><input type="radio" name="meses" value="12" @checked(old('meses') === '12')><span><b>12 meses</b><small>pagas 10</small></span></label>
    </div>
    <div class="resumen-pago" style="position:static;padding:0"><div class="total">Total a pagar <b id="sus-total"></b></div></div>
    <div class="fr"><div><label for="su-fa" class="req">Razón social</label><input class="in" id="su-fa" name="facturar_a" required minlength="3" maxlength="100" value="{{ old('facturar_a', $vet->nombre) }}" data-msg="Escribe la razón social para el comprobante."></div><div><label for="su-doc" class="req">NIT</label><input class="in" id="su-doc" name="documento" inputmode="numeric" required pattern="\d{5,15}" value="{{ old('documento', $vet->nit) }}" data-msg="Escribe el NIT (solo números, 5 a 15 dígitos)."></div></div>
    <h3 class="mp-h">Forma de pago</h3>
    @include('partials.metodo-pago', ['qr' => route('archivos.qr'), 'banco' => $banco, 'destino' => 'PetCare'])
    <x-terminos para="veterinaria"/>
    <button class="btn full" type="submit" data-btn-pago @disabled($pendiente)><x-icon name="card"/><span data-txt="tarjeta">Pagar ahora</span><span data-txt="qr" hidden>Enviar comprobante</span><span data-txt="whatsapp" hidden>Pedir QR por WhatsApp</span></button>
    <p class="hint">PetCare verifica el pago, activa tu suscripción y te envía la factura en PDF.</p>
  </form>
</div>
@endsection
