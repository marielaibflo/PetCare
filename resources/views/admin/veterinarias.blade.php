@extends('layouts.app')
@section('title', 'Veterinarias afiliadas')
@section('heading', 'Veterinarias afiliadas')
@section('sub', 'Al registrar una veterinaria se crea también su cuenta de acceso.')
@section('acts')<x-exportar/><button class="btn" data-open="dlg-vet-nueva"><x-icon name="plus"/>Nueva veterinaria</button>@endsection
@section('content')
@if($solicitudes->isNotEmpty())
<section style="margin-bottom:18px"><h2 style="margin-bottom:10px">Solicitudes de afiliación <span class="badge">{{ $solicitudes->count() }}</span></h2>
  <div class="card lst">@foreach($solicitudes as $v)
    <div class="row"><span class="ti c-w"><x-icon name="store"/></span>
      <div class="tx"><b>{{ $v->nombre }} · NIT {{ $v->nit }}</b>
        <span>{{ $v->direccion }}, {{ $v->zona }} · Tel. {{ $v->telefono }} · WhatsApp {{ \App\Support\Paises::mostrar($v->whatsapp_pais, $v->whatsapp) }} · Responsable: {{ $v->personal->first()?->name }} (<x-correo :email="$v->personal->first()?->email"/>)</span>
        <span>Plan elegido: {{ $v->suscripcion()['nombre'] }} · Cobro después del mes gratis: {{ $v->cobroTexto() }} · Servicios: {{ implode(', ', $v->servicios) }} · Solicitud del {{ $v->created_at->format('d/m/Y H:i') }}</span>
        @php($conf = $v->personal->first()?->email_verified_at)
        <span>@if($conf)<span class="chip ok">✓ Correo confirmado</span>@else<span class="chip warn">Correo sin confirmar · no se puede aprobar todavía</span>@endif</span></div>
      <div class="ra">
        @if($v->documento)<a class="btn sm ghost" href="{{ route('archivos.documento', $v) }}" target="_blank" rel="noopener"><x-icon name="eye"/>Documento</a>@endif
        <form method="POST" action="{{ route('admin.veterinarias.aprobar', $v) }}" data-confirm="{{ $v->nombre }} quedará activa con {{ \App\Support\Catalogo::DIAS_PRUEBA_VETERINARIA }} días de prueba gratis." data-confirm-title="Aprobar veterinaria" data-confirm-btn="Aprobar">@csrf @method('PATCH')<button class="btn sm ok" @disabled(! $conf) @unless($conf) title="Espera a que la clínica confirme su correo" @endunless>Aprobar</button></form>
        <button type="button" class="btn sm danger" data-open="dlg-rechazar-vet-{{ $v->id }}">Rechazar</button>
      </div></div>
  @endforeach</div>
</section>
@endif
<div class="lista-bar"><h2>Afiliadas ({{ $veterinarias->count() }})</h2>
  <nav class="seg-mini" aria-label="Ordenar">@foreach(['reciente' => 'Más recientes', 'nombre' => 'Por nombre', 'vence' => 'Por vencimiento'] as $k => $t)<a class="{{ $orden === $k ? 'on' : '' }}" href="{{ route('admin.veterinarias.index', ['orden' => $k]) }}">{{ $t }}</a>@endforeach</nav></div>
<div class="card tw"><table><thead><tr><th>Veterinaria</th><th>Afiliada desde</th><th>Teléfono</th><th>Cuenta de acceso</th><th>Suscripción</th><th>Atenciones</th><th>Estado</th><th></th></tr></thead><tbody>
@foreach($veterinarias as $v)
  @php($desdeAf = $v->aprobada_at ?? $v->created_at)
  <tr><td><b>{{ $v->nombre }}</b><small class="blk">{{ $v->zona }}</small></td><td data-l="Afiliada desde">{{ $desdeAf->format('d/m/Y') }}<small class="blk">{{ $desdeAf->diffForHumans() }}</small></td><td data-l="Teléfono">{{ $v->telefono }}</td><td data-l="Cuenta"><x-correo :email="$v->personal->first()?->email"/></td>
    <td data-l="Suscripción">@if($v->estado === 'rechazada')<span class="chip bad">Rechazada</span>@elseif($v->baja_at)<span class="chip mut">Cuenta eliminada el {{ $v->baja_at->format('d/m/Y') }}</span>@else
      @php([$sd, $sh, $st, $sc] = $v->periodoSuscripcion())
      <b>{{ $v->suscripcion()['nombre'] }}</b>@if($sh)<small class="blk">desde {{ $sd?->format('d/m/Y') }} hasta {{ $sh->format('d/m/Y') }}</small>@endif<span class="chip {{ $sc }}">{{ $st }}</span>@if($v->suscripcion_cancelada_at)<span class="chip warn" title="{{ $v->motivo_cancelacion }}">Cancelada el {{ $v->suscripcion_cancelada_at->format('d/m/Y') }}</span>@endif @endif</td>
    <td data-l="Atenciones">{{ $v->atenciones_count }}</td>
    <td data-l="Estado">@if($v->baja_at)<span class="chip mut">Dada de baja</span>@elseif($v->activa)<span class="chip ok">Activa</span>@else<span class="chip mut">Inactiva</span>@endif</td>
    <td class="ac">@unless($v->baja_at)<div class="acts"><button class="icb" title="Editar" aria-label="Editar" data-open="dlg-vet-{{ $v->id }}"><x-icon name="edit"/></button>
      <form method="POST" action="{{ route('admin.veterinarias.toggle', $v) }}">@csrf @method('PATCH')<button class="btn sm {{ $v->activa ? 'ghost' : 'ok' }}">{{ $v->activa ? 'Desactivar' : 'Activar' }}</button></form></div>@endunless</td></tr>
@endforeach
</tbody></table></div>
@push('dialogs')
@foreach(collect([null])->concat($veterinarias) as $v)
  @php($id = $v ? 'dlg-vet-'.$v->id : 'dlg-vet-nueva')
  @php($val = fn ($k) => old('_form') === $id ? old($k) : $v?->{$k})
  @php($srv = old('_form') === $id ? old('servicios', []) : ($v?->servicios ?? ['Consulta', 'Vacuna']))
  <x-dialog :id="$id" :title="$v ? 'Editar veterinaria' : 'Nueva veterinaria'" icon="store">
    <form class="f" method="POST" action="{{ $v ? route('admin.veterinarias.update', $v) : route('admin.veterinarias.store') }}" novalidate>
      @csrf @if($v) @method('PUT') @endif
      <x-form-errors :for="$id"/>
      <div><label>Nombre de la veterinaria</label><input class="in" name="nombre" value="{{ $val('nombre') }}" required minlength="3" maxlength="100" data-msg="Escribe el nombre de la veterinaria."></div>
      <div class="fr"><div><label>Zona</label><input class="in" name="zona" value="{{ $val('zona') }}" required maxlength="60" data-msg="Escribe la zona."></div><div><label>Teléfono</label><input class="in" name="telefono" inputmode="numeric" maxlength="10" value="{{ $val('telefono') }}" required pattern="\d{7,10}" data-msg="Escribe el teléfono." data-msg-pattern="El teléfono debe tener de 7 a 10 dígitos."></div></div>
      <x-telefono :id="$id.'-wa'" label="WhatsApp de la clínica (opcional)" campo-pais="whatsapp_pais" campo-numero="whatsapp" :pais="$val('whatsapp_pais')" :numero="$val('whatsapp')" :requerido="false" ayuda="Recibe un aviso por cada cita nueva."/>
      <div><label>Dirección</label><input class="in" name="direccion" value="{{ $val('direccion') }}" required minlength="5" maxlength="150" data-msg="Escribe la dirección."></div>
      <x-horario :valor="$v?->horario" :id="$id.'-hor'" :usar-old="old('_form') === $id"/>
      <x-servicios :seleccion="$v?->servicios ?? ['Consulta', 'Vacuna']" :id="$id.'-srv'" label="Servicios" :usar-old="old('_form') === $id"/>
      <div><label>Suscripción</label><select class="in" name="plan_suscripcion" required>@foreach(\App\Support\Catalogo::SUSCRIPCIONES as $k => $x)<option value="{{ $k }}" @selected(($val('plan_suscripcion') ?: 'profesional') === $k)>{{ $x['nombre'] }} · {{ \App\Support\Formato::bs($x['precio']) }}/mes</option>@endforeach</select></div>
      @unless($v)
        <div class="note info"><x-icon name="info"/><span>Cuenta de acceso para el personal de la veterinaria. Tendrá {{ \App\Support\Catalogo::DIAS_PRUEBA_VETERINARIA }} días de prueba gratis.</span></div>
        <div><label>Responsable</label><input class="in" name="responsable" value="{{ old('_form') === $id ? old('responsable') : '' }}" placeholder="Dra. / Dr." required minlength="3" maxlength="100" data-msg="Escribe el nombre del responsable."></div>
        <div class="fr"><div><label>Correo de acceso</label><input class="in" type="email" name="email" value="{{ old('_form') === $id ? old('email') : '' }}" required maxlength="120" data-msg="Escribe el correo de acceso de la clínica."></div><div><label>Contraseña inicial</label><input class="in" type="password" name="password" autocomplete="new-password" data-fuerza required data-segura data-msg="Escribe una contraseña inicial."><div class="fuerza" aria-hidden="true"><span></span></div><div class="hint">8+ caracteres con mayúscula, minúscula, número y símbolo.</div></div></div>
      @endunless
      <div class="md-f"><button type="button" class="btn ghost" data-close>Cancelar</button><button class="btn" type="submit">Guardar</button></div>
    </form>
  </x-dialog>
@endforeach
@foreach($solicitudes as $v)
  <x-dialog :id="'dlg-rechazar-vet-'.$v->id" title="Rechazar solicitud" icon="x">
    <form class="f" method="POST" action="{{ route('admin.veterinarias.rechazar', $v) }}" novalidate>
      @csrf @method('PATCH')
      <x-form-errors :for="'dlg-rechazar-vet-'.$v->id"/>
      <div><label>Motivo (se enviará a la clínica)</label><textarea class="in" name="motivo" placeholder="Ej.: El documento no es legible; envíe la licencia de funcionamiento vigente." required minlength="5" maxlength="300" data-msg="Escribe el motivo del rechazo para la clínica."></textarea></div>
      <div class="md-f"><button type="button" class="btn ghost" data-close>Volver</button><button class="btn danger" type="submit">Rechazar</button></div>
    </form>
  </x-dialog>
@endforeach
@endpush
@endsection
