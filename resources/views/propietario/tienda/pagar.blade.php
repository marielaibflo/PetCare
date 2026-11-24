@extends('layouts.app')
@php($bs = fn ($n) => \App\Support\Formato::bs($n))
@section('title', 'Pagar pedido')
@section('heading', 'Pagar pedido · '.$vet->nombre)
@section('sub', 'El pago va directo a la clínica; ella lo verifica y prepara tu pedido.')
@section('back', route('propietario.tienda.carrito'))
@section('content')
@php($datosPedido = ['subtotal' => $grupo['subtotal'], 'envio' => (float) $vet->envio_costo, 'descuentos' => $descuentos->map(fn ($d) => ['monto' => $d['descuento'], 'nota' => $d['nota']])])
<form class="cols" method="POST" action="{{ route('propietario.tienda.comprar', $vet) }}" enctype="multipart/form-data" novalidate id="fpago" data-validar
  data-pedido='@json($datosPedido)'>
  @csrf
  <div class="grid" style="gap:16px">
    <x-errors/>
    <section class="card pad f">
      <h2>1. Entrega</h2>
      <div class="opciones" data-campo="entrega">
        <label class="opc"><input type="radio" name="entrega" value="recoger" required data-msg="Elige cómo recibirás tu pedido." @checked(old('entrega', 'recoger') === 'recoger')><span><b>Recoger en la clínica</b><small>{{ $vet->direccion }}, {{ $vet->zona }} · {{ $vet->horario }}</small></span></label>
        @if($vet->envio_activo)
          <label class="opc"><input type="radio" name="entrega" value="domicilio" @checked(old('entrega') === 'domicilio')><span><b>Envío a domicilio · {{ $vet->envio_costo > 0 ? $bs($vet->envio_costo) : 'gratis' }}</b><small>{{ $vet->envio_zonas ?: 'La clínica te confirma el horario por WhatsApp.' }}</small></span></label>
        @endif
      </div>
      <div class="fr" data-domicilio @unless(old('entrega') === 'domicilio') hidden @endunless>
        <div><label for="pd-d">Dirección de entrega</label><input class="in" id="pd-d" name="direccion" value="{{ old('direccion', $user->direccion) }}" required minlength="8" maxlength="200" placeholder="Calle, número y zona" data-msg="Escribe la dirección de entrega (calle, número y zona)." data-msg-min="Escribe la dirección completa (calle, número y zona)."></div>
        <div><label for="pd-r">Referencia (opcional)</label><input class="in" id="pd-r" name="referencia" value="{{ old('referencia') }}" maxlength="160" placeholder="Ej.: portón verde, 2.º piso"></div>
      </div>
    </section>
    @if($mascotas->isNotEmpty())
    <section class="card pad f">
      <h2>2. ¿Para qué mascota? (opcional)</h2>
      <p class="sub" style="margin-top:-6px">Si tu mascota tiene plan en <b>{{ $vet->nombre }}</b>, descuenta medicamentos, vacunas y antiparasitarios.</p>
      <select class="in" name="mascota_id" aria-label="Mascota"><option value="">Sin mascota / sin descuento</option>@foreach($mascotas as $m)<option value="{{ $m->id }}" @selected((string) old('mascota_id') === (string) $m->id)>{{ $m->nombre }}{{ $descuentos[$m->id]['descuento'] > 0 ? ' · descuento '.$bs($descuentos[$m->id]['descuento']) : '' }}</option>@endforeach</select>
      <div class="note info" data-desc-nota hidden><x-icon name="shield"/><span></span></div>
    </section>
    @endif
    <section class="card pad f">
      <h2>{{ $mascotas->isNotEmpty() ? 3 : 2 }}. Forma de pago</h2>
      @include('partials.metodo-pago', ['qr' => route('archivos.qr-veterinaria', $vet), 'banco' => null, 'destino' => $vet->nombre, 'monto' => $grupo['subtotal']])
      <div><label for="pd-n">Nota para la clínica (opcional)</label><input class="in" id="pd-n" name="nota" value="{{ old('nota') }}" maxlength="200" placeholder="Ej.: llamar antes de llegar"></div>
    </section>
  </div>
  <aside class="card pad resumen-pago">
    <h2>Tu pedido</h2>
    <div class="kv" style="padding:0">
      @foreach($grupo['lineas'] as $l)<div><span>{{ $l['cantidad'] }} × {{ $l['producto']->nombre }}</span><b>{{ $bs($l['subtotal']) }}</b></div>@endforeach
      <div><span>Subtotal</span><b>{{ $bs($grupo['subtotal']) }}</b></div>
      <div data-fila-desc hidden><span>Descuento del plan</span><b class="t-ok" data-desc>− Bs 0</b></div>
      <div data-fila-envio hidden><span>Envío</span><b data-envio-monto>{{ $bs($vet->envio_costo) }}</b></div>
    </div>
    <div class="total">Total a pagar <b data-total>{{ $bs($grupo['subtotal']) }}</b></div>
    <x-terminos para="propietario"/>
    <button class="btn full" type="submit" data-btn-pago><x-icon name="card"/><span data-txt="tarjeta">Pagar ahora</span><span data-txt="qr" hidden>Enviar comprobante</span><span data-txt="whatsapp" hidden>Pedir QR por WhatsApp</span></button>
    <p class="hint">Te llegará la confirmación por WhatsApp y podrás seguir tu pedido en «Mis pedidos».</p>
  </aside>
</form>
@endsection
