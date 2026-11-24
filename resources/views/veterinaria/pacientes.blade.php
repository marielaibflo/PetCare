@extends('layouts.app')
@section('title', 'Pacientes')
@section('heading', 'Pacientes')
@section('sub', 'Mascotas con citas o atenciones en tu clínica.')
@section('back', route('veterinaria.inicio'))
@section('content')
@if($afiliados->isNotEmpty())
<section style="margin-bottom:18px"><h2 style="margin-bottom:4px">Afiliados a mi clínica <span class="badge">{{ $afiliados->count() }}</span></h2>
  <p class="sub" style="margin-bottom:10px">Mascotas que eligieron tu clínica para su plan PetCare: solo aquí tienen cobertura.</p>
  <div class="card tw"><table><thead><tr><th>Mascota</th><th>Dueño</th><th>Plan</th><th>Inicio</th><th>Vence</th><th>Uso del plan</th><th>Estado</th></tr></thead><tbody>
    @foreach($afiliados as $a)@php([$et, $ec] = $a->estadoTexto())
      <tr><td><a class="lnk" href="{{ route('veterinaria.pacientes.show', $a->mascota) }}"><b>{{ $a->mascota->nombre }}</b></a><small class="blk">{{ $a->mascota->especie }} · {{ $a->mascota->raza }}</small></td>
        <td data-l="Dueño">{{ $a->mascota->propietario?->name }}<small class="blk">{{ $a->mascota->propietario?->telefonoVisible() }}</small></td>
        <td data-l="Plan">{{ $a->plan->nombre }} · {{ $a->plan->porcentaje }} %</td><td data-l="Inicio">{{ $a->fecha_inicio->format('d/m/Y') }}</td><td data-l="Vence">{{ $a->fecha_fin->format('d/m/Y') }}</td>
        <td data-l="Uso del plan">{{ $a->enCarencia() ? 'Desde el '.$a->carencia_hasta->format('d/m/Y') : 'Disponible' }}</td><td data-l="Estado"><span class="chip {{ $ec }}">{{ $et }}</span></td></tr>
    @endforeach
  </tbody></table></div>
</section>
@endif
<form class="flt" method="GET"><label class="search"><x-icon name="search"/><span class="sr">Buscar</span><input name="q" value="{{ $q }}" placeholder="Buscar por mascota, raza o propietario..."></label><button class="btn sec">Buscar</button></form>
<div class="card">
  @if($pacientes->isEmpty())<x-empty icon="pets" title="Sin pacientes" text="Las mascotas que agenden o se atiendan en tu clínica aparecerán aquí."/>
  @else<div class="lst">@foreach($pacientes as $p)
    <a class="row" href="{{ route('veterinaria.pacientes.show', $p) }}"><x-pet :m="$p" :s="46"/><div class="tx"><b>{{ $p->nombre }}</b><span>{{ $p->resumen() }} · {{ $p->raza }} · Propietario: {{ $p->propietario->name }}</span></div><div class="ra"><x-plan-chip :m="$p"/><span class="chip p">{{ $p->atenciones_count }} {{ $p->atenciones_count === 1 ? 'atención' : 'atenciones' }}</span></div></a>
  @endforeach</div>@endif
</div>
@endsection
