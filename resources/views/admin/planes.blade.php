@extends('layouts.app')
@php($bs = fn ($n) => \App\Support\Formato::bs($n))
@section('title', 'Gestión de planes')
@section('heading', 'Gestión de planes')
@section('sub', 'Vigencia, precio, cobertura, topes y reglas de cada plan. Del más reciente al más antiguo. Los planes inactivos o vencidos no se ofrecen a nuevos clientes.')
@section('acts')<x-exportar/><button class="btn" data-open="dlg-plan-nuevo"><x-icon name="plus"/>Nuevo plan</button>@endsection
@section('content')
<div class="card tw"><table><thead><tr><th>Plan</th><th>Precio</th><th>Cobertura</th><th>Topes</th><th>Vigencia</th><th>Afiliados</th><th></th></tr></thead><tbody>
@foreach($planes as $p)
  @php([$vt, $vc] = $p->vigencia())
  <tr><td><div class="plan-nom"><span class="plan-dot" style="background:{{ $p->colorHex() }}"><x-icon :name="$p->icono"/></span><div><b>{{ $p->nombre }}</b><small>Creado el {{ $p->created_at->format('d/m/Y') }}</small></div></div></td>
    <td data-l="Precio"><b>{{ $bs($p->precio) }}</b>/mes<small class="blk">{{ $bs($p->precioAnual()) }}/año</small></td>
    <td data-l="Cobertura"><b>{{ $p->porcentaje }} %</b> · {{ count($p->cobertura) }} servicios{{ $p->permite_grave ? ' · +Grave' : '' }}<small class="blk">Carencia {{ $p->carencia_dias }} días · mínimo {{ $p->permanencia_meses }} meses</small></td>
    <td data-l="Topes">{{ $bs($p->tope_mensual) }}/mes · {{ $bs($p->tope_anual) }}/año<small class="blk">{{ $p->limite_atenciones ? $p->limite_atenciones.' atenciones/año' : 'Sin límite de atenciones' }}</small></td>
    <td data-l="Vigencia"><span class="vig">{{ $p->vigente_desde?->format('d/m/Y') ?? '—' }} <x-icon name="back"/> {{ $p->vigente_hasta?->format('d/m/Y') ?? 'sin fecha de fin' }}</span><span class="chip {{ $vc }}">{{ $vt }}</span></td>
    <td data-l="Afiliados"><button type="button" class="btn sm ghost" data-open="dlg-afil-{{ $p->id }}"><x-icon name="pets"/>{{ $p->afiliaciones_vigentes_count }} {{ $p->afiliaciones_vigentes_count === 1 ? 'vigente' : 'vigentes' }}</button></td>
    <td class="ac"><div class="acts">
      <button class="icb" title="Editar" aria-label="Editar" data-open="dlg-plan-{{ $p->id }}"><x-icon name="edit"/></button>
      <form method="POST" action="{{ route('admin.planes.toggle', $p) }}">@csrf @method('PATCH')<button class="icb {{ $p->activo ? '' : 'ok' }}" title="{{ $p->activo ? 'Desactivar' : 'Activar' }}" aria-label="{{ $p->activo ? 'Desactivar' : 'Activar' }}"><x-icon :name="$p->activo ? 'lock' : 'check'"/></button></form>
      <form method="POST" action="{{ route('admin.planes.destroy', $p) }}" data-confirm="Se eliminará el plan {{ $p->nombre }}." data-confirm-title="Eliminar plan" data-confirm-btn="Eliminar">@csrf @method('DELETE')<button class="icb bad" title="Eliminar" aria-label="Eliminar"><x-icon name="trash"/></button></form>
    </div></td></tr>
@endforeach
</tbody></table></div>
<div class="note info" style="margin-top:14px"><x-icon name="info"/><span>Cada mascota contrata el plan en <b>una sola veterinaria</b>, con un contrato de permanencia mínima y un periodo de carencia antes de poder usarlo. El adicional Cobertura Grave cuesta {{ $bs(\App\Support\Catalogo::ADICIONAL_GRAVE['precio']) }}/mes y cubre el {{ \App\Support\Catalogo::ADICIONAL_GRAVE['porcentaje'] }} % de oncología y crónicos hasta {{ $bs(\App\Support\Catalogo::ADICIONAL_GRAVE['tope_anual']) }}/año.</span></div>

@push('dialogs')
{{-- Afiliados de cada plan, con sus fechas --}}
@foreach($planes as $p)
  <dialog class="md win md-xl" id="dlg-afil-{{ $p->id }}" aria-label="Afiliados del {{ $p->nombre }}">
    <div class="md-h"><span class="ti c-p"><x-icon name="pets"/></span><h2>Afiliados · {{ $p->nombre }}</h2><button type="button" class="icb" data-close aria-label="Cerrar"><x-icon name="x"/></button></div>
    <div class="afil-bar"><input class="in" type="search" placeholder="Buscar mascota, dueño o veterinaria…" data-filtra="#afil-t-{{ $p->id }}" aria-label="Buscar">
      <a class="btn sm ghost" href="{{ route('admin.planes.afiliados', $p) }}"><x-icon name="down"/>Excel</a>
      <button type="button" class="btn sm ghost" data-imprimir-dlg="dlg-afil-{{ $p->id }}"><x-icon name="print"/>Imprimir</button></div>
    @if($p->afiliaciones->isEmpty())
      <x-empty icon="pets" title="Sin afiliados" text="Cuando una mascota contrate este plan aparecerá aquí con sus fechas."/>
    @else
    <div class="tw"><table id="afil-t-{{ $p->id }}"><thead><tr><th>Mascota</th><th>Dueño</th><th>Veterinaria</th><th>Modalidad</th><th>Inicio</th><th>Vence</th><th>Días restantes</th><th>Estado</th></tr></thead><tbody>
      @foreach($p->afiliaciones as $a)
        @php([$et, $ec] = $a->estadoTexto())
        <tr><td><b>{{ $a->mascota?->nombre }}</b><small class="blk">{{ $a->mascota?->especie }}</small></td><td data-l="Dueño">{{ $a->mascota?->propietario?->name }}</td>
          <td data-l="Veterinaria">{{ $a->veterinaria?->nombre ?? '—' }}</td><td data-l="Modalidad">{{ ucfirst($a->modalidad) }}</td>
          <td data-l="Inicio">{{ $a->fecha_inicio->format('d/m/Y') }}</td><td data-l="Vence">{{ $a->fecha_fin->format('d/m/Y') }}</td>
          <td data-l="Días restantes">{{ $a->estado === 'activo' && $a->diasRestantes() >= 0 ? $a->diasRestantes() : '—' }}</td><td data-l="Estado"><span class="chip {{ $ec }}">{{ $et }}</span></td></tr>
      @endforeach
    </tbody></table></div>
    @endif
  </dialog>
@endforeach

{{-- Formulario completo de plan, con vista previa de la tarjeta --}}
@foreach(collect([null])->concat($planes) as $p)
  @php($id = $p ? 'dlg-plan-'.$p->id : 'dlg-plan-nuevo')
  @php($mine = old('_form') === $id)
  @php($v = fn ($k, $d = null) => $mine ? old($k, $d) : ($p?->{$k} ?? $d))
  @php($cob = $mine ? old('cobertura', []) : ($p?->cobertura ?? []))
  @php($esp = $mine ? old('especies', []) : ($p?->especies ?? []))
  @php($fecha = fn ($k, $d = null) => $mine ? old($k, $d) : ($p?->{$k}?->format('Y-m-d') ?? $d))
  <x-dialog :id="$id" :title="$p ? 'Editar '.$p->nombre : 'Nuevo plan'" icon="shield" clase="md-xl">
    <form class="f plan-form" method="POST" action="{{ $p ? route('admin.planes.update', $p) : route('admin.planes.store') }}" novalidate data-plan-form>
      @csrf @if($p) @method('PUT') @endif
      <x-form-errors :for="$id"/>
      <div class="plan-form-g">
        <div class="f">
          <h3 class="fs-sec">Datos generales</h3>
          <div class="fr"><div><label>Nombre</label><input class="in" name="nombre" value="{{ $v('nombre') }}" required minlength="3" maxlength="80" data-msg="Escribe el nombre del plan." data-pv="nombre"></div>
            <div><label>Descripción corta</label><input class="in" name="descripcion" value="{{ $v('descripcion') }}" maxlength="160" data-pv="descripcion" placeholder="Ej.: Consultas, emergencias y cirugías."></div></div>
          <div class="fr"><div><label>Se ofrece desde</label><input class="in" type="date" name="vigente_desde" value="{{ $fecha('vigente_desde', today()->format('Y-m-d')) }}" required data-msg="Indica desde qué fecha se ofrece el plan."></div>
            <div><label>Hasta (opcional)</label><input class="in" type="date" name="vigente_hasta" value="{{ $fecha('vigente_hasta') }}" data-msg-min="La fecha de fin debe ser posterior a la de inicio."><div class="hint">Al vencer deja de ofrecerse; quienes lo tienen lo conservan hasta su fecha.</div></div></div>

          <h3 class="fs-sec">Precio y contrato</h3>
          <div class="fr"><div><label>Precio mensual (Bs)</label><input class="in" name="precio" inputmode="decimal" value="{{ $v('precio') }}" required data-numero min="1" max="10000" data-msg="Escribe el precio mensual del plan." data-msg-min="El precio debe ser al menos Bs 1." data-pv="precio"></div>
            <div><label>Precio anual (Bs, opcional)</label><input class="in" name="precio_anual" inputmode="decimal" value="{{ $v('precio_anual') }}" data-numero min="1" max="100000" placeholder="Vacío = 10 meses (2 gratis)" data-pv="anual"><div class="hint" data-pv-ahorro></div></div></div>
          <div class="fr"><div><label>Permanencia mínima (meses)</label><input class="in" type="number" name="permanencia_meses" min="1" max="24" value="{{ $v('permanencia_meses', 3) }}" required data-msg="Indica la permanencia mínima del contrato."></div>
            <div><label>Carencia (días)</label><input class="in" type="number" name="carencia_dias" min="0" max="365" value="{{ $v('carencia_dias', 60) }}" required data-msg="Indica los días de carencia (0 si no hay)." data-pv="carencia"><div class="hint">Días desde el contrato hasta poder usar el plan.</div></div></div>

          <h3 class="fs-sec">Cobertura</h3>
          <div class="fr"><div><label>Porcentaje de cobertura</label><input class="in" name="porcentaje" type="number" min="1" max="100" value="{{ $v('porcentaje') }}" required data-msg="Escribe el porcentaje que cubre el plan (1 a 100)." data-msg-min="El porcentaje debe estar entre 1 y 100." data-msg-max="El porcentaje debe estar entre 1 y 100." data-pv="pct"></div>
            <div><label>Medicamentos</label><select class="in" name="medicamentos" required>@foreach(\App\Support\Catalogo::REGLA_MEDICAMENTOS as $k => $t)<option value="{{ $k }}" @selected($v('medicamentos', 'todos') === $k)>{{ $t }}</option>@endforeach</select></div></div>
          <div class="fr"><div><label>Tope mensual (Bs)</label><input class="in" name="tope_mensual" inputmode="decimal" value="{{ $v('tope_mensual') }}" required data-numero min="1" data-msg="Escribe el tope mensual en bolivianos." data-msg-min="El tope mensual debe ser mayor a 0." data-pv="tm"></div>
            <div><label>Tope anual (Bs)</label><input class="in" name="tope_anual" inputmode="decimal" value="{{ $v('tope_anual') }}" required data-numero min="1" data-msg="Escribe el tope anual en bolivianos." data-msg-min="El tope anual debe ser mayor a 0." data-pv="ta"></div></div>
          <div class="fr"><div><label>Atenciones cubiertas por año</label><input class="in" name="limite_atenciones" type="number" min="1" max="365" value="{{ $v('limite_atenciones') }}" placeholder="Vacío = sin límite" data-msg-min="El límite debe ser de al menos 1 atención." data-msg-max="El límite máximo es 365 atenciones." data-pv="lim"></div>
            <div><label>Espera para cirugías (días)</label><input class="in" name="espera_cirugia_dias" type="number" min="0" max="365" value="{{ $v('espera_cirugia_dias', 0) }}" required data-msg="Escribe los días de espera (0 si no hay)." data-msg-max="La espera máxima es de 365 días."></div></div>
          <label class="cb"><input type="checkbox" name="permite_grave" value="1" @checked($v('permite_grave'))><span>Permite el adicional <b>Cobertura Grave</b> (oncología y crónicos)</span></label>
          <div><label>Servicios cubiertos</label><div class="cbs">@foreach(array_diff(\App\Support\Catalogo::SERVICIOS, [\App\Support\Catalogo::GRAVE]) as $s)<label class="cb"><input type="checkbox" name="cobertura[]" value="{{ $s }}" required data-msg="Marca al menos un servicio cubierto." @checked(in_array($s, $cob))>{{ $s }}</label>@endforeach</div></div>

          <h3 class="fs-sec">¿Quién puede contratarlo?</h3>
          <div class="fr"><div><label>Edad mínima (meses)</label><input class="in" type="number" name="edad_min_meses" min="0" max="240" value="{{ $v('edad_min_meses', 2) }}" required data-msg="Indica la edad mínima en meses (0 = sin mínimo)."></div>
            <div><label>Edad máxima (años, opcional)</label><input class="in" type="number" name="edad_max_anios" min="1" max="40" value="{{ $v('edad_max_anios') }}" placeholder="Vacío = sin máximo"></div></div>
          <div><label>Especies</label><div class="cbs">@foreach(\App\Support\Catalogo::ESPECIES as $e)<label class="cb"><input type="checkbox" name="especies[]" value="{{ $e }}" @checked(in_array($e, $esp ?? []))>{{ $e === 'Otro' ? 'Otras (hámster, conejo…)' : $e }}</label>@endforeach</div><div class="hint">Sin marcar ninguna = todas las especies.</div></div>

          <h3 class="fs-sec">Presentación</h3>
          <div><label>Beneficios (uno por línea)</label><textarea class="in" name="beneficios" maxlength="600" rows="4" data-pv="beneficios">{{ $mine ? old('beneficios') : implode("\n", $p?->beneficios ?? []) }}</textarea></div>
          <div class="fr"><div><label>Icono</label><select class="in" name="icono" required data-pv="icono">@foreach(\App\Models\Plan::ICONOS as $k => $t)<option value="{{ $k }}" @selected($v('icono', 'shield') === $k)>{{ $t }}</option>@endforeach</select></div>
            <div><label>Color</label><div class="colores">@foreach(\App\Models\Plan::COLORES as $k => $hex)<label class="color-op" title="{{ ucfirst($k) }}"><input type="radio" name="color" value="{{ $k }}" required data-pv="color" data-hex="{{ $hex }}" @checked($v('color', 'violeta') === $k)><i style="background:{{ $hex }}"></i></label>@endforeach</div></div></div>
        </div>

        {{-- Vista previa: así lo verá el cliente --}}
        <aside class="plan-prev"><span class="hint">Vista previa para el cliente</span>
          <div class="card plan pv-card" data-pv-card>
            <div class="t"><span class="ti pv-ic" data-pv-ic>@foreach(\App\Models\Plan::ICONOS as $k => $t)<span data-ic="{{ $k }}" hidden><x-icon :name="$k"/></span>@endforeach</span><b data-pv-o="nombre">Nombre del plan</b></div>
            <div class="pct"><b data-pv-o="pct">0</b><small>%</small><span>de cobertura</span></div>
            <p data-pv-o="descripcion"></p>
            <ul data-pv-o="beneficios"></ul>
            <div class="topes"><span>Tope mensual <b data-pv-o="tm"></b></span><span>Tope anual <b data-pv-o="ta"></b></span><span>Atenciones cubiertas <b data-pv-o="lim"></b></span><span>Se usa desde <b data-pv-o="carencia"></b></span></div>
            <div class="pr"><span data-pv-o="precio"></span><small> / mes</small></div>
            <span class="btn full" aria-hidden="true">Solicitar plan</span>
          </div>
        </aside>
      </div>
      <div class="md-f"><button type="button" class="btn ghost" data-close>Cancelar</button><button class="btn" type="submit">Guardar plan</button></div>
    </form>
  </x-dialog>
@endforeach
@endpush
@endsection
