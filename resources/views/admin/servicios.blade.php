@extends('layouts.app')
@section('title', 'Servicios')
@section('heading', 'Servicios')
@section('sub', 'Atenciones registradas y citas solicitadas en la red de veterinarias.')
@section('acts')<x-exportar/>@endsection
@section('content')
<form class="flt" method="GET">
  <nav class="tabs" style="flex:1;min-width:240px">
    <a class="{{ $tab === 'atenciones' ? 'on' : '' }}" href="{{ route('admin.servicios', array_filter(['veterinaria' => $vet])) }}">Atenciones</a>
    <a class="{{ $tab === 'citas' ? 'on' : '' }}" href="{{ route('admin.servicios', array_filter(['tab' => 'citas', 'veterinaria' => $vet])) }}">Citas</a>
  </nav>
  <input type="hidden" name="tab" value="{{ $tab }}">
  <select class="in" name="veterinaria" aria-label="Veterinaria" data-autosubmit><option value="">Todas las veterinarias</option>@foreach($veterinarias as $v)<option value="{{ $v->id }}" @selected((string) $vet === (string) $v->id)>{{ $v->nombre }}</option>@endforeach</select>
</form>
<div class="card tw">
  @if($tab === 'atenciones')
  <table><thead><tr><th>Mascota</th><th>Fecha</th><th>Servicio</th><th>Veterinaria</th><th>Total</th><th>Cubre PetCare</th></tr></thead><tbody>
    @forelse($lista as $a)<tr><td>{{ $a->mascota->nombre }}</td><td data-l="Fecha">{{ $a->fecha->format('d/m/Y') }}</td><td data-l="Servicio">{{ $a->servicio }}{{ $a->producto ? ' · '.$a->producto : '' }}</td><td data-l="Veterinaria">{{ $a->lugar() }}</td><td data-l="Total">{{ $a->total > 0 ? \App\Support\Formato::bs($a->total) : '—' }}</td><td data-l="Cobertura">@if($a->monto_cubierto > 0)<span class="chip ok">{{ \App\Support\Formato::bs($a->monto_cubierto) }} ({{ $a->porcentaje }} %)</span>@else<span class="chip mut">Particular</span>@endif</td></tr>
    @empty<tr><td colspan="6"><x-empty icon="doc" title="Sin atenciones" text="No hay registros con este filtro."/></td></tr>@endforelse
  </tbody></table>
  @else
  <table><thead><tr><th>Mascota</th><th>Fecha y hora</th><th>Servicio</th><th>Veterinaria</th><th>Estado</th></tr></thead><tbody>
    @forelse($lista as $c)<tr><td>{{ $c->mascota->nombre }}</td><td data-l="Fecha">{{ $c->fecha->format('d/m/Y') }} · {{ $c->hora }}</td><td data-l="Servicio">{{ $c->servicio }}</td><td data-l="Veterinaria">{{ $c->veterinaria->nombre }}</td><td data-l="Estado"><x-estado :estado="$c->estado"/></td></tr>
    @empty<tr><td colspan="5"><x-empty icon="cal" title="Sin citas" text="No hay registros con este filtro."/></td></tr>@endforelse
  </tbody></table>
  @endif
  <x-pagination :p="$lista"/>
</div>
@endsection
