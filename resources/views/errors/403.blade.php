@extends('layouts.base')
@section('title', 'Acceso no permitido')
@section('body')
<div class="err-page"><div><b>403</b><h1 style="margin:10px 0 6px">Acceso no permitido</h1><p class="sub" style="margin-bottom:18px">{{ $exception->getMessage() ?: 'No tienes permiso para acceder a esta sección.' }}</p>
<a class="btn" href="{{ auth()->check() ? auth()->user()->rutaInicio() : route('welcome') }}">Ir a mi inicio</a></div></div>
@endsection
