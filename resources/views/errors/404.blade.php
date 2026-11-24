@extends('layouts.base')
@section('title', 'No encontrado')
@section('body')
<div class="err-page"><div><b>404</b><h1 style="margin:10px 0 6px">No encontramos lo que buscas</h1><p class="sub" style="margin-bottom:18px">Puede que el registro haya sido eliminado o que no tengas acceso a él.</p>
<a class="btn" href="{{ auth()->check() ? auth()->user()->rutaInicio() : route('welcome') }}">Ir a mi inicio</a></div></div>
@endsection
