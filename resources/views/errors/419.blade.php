@extends('layouts.base')
@section('title', 'Sesión vencida')
@section('body')
<div class="err-page"><div><b>419</b><h1 style="margin:10px 0 6px">La página venció</h1><p class="sub" style="margin-bottom:18px">Pasó mucho tiempo sin actividad. Vuelve a intentarlo.</p>
<a class="btn" href="{{ url()->previous() }}">Volver</a></div></div>
@endsection
