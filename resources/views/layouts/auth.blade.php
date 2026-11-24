@extends('layouts.base')
@section('body')
<div class="auth">
  <div class="auth-art"><div style="width:100%;max-width:460px">@yield('art')</div><h2>@yield('art-title')</h2><p>@yield('art-text')</p></div>
  <div class="auth-f"><div class="auth-box">
    <div class="logo-c"><span class="lg"><svg viewBox="0 0 24 24" aria-hidden="true">{!! \App\Support\Iconos::PATA !!}</svg></span><h1>PetCare</h1><span>@yield('logo-sub', 'Asistencia veterinaria')</span></div>
    @yield('content')
  </div></div>
</div>
@endsection
