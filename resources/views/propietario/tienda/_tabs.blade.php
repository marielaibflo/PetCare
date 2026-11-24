@php($nCarro = app(\App\Services\TiendaService::class)->cantidadEnCarrito())
<nav class="tabs tienda-tabs" aria-label="Tienda">
  <a class="{{ request()->routeIs('propietario.tienda.index') ? 'on' : '' }}" href="{{ route('propietario.tienda.index') }}"><x-icon name="store"/>Productos</a>
  <a class="{{ request()->routeIs('propietario.tienda.carrito', 'propietario.tienda.checkout') ? 'on' : '' }}" href="{{ route('propietario.tienda.carrito') }}"><x-icon name="cart"/>Carrito @if($nCarro)<span class="badge">{{ $nCarro }}</span>@endif</a>
  <a class="{{ request()->routeIs('propietario.tienda.pedidos') ? 'on' : '' }}" href="{{ route('propietario.tienda.pedidos') }}"><x-icon name="doc"/>Mis pedidos</a>
</nav>
