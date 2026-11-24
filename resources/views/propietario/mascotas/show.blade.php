@extends('layouts.app')
@php($a = $mascota->afiliacionActiva)
@section('title', 'Detalle de mascota')
@section('heading', 'Detalle de mascota')
@section('back', route('propietario.mascotas.index'))
@section('content')
<div class="det">
  <div class="card det-h"><x-pet :m="$mascota" :s="110"/><div><h2>{{ $mascota->nombre }}</h2><div class="sub">{{ $mascota->resumen() }}<br>{{ $mascota->raza }}</div><div style="margin-top:8px"><x-plan-chip :m="$mascota"/></div>
    <div style="margin-top:12px"><x-libreta-visor :mascota="$mascota" :carnet="$carnet" :imprimir="route('propietario.mascotas.cartilla', $mascota)" boton="Cartilla" clase="btn sm sec"/></div></div></div>
  <div class="grid">
    <nav class="tabs" aria-label="Secciones de la ficha">
      @foreach(['info' => 'Información', 'vacunas' => 'Cartilla', 'historial' => 'Historial', 'plan' => 'Plan', 'citas' => 'Citas'] as $k => $t)
        <a class="{{ $tab === $k ? 'on' : '' }}" href="{{ route('propietario.mascotas.show', [$mascota, $k]) }}" @if($tab === $k) aria-current="page" @endif>{{ $t }}</a>
      @endforeach
    </nav>

    @if($tab === 'info')
      <div class="card kv">
        @foreach([['Nombre', $mascota->nombre], ['Especie', $mascota->especie], ['Raza', $mascota->raza], ['Fecha de nacimiento', $mascota->fecha_nacimiento->format('d/m/Y')], ['Edad', $mascota->edad()], ['Peso', $mascota->peso.' kg'], ['Sexo', $mascota->sexo], ['Esterilizado', $mascota->esterilizado ? 'Sí' : 'No'], ['Alergias', $mascota->alergias ?: 'Ninguna registrada']] as [$k, $v])
          <div><span>{{ $k }}</span><b>{{ $v }}</b></div>
        @endforeach
      </div>
      <div style="display:flex;gap:10px;flex-wrap:wrap">
        <button class="btn sec" data-open="dlg-mascota-{{ $mascota->id }}"><x-icon name="edit"/>Editar datos</button>
        <form method="POST" action="{{ route('propietario.mascotas.destroy', $mascota) }}" data-confirm="Se eliminará la ficha de {{ $mascota->nombre }} y se cancelarán sus citas pendientes. El historial clínico se conserva para la veterinaria." data-confirm-title="Eliminar mascota" data-confirm-btn="Eliminar">@csrf @method('DELETE')<button class="btn danger"><x-icon name="trash"/>Eliminar</button></form>
      </div>
    @elseif($tab === 'vacunas')
      <section><h2 style="margin-bottom:10px">Próximas dosis</h2>
        <div class="card">@if($dosis->isEmpty())<x-empty icon="shield" title="Sin dosis programadas" text="Cuando se registre una vacuna con su próxima dosis, aparecerá aquí."/>@else<div class="lst">@foreach($dosis as $x)@include('partials.dosis-row')@endforeach</div>@endif</div>
      </section>
      @php($ultVac = $carnet->where('servicio', 'Vacuna')->sortByDesc('fecha')->first())
      @php($ultDesp = $carnet->whereIn('servicio', ['Desparasitación', 'Control de pulgas'])->sortByDesc('fecha')->first())
      <section class="card pad cartilla-res">
        <div class="cr-libro" aria-hidden="true"><span class="cr-tapa"><svg viewBox="0 0 24 24">{!! \App\Support\Iconos::PATA !!}</svg><b>Cartilla</b><small>{{ $mascota->nombre }}</small></span></div>
        <div class="cr-info">
          <h2>Cartilla de vacunación</h2>
          <div class="cr-datos">
            <div><span>Última vacuna</span><b>{{ $ultVac ? ($ultVac->producto ?: 'Vacuna').' · '.$ultVac->fecha->format('d/m/Y') : 'Sin registros' }}</b></div>
            <div><span>Última desparasitación</span><b>{{ $ultDesp ? ($ultDesp->producto ?: $ultDesp->servicio).' · '.$ultDesp->fecha->format('d/m/Y') : 'Sin registros' }}</b></div>
            <div><span>Registros en la cartilla</span><b>{{ $carnet->count() }}</b></div>
          </div>
          <div style="display:flex;gap:10px;flex-wrap:wrap"><x-libreta-visor :mascota="$mascota" :carnet="$carnet" :imprimir="route('propietario.mascotas.cartilla', $mascota)" boton="Abrir cartilla" clase="btn"/>
            <a class="btn ghost" href="{{ route('propietario.mascotas.cartilla', $mascota) }}" target="_blank" rel="noopener"><x-icon name="print"/>Imprimir / PDF</a></div>
        </div>
      </section>
      @if($carnet->isNotEmpty())
        <section><h2 style="margin-bottom:10px">Registros y certificados</h2>
          <div class="card lst">@foreach($carnet->sortByDesc('fecha') as $c)
            <div class="row"><span class="ti {{ $c->servicio === 'Vacuna' ? 'c-p' : 'c-ok' }}"><x-icon name="shield"/></span>
              <div class="tx"><b>{{ $c->producto ?: $c->servicio }}</b><span>{{ $c->servicio }} · {{ $c->fecha->format('d/m/Y') }} · {{ $c->veterinaria?->nombre ?? $c->clinica_externa ?? '—' }}</span></div>
              @if($c->documento_dueno)<span class="chip ok"><x-icon name="check"/>Certificado</span>@endif
              <button type="button" class="btn sm ghost" data-open="dlg-reg-{{ $c->id }}">Ver detalles</button></div>
          @endforeach</div>
        </section>
        @include('partials.cartilla-registros')
      @endif
      <div class="note info"><x-icon name="info"/><span>Solo las veterinarias registran vacunas y desparasitaciones, con su matrícula profesional y el sello de la clínica. Tú puedes ver el detalle de cada registro y adjuntar el certificado físico que te entregaron. Te avisaremos por correo y WhatsApp 7 días antes de cada próxima dosis.</span></div>
    @elseif($tab === 'historial')
      <div class="card">@include('partials.historial', ['atenciones' => $mascota->atenciones])</div>
    @elseif($tab === 'plan')
      @if($a)
        <div class="card pad grid">
          <div style="display:flex;gap:12px;align-items:center"><span class="ti c-ok"><x-icon name="crown"/></span><div><b style="font-size:17px">{{ $a->plan->nombre }} · cubre el {{ $a->plan->porcentaje }} %</b><div class="sub">{{ \App\Support\Formato::bs($a->plan->precio) }} / mes{{ $a->cobertura_grave ? ' + Cobertura Grave' : '' }} · Vigente del {{ $a->fecha_inicio->format('d/m/Y') }} al {{ $a->fecha_fin->format('d/m/Y') }}</div></div></div>
          <div class="contrato-g">
            <div><span>Veterinaria del plan</span><b>{{ $a->veterinaria?->nombre ?? 'Cualquier veterinaria afiliada' }}</b>@if($a->veterinaria)<small>{{ $a->veterinaria->direccion }}, {{ $a->veterinaria->zona }}</small>@endif</div>
            <div><span>Contrato</span><b>{{ $a->contrato_numero ?? '—' }}</b>@if($a->contrato_at)<small>Aceptado el {{ $a->contrato_at->format('d/m/Y') }}</small>@endif</div>
            <div><span>Permanencia mínima</span><b>{{ $a->permanencia_hasta ? 'Hasta el '.$a->permanencia_hasta->format('d/m/Y') : '—' }}</b><small>{{ $a->puedeCancelar() ? 'Cumplida' : 'No se puede cancelar antes' }}</small></div>
            <div><span>Uso del plan</span><b>{{ $a->enCarencia() ? 'Desde el '.$a->carencia_hasta->format('d/m/Y') : 'Disponible' }}</b><small>{{ $a->enCarencia() ? 'En carencia: faltan '.today()->diffInDays($a->carencia_hasta).' días (incluye urgencias)' : 'Ya puedes usarlo' }}</small></div>
          </div>
          @if($a->enCarencia())<div class="note info"><x-icon name="clock"/><span>{{ $mascota->nombre }} está en periodo de carencia. Podrás usar el plan desde el <b>{{ $a->carencia_hasta->format('d/m/Y') }}</b>; hasta entonces las atenciones se pagan completas.</span></div>@endif
          @if($deuda = $a->mesesAdeudados())<div class="note bad"><x-icon name="alert"/><span>Tienes <b>{{ $deuda }} {{ $deuda === 1 ? 'mes' : 'meses' }}</b> del contrato mínimo sin pagar ({{ \App\Support\Formato::bs($deuda * $a->plan->precio) }}). Paga para mantener la cobertura.</span></div>@endif
          @if($a->porVencer())<div class="note warn"><x-icon name="alert"/><span>El plan vence en {{ $a->diasRestantes() }} {{ $a->diasRestantes() === 1 ? 'día' : 'días' }}. Renuévalo para no perder la cobertura.</span></div>@endif
          @if($saldo)
            <div class="saldo">
              @foreach([['Usado este mes', $saldo['mes_usado'], $saldo['mes_tope']], ['Usado en el año', $saldo['anio_usado'], $saldo['anio_tope']]] as [$t, $u, $tope])
                @php($pc = $tope > 0 ? min(100, $u / $tope * 100) : 0)
                <div><div class="saldo-t"><span>{{ $t }}</span><b>{{ \App\Support\Formato::bs($u) }} de {{ \App\Support\Formato::bs($tope) }}</b></div><div class="barra"><i style="width:{{ $pc }}%" class="{{ $pc >= 90 ? 'alto' : '' }}"></i></div></div>
              @endforeach
              @if($saldo['atenciones_limite'])
                @php($pc = min(100, $saldo['atenciones_usadas'] / $saldo['atenciones_limite'] * 100))
                <div><div class="saldo-t"><span>Atenciones cubiertas</span><b>{{ $saldo['atenciones_usadas'] }} de {{ $saldo['atenciones_limite'] }} al año</b></div><div class="barra"><i style="width:{{ $pc }}%" class="{{ $pc >= 75 ? 'alto' : '' }}"></i></div></div>
              @endif
            </div>
          @endif
          <div><b style="font-size:14px">Servicios cubiertos al {{ $a->plan->porcentaje }} %</b><div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:8px">@foreach(array_diff(\App\Support\Catalogo::SERVICIOS, [\App\Support\Catalogo::GRAVE]) as $s)@if($a->plan->cubre($s))<span class="chip ok"><x-icon name="check"/>{{ $s }}</span>@else<span class="chip mut">{{ $s }}</span>@endif @endforeach</div>
            <div class="hint" style="margin-top:8px">Medicamentos: {{ \App\Support\Catalogo::REGLA_MEDICAMENTOS[$a->plan->medicamentos] }}.@if($a->plan->espera_cirugia_dias) Cirugías desde el día {{ $a->plan->espera_cirugia_dias }} de afiliación.@endif No cubre enfermedades previas a la afiliación.</div></div>
          <div style="display:flex;gap:10px;flex-wrap:wrap">
            <a class="btn" href="{{ route('propietario.planes.solicitar', [$a->plan, 'mascota' => $mascota->id]) }}">Renovar / pagar</a>
            @if($a->contrato_numero)<a class="btn ghost" href="{{ route('propietario.planes.contrato', $mascota) }}" target="_blank" rel="noopener"><x-icon name="doc"/>Ver contrato</a>@endif
            @if($a->puedeCancelar())
              <a class="btn sec" href="{{ route('propietario.planes.index') }}">Cambiar de plan</a>
              <form method="POST" action="{{ route('propietario.planes.cancelar', $mascota) }}" data-confirm="{{ $mascota->nombre }} dejará de tener cobertura desde hoy." data-confirm-title="Cancelar plan" data-confirm-btn="Cancelar plan">@csrf @method('DELETE')<button class="btn danger">Cancelar plan</button></form>
            @else
              <button class="btn danger" disabled title="Permanencia mínima del contrato"><x-icon name="lock"/>Cancelar plan · desde el {{ $a->permanencia_hasta->format('d/m/Y') }}</button>
            @endif
          </div>
        </div>
      @else
        <div class="card"><x-empty icon="shield" :title="$mascota->nombre.' no tiene un plan activo'" text="Con un plan, PetCare paga el 40 %, 60 % o 90 % de sus atenciones."><a class="btn" href="{{ route('propietario.planes.index') }}">Ver planes</a></x-empty></div>
      @endif
    @else
      @php($cs = $mascota->citas->sortByDesc(fn ($c) => $c->fecha->format('Y-m-d').$c->hora))
      <div class="card">@if($cs->isEmpty())<x-empty icon="cal" title="Sin citas" :text="'Agenda la primera cita de '.$mascota->nombre.'.'"/>@else<div class="lst">@foreach($cs as $c)@include('partials.cita-row', ['ctx' => 'owner'])@endforeach</div>@endif</div>
      <button class="btn" data-open="dlg-cita" data-cita-mascota="{{ $mascota->id }}"><x-icon name="plus"/>Agendar cita</button>
    @endif
  </div>
</div>
@push('dialogs')@include('partials.mascota-form', ['m' => $mascota])@endpush
@endsection
