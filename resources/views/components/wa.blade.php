{{-- Teléfono que abre WhatsApp con ese número. $internacional: 591XXXXXXXX --}}
@props(['internacional' => null, 'texto' => null])
@if($internacional)
<a class="lnk-mail wa" href="{{ \App\Services\WhatsAppService::enlace($internacional) }}" target="_blank" rel="noopener" title="Escribir por WhatsApp"><x-icon name="phone"/>{{ $texto ?? '+'.$internacional }}</a>
@else {{ $texto ?? '—' }} @endif
