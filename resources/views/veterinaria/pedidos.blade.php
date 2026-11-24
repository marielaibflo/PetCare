@extends('layouts.app')
@php($bs = fn ($n) => \App\Support\Formato::bs($n))
@section('title', 'Pedidos de la tienda')
@section('heading', 'Pedidos de la tienda')
@section('sub', 'Verifica el pago, prepara el pedido y márcalo como listo, en camino o entregado. El cliente recibe un aviso por WhatsApp en cada paso.')
@section('back', route('veterinaria.inicio'))
@section('acts')<a class="btn ghost" href="{{ route('veterinaria.productos.index') }}"><x-icon name="syr"/>Mis productos</a>@endsection
@section('content')
<nav class="tabs" style="margin-bottom:14px" aria-label="Filtrar pedidos">
  @foreach($filtros as $k => $t)<a class="{{ $filtro === $k ? 'on' : '' }}" href="{{ route('veterinaria.pedidos', ['estado' => $k]) }}">{{ $t }}@if($k === 'abiertos' && $abiertos) <span class="badge">{{ $abiertos }}</span>@endif</a>@endforeach
</nav>
<x-errors/>
@if($pedidos->isEmpty())
  <div class="card"><x-empty icon="cart" title="Sin pedidos en esta vista" text="Cuando un dueño compre en tu tienda, el pedido aparecerá aquí."/></div>
@else
<div class="grid">
@foreach($pedidos as $pd)
  <article class="card pad pedido">
    <header class="pedido-h"><div><b>Pedido {{ $pd->numero }}</b><small>{{ $pd->user->name }} · {{ $pd->user->telefonoVisible() }} · {{ $pd->created_at->format('d/m/Y H:i') }}@if($pd->mascota) · para {{ $pd->mascota->nombre }}@endif</small>
      <small><b>{{ $pd->entrega === 'domicilio' ? '🛵 Envío a: '.$pd->direccion.($pd->referencia ? ' ('.$pd->referencia.')' : '') : '🏥 Recoge en la clínica' }}</b>@if($pd->nota) · Nota: {{ $pd->nota }}@endif</small></div>
      <span class="chip {{ $pd->estadoClase() }}">{{ $pd->estadoTexto() }}</span></header>
    <ul class="pedido-items">@foreach($pd->items as $it)<li><span>{{ $it->cantidad }} × {{ $it->nombre }}</span><b>{{ $bs($it->subtotal) }}</b></li>@endforeach</ul>
    <footer class="pedido-f"><span>@if($pd->descuento > 0)Plan PetCare − {{ $bs($pd->descuento) }} · @endif @if($pd->envio > 0)Envío {{ $bs($pd->envio) }} · @endif Cobrar <b>{{ $bs($pd->total) }}</b> · {{ $pd->pago?->metodoTexto() }}</span>
      <span class="acts">
        @if($pd->pago?->comprobante)<a class="btn sm ghost" href="{{ route('archivos.comprobante', $pd->pago) }}" target="_blank" rel="noopener"><x-icon name="eye"/>Comprobante</a>@endif
        @if($wa = \App\Services\WhatsAppService::enlace($pd->user->telefonoInternacional(), "Hola {$pd->user->primerNombre()}, te escribo de {$vet->nombre} por tu pedido {$pd->numero}."))<a class="btn sm wa-btn" href="{{ $wa }}" target="_blank" rel="noopener"><x-icon name="phone"/>WhatsApp</a>@endif
        @if($pd->estado === 'pendiente_pago')
          <form method="POST" action="{{ route('veterinaria.pedidos.pago.aprobar', $pd) }}" data-confirm="¿Recibiste {{ $bs($pd->total) }}? Se confirmará el pedido {{ $pd->numero }}." data-confirm-title="Confirmar pago" data-confirm-btn="Confirmar pago">@csrf<button class="btn sm ok">Confirmar pago</button></form>
          <button type="button" class="btn sm danger" data-open="dlg-rp-{{ $pd->id }}">Rechazar pago</button>
        @elseif($sig = $pd->siguiente())
          <form method="POST" action="{{ route('veterinaria.pedidos.avanzar', $pd) }}">@csrf<button class="btn sm">{{ ['preparando' => 'Empezar a preparar', 'listo' => 'Listo para recoger', 'en_camino' => 'Enviar (en camino)', 'entregado' => 'Marcar entregado'][$sig] }}</button></form>
        @endif
        @if(in_array($pd->estado, ['pagado', 'preparando'], true))<button type="button" class="btn sm ghost" data-open="dlg-cp-{{ $pd->id }}">Cancelar</button>@endif
      </span></footer>
  </article>
  @push('dialogs')
    <x-dialog :id="'dlg-rp-'.$pd->id" title="Rechazar pago" icon="x">
      <form class="f" method="POST" action="{{ route('veterinaria.pedidos.pago.rechazar', $pd) }}" novalidate>@csrf<x-form-errors :for="'dlg-rp-'.$pd->id"/>
        <p class="sub" style="margin:0">El pedido {{ $pd->numero }} se cancelará y el stock volverá a tu catálogo.</p>
        <div><label>Motivo</label><textarea class="in" name="motivo" required minlength="5" maxlength="200" placeholder="Ej.: el comprobante no corresponde al monto" data-msg="Escribe por qué no se pudo verificar el pago."></textarea></div>
        <div class="md-f"><button type="button" class="btn ghost" data-close>Volver</button><button class="btn danger">Rechazar pago</button></div></form>
    </x-dialog>
    <x-dialog :id="'dlg-cp-'.$pd->id" title="Cancelar pedido" icon="x">
      <form class="f" method="POST" action="{{ route('veterinaria.pedidos.cancelar', $pd) }}" novalidate>@csrf<x-form-errors :for="'dlg-cp-'.$pd->id"/>
        <p class="sub" style="margin:0">Avisaremos al cliente. Si ya pagó, coordina la devolución con él.</p>
        <div><label>Motivo</label><textarea class="in" name="motivo" required minlength="5" maxlength="200" data-msg="Escribe el motivo de la cancelación."></textarea></div>
        <div class="md-f"><button type="button" class="btn ghost" data-close>Volver</button><button class="btn danger">Cancelar pedido</button></div></form>
    </x-dialog>
  @endpush
@endforeach
</div>
@endif
@endsection
