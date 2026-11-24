@php
  $misMascotas = auth()->user()->mascotas()->with('afiliacionActiva.plan')->orderBy('nombre')->get();
  // Las mejor calificadas primero, para que el dueño las vea apenas abre la lista.
  $vets = \App\Models\Veterinaria::activas()->orderByDesc('valoracion')->orderByDesc('resenas')->get();
  $planesCita = \App\Models\Plan::activos()->orderBy('precio')->get();
  $planDeMascota = $misMascotas->mapWithKeys(fn ($mm) => [$mm->id => $mm->afiliacionActiva?->plan_id]);
  $f = old('_form') === 'dlg-cita';
@endphp
@include('partials.mascota-form', ['m' => null])

<x-dialog id="dlg-cita" title="Agendar cita" icon="cal">
  @if($misMascotas->isEmpty())
    <x-empty icon="pets" title="Primero registra a tu mascota" text="Necesitas al menos una mascota para agendar."><button type="button" class="btn" data-open="dlg-mascota-nueva">Agregar mascota</button></x-empty>
  @else
  <form class="f" id="fcita" method="POST" action="{{ route('propietario.citas.store') }}" novalidate data-estimar="{{ route('propietario.citas.estimar') }}" data-planes='@json($planDeMascota)'>
    @csrf
    <x-form-errors for="dlg-cita"/>
    <div class="fr"><div><label for="cita-m">Mascota</label><select class="in" id="cita-m" name="mascota_id" required data-msg="Selecciona la mascota que llevarás a la cita.">@foreach($misMascotas as $mm)<option value="{{ $mm->id }}" @selected($f && old('mascota_id') == $mm->id)>{{ $mm->nombre }}</option>@endforeach</select></div>
      <div><label for="cita-v">Veterinaria</label><select class="in" id="cita-v" name="veterinaria_id" required data-msg="Selecciona la veterinaria." data-servicios='@json($vets->pluck('servicios', 'id'))'>@foreach($vets as $vv)<option value="{{ $vv->id }}" @selected($f && old('veterinaria_id') == $vv->id)>{{ $vv->valoracion ? '★ '.number_format($vv->valoracion, 1).' · ' : '' }}{{ $vv->nombre }} ({{ $vv->zona }})</option>@endforeach</select>
        <div class="hint">Ordenadas de mejor a menor calificación. <a class="lnk" href="{{ route('propietario.veterinarias.index') }}">Ver detalles</a></div></div></div>
    <div class="fr"><div><label for="cita-s">Servicio</label><select class="in" id="cita-s" name="servicio" required data-msg="Selecciona el servicio que necesitas." data-old="{{ $f ? old('servicio') : '' }}" data-otro="cita-so-box">@foreach(\App\Support\Catalogo::SERVICIOS as $s)<option>{{ $s }}</option>@endforeach</select>
      <div id="cita-so-box" class="otro-campo" hidden><label class="req" for="cita-so">¿Qué servicio necesitas?</label><input class="in" id="cita-so" name="servicio_otro" value="{{ $f ? old('servicio_otro') : '' }}" required minlength="3" maxlength="40" placeholder="Ej.: Limpieza dental, Microchip" data-msg="Escribe qué servicio necesitas (ej.: Limpieza dental)."><div class="hint">La clínica te confirmará el precio. No lo cubre el plan.</div></div></div>
      <div><label>&nbsp;</label><label class="cb urg"><input type="checkbox" name="urgente" value="1" id="cita-u" @checked($f && old('urgente'))><span>🚨 Es <b>urgente</b> (la clínica la verá primero)</span></label></div></div>
    <div><label>Plan</label>
      <div class="planes-cita" id="cita-planes">
        @foreach($planesCita as $pc)<label class="pc-op" data-plan="{{ $pc->id }}"><input type="radio" name="_plan" value="{{ $pc->id }}" disabled><span><b>{{ $pc->nombre }}</b><small>{{ $pc->porcentaje }} % · <i data-estado>No contratado</i></small></span></label>@endforeach
        <label class="pc-op" data-plan=""><input type="radio" name="_plan" value="" disabled><span><b>Sin plan</b><small>Atención particular</small></span></label>
      </div>
      <div class="hint">Solo puedes usar el plan que ya pagaste para esta mascota. <a class="lnk" href="{{ route('propietario.planes.index') }}">Ver planes</a></div></div>
    <div class="fr"><div><label for="cita-f">Fecha</label><input class="in" id="cita-f" type="date" name="fecha" min="{{ today()->format('Y-m-d') }}" max="{{ today()->addMonths(3)->format('Y-m-d') }}" value="{{ $f ? old('fecha') : today()->addDay()->format('Y-m-d') }}" required data-no-domingo data-msg="Elige la fecha de la cita." data-msg-min="La fecha de la cita no puede ser anterior a hoy." data-msg-max="Solo se pueden agendar citas hasta 3 meses adelante."></div>
      <div><label for="cita-h">Hora</label><select class="in" id="cita-h" name="hora" required data-msg="Elige la hora de la cita.">@foreach(\App\Support\Catalogo::HORAS as $h)<option @selected(($f ? old('hora') : '10:00') === $h)>{{ $h }}</option>@endforeach</select></div></div>
    <div><label for="cita-n">Motivo o nota (opcional)</label><textarea class="in" id="cita-n" name="nota" placeholder="Ej.: control anual, vacuna antirrábica" maxlength="200">{{ $f ? old('nota') : '' }}</textarea></div>
    <div id="cita-est" class="cita-est" aria-live="polite"></div>
    <div class="md-f"><button type="button" class="btn ghost" data-close>Cancelar</button><button class="btn" type="submit"><x-icon name="cal"/>Solicitar cita</button></div>
    <p class="hint" style="margin:0">Después podrás pagar tu parte con tarjeta, con el QR de la clínica o por WhatsApp.</p>
  </form>
  @endif
</x-dialog>
