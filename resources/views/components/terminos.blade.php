{{-- Casilla «Acepto…» que obliga a leer los términos en una ventana flotante. Uso: <x-terminos para="propietario"/> --}}
@props(['para' => 'propietario', 'name' => 'acepto'])
@php($id = 'dlg-terminos-'.$para)
<div class="acepto-box" data-campo="{{ $name }}">
  <label class="cb acepto"><input type="checkbox" name="{{ $name }}" value="1" data-terminos="{{ $id }}" @checked(old($name)) required data-msg="Lee y acepta los términos y condiciones para continuar.">
    <span>Acepto todos los <button type="button" class="lnk" data-open="{{ $id }}">términos y condiciones del servicio</button>, incluidos los topes y el límite de atenciones cubiertas.</span></label>
</div>
@pushOnce('dialogs', 'terminos-'.$para)
<dialog class="md md-lg win" id="{{ $id }}" aria-label="Términos y condiciones">
  <div class="md-h"><span class="ti c-p"><x-icon name="doc"/></span><h2>Términos y condiciones · {{ $para === 'propietario' ? 'Propietarios' : 'Veterinarias' }}</h2><button type="button" class="icb" data-close aria-label="Cerrar"><x-icon name="x"/></button></div>
  <div class="legal-scroll" tabindex="0">@include('partials.terminos-contenido', ['para' => $para])</div>
  <div class="legal-pie">
    <div class="legal-prog"><i></i></div>
    <label class="cb"><input type="checkbox" data-terminos-ok disabled><span>He leído y <b>acepto todos los términos y condiciones</b></span></label>
    <div class="md-f" style="margin:0"><span class="hint" data-terminos-hint>Desliza hasta el final para poder aceptar.</span><button type="button" class="btn" data-terminos-aceptar disabled>Aceptar y continuar</button></div>
  </div>
</dialog>
@endPushOnce
