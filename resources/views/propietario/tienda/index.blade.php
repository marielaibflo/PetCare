@extends('layouts.app')
@php($bs = fn ($n) => \App\Support\Formato::bs($n))
@section('title', 'Tienda')
@section('heading', 'Tienda PetCare')
@section('sub', 'Medicamentos, alimentos y accesorios de las veterinarias afiliadas. Recoge en la clínica o recíbelo en casa.')
@section('content')
@include('propietario.tienda._tabs')
<form class="tienda-flt" method="GET">
  <label class="search"><x-icon name="search"/><span class="sr">Buscar</span><input name="q" value="{{ $f['q'] ?? '' }}" placeholder="Buscar producto…"></label>
  <select class="in" name="categoria" aria-label="Categoría" data-autosubmit><option value="">Todas las categorías</option>@foreach(\App\Http\Controllers\Propietario\TiendaController::categorias() as $k => $n)<option value="{{ $k }}" @selected(($f['categoria'] ?? '') === $k)>{{ $n }}</option>@endforeach</select>
  <select class="in" name="veterinaria" aria-label="Clínica" data-autosubmit><option value="">Todas las clínicas</option>@foreach($clinicas as $c)<option value="{{ $c->id }}" @selected((string) ($f['veterinaria'] ?? '') === (string) $c->id)>{{ $c->nombre }}</option>@endforeach</select>
  <select class="in" name="max" aria-label="Precio" data-autosubmit><option value="">Cualquier precio</option>@foreach([50, 100, 200, 500] as $m)<option value="{{ $m }}" @selected((string) ($f['max'] ?? '') === (string) $m)>Hasta {{ $bs($m) }}</option>@endforeach</select>
  <select class="in" name="orden" aria-label="Ordenar" data-autosubmit><option value="">Por nombre</option><option value="precio" @selected(($f['orden'] ?? '') === 'precio')>Menor precio</option></select>
  <button class="btn sec">Buscar</button>
</form>
@if($productos->isEmpty())
  <div class="card"><x-empty icon="store" title="No encontramos productos" text="Prueba con otra búsqueda o categoría."/></div>
@else
<div class="vitrina">
  @foreach($productos as $p)
    @php($agotado = $p->stock === 0)
    <article class="card prod-card {{ $agotado ? 'agotado' : '' }}">
      <div class="pc-foto">@if($p->foto)<img src="{{ route('archivos.producto', $p) }}" alt="{{ $p->nombre }}" loading="lazy">@else<span>{{ ['medicamento' => '💊', 'vacuna' => '💉', 'antiparasitario' => '🛡️', 'alimento' => '🥣', 'accesorio' => '🦮', 'higiene' => '🧴', 'juguete' => '🎾'][$p->categoria] ?? '📦' }}</span>@endif
        @if($agotado)<span class="pc-tag bad">Agotado</span>@elseif($p->stock <= 3)<span class="pc-tag warn">Quedan {{ $p->stock }}</span>@endif
        @if($p->cubre_seguro && in_array($p->categoria, \App\Support\Catalogo::DESCUENTO_TIENDA, true))<span class="pc-tag plan">Descuento con plan</span>@endif</div>
      <div class="pc-body">
        <small class="pc-cat">{{ \App\Support\Catalogo::CATEGORIAS_PRODUCTO[$p->categoria] }}</small>
        <b class="pc-nom">{{ $p->nombre }}</b>
        @if($p->descripcion)<p class="pc-desc">{{ $p->descripcion }}</p>@endif
        <a class="pc-vet" href="{{ route('veterinarias.show', $p->veterinaria) }}"><x-icon name="store"/>{{ $p->veterinaria->nombre }}</a>
        <div class="pc-pie"><span class="pc-precio">{{ $bs($p->precio) }}</span>
          @if(! $agotado)
            <form method="POST" action="{{ route('propietario.tienda.agregar', $p) }}" class="pc-add">@csrf
              <input class="in" type="number" name="cantidad" value="1" min="1" max="{{ min(99, $p->stock) }}" aria-label="Cantidad" required data-msg="Elige la cantidad." data-msg-max="Solo quedan {{ $p->stock }} unidades.">
              <button class="btn sm"><x-icon name="cart"/>{{ isset($enCarrito[$p->id]) ? 'Agregar más' : 'Agregar' }}</button></form>
          @endif</div>
        @if(isset($enCarrito[$p->id]))<small class="pc-en">✓ {{ $enCarrito[$p->id] }} en tu carrito</small>@endif
      </div>
    </article>
  @endforeach
</div>
@endif
@endsection
