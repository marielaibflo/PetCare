@extends('layouts.app')
@php($bs = fn ($n) => \App\Support\Formato::bs($n))
@section('title', 'Carrito')
@section('heading', 'Mi carrito')
@section('sub', 'Cada clínica cobra y entrega lo suyo: tu carrito se paga por separado en cada clínica.')
@section('content')
@include('propietario.tienda._tabs')
<x-errors/>
@if($grupos->isEmpty())
  <div class="card"><x-empty icon="cart" title="Tu carrito está vacío" text="Agrega productos desde la tienda."><a class="btn" href="{{ route('propietario.tienda.index') }}">Ir a la tienda</a></x-empty></div>
@else
  @foreach($grupos as $g)
    <section class="card carro-g">
      <header class="carro-h"><x-sello :v="$g['vet']" :s="38"/><div><b>{{ $g['vet']->nombre }}</b><small>{{ $g['vet']->zona }} · {{ $g['vet']->envio_activo ? 'Recoger o envío a domicilio ('.($g['vet']->envio_costo > 0 ? $bs($g['vet']->envio_costo) : 'gratis').')' : 'Solo recoger en la clínica' }}</small></div></header>
      <div class="lst">@foreach($g['lineas'] as $l)@php($p = $l['producto'])
        <div class="row carro-row {{ $l['disponible'] ? '' : 'sin-stock' }}">
          <div class="pc-mini">@if($p->foto)<img src="{{ route('archivos.producto', $p) }}" alt="">@else📦@endif</div>
          <div class="tx"><b>{{ $p->nombre }}</b><span>{{ $bs($p->precio) }} c/u @unless($l['disponible'])· <b class="txt-bad">Sin stock suficiente (quedan {{ $p->stock }})</b>@endunless</span></div>
          <form method="POST" action="{{ route('propietario.tienda.cambiar', $p) }}" class="cant-f">@csrf @method('PATCH')
            <input class="in" type="number" name="cantidad" value="{{ $l['cantidad'] }}" min="0" max="99" aria-label="Cantidad de {{ $p->nombre }}" data-autosubmit></form>
          <b class="carro-sub">{{ $bs($l['subtotal']) }}</b>
          <form method="POST" action="{{ route('propietario.tienda.cambiar', $p) }}">@csrf @method('PATCH')<input type="hidden" name="cantidad" value="0"><button class="icb bad" title="Quitar" aria-label="Quitar {{ $p->nombre }}"><x-icon name="trash"/></button></form>
        </div>
      @endforeach</div>
      <footer class="carro-f"><span>Subtotal <b>{{ $bs($g['subtotal']) }}</b></span>
        <a class="btn" href="{{ route('propietario.tienda.checkout', $g['vet']) }}">Pagar en {{ $g['vet']->nombre }}</a></footer>
    </section>
  @endforeach
@endif
@endsection
