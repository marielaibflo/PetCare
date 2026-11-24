{{-- Casilla «Acepto el contrato de afiliación» con el contrato en ventana flotante (se lee hasta el final, como los términos).
     Los datos marcados con data-ct se completan con lo que el dueño elige en el formulario. --}}
@props(['plan', 'user'])
@php
  $hoy = today();
  $tok = fn ($k) => '§'.$k.'§';
  $d = ['dueno' => e($user->name), 'ci' => $tok('ci'), 'mascota' => $tok('mascota'), 'especie' => $tok('especie'), 'plan' => e($plan->nombre).$tok('grave'),
    'porcentaje' => $plan->porcentaje, 'veterinaria' => $tok('veterinaria'), 'direccion_vet' => $tok('direccion_vet'), 'precio' => $tok('precio'),
    'modalidad' => $tok('modalidad'), 'inicio' => $hoy->format('d/m/Y'), 'permanencia' => $hoy->copy()->addMonths($plan->permanencia_meses)->format('d/m/Y'),
    'carencia' => 'desde el '.$hoy->copy()->addDays($plan->carencia_dias)->format('d/m/Y'), 'permanencia_meses' => $plan->permanencia_meses,
    'carencia_dias' => $plan->carencia_dias, 'tope_mensual' => \App\Support\Formato::bs($plan->tope_mensual), 'tope_anual' => \App\Support\Formato::bs($plan->tope_anual),
    'limite' => $plan->limite_atenciones];
  $html = fn ($s) => preg_replace('/§(\w+)§/', '<b class="ct-dato" data-ct="$1">…</b>', $s);
@endphp
<div class="acepto-box" data-campo="contrato">
  <label class="cb acepto"><input type="checkbox" name="contrato" value="1" data-terminos="dlg-terminos-contrato" @checked(old('contrato')) required data-msg="Lee y acepta el contrato de afiliación para continuar.">
    <span>Acepto el <button type="button" class="lnk" data-open="dlg-terminos-contrato">contrato de afiliación</button>: permanencia mínima de {{ $plan->permanencia_meses }} meses y uso del plan desde el día {{ $plan->carencia_dias + 1 }}.</span></label>
</div>
@pushOnce('dialogs', 'contrato')
<dialog class="md md-lg win" id="dlg-terminos-contrato" aria-label="Contrato de afiliación">
  <div class="md-h"><span class="ti c-p"><x-icon name="doc"/></span><h2>Contrato de afiliación · {{ $plan->nombre }}</h2><button type="button" class="icb" data-close aria-label="Cerrar"><x-icon name="x"/></button></div>
  <div class="legal-scroll contrato" tabindex="0">
    <p class="ct-intro">Contrato N.º <b>se asigna al aprobar el pago</b> · Fecha: {{ $hoy->format('d/m/Y') }}</p>
    <ol>@foreach(\App\Support\Contrato::clausulas($d) as [$t, $txt])<li><b>{{ $t }}.</b> {!! $html($txt) !!}</li>@endforeach</ol>
    <p class="hint">Recibirás este contrato en PDF, junto con tu factura, cuando PetCare verifique el pago.</p>
  </div>
  <div class="legal-pie">
    <div class="legal-prog"><i></i></div>
    <label class="cb"><input type="checkbox" data-terminos-ok disabled><span>He leído y <b>acepto el contrato de afiliación</b></span></label>
    <div class="md-f" style="margin:0"><span class="hint" data-terminos-hint>Desliza hasta el final para poder aceptar.</span><button type="button" class="btn" data-terminos-aceptar disabled>Aceptar contrato</button></div>
  </div>
</dialog>
@endPushOnce
