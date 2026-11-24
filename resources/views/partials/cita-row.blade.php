@php
  $p = $c->mascota;
  $dueno = $p->propietario;
  $wa = $ctx === 'vet'
      ? \App\Services\WhatsAppService::enlace($dueno?->telefonoInternacional(), "Hola {$dueno?->primerNombre()}, le escribimos de {$c->veterinaria->nombre} por la cita de {$p->nombre} del {$c->fecha->format('d/m/Y')} a las {$c->hora}.")
      : \App\Services\WhatsAppService::enlace($c->veterinaria->whatsappInternacional(), "Hola, escribo por la cita de {$p->nombre} ({$c->servicio}) del {$c->fecha->format('d/m/Y')} a las {$c->hora}.");
  $info = "{$p->nombre} · {$c->servicio} · {$c->fecha->format('d/m/Y')} {$c->hora}";
  $pg = $c->relationLoaded('pagos') ? $c->pagoVigente() : null;
  $debe = $c->porPagar();
@endphp
<div class="row{{ $c->urgente && $c->abierta() ? ' urgente' : '' }}">
  @if($ctx === 'vet')<span class="tm">{{ $c->hora }}</span>@else<x-svc :servicio="$c->servicio"/>@endif
  <div class="tx">
    @if($ctx === 'vet')
      <b>@if($c->urgente)<span class="tag-urg">🚨 URGENTE</span> @endif{{ $p->nombre }} · {{ $c->servicio }}</b>
      <span>{{ $c->fecha->format('d/m/Y') }} · Propietario: {{ $dueno?->name ?? '—' }}{{ $c->nota ? ' · '.$c->nota : '' }}</span>
    @else
      <b>@if($c->urgente)<span class="tag-urg">🚨 URGENTE</span> @endif{{ $c->servicio }} · {{ $p->nombre }}</b>
      <span>{{ \App\Support\Formato::fechaLarga($c->fecha) }} · {{ $c->hora }} · {{ $c->veterinaria->nombre }}{{ $c->nota ? ' · '.$c->nota : '' }}</span>
    @endif
    @if($c->monto_estimado !== null && $c->estado !== 'cancelada')<span class="monto-cita">Precio Bs {{ number_format($c->monto_estimado, 2, ',', '.') }}@if($c->cubierto_estimado > 0) · cubre el plan Bs {{ number_format($c->cubierto_estimado, 2, ',', '.') }}@endif · {{ $ctx === 'vet' ? 'cobrar' : 'pagas' }} <b>Bs {{ number_format($c->monto_estimado - (float) $c->cubierto_estimado, 2, ',', '.') }}</b> (estimado)</span>@endif
    @if($c->estado === 'rechazada' && $c->motivo_rechazo)<span class="motivo">Motivo del rechazo: {{ $c->motivo_rechazo }}</span>@endif
    @if($pg)
      <span class="pago-cita {{ $pg->estado }}"><x-icon name="{{ $pg->estado === 'aprobado' ? 'check' : 'clock' }}"/>{{ $pg->estado === 'aprobado' ? 'Pagado' : 'Pago por verificar' }} · {{ \App\Support\Formato::bs($pg->monto) }} · {{ $pg->metodoTexto() }}
        @if($pg->comprobante)· <a class="lnk" href="{{ route('archivos.comprobante', $pg) }}" target="_blank" rel="noopener" data-visor="Comprobante · {{ $info }}">ver comprobante</a>@endif</span>
    @elseif($ctx === 'owner' && $c->estado === 'pendiente')<span class="motivo info">Esperando que la clínica acepte la cita.</span>@endif
  </div>
  <div class="ra">
    <x-estado :estado="$c->estado"/>
    @if($ctx === 'owner' && $c->abierta() && ! $pg && $debe > 0)
      <a class="btn sm" href="{{ route('propietario.citas.pago', $c) }}"><x-icon name="card"/>Pagar {{ \App\Support\Formato::bs($debe) }}</a>
    @endif
    @if($ctx === 'vet' && $pg && $pg->estado === 'pendiente')
      <form method="POST" action="{{ route('veterinaria.citas.pago.aprobar', $pg) }}">@csrf @method('PATCH')<button class="btn sm ok"><x-icon name="check"/>Confirmar pago</button></form>
      <button type="button" class="btn sm ghost" data-open="dlg-rechazar-pago" data-rpago-url="{{ route('veterinaria.citas.pago.rechazar', $pg) }}" data-rpago-info="{{ $info }} · {{ \App\Support\Formato::bs($pg->monto) }}">Rechazar pago</button>
    @endif
    @if($wa && $c->estado !== 'cancelada')<a class="icb wa" href="{{ $wa }}" target="_blank" rel="noopener" title="Escribir por WhatsApp" aria-label="Escribir por WhatsApp"><x-icon name="phone"/></a>@endif
    @if($ctx === 'owner')
      @if($c->abierta())
        <form method="POST" action="{{ route('propietario.citas.cancelar', $c) }}" data-confirm="La cita se marcará como cancelada y el horario quedará libre." data-confirm-title="Cancelar cita" data-confirm-btn="Cancelar cita">@csrf @method('PATCH')<button class="btn sm ghost">Cancelar</button></form>
      @elseif($c->estado === 'atendida')
        @if($c->resena)<span class="estrellas-mini" title="Tu calificación">{{ str_repeat('★', $c->resena->estrellas) }}{{ str_repeat('☆', 5 - $c->resena->estrellas) }}</span>
        @else<button type="button" class="btn sm sec" data-open="dlg-calificar" data-calificar-url="{{ route('propietario.citas.calificar', $c) }}" data-calificar-info="{{ $c->veterinaria->nombre }} · {{ $info }}">⭐ Calificar</button>@endif
      @elseif($c->estado === 'rechazada')
        <button type="button" class="btn sm sec" data-open="dlg-cita" data-cita-mascota="{{ $p->id }}" data-cita-servicio="{{ $c->servicio }}" data-cita-nota="{{ $c->nota }}">Agendar otra</button>
      @endif
    @else
      @if($c->estado === 'pendiente')
        <form method="POST" action="{{ route('veterinaria.citas.confirmar', $c) }}">@csrf @method('PATCH')<button class="btn sm ok">Aceptar</button></form>
      @endif
      @if($c->abierta())
        @if($c->fecha->lte(today()))
          <a class="btn sm" href="{{ route('veterinaria.atenciones.create', ['cita' => $c->id]) }}">Atender</a>
          <form method="POST" action="{{ route('veterinaria.citas.atendida', $c) }}" data-confirm="Se marcará la cita de {{ $p->nombre }} como atendida sin registrar el detalle clínico. Si quieres guardar diagnóstico, costos y cobertura, usa «Atender»." data-confirm-title="Marcar como atendida" data-confirm-btn="Marcar atendida">@csrf @method('PATCH')<button class="btn sm ghost">Marcar atendida</button></form>
        @endif
        @if($c->estado === 'confirmada')
          <form method="POST" action="{{ route('veterinaria.citas.pendiente', $c) }}">@csrf @method('PATCH')<button class="btn sm ghost" title="Volver a pendiente">Pendiente</button></form>
        @endif
        <button type="button" class="btn sm danger" data-open="dlg-rechazar" data-rechazar-url="{{ route('veterinaria.citas.rechazar', $c) }}" data-rechazar-info="{{ $info }}">Rechazar</button>
      @elseif($c->estado === 'rechazada')
        <form method="POST" action="{{ route('veterinaria.citas.pendiente', $c) }}">@csrf @method('PATCH')<button class="btn sm ghost">Volver a pendiente</button></form>
      @endif
    @endif
  </div>
</div>
@if($ctx === 'owner')
  @once
  @push('dialogs')
  <x-dialog id="dlg-calificar" title="Calificar la atención" icon="star">
    @php($clUrl = old('_form') === 'dlg-calificar' ? old('_calificar_url') : '')
    <form class="f" method="POST" action="{{ $clUrl }}" id="form-calificar" novalidate>
      @csrf
      <input type="hidden" name="_calificar_url" id="cl-url" value="{{ $clUrl }}">
      <x-form-errors for="dlg-calificar"/>
      <p class="sub" id="calificar-info"></p>
      <div><label>¿Cómo te atendieron?</label>
        <div class="estrellas-in">@for($i = 5; $i >= 1; $i--)<input type="radio" id="cl-{{ $i }}" name="estrellas" value="{{ $i }}" required data-msg="Elige de 1 a 5 estrellas." data-label="calificación" @checked(old('estrellas') == $i)><label for="cl-{{ $i }}" title="{{ $i }} de 5">★</label>@endfor</div></div>
      <div><label for="cl-c">Comentario (opcional, lo verán otros dueños)</label>
        <textarea class="in" id="cl-c" name="comentario" maxlength="300" placeholder="Ej.: Muy amables, explicaron todo y el lugar estaba limpio.">{{ old('_form') === 'dlg-calificar' ? old('comentario') : '' }}</textarea></div>
      <div class="md-f"><button type="button" class="btn ghost" data-close>Volver</button><button class="btn" type="submit">Enviar calificación</button></div>
    </form>
  </x-dialog>
  @endpush
  @endonce
@endif
@if($ctx === 'vet')
  @once
  @push('dialogs')
  <x-dialog id="dlg-rechazar-pago" title="Rechazar pago" icon="x">
    @php($rpUrl = old('_form') === 'dlg-rechazar-pago' ? old('_rpago_url') : '')
    <form class="f" method="POST" action="{{ $rpUrl }}" id="form-rpago" novalidate>
      @csrf @method('PATCH')
      <input type="hidden" name="_rpago_url" id="rp-url" value="{{ $rpUrl }}">
      <x-form-errors for="dlg-rechazar-pago"/>
      <p class="sub" id="rpago-info"></p>
      <div><label for="rp-m" class="req">Motivo (se enviará al dueño por WhatsApp)</label>
        <textarea class="in" id="rp-m" name="motivo" maxlength="200" required minlength="5" data-msg="Escribe por qué no se pudo verificar el pago." placeholder="Ej.: El comprobante no muestra el monto completo.">{{ old('_form') === 'dlg-rechazar-pago' ? old('motivo') : '' }}</textarea></div>
      <div class="md-f"><button type="button" class="btn ghost" data-close>Volver</button><button class="btn danger" type="submit">Rechazar pago</button></div>
    </form>
  </x-dialog>
  <x-dialog id="dlg-rechazar" title="Rechazar cita" icon="x">
    @php($rzUrl = old('_form') === 'dlg-rechazar' ? old('_rechazar_url') : '')
    <form class="f" method="POST" action="{{ $rzUrl }}" id="form-rechazar" novalidate>
      @csrf @method('PATCH')
      <input type="hidden" name="_rechazar_url" id="rz-url" value="{{ $rzUrl }}">
      <x-form-errors for="dlg-rechazar"/>
      <p class="sub" id="rechazar-info"></p>
      <div><label for="rz-m">Motivo (se enviará al propietario por WhatsApp)</label>
        <textarea class="in" id="rz-m" name="motivo" maxlength="200" required minlength="5" data-msg="Escribe el motivo del rechazo: se enviará al dueño." data-msg-min="El motivo debe tener al menos 5 caracteres." placeholder="Ej.: No tenemos disponibilidad ese día; le sugerimos el jueves a las 10:00.">{{ old('_form') === 'dlg-rechazar' ? old('motivo') : '' }}</textarea></div>
      <div class="md-f"><button type="button" class="btn ghost" data-close>Volver</button><button class="btn danger" type="submit">Rechazar cita</button></div>
    </form>
  </x-dialog>
  @endpush
  @endonce
@endif
