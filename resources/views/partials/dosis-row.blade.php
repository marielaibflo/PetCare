@php($a = $x->atencion)
<div class="row"><x-svc :servicio="$a->servicio"/>
  <div class="tx"><b>{{ $a->producto ?: $a->servicio }} · {{ $x->mascota->nombre }}</b><span>Próxima dosis: {{ \App\Support\Formato::fechaLarga($a->proxima_dosis) }} · última aplicación {{ $a->fecha->format('d/m/Y') }}</span></div>
  <div class="ra">
    @if($x->estado === 'vencida')<span class="chip bad">Vencida hace {{ -$x->dias }} {{ $x->dias === -1 ? 'día' : 'días' }}</span>
    @elseif($x->estado === 'proxima')<span class="chip warn">{{ $x->dias === 0 ? 'Hoy' : 'En '.$x->dias.($x->dias === 1 ? ' día' : ' días') }}</span>
    @else<span class="chip ok">Al día</span>@endif
    @if($x->cita)<span class="chip info">Cita {{ $x->cita->fecha->format('d/m/Y') }}</span>
    @elseif($agendar ?? true)<button type="button" class="btn sm sec" data-open="dlg-cita" data-cita-mascota="{{ $x->mascota->id }}" data-cita-vet="{{ $a->veterinaria_id }}" data-cita-servicio="{{ $a->servicio }}" data-cita-nota="Próxima dosis: {{ $a->producto ?: $a->servicio }}">Agendar</button>@endif
  </div>
</div>
