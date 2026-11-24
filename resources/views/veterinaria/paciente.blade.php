@extends('layouts.app')
@php($a = $mascota->afiliacionActiva)
@section('title', 'Historial de atenciones')
@section('heading', 'Historial de atenciones')
@section('back', route('veterinaria.pacientes.index'))
@section('content')
<div class="det">
  <div class="grid">
    <div class="card det-h"><x-pet :m="$mascota" :s="110"/><div><h2>{{ $mascota->nombre }}</h2><div class="sub">{{ $mascota->resumen() }} · {{ $mascota->raza }}<br>Propietario: {{ $mascota->propietario->name }}<br>Cel. {{ $mascota->propietario->telefonoVisible() }}</div>
      @if($wa = \App\Services\WhatsAppService::enlace($mascota->propietario->telefonoInternacional()))<a class="btn sm ghost" style="margin-top:8px" href="{{ $wa }}" target="_blank" rel="noopener"><x-icon name="phone"/>WhatsApp</a>@endif</div></div>
    <div class="card kv">
      <div><span>Peso</span><b>{{ $mascota->peso }} kg</b></div><div><span>Sexo</span><b>{{ $mascota->sexo }}</b></div><div><span>Esterilizado</span><b>{{ $mascota->esterilizado ? 'Sí' : 'No' }}</b></div>
      <div><span>Alergias</span><b>{{ $mascota->alergias ?: 'Ninguna registrada' }}</b></div>
      <div><span>Plan</span><b>{{ $a ? $a->plan->nombre.' · '.$a->plan->porcentaje.' %' : 'Sin plan' }}{{ $a?->cobertura_grave ? ' + Grave' : '' }}</b></div>
      @if($a)<div><span>Cubre</span><b style="font-weight:600;font-size:13px">{{ implode(', ', $a->plan->cobertura) }}</b></div><div><span>Vigente hasta</span><b>{{ $a->fecha_fin->format('d/m/Y') }}</b></div>@endif
    </div>
  </div>
  <div class="grid">
    @if($dosis->isNotEmpty())<section><h2 style="margin-bottom:10px">Próximas dosis</h2><div class="card lst">@foreach($dosis as $x)@include('partials.dosis-row', ['agendar' => false])@endforeach</div></section>@endif
    <section><h2 style="margin-bottom:10px">Atenciones</h2><div class="card">@include('partials.historial', ['atenciones' => $mascota->atenciones])</div></section>
    <div style="display:flex;gap:10px;flex-wrap:wrap"><a class="btn" href="{{ route('veterinaria.atenciones.create', ['mascota' => $mascota->id]) }}"><x-icon name="plus"/>Registrar nueva atención</a>
      <x-libreta-visor :mascota="$mascota" :carnet="$carnet" :imprimir="route('veterinaria.pacientes.cartilla', $mascota)" boton="Ver cartilla"/>
      <a class="btn ghost" href="{{ route('veterinaria.pacientes.cartilla', $mascota) }}"><x-icon name="print"/>Imprimir cartilla</a></div>
  </div>
</div>
@endsection
