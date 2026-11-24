<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>@yield('title', 'PetCare') · PetCare</title>
<meta name="description" content="PetCare: plataforma web de gestión de seguros y cuidado animal.">
<link rel="icon" href="data:image/svg+xml,{{ rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><rect width="24" height="24" rx="6" fill="#5B3FD6"/><g fill="#fff" transform="translate(3 3) scale(.75)">'.\App\Support\Iconos::PATA.'</g></svg>') }}">
<link rel="stylesheet" href="{{ asset('css/petcare.css') }}?v={{ filemtime(public_path('css/petcare.css')) }}">
</head>
<body @if(old('_form') && $errors->any()) data-reabrir="{{ old('_form') }}" @endif>
@yield('body')
@stack('dialogs')

<div id="toast" aria-live="polite">
  @if(session('ok'))<div class="toast" role="status"><x-icon name="check"/><span>{{ session('ok') }}</span></div>@endif
  @if($errors->any() && ! old('_form') && ! $errors->hasBag('perfil') && ! $errors->hasBag('clave') && ! $errors->hasBag('clinica') && ! request()->routeIs('login', 'registro', 'verificacion.show', 'registro.veterinaria', 'propietario.planes.solicitar', 'veterinaria.suscripcion') && ! $errors->hasBag('pago') && ! $errors->hasBag('tarifario'))
    <div class="toast bad" role="alert"><x-icon name="alert"/><span>{{ $errors->first() }}</span></div>
  @endif
</div>

<x-dialog id="dlg-confirm" title="Confirmar">
  <p class="sub" id="dlg-confirm-msg" style="margin-bottom:18px;font-size:15px"></p>
  <div class="md-f"><button type="button" class="btn ghost" data-close>Volver</button><button type="button" class="btn danger" id="dlg-confirm-ok">Confirmar</button></div>
</x-dialog>
@if($errors->any())<script>window.PC_ERR = @json(['form' => old('_form'), 'bags' => collect($errors->getBags())->map(fn ($b) => $b->getMessages())]);</script>@endif
<script src="{{ asset('js/petcare.js') }}?v={{ filemtime(public_path('js/petcare.js')) }}"></script>
@stack('scripts')
</body>
</html>
