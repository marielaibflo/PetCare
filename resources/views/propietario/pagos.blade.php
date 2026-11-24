@extends('layouts.app')
@section('title', 'Mis pagos')
@section('heading', 'Mis pagos')
@section('sub', 'Solicitudes de planes y su estado de verificación.')
@section('back', route('propietario.inicio'))
@section('acts')<a class="btn" href="{{ route('propietario.planes.index') }}"><x-icon name="plus"/>Solicitar un plan</a>@endsection
@section('content')
@include('partials.pago-whatsapp')
<div class="card">
  @if($pagos->isEmpty())
    <x-empty icon="doc" title="Aún no tienes pagos" text="Cuando solicites un plan, verás aquí el estado de tu pago y podrás descargar la factura."/>
  @else
  <div class="lst">@foreach($pagos as $p)
    <div class="row"><span class="ti {{ $p->estado === 'aprobado' ? 'c-ok' : ($p->estado === 'rechazado' ? 'c-b' : 'c-w') }}"><x-icon name="doc"/></span>
      <div class="tx"><b>{{ $p->detalle() }}</b>
        <span>{{ \App\Support\Formato::bs($p->monto) }} · {{ $p->metodoTexto() }} · enviado el {{ $p->created_at->format('d/m/Y H:i') }}{{ $p->numero ? ' · Factura '.$p->numero : '' }}</span>
        @if($p->estado === 'rechazado')<span class="motivo">Motivo: {{ $p->motivo_rechazo }}</span>@endif
        @if($p->estado === 'pendiente')<span class="motivo info">Pendiente: concluye el pago por WhatsApp. Cuando PetCare lo verifique, se activa el plan.</span>@endif
      </div>
      <div class="ra"><span class="chip {{ \App\Models\Pago::ESTADOS[$p->estado] }}">{{ ucfirst($p->estado) }}</span>
        @if($p->estado === 'pendiente')<a class="btn sm wa-btn" href="{{ $p->enlaceWhatsapp() }}" target="_blank" rel="noopener"><x-icon name="phone"/>WhatsApp</a>@endif
        @if($p->estado === 'aprobado')<a class="btn sm sec" href="{{ route('propietario.pagos.pdf', $p) }}" target="_blank"><x-icon name="down"/>Descargar factura</a>@endif
        @if($p->estado === 'rechazado' && $p->plan)<a class="btn sm" href="{{ route('propietario.planes.solicitar', [$p->plan, 'mascota' => $p->mascota_id]) }}">Volver a enviar</a>@endif
      </div>
    </div>
  @endforeach</div>
  @endif
</div>
@endsection
