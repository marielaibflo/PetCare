{{-- Sello de la clínica en la cartilla: su logo o, si no lo subió, un sello con sus siglas. --}}
@props(['v' => null, 's' => 46])
@if($v?->logo)
  <img class="sello" src="{{ route('archivos.logo', $v) }}" alt="Sello de {{ $v->nombre }}" width="{{ $s }}" height="{{ $s }}" style="width:{{ $s }}px;height:{{ $s }}px">
@elseif($v)
  <span class="sello sello-txt" style="width:{{ $s }}px;height:{{ $s }}px" title="{{ $v->nombre }}" aria-label="Sello de {{ $v->nombre }}"><b>{{ $v->siglas() }}</b><i>{{ \Illuminate\Support\Str::limit($v->nombre, 18, '') }}</i></span>
@else
  <span class="sello sello-txt ext" style="width:{{ $s }}px;height:{{ $s }}px"><b>EXT</b></span>
@endif
