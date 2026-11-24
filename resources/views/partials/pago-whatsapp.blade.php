{{-- Después de registrar un pago: mensaje corto para WhatsApp (obligatorio si eligió WhatsApp, opcional en tarjeta/QR). --}}
@php($pw = session('pago_whatsapp') ? \App\Models\Pago::with(['plan', 'mascota', 'veterinaria', 'cita'])->find(session('pago_whatsapp')) : null)
@if($pw && $pw->estado === 'pendiente')
  @php($a = $pw->loVerificaLaClinica() ? $pw->veterinaria?->nombre : 'PetCare')
  <div class="card pad wa-paso">
    <span class="ti {{ $pw->metodo === 'whatsapp' ? 'wa-ic' : 'c-ok' }}"><x-icon :name="$pw->metodo === 'whatsapp' ? 'phone' : 'check'"/></span>
    <div class="tx">
      @if($pw->metodo === 'whatsapp')
        <b>Último paso: pide el QR por WhatsApp</b>
        <span>Envía este mensaje a {{ $a }} y te responderán con el QR para pagar <b>{{ \App\Support\Formato::bs($pw->monto) }}</b>.</span>
      @else
        <b>¡Pago enviado! {{ $a }} lo verificará</b>
        <span>Te avisaremos por WhatsApp y correo cuando se confirme. Si quieres, avísales ahora:</span>
      @endif
      <blockquote>{{ $pw->mensajeWhatsapp() }}</blockquote></div>
    <a class="btn wa-btn" href="{{ $pw->enlaceWhatsapp() }}" target="_blank" rel="noopener" data-abrir-wa><x-icon name="phone"/>{{ $pw->metodo === 'whatsapp' ? 'Abrir WhatsApp' : 'Avisar por WhatsApp' }}</a>
  </div>
@endif
