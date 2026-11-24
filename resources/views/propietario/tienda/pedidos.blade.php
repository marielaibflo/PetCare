@extends('layouts.app')
@php($bs = fn ($n) => \App\Support\Formato::bs($n))
@section('title', 'Mis pedidos')
@section('heading', 'Mis pedidos')
@section('content')
@include('propietario.tienda._tabs')
@include('partials.pago-whatsapp')
@if($pedidos->isEmpty())
  <div class="card"><x-empty icon="doc" title="Aún no tienes pedidos" text="Cuando compres en la tienda verás aquí el estado de cada pedido."><a class="btn" href="{{ route('propietario.tienda.index') }}">Ir a la tienda</a></x-empty></div>
@else
<div class="grid">
@foreach($pedidos as $pd)
  <article class="card pad pedido">
    <header class="pedido-h"><div><b>Pedido {{ $pd->numero }}</b><small>{{ $pd->veterinaria->nombre }} · {{ $pd->created_at->format('d/m/Y H:i') }} · {{ $pd->entrega === 'domicilio' ? 'Envío a '.$pd->direccion : 'Recoger en la clínica' }}</small></div>
      <span class="chip {{ $pd->estadoClase() }}">{{ $pd->estadoTexto() }}</span></header>
    @if($pd->estado !== 'cancelado')
      @php($pasos = $pd->pasos()) @php($i = array_search($pd->estado, $pasos))
      <ol class="seguimiento">@foreach($pasos as $k => $e)<li class="{{ $k <= $i ? 'hecho' : '' }} {{ $k === $i ? 'actual' : '' }}"><i></i><span>{{ \App\Models\Pedido::ESTADOS[$e][0] }}</span></li>@endforeach</ol>
    @elseif($pd->pago?->motivo_rechazo)<p class="motivo">Motivo: {{ $pd->pago->motivo_rechazo }}</p>@endif
    <ul class="pedido-items">@foreach($pd->items as $it)<li><span>{{ $it->cantidad }} × {{ $it->nombre }}</span><b>{{ $bs($it->subtotal) }}</b></li>@endforeach</ul>
    <footer class="pedido-f"><span>@if($pd->descuento > 0)Descuento plan − {{ $bs($pd->descuento) }} · @endif @if($pd->envio > 0)Envío {{ $bs($pd->envio) }} · @endif Total <b>{{ $bs($pd->total) }}</b> · {{ $pd->pago?->metodoTexto() }}</span>
      <span class="acts">@if($pd->estado === 'pendiente_pago')
        @if($pd->pago)<a class="btn sm wa-btn" href="{{ $pd->pago->enlaceWhatsapp() }}" target="_blank" rel="noopener"><x-icon name="phone"/>WhatsApp</a>@endif
        <form method="POST" action="{{ route('propietario.tienda.cancelar', $pd) }}" data-confirm="Se cancelará el pedido {{ $pd->numero }}." data-confirm-title="Cancelar pedido" data-confirm-btn="Cancelar pedido">@csrf @method('DELETE')<button class="btn sm ghost">Cancelar</button></form>
      @endif</span></footer>
  </article>
@endforeach
</div>
@endif
@endsection
