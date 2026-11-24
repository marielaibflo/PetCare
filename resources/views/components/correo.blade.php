{{-- Correo que abre Gmail en una pestaña nueva con el mensaje listo para escribir. --}}
@props(['email' => null, 'asunto' => 'PetCare – '])
@if($email)
<a class="lnk-mail" href="https://mail.google.com/mail/?view=cm&fs=1&to={{ rawurlencode($email) }}&su={{ rawurlencode($asunto) }}" target="_blank" rel="noopener" title="Escribir en Gmail"><x-icon name="mail"/>{{ $email }}</a>
@else — @endif
