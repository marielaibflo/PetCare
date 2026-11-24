@props(['servicio'])
@php([$ic, $cl] = \App\Support\Catalogo::SERVICIO_ICONO[$servicio] ?? ['doc', 'c-i'])
<span class="ti {{ $cl }}"><x-icon :name="$ic"/></span>
