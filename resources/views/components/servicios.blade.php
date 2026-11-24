{{-- Casillas de servicios del catálogo + «Otros» con un campo para escribir los servicios propios de la clínica.
     Uso: <x-servicios :seleccion="$vet->servicios" :id="'vs'"/> --}}
@props(['seleccion' => [], 'id' => 'srv', 'label' => 'Servicios que ofrece', 'usarOld' => true])
@php
  $sel = $usarOld && old('servicios') !== null ? (array) old('servicios') : (array) $seleccion;
  $otros = \App\Support\Catalogo::serviciosOtros(array_diff($sel, ['Otros']));
  $textoOtros = $usarOld && old('servicios_otros') !== null ? old('servicios_otros') : implode(', ', $otros);
  $hayOtros = in_array('Otros', $sel, true) || $otros || ($usarOld && old('servicios_otros'));
@endphp
<div data-servicios-box>
  <label class="req">{{ $label }}</label>
  <div class="cbs">
    @foreach(\App\Support\Catalogo::SERVICIOS as $s)<label class="cb"><input type="checkbox" name="servicios[]" value="{{ $s }}" required data-msg="Marca al menos un servicio que ofrece la clínica." @checked(in_array($s, $sel, true))><span>{{ $s }}</span></label>@endforeach
    <label class="cb otros"><input type="checkbox" name="servicios[]" value="Otros" required data-msg="Marca al menos un servicio que ofrece la clínica." data-otros @checked($hayOtros)><span><b>Otros…</b></span></label>
  </div>
  <div class="otros-campo" data-otros-campo @unless($hayOtros) hidden @endunless>
    <label for="{{ $id }}-otros">¿Qué otros servicios ofreces?</label>
    <input class="in" id="{{ $id }}-otros" name="servicios_otros" value="{{ $textoOtros }}" maxlength="400" required
      placeholder="Ej.: Fisioterapia, Odontología, Acupuntura"
      data-msg="Escribe qué otros servicios ofreces, separados por comas."
      pattern="[\p{L}\d][\p{L}\d ()\/.\-]{2,39}(\s*,\s*[\p{L}\d][\p{L}\d ()\/.\-]{2,39})*\s*,?"
      data-msg-pattern="Separa los servicios con comas; cada uno de 3 a 40 letras o números.">
    <div class="hint">Sepáralos con comas (hasta 8). Aparecerán en tu página, en tu tarifario y para agendar citas. Los planes PetCare solo cubren los servicios del catálogo.</div>
  </div>
</div>
