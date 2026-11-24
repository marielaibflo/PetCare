@extends('layouts.app')
@section('title', $v->nombre)
@section('heading', $v->nombre)
@section('sub', 'Zona '.$v->zona.', La Paz')
@section('back', $esDueno ? route('propietario.veterinarias.index') : ($propia ? route('veterinaria.clinica') : url()->previous()))
@section('acts')
  @if($esDueno && $v->activa)<button type="button" class="btn" data-open="dlg-cita" data-cita-vet="{{ $v->id }}"><x-icon name="cal"/>Agendar cita aquí</button>@endif
  @if($propia)<a class="btn sec" href="{{ route('veterinaria.clinica') }}"><x-icon name="edit"/>Editar mi página</a>@endif
@endsection
@section('content')
@php($wa = \App\Services\WhatsAppService::enlace($v->whatsappCitas(), "Hola {$v->nombre}, les escribo desde PetCare 🐾"))
@if($propia)<div class="card pad hint" style="margin-bottom:14px">Así ven los dueños de mascotas la página de tu clínica.</div>@endif

<div class="vd-top card">
  <div class="vd-logo"><x-sello :v="$v" :s="84"/></div>
  <div class="vd-info">
    <div class="vd-badges">
      <span class="chip p">{{ $v->plan_suscripcion === 'plus' ? 'Clínica Plus' : 'Acepta planes PetCare' }}</span>
      @if($solicitudes)<span class="chip info">🔥 {{ $solicitudes }} {{ $solicitudes === 1 ? 'cita' : 'citas' }} en 90 días</span>@endif
    </div>
    <div class="vd-rate">@if($v->resenas)<span class="estrellas" style="--v:{{ $v->valoracion }}" aria-hidden="true"></span><b>{{ number_format($v->valoracion, 1) }}</b><span>· {{ $v->resenas }} {{ $v->resenas === 1 ? 'calificación' : 'calificaciones' }}</span>@else<b>Nueva en PetCare</b><span>· aún sin calificaciones</span>@endif</div>
    @if($v->descripcion)<p class="vd-desc">{{ $v->descripcion }}</p>@endif
    <div class="vd-btns">
      <a class="btn sec" href="{{ $v->enlaceMapa() }}" target="_blank" rel="noopener"><x-icon name="pin"/>Cómo llegar</a>
      @if($wa)<a class="btn wa-btn" href="{{ $wa }}" target="_blank" rel="noopener"><x-icon name="phone"/>WhatsApp</a>@endif
      <a class="btn ghost" href="tel:{{ $v->telefono }}"><x-icon name="phone"/>Llamar</a>
    </div>
  </div>
</div>

@if($v->promocion)<div class="vd-promo"><b>🎁 Promoción del mes</b><span>{{ $v->promocion }}</span></div>@endif

<div class="vd-grid">
  <section class="card">
    <h2 class="vd-h">Ubicación</h2>
    <div class="vd-mapa"><iframe src="{{ $v->mapaEmbebido() }}" title="Mapa de {{ $v->nombre }}" loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe></div>
    <div class="kv">
      <div><span>Dirección</span><b>{{ $v->direccion }}, {{ $v->zona }}</b></div>
      <div><span>Horario</span><b>{{ $v->horario }}</b></div>
      <div><span>Teléfono</span><b>{{ $v->telefono }}</b></div>
      @if($v->metodos_pago)<div><span>Formas de pago</span><b>{{ implode(' · ', $v->metodos_pago) }}</b></div>@endif
    </div>
  </section>

  <section class="card pad">
    <h2 class="vd-h" style="padding:0">Servicios y precios</h2>
    <div class="vd-servs">
      @foreach($v->servicios as $s)<div class="vd-serv"><x-svc :servicio="$s"/><span>{{ $s }}</span><b>{{ $v->precio($s) !== null ? \App\Support\Formato::bs($v->precio($s)) : 'Consultar' }}</b></div>@endforeach
    </div>
    @if($v->servicios_especiales)
      <h3 class="vd-h3">Servicios especiales</h3>
      <div class="vd-chips">@foreach($v->servicios_especiales as $e)<span class="chip ok">✓ {{ $e }}</span>@endforeach</div>
    @endif
    @unless($v->atiendePlanes())<p class="hint" style="margin-top:12px">Esta clínica todavía no atiende con planes PetCare: las atenciones son particulares.</p>@endunless
  </section>
</div>

<section class="card pad vd-sec">
  <h2 class="vd-h" style="padding:0">Nuestras instalaciones</h2>
  @if($fotos->isEmpty())
    <x-empty icon="store" title="Sin fotos todavía" text="La clínica aún no subió fotos de sus instalaciones."/>
  @else
    <div class="vd-galeria">@foreach($fotos as $f)<a href="{{ route('archivos.foto-veterinaria', $f) }}" target="_blank" rel="noopener"><img src="{{ route('archivos.foto-veterinaria', $f) }}" alt="{{ $f->titulo ?: 'Instalaciones de '.$v->nombre }}" loading="lazy">@if($f->titulo)<span>{{ $f->titulo }}</span>@endif</a>@endforeach</div>
  @endif
</section>

<section class="card pad vd-sec">
  <h2 class="vd-h" style="padding:0">Catálogo de productos</h2>
  @if($productos->isEmpty())
    <x-empty icon="store" title="Catálogo vacío" text="La clínica aún no publicó productos."/>
  @else
    <nav class="vd-tabs">@foreach($productos as $cat => $ps)<a href="#cat-{{ $cat }}">{{ \App\Support\Catalogo::CATEGORIAS_PRODUCTO[$cat] ?? ucfirst($cat) }} <span>{{ $ps->count() }}</span></a>@endforeach</nav>
    @foreach($productos as $cat => $ps)
      <h3 class="vd-h3" id="cat-{{ $cat }}">{{ \App\Support\Catalogo::CATEGORIAS_PRODUCTO[$cat] ?? ucfirst($cat) }}</h3>
      <div class="vd-prods">
        @foreach($ps as $p)
          <div class="vd-prod">
            <div class="vd-pimg">@if($p->foto)<img src="{{ route('archivos.producto', $p) }}" alt="{{ $p->nombre }}" loading="lazy">@else<span class="ph cat-{{ $cat }}">{{ ['medicamento' => '💊', 'vacuna' => '💉', 'antiparasitario' => '🛡️', 'alimento' => '🦴', 'accesorio' => '🎀', 'higiene' => '🧴', 'juguete' => '🎾', 'insumo' => '🩹'][$cat] ?? '📦' }}</span>@endif</div>
            <b>{{ $p->nombre }}</b>
            @if($p->descripcion)<small>{{ $p->descripcion }}</small>@endif
            <div class="vd-pp"><span>{{ \App\Support\Formato::bs($p->precio) }}</span>
              @if(in_array($cat, \App\Support\Catalogo::PRODUCTOS_TIENDA, true))<i class="chip mut">Tienda</i>@elseif($p->cubre_seguro && $v->atiendePlanes())<i class="chip ok">Cubre plan</i>@endif</div>
          </div>
        @endforeach
      </div>
    @endforeach
    <p class="hint" style="margin-top:12px">Los productos de tienda (alimentos, accesorios, higiene y juguetes) no los cubren los planes PetCare.</p>
  @endif
</section>

<section class="card pad vd-sec">
  <h2 class="vd-h" style="padding:0">Calificaciones de los dueños</h2>
  @if($resenas->isEmpty())
    <x-empty icon="star" title="Sin calificaciones" text="Después de una cita atendida, los dueños pueden calificar a la clínica."/>
  @else
    <div class="vd-res">
      <div class="vd-res-n"><b>{{ number_format($v->valoracion, 1) }}</b><span class="estrellas" style="--v:{{ $v->valoracion }}" aria-hidden="true"></span><small>{{ $v->resenas }} {{ $v->resenas === 1 ? 'calificación' : 'calificaciones' }}</small></div>
      <div class="vd-barras">@for($i = 5; $i >= 1; $i--)@php($n = $distribucion[$i] ?? 0)<div><span>{{ $i }} ★</span><i><em style="width:{{ $v->resenas ? round($n * 100 / $v->resenas) : 0 }}%"></em></i><small>{{ $n }}</small></div>@endfor</div>
    </div>
    <div class="vd-comentarios">
      @foreach($resenas as $r)
        <div class="vd-com"><div><b>{{ $r->user?->primerNombre() ?? 'Dueño PetCare' }}</b><span class="estrellas-mini">{{ str_repeat('★', $r->estrellas) }}{{ str_repeat('☆', 5 - $r->estrellas) }}</span><small>{{ $r->created_at->diffForHumans() }}</small></div>@if($r->comentario)<p>{{ $r->comentario }}</p>@endif</div>
      @endforeach
    </div>
  @endif
</section>
@endsection
