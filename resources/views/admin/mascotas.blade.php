@extends('layouts.app')
@section('title', 'Mascotas')
@section('heading', 'Mascotas')
@section('sub', $mascotas->total().' mascotas · los datos los registra cada propietario')
@section('acts')<x-exportar/>@endsection
@section('content')
@include('admin.buscar', ['ph' => 'Buscar por mascota, raza o propietario...'])
<div class="card tw"><table><thead><tr><th>Mascota</th><th>Especie</th><th>Raza</th><th>Edad</th><th>Propietario</th><th>Plan</th><th>Veterinaria del plan</th><th>Inicio</th><th>Vence</th><th>Registrada</th></tr></thead><tbody>
@forelse($mascotas as $m)
  <tr><td>{{ $m->nombre }}</td><td data-l="Especie">{{ $m->especie }}</td><td data-l="Raza">{{ $m->raza }}</td><td data-l="Edad">{{ $m->edad() }}</td><td data-l="Propietario">{{ $m->propietario->name }}</td><td data-l="Plan"><x-plan-chip :m="$m"/></td><td data-l="Veterinaria">{{ $m->afiliacionActiva?->veterinaria?->nombre ?? '—' }}</td><td data-l="Inicio">{{ $m->afiliacionActiva?->fecha_inicio->format('d/m/Y') ?? '—' }}</td><td data-l="Vence">{{ $m->afiliacionActiva?->fecha_fin->format('d/m/Y') ?? '—' }}</td><td data-l="Registrada">{{ $m->created_at->format('d/m/Y') }}</td></tr>
@empty
  <tr><td colspan="10"><x-empty icon="pets" title="Sin resultados" text="Prueba con otra búsqueda."/></td></tr>
@endforelse
</tbody></table><x-pagination :p="$mascotas"/></div>
@endsection
