@props(['m', 's' => 72])
@php
    $fondos = ['#FFF1DB', '#EEF1F6', '#E3F6EF', '#EFEBFF', '#FDE8E8', '#E3EEFF'];
    $bg = $fondos[mb_strlen($m->nombre) % count($fondos)];
    $r = $s >= 96 ? '22%' : '50%';
@endphp
@if($m->foto)
<img class="pet" src="{{ route('archivos.mascota', $m) }}?v={{ substr(md5($m->foto), 0, 6) }}" alt="Foto de {{ $m->nombre }}" width="{{ $s }}" height="{{ $s }}" style="border-radius:{{ $r }};object-fit:cover;width:{{ $s }}px;height:{{ $s }}px" loading="lazy">
@else
<svg class="pet" style="border-radius:{{ $r }}" width="{{ $s }}" height="{{ $s }}" viewBox="0 0 100 100" role="img" aria-label="{{ $m->nombre }}"><rect width="100" height="100" fill="{{ $bg }}"/>@if($m->especie === 'Perro')@include('partials.art.dog')@elseif($m->especie === 'Gato')@include('partials.art.cat')@else<g transform="translate(22 22) scale(2.35)" fill="#8B74F0">{!! \App\Support\Iconos::PATA !!}</g>@endif</svg>
@endif
