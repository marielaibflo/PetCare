{{-- Horario de atención: días y horas con listas (se guarda como «Lun–Sáb · 08:30 a 18:00»).
     Uso: <x-horario :valor="$vet->horario" id="vh"/> --}}
@props(['valor' => null, 'id' => 'hor', 'label' => 'Horario de atención', 'usarOld' => true])
@php
  $p = \App\Support\Horario::partes($valor);
  $o = fn ($k) => $usarOld ? old('horario_'.$k, $p[$k] ?? null) : ($p[$k] ?? null);
  $h24 = $usarOld && old('horario_dia_desde') ? (bool) old('horario_24') : $p['h24'];
@endphp
<div class="horario" data-horario>
  <span class="lbl req">{{ $label }}</span>
  <div class="hor-g">
    <div><label for="{{ $id }}-dd">Desde el día</label>
      <select class="in" id="{{ $id }}-dd" name="horario_dia_desde" required data-msg="Elige desde qué día atiende la clínica.">@foreach(\App\Support\Horario::DIAS as $k => $d)<option value="{{ $k }}" @selected($o('dia_desde') === $k)>{{ $d }}</option>@endforeach</select></div>
    <div><label for="{{ $id }}-dh">Hasta el día</label>
      <select class="in" id="{{ $id }}-dh" name="horario_dia_hasta" required data-msg="Elige hasta qué día atiende la clínica.">@foreach(\App\Support\Horario::DIAS as $k => $d)<option value="{{ $k }}" @selected($o('dia_hasta') === $k)>{{ $d }}</option>@endforeach</select></div>
    <div data-hor-horas><label for="{{ $id }}-hd">Abre a las</label>
      <select class="in" id="{{ $id }}-hd" name="horario_hora_desde" required data-msg="Elige la hora de apertura.">@foreach(\App\Support\Horario::horas() as $h)<option @selected($o('hora_desde') === $h)>{{ $h }}</option>@endforeach</select></div>
    <div data-hor-horas><label for="{{ $id }}-hh">Cierra a las</label>
      <select class="in" id="{{ $id }}-hh" name="horario_hora_hasta" required data-msg="Elige la hora de cierre.">@foreach(\App\Support\Horario::horas() as $h)<option @selected($o('hora_hasta') === $h)>{{ $h }}</option>@endforeach</select></div>
  </div>
  <label class="cb" style="margin-top:8px"><input type="checkbox" name="horario_24" value="1" @checked($h24)><span>Atiende las 24 horas</span></label>
  <div class="hint">Se mostrará así: <b data-hor-vista></b></div>
</div>
