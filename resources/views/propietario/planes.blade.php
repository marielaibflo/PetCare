@extends('layouts.app')
@php($srv = array_values(array_diff(\App\Support\Catalogo::SERVICIOS, [\App\Support\Catalogo::GRAVE])))
@section('title', 'Planes de asistencia')
@section('heading', 'Planes de asistencia')
@section('sub', 'PetCare paga un porcentaje de cada atención en las veterinarias afiliadas; tú pagas el resto. Pago mensual o anual (2 meses gratis).')
@section('back', route('propietario.inicio'))
@section('content')
<div class="plans">
  @foreach($planes as $i => $p)
    @php($activos = $mascotas->filter(fn ($m) => $m->afiliacionActiva?->plan_id === $p->id))
    <div class="card plan v{{ $i % 4 }}" style="--plan:{{ $p->colorHex() }}">
      <div class="t"><span class="ti c-ok" style="background:#fff"><x-icon :name="$p->icono"/></span><b>{{ $p->nombre }}</b></div>
      <div class="pct">{{ $p->porcentaje }}<small>%</small><span>de cobertura</span></div>
      <p>{{ $p->descripcion }}</p>
      <ul>@foreach($p->beneficios ?? [] as $b)<li><x-icon name="check"/><span>{{ $b }}</span></li>@endforeach</ul>
      <div class="topes"><span>Tope mensual <b>{{ \App\Support\Formato::bs($p->tope_mensual) }}</b></span><span>Tope anual <b>{{ \App\Support\Formato::bs($p->tope_anual) }}</b></span><span>Atenciones cubiertas <b>{{ $p->limite_atenciones ? $p->limite_atenciones.' al año' : 'Sin límite' }}</b></span></div>
      @if($activos->isNotEmpty())<div class="note ok" style="padding:8px 12px"><x-icon name="check"/><span>Activo para: {{ $activos->pluck('nombre')->join(', ') }}</span></div>@endif
      <div class="pr">{{ \App\Support\Formato::bs($p->precio) }}<small> / mes</small></div>
      <a class="btn full" href="{{ route('propietario.planes.solicitar', $p) }}">Solicitar plan</a>
    </div>
  @endforeach
</div>
<div class="card pad" style="margin-top:18px"><h2 style="margin-bottom:4px">¿Cuánto cubre cada plan?</h2>
  <p class="sub" style="margin-bottom:12px">Ejemplo: una consulta de Bs 100 con el plan del 60 % → PetCare paga Bs 60 y tú Bs 40. Cada clínica fija sus propios precios.</p>
  <div class="tw"><table><thead><tr><th>Servicio</th>@foreach($planes as $p)<th>{{ $p->nombre }}</th>@endforeach</tr></thead><tbody>
    @foreach($srv as $s)<tr><td>{{ $s }}</td>@foreach($planes as $p)<td data-l="{{ $p->nombre }}">@if($p->cubre($s))<span class="chip ok">{{ $p->porcentaje }} %</span>@else<span class="chip mut">No incluido</span>@endif</td>@endforeach</tr>@endforeach
    <tr><td>Medicamentos</td>@foreach($planes as $p)<td data-l="{{ $p->nombre }}">@if($p->medicamentos === 'todos')<span class="chip ok">{{ $p->porcentaje }} %</span>@elseif($p->medicamentos === 'urgencia')<span class="chip warn">{{ $p->porcentaje }} % solo urgencias</span>@else<span class="chip mut">No incluido</span>@endif</td>@endforeach</tr>
    <tr><td>Espera para cirugías</td>@foreach($planes as $p)<td data-l="{{ $p->nombre }}">{{ $p->cubre('Cirugía / Castración') ? ($p->espera_cirugia_dias ? $p->espera_cirugia_dias.' días' : 'Sin espera') : '—' }}</td>@endforeach</tr>
    <tr><td>Adicional Cobertura Grave</td>@foreach($planes as $p)<td data-l="{{ $p->nombre }}">@if($p->permite_grave)<span class="chip p">+{{ \App\Support\Formato::bs(\App\Support\Catalogo::ADICIONAL_GRAVE['precio']) }}/mes</span>@else<span class="chip mut">No disponible</span>@endif</td>@endforeach</tr>
  </tbody></table></div>
  <div class="note info" style="margin-top:14px"><x-icon name="info"/><span><b>Cobertura Grave (opcional):</b> cubre el {{ \App\Support\Catalogo::ADICIONAL_GRAVE['porcentaje'] }} % de tratamientos de cáncer y enfermedades crónicas hasta {{ \App\Support\Formato::bs(\App\Support\Catalogo::ADICIONAL_GRAVE['tope_anual']) }} al año, después de {{ \App\Support\Catalogo::ADICIONAL_GRAVE['espera_dias'] }} días de afiliación. Ningún plan cubre enfermedades que la mascota tenía antes de afiliarse.</span></div>
</div>
@endsection
