{{-- Avatar del usuario: su foto de perfil o, si no tiene, sus iniciales. --}}
@props(['u', 'clase' => '', 'style' => null])
<span {{ $attributes->merge(['class' => 'av '.$u->rol.' '.$clase.($u->foto ? ' con-foto' : '')]) }} @if($style) style="{{ $style }}" @endif>@if($u->foto)<img src="{{ route('archivos.usuario', $u) }}?v={{ $u->updated_at?->timestamp }}" alt="">@else{{ $u->iniciales() }}@endif</span>
