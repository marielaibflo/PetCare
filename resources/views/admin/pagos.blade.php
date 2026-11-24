@extends('layouts.app')
@section('title', 'Pagos')
@section('heading', 'Verificación de pagos')
@section('sub', 'Pagos de planes y suscripciones (tarjeta, QR con comprobante o WhatsApp). Revisa el comprobante y aprueba: se activa el plan o la suscripción y el cliente recibe su factura en PDF.')
@section('acts')<x-exportar/>@endsection
@section('content')
<nav class="tabs" style="margin-bottom:14px" aria-label="Filtrar pagos">
  @foreach($filtros as $k => $t)<a class="{{ $filtro === $k ? 'on' : '' }}" href="{{ route('admin.pagos.index', ['estado' => $k]) }}">{{ $t }}@if($k === 'pendiente' && $pendientes) <span class="badge">{{ $pendientes }}</span>@endif</a>@endforeach
</nav>
<div class="card">
  @if($pagos->isEmpty())<x-empty icon="doc" title="Sin pagos en esta vista" text="Los pagos de planes y suscripciones aparecerán aquí."/>
  @else
  <div class="lst">@foreach($pagos as $p)
    <div class="row pago-row {{ (string) request('ver') === (string) $p->id ? 'resaltado' : '' }}" id="pago-{{ $p->id }}"><span class="ti {{ $p->concepto === 'plan' ? 'c-ok' : 'c-p' }}"><x-icon :name="$p->concepto === 'plan' ? 'shield' : 'store'"/></span>
      <div class="tx"><b>{{ \App\Support\Formato::bs($p->monto) }} · {{ $p->detalle() }}</b>
        <span>{{ $p->user->name }} (<x-correo :email="$p->user->email" :asunto="'PetCare – Pago '.($p->numero ?: 'N.º '.$p->id)"/>) · {{ $p->facturar_a }} · CI/NIT {{ $p->documento }} · {{ $p->metodoTexto() }}{{ $p->titular ? ' · titular '.$p->titular : '' }} · {{ $p->created_at->format('d/m/Y H:i') }}</span>
        @if($p->numero)<span>Factura {{ $p->numero }} · revisado el {{ $p->revisado_at?->format('d/m/Y H:i') }}</span>@endif
        @if($p->estado === 'rechazado')<span class="motivo">Motivo: {{ $p->motivo_rechazo }}</span>@endif
      </div>
      <div class="ra">
        <span class="chip {{ \App\Models\Pago::ESTADOS[$p->estado] }}">{{ ucfirst($p->estado) }}</span>
        @if($p->comprobante)<a class="btn sm ghost" href="{{ route('archivos.comprobante', $p) }}" target="_blank" rel="noopener" data-visor="Comprobante · {{ $p->user->name }} · {{ \App\Support\Formato::bs($p->monto) }}"><x-icon name="eye"/>Ver comprobante</a>@endif
        @if($p->estado === 'pendiente')
          <form method="POST" action="{{ route('admin.pagos.aprobar', $p) }}" data-confirm="¿Verificaste que llegaron {{ \App\Support\Formato::bs($p->monto) }} a la cuenta? Se activará: {{ $p->detalle() }}." data-confirm-title="Aprobar pago" data-confirm-btn="Aprobar">@csrf @method('PATCH')<button class="btn sm ok">Aprobar</button></form>
          <button type="button" class="btn sm danger" data-open="dlg-rechazar-pago-{{ $p->id }}">Rechazar</button>
        @elseif($p->estado === 'aprobado')
          <a class="btn sm sec" href="{{ route('admin.pagos.pdf', $p) }}" target="_blank"><x-icon name="down"/>Descargar factura</a>
          <form method="POST" action="{{ route('admin.pagos.reenviar', $p) }}">@csrf<button class="btn sm ghost" title="Reenviar la factura por correo"><x-icon name="mail"/></button></form>
        @endif
      </div>
    </div>
  @endforeach</div>
  @endif
  <x-pagination :p="$pagos"/>
</div>
@push('dialogs')
@foreach($pagos->where('estado', 'pendiente') as $p)
  <x-dialog :id="'dlg-rechazar-pago-'.$p->id" title="Rechazar pago" icon="x">
    <form class="f" method="POST" action="{{ route('admin.pagos.rechazar', $p) }}" novalidate>
      @csrf @method('PATCH')
      <x-form-errors :for="'dlg-rechazar-pago-'.$p->id"/>
      <p class="sub">{{ $p->user->name }} · {{ \App\Support\Formato::bs($p->monto) }} · {{ $p->detalle() }}</p>
      <div><label class="req">Motivo (se enviará al cliente)</label><textarea class="in" name="motivo" required minlength="5" maxlength="200" data-msg="Escribe el motivo del rechazo (al menos 5 caracteres)." placeholder="Ej.: El monto transferido no coincide; recibimos Bs 70.">{{ old('_form') === 'dlg-rechazar-pago-'.$p->id ? old('motivo') : '' }}</textarea></div>
      <div class="md-f"><button type="button" class="btn ghost" data-close>Volver</button><button class="btn danger" type="submit">Rechazar pago</button></div>
    </form>
  </x-dialog>
@endforeach
@endpush
@endsection
