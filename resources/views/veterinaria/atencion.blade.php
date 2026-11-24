@extends('layouts.app')
@php($pOpt = fn ($m) => '<option value="'.$m->id.'"'.((string) $seleccionada === (string) $m->id ? ' selected' : '').'>'.e($m->nombre.' - '.$m->especie.' ('.($m->propietario?->name ?? '—').')'.($m->afiliacionActiva ? ' · '.$m->afiliacionActiva->plan->nombre : '')).'</option>')
@php($oldProd = collect(old('productos', []))->filter(fn ($f) => ! empty($f['id']))->values())
@php($catalogoJs = $productos->map(fn ($p) => ['id' => $p->id, 'nombre' => $p->nombre, 'precio' => $p->precio, 'cat' => $p->categoria])->values())
@section('title', 'Registrar atención')
@section('heading', 'Registrar atención')
@section('sub', 'Queda en el historial clínico. PetCare calcula cuánto cubre el plan y cuánto paga el dueño.')
@section('back', route('veterinaria.inicio'))
@section('content')
<div class="cols">
  <div class="card pad">
    <form class="f" id="fa" method="POST" action="{{ route('veterinaria.atenciones.store') }}" enctype="multipart/form-data" novalidate
      data-precios='@json($vet->precios ?? [])' data-preventivos='@json(\App\Support\Catalogo::PREVENTIVOS)'
      data-historial="{{ url('veterinaria/historial') }}" data-cobertura="{{ route('veterinaria.atenciones.cobertura') }}">
      @csrf
      <x-errors/>
      @if($cita)
        <div class="note info"><x-icon name="cal"/><span>Atendiendo la cita de las {{ $cita->hora }} ({{ $cita->servicio }}). Al guardar, la cita quedará como atendida y se avisará al dueño.</span></div>
        <input type="hidden" name="cita_id" value="{{ $cita->id }}">
      @endif
      <div class="fr">
        <div><label for="ap">Mascota</label><select class="in" id="ap" name="mascota_id" required data-msg="Selecciona la mascota que atendiste."><option value="">Selecciona...</option>
          @if($pacientes->isNotEmpty())<optgroup label="Pacientes de la clínica">{!! $pacientes->map($pOpt)->join('') !!}</optgroup>@endif
          @if($otras->isNotEmpty())<optgroup label="Otras mascotas afiliadas">{!! $otras->map($pOpt)->join('') !!}</optgroup>@endif
        </select></div>
        @php($srvSel = old('servicio', $cita?->servicio))
        @php($esOtro = $srvSel === \App\Support\Catalogo::OTRO_SERVICIO || ($srvSel && ! in_array($srvSel, $vet->servicios ?? [], true)))
        <div><label for="as">Tipo de servicio</label><select class="in" id="as" name="servicio" required data-msg="Selecciona el tipo de servicio." data-otro="aso-box">@foreach($vet->servicios as $s)<option @selected(! $esOtro && $srvSel === $s)>{{ $s }}</option>@endforeach<option value="{{ \App\Support\Catalogo::OTRO_SERVICIO }}" @selected($esOtro)>Otro… (escribir)</option></select>
          <div id="aso-box" class="otro-campo" @unless($esOtro) hidden @endunless><label class="req" for="aso">Describe el servicio</label><input class="in" id="aso" name="servicio_otro" list="aso-sug" value="{{ old('servicio_otro', $esOtro && $srvSel !== \App\Support\Catalogo::OTRO_SERVICIO ? $srvSel : '') }}" required minlength="3" maxlength="40" placeholder="Ej.: Limpieza dental, Microchip" data-msg="Describe el servicio (ej.: Limpieza dental).">
            <datalist id="aso-sug">@foreach($sugeridos as $s)<option value="{{ $s }}">@endforeach</datalist>
            <div class="hint">Los servicios «Otro» no los cubre el plan: el dueño paga el 100 %.</div></div></div>
      </div>
      <div class="fr">
        <div><label for="af">Fecha</label><input class="in" id="af" name="fecha" type="date" max="{{ today()->format('Y-m-d') }}" min="{{ today()->subYear()->format('Y-m-d') }}" value="{{ old('fecha', today()->format('Y-m-d')) }}" required data-msg="Indica la fecha de la atención." data-msg-max="La atención no puede tener una fecha futura."></div>
        <div><label for="acs">Precio del servicio (Bs)</label><input class="in" id="acs" name="costo_servicio" inputmode="decimal" value="{{ old('costo_servicio') }}" placeholder="Según tu tarifario" required data-numero min="0" max="100000" data-msg="Escribe el precio del servicio (puedes guardarlo en tu tarifario)." data-msg-min="El precio no puede ser negativo."><div class="hint">Se completa con tu <a class="lnk" href="{{ route('veterinaria.clinica') }}">tarifario</a>; puedes ajustarlo.</div></div>
      </div>

      <div><label>Medicamentos y productos usados</label>
        <div id="prods" class="prods" data-productos='@json($catalogoJs)'>
          @foreach($oldProd as $i => $f)
            <div class="prod-row"><select class="in" name="productos[{{ $i }}][id]">@foreach($productos as $p)<option value="{{ $p->id }}" @selected((string) $f['id'] === (string) $p->id)>{{ $p->nombre }} · {{ \App\Support\Formato::bs($p->precio) }}</option>@endforeach</select><input class="in" name="productos[{{ $i }}][cantidad]" inputmode="decimal" data-numero min="0.01" max="999" data-label="cantidad" data-msg-min="La cantidad debe ser mayor a 0." value="{{ $f['cantidad'] ?? 1 }}" aria-label="Cantidad"><button type="button" class="icb bad" data-quitar aria-label="Quitar"><x-icon name="x"/></button></div>
          @endforeach
        </div>
        @if($productos->isEmpty())<div class="hint">Tu catálogo está vacío. <a class="lnk" href="{{ route('veterinaria.productos.index') }}">Agrega tus medicamentos</a> para registrarlos aquí.</div>
        @else<button type="button" class="btn sm ghost" id="add-prod" style="margin-top:8px"><x-icon name="plus"/>Agregar producto</button>@endif
      </div>
      <label class="cb"><input type="checkbox" name="preexistente" value="1" id="apre" @checked(old('preexistente'))><span>Es una enfermedad que la mascota ya tenía antes de afiliarse (no la cubre el plan)</span></label>

      <div id="cov" class="cov" aria-live="polite"></div>

      <div class="grid" id="vacf" hidden style="gap:14px">
        <div class="note {{ auth()->user()->matricula && $vet->registro ? 'ok' : 'warn' }}"><x-icon name="{{ auth()->user()->matricula && $vet->registro ? 'check' : 'alert' }}"/><span>
          @if(auth()->user()->matricula && $vet->registro)Se registrará en la cartilla con tu firma: {{ auth()->user()->name }} · Mat. {{ auth()->user()->matricula }} · {{ $vet->nombre }} (Reg. {{ $vet->registro }}).
          @else Para registrar vacunas y desparasitaciones completa tu N.º de matrícula y el registro de la clínica en <a class="lnk" href="{{ route('veterinaria.clinica') }}">Mi clínica</a>.@endif</span></div>
        <div class="fr">
          <div><label for="apr">Vacuna o producto aplicado</label><input class="in" id="apr" name="producto" value="{{ old('producto') }}" placeholder="Ej.: Antirrábica, Triple felina" required maxlength="100" data-msg="Escribe qué vacuna o antiparasitario aplicaste: aparece en la cartilla."></div>
          <div><label for="alote">Lote (opcional)</label><input class="in" id="alote" name="lote" value="{{ old('lote') }}" placeholder="Del sticker de la vacuna" maxlength="40"></div>
        </div>
        <div class="fr">
          <div><label for="apx">Próxima dosis</label><input class="in" id="apx" name="proxima_dosis" type="date" min="{{ today()->addDay()->format('Y-m-d') }}" max="{{ today()->addYears(3)->format('Y-m-d') }}" value="{{ old('proxima_dosis') }}" required data-msg="Indica la fecha de la próxima dosis: con ella se envía el recordatorio." data-msg-min="La próxima dosis debe ser una fecha futura."><div class="hint">El dueño recibirá un aviso por correo y WhatsApp 7 días antes.</div></div>
        </div>
        <div><label for="aft">Foto de la etiqueta (opcional)</label><input class="in" id="aft" type="file" name="foto_etiqueta" accept="image/jpeg,image/png,image/webp" capture="environment" data-max-mb="10">
          <div class="hint">Toma la foto de la etiqueta pegada en la cartilla física con tu firma: queda en la cartilla digital del dueño.</div></div>
      </div>
      <div class="fr">
        <div><label for="adg">Diagnóstico (opcional)</label><input class="in" id="adg" name="diagnostico" value="{{ old('diagnostico') }}" placeholder="Ej.: Sano, dermatitis leve" maxlength="150"></div>
        <div><label for="at">Tratamiento</label><input class="in" id="at" name="tratamiento" list="trs" value="{{ old('tratamiento') }}" placeholder="Ninguno" maxlength="150">
          <datalist id="trs"><option>Ninguno</option><option>Antibiótico por 7 días</option><option>Antiparasitario oral, dosis única</option><option>Pipeta antipulgas</option><option>Antiinflamatorio por 5 días</option><option>Control en 30 días</option></datalist></div>
      </div>
      <div class="fr">
        <div><label for="apeso">Peso (kg, opcional)</label><input class="in" id="apeso" name="peso" inputmode="decimal" value="{{ old('peso') }}" data-numero min="0.1" max="150" data-msg-min="El peso debe ser mayor a 0 kg." data-msg-max="El peso máximo es 150 kg."></div>
        <div><label for="atemp">Temperatura (°C, opcional)</label><input class="in" id="atemp" name="temperatura" inputmode="decimal" value="{{ old('temperatura') }}" data-numero min="30" max="45" data-msg-min="La temperatura debe estar entre 30 y 45 °C." data-msg-max="La temperatura debe estar entre 30 y 45 °C."></div>
      </div>
      <div><label for="ad">Descripción</label><textarea class="in" id="ad" name="descripcion" placeholder="Motivo, hallazgos y recomendaciones" required minlength="5" maxlength="1000" data-msg="Describe el motivo, los hallazgos y las recomendaciones." data-msg-min="La descripción debe tener al menos 5 caracteres.">{{ old('descripcion', $cita?->nota ? $cita->nota.'. ' : '') }}</textarea></div>
      <button class="btn full" type="submit"><x-icon name="check"/>Guardar atención</button>
    </form>
  </div>
  <div class="card pad" id="prev"><h2 style="margin-bottom:6px">Historial de la mascota</h2><div class="sub">Selecciona una mascota para ver sus atenciones anteriores.</div></div>
</div>
@endsection
