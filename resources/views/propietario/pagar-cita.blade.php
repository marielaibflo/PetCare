@extends('layouts.app')
@php($v = $cita->veterinaria)
@section('title', 'Pagar cita')
@section('heading', 'Pagar la cita de '.$cita->mascota->nombre)
@section('sub', $cita->servicio.' · '.\App\Support\Formato::fechaLarga($cita->fecha).' a las '.$cita->hora.' · '.$v->nombre)
@section('back', route('propietario.citas.index'))
@section('content')
@if($pago)
  <div class="card pad"><x-empty icon="check" title="{{ $pago->estado === 'aprobado' ? 'Cita pagada' : 'Pago en verificación' }}" text="{{ \App\Support\Formato::bs($pago->monto) }} · {{ $pago->metodoTexto() }}. {{ $pago->estado === 'aprobado' ? 'La clínica confirmó tu pago.' : 'La clínica revisará tu pago y te avisará por WhatsApp.' }}"><a class="btn" href="{{ route('propietario.citas.index') }}">Ver mis citas</a></x-empty></div>
@elseif(! $cita->abierta() || $monto <= 0)
  <div class="card pad"><x-empty icon="check" title="No hay nada que pagar" text="{{ $monto <= 0 ? 'Tu plan cubre todo el servicio.' : 'Esta cita ya no está activa.' }}"><a class="btn" href="{{ route('propietario.citas.index') }}">Ver mis citas</a></x-empty></div>
@else
<form class="cols" method="POST" action="{{ route('propietario.citas.pagar', $cita) }}" enctype="multipart/form-data" novalidate id="fpago" data-validar>
  @csrf
  <div class="grid" style="gap:16px">
    <x-errors/>
    <section class="card pad f">
      <h2>Forma de pago</h2>
      <p class="sub" style="margin:-4px 0 4px">El pago va directo a <b>{{ $v->nombre }}</b>; la clínica lo verifica y confirma tu cita.</p>
      @include('partials.metodo-pago', ['qr' => route('archivos.qr-veterinaria', $v), 'banco' => null, 'destino' => $v->nombre, 'monto' => $monto])
    </section>
  </div>
  <aside class="card pad resumen-pago">
    <h2>Resumen</h2>
    <div class="kv" style="padding:0">
      <div><span>Mascota</span><b>{{ $cita->mascota->nombre }}</b></div>
      <div><span>Servicio</span><b>{{ $cita->servicio }}{{ $cita->urgente ? ' · URGENTE' : '' }}</b></div>
      <div><span>Precio en la clínica</span><b>{{ \App\Support\Formato::bs($cita->monto_estimado) }}</b></div>
      <div><span>Cubre {{ $cita->mascota->afiliacionActiva?->plan?->nombre ?? 'tu plan' }}</span><b class="t-ok">− {{ \App\Support\Formato::bs($cita->cubierto_estimado ?? 0) }}</b></div>
    </div>
    <div class="total">Total a pagar <b>{{ \App\Support\Formato::bs($monto) }}</b></div>
    <button class="btn full" type="submit" data-btn-pago><x-icon name="card"/><span data-txt="tarjeta">Pagar {{ \App\Support\Formato::bs($monto) }}</span><span data-txt="qr" hidden>Enviar comprobante</span><span data-txt="whatsapp" hidden>Pedir QR por WhatsApp</span></button>
    <p class="hint">Si en la atención se usan medicamentos, la diferencia se paga en la clínica. También puedes pagar directamente en la clínica.</p>
  </aside>
</form>
@endif
@endsection
