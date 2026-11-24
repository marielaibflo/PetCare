@extends('layouts.app')
@php($s = $vet->suscripcion())
@section('title', 'Productos y tienda')
@section('heading', 'Productos y tienda')
@section('sub', 'Tu catálogo con fotos, precios y stock. Lo que marques «en la tienda» lo pueden comprar los dueños.')
@section('back', route('veterinaria.inicio'))
@section('acts')<a class="btn ghost" href="{{ route('veterinaria.pedidos') }}"><x-icon name="doc"/>Pedidos</a><button class="btn" data-open="dlg-prod-nuevo"><x-icon name="plus"/>Nuevo producto</button>@endsection
@section('content')
<div class="tienda-top">
  <div class="card pad cupo">
    <div><b>Productos a la venta</b><span class="sub">Suscripción {{ $s['nombre'] }}</span></div>
    @if($limite)
      @php($pc = min(100, $enVenta / $limite * 100))
      <div class="cupo-n"><b>{{ $enVenta }}</b> de {{ $limite }}</div>
      <div class="barra"><i style="width:{{ $pc }}%" class="{{ $pc >= 100 ? 'alto' : '' }}"></i></div>
      @if($enVenta >= $limite)<p class="hint">Llegaste al límite. <a class="lnk" href="{{ route('veterinaria.suscripcion') }}">Mejora tu suscripción</a> para vender {{ $vet->plan_suscripcion === 'inicial' ? 'hasta 20' : 'sin límite' }}.</p>@endif
    @else
      <div class="cupo-n"><b>{{ $enVenta }}</b> · ilimitados</div>
    @endif
    <p class="hint">No cuentan los productos agotados, inactivos o fuera de la tienda.</p>
  </div>
  <form class="card pad f envio-f" method="POST" action="{{ route('veterinaria.pedidos.envio') }}" novalidate>
    @csrf @method('PUT')
    <label class="cb"><input type="checkbox" name="envio_activo" value="1" @checked(old('envio_activo', $vet->envio_activo)) data-envio><span><b>Envío a domicilio</b> (además de recoger en la clínica)</span></label>
    <div class="fr" data-envio-campos @unless(old('envio_activo', $vet->envio_activo)) hidden @endunless>
      <div><label for="ev-c">Costo del envío (Bs)</label><input class="in" id="ev-c" name="envio_costo" inputmode="decimal" value="{{ old('envio_costo', $vet->envio_costo) }}" data-numero min="0" max="500" required data-msg="Escribe el costo del envío (0 si es gratis)."></div>
      <div><label for="ev-z">Zonas (opcional)</label><input class="in" id="ev-z" name="envio_zonas" maxlength="160" value="{{ old('envio_zonas', $vet->envio_zonas) }}" placeholder="Ej.: Zona Sur y Sopocachi"></div>
    </div>
    <button class="btn sm sec" type="submit">Guardar entrega</button>
  </form>
</div>
<x-errors/>
<div class="card tw"><table><thead><tr><th>Producto</th><th>Precio</th><th>Stock</th><th>Tienda</th><th>Plan PetCare</th><th></th></tr></thead><tbody>
@forelse($productos as $p)
  <tr class="{{ $p->activo ? '' : 'inact' }}"><td><div class="prod-n">@if($p->foto)<img src="{{ route('archivos.producto', $p) }}" alt="">@else<span class="prod-ph">📦</span>@endif<span><b>{{ $p->nombre }}</b><small>{{ \App\Support\Catalogo::CATEGORIAS_PRODUCTO[$p->categoria] }}{{ $p->descripcion ? ' · '.$p->descripcion : '' }}</small></span></div></td>
    <td data-l="Precio">{{ \App\Support\Formato::bs($p->precio) }}</td>
    <td data-l="Stock">@if($p->stock > 0)<b>{{ $p->stock }}</b>@if($p->stock <= 3)<span class="chip warn">Quedan pocos</span>@endif @else<span class="chip bad">Agotado</span>@endif</td>
    <td data-l="Tienda">@if(! $p->activo)<span class="chip mut">Inactivo</span>@elseif(! $p->en_tienda)<span class="chip mut">Solo uso clínico</span>@elseif($p->stock === 0)<span class="chip warn">Visible como agotado</span>@elseif(in_array($p->id, $visibles))<span class="chip ok">A la venta</span>@else<span class="chip warn" title="Tu suscripción permite {{ $limite }} productos a la venta">Oculto por el límite</span>@endif</td>
    <td data-l="Plan">@if(! $p->cubre_seguro)<span class="chip mut">No cubre</span>@elseif(empty($p->planes))<span class="chip ok">Todos los planes</span>@else<span class="chip ok">{{ $planes->whereIn('id', $p->planes)->pluck('nombre')->join(', ') }}</span>@endif</td>
    <td class="ac"><div class="acts"><button class="icb" title="Editar" aria-label="Editar" data-open="dlg-prod-{{ $p->id }}"><x-icon name="edit"/></button>
      <form method="POST" action="{{ route('veterinaria.productos.toggle', $p) }}">@csrf @method('PATCH')<button class="icb {{ $p->activo ? '' : 'ok' }}" title="{{ $p->activo ? 'Desactivar' : 'Activar' }}" aria-label="{{ $p->activo ? 'Desactivar' : 'Activar' }}"><x-icon :name="$p->activo ? 'lock' : 'check'"/></button></form></div></td></tr>
@empty
  <tr><td colspan="6"><x-empty icon="syr" title="Tu catálogo está vacío" text="Agrega los medicamentos, vacunas y productos que vendes o usas en tus atenciones."/></td></tr>
@endforelse
</tbody></table></div>
<div class="note info" style="margin-top:14px"><x-icon name="info"/><span>Los alimentos, accesorios, higiene y juguetes nunca los cubren los planes. Los medicamentos, vacunas y antiparasitarios marcados con plan tienen descuento en la tienda solo para las mascotas que eligieron tu clínica en su plan.</span></div>
@push('dialogs')
@foreach(collect([null])->concat($productos) as $p)
  @php($id = $p ? 'dlg-prod-'.$p->id : 'dlg-prod-nuevo')
  @php($mine = old('_form') === $id)
  @php($pl = $mine ? old('planes', []) : ($p?->planes ?? []))
  <x-dialog :id="$id" :title="$p ? 'Editar producto' : 'Nuevo producto'" icon="syr">
    <form class="f" method="POST" action="{{ $p ? route('veterinaria.productos.update', $p) : route('veterinaria.productos.store') }}" enctype="multipart/form-data" novalidate>
      @csrf @if($p) @method('PUT') @endif
      <x-form-errors :for="$id"/>
      <div><label>Nombre</label><input class="in" name="nombre" value="{{ $mine ? old('nombre') : $p?->nombre }}" placeholder="Ej.: Amoxicilina 500 mg (caja)" required minlength="2" maxlength="100" data-msg="Escribe el nombre del producto."></div>
      <div class="fr"><div><label>Categoría</label><select class="in" name="categoria" required data-msg="Elige la categoría del producto.">@foreach(\App\Support\Catalogo::CATEGORIAS_PRODUCTO as $k => $n)<option value="{{ $k }}" @selected(($mine ? old('categoria') : $p?->categoria) === $k)>{{ $n }}</option>@endforeach</select></div>
        <div><label>Precio (Bs)</label><input class="in" name="precio" inputmode="decimal" value="{{ $mine ? old('precio') : $p?->precio }}" required data-numero min="0.01" max="100000" data-msg="Escribe el precio del producto en bolivianos." data-msg-min="El precio debe ser mayor a 0."></div></div>
      <div class="fr"><div><label>Stock (unidades)</label><input class="in" name="stock" type="number" min="0" max="9999" value="{{ $mine ? old('stock') : ($p?->stock ?? 10) }}" required data-msg="Escribe cuántas unidades tienes (0 si está agotado)." data-msg-max="El stock máximo es 9999 unidades."><div class="hint">Con 0 se muestra «Agotado» y no cuenta para tu límite.</div></div>
        <div><label>&nbsp;</label><label class="cb"><input type="checkbox" name="en_tienda" value="1" @checked($mine ? old('en_tienda') : ($p?->en_tienda ?? true))><span>Vender en la tienda PetCare</span></label></div></div>
      <div><label>Descripción corta (opcional)</label><input class="in" name="descripcion" maxlength="160" value="{{ $mine ? old('descripcion') : $p?->descripcion }}" placeholder="Ej.: Caja de 10 comprimidos · perros y gatos"></div>
      <div><label>Foto del producto (opcional)</label><input class="in" type="file" name="foto" accept="image/jpeg,image/png,image/webp" data-max-mb="10" @if($p?->foto) data-quitar-guardada="{{ $id }}-qf" @endif>
        @if($p?->foto)<input type="hidden" name="quitar_foto" id="{{ $id }}-qf" value="0">@endif
        <div class="hint">JPG, PNG o WEBP de hasta 10 MB.</div></div>
      <label class="cb"><input type="checkbox" name="cubre_seguro" value="1" @checked($mine ? old('cubre_seguro') : ($p?->cubre_seguro ?? true))><span>Tiene descuento con los planes PetCare</span></label>
      <div><label>¿Con qué planes? (si no eliges ninguno, aplica a todos)</label><div class="cbs">@foreach($planes as $pl1)<label class="cb"><input type="checkbox" name="planes[]" value="{{ $pl1->id }}" @checked(in_array($pl1->id, array_map('intval', $pl)))>{{ $pl1->nombre }} ({{ $pl1->porcentaje }} %)</label>@endforeach</div></div>
      <div class="md-f"><button type="button" class="btn ghost" data-close>Cancelar</button><button class="btn" type="submit">Guardar</button></div>
    </form>
  </x-dialog>
@endforeach
@endpush
@endsection
