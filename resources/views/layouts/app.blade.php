@extends('layouts.base')
@php
  $u = auth()->user();
  $nav = \App\Support\Navegacion::para($u);
  $on = fn ($n) => request()->routeIs(...$n[3]);
  $movil = array_slice($nav, 0, 4);
  $resto = array_slice($nav, 4);
@endphp
@section('body')
<div class="layout">
  <aside class="sb">
    <div><a class="brand" href="{{ $u->rutaInicio() }}"><span class="lg"><svg viewBox="0 0 24 24" aria-hidden="true">{!! \App\Support\Iconos::PATA !!}</svg></span><span>PetCare<small>{{ \App\Support\Navegacion::subtitulo($u) }}</small></span></a></div>
    <div class="rbox"><span class="rtag {{ $u->rol }}">{{ $u->rolNombre() }}</span></div>
    <nav aria-label="Menú principal">
      @foreach($nav as $n)<a class="{{ $on($n) ? 'on' : '' }}" href="{{ route($n[0]) }}" @if($on($n)) aria-current="page" @endif><x-icon :name="$n[1]"/>{{ $n[2] }}@if(! empty($n[4]))<span class="nav-n" title="{{ $n[4] }} por revisar">{{ $n[4] }}</span>@endif</a>@endforeach
    </nav>
    <div class="me"><x-avatar :u="$u"/><div style="min-width:0"><b>{{ $u->name }}</b><span>{{ $u->email }}</span></div>
      <form method="POST" action="{{ route('logout') }}">@csrf<button class="icb" title="Cerrar sesión" aria-label="Cerrar sesión"><x-icon name="out"/></button></form></div>
  </aside>
  <div class="main">
    <header class="tb"><div class="tb-in">
      <a class="brand" href="{{ $u->rutaInicio() }}"><span class="lg"><svg viewBox="0 0 24 24" aria-hidden="true">{!! \App\Support\Iconos::PATA !!}</svg></span><span>PetCare</span></a>
      <div class="pt">
        @hasSection('top')
          @yield('top')
        @else
          @yield('title')
        @endif
      </div>
      <div class="tb-r">
        <button class="ib" aria-label="Notificaciones" data-open="dlg-notif"><x-icon name="bell"/>@if(count($notificaciones))<i class="dot">{{ count($notificaciones) }}</i>@endif</button>
        <a class="who" href="{{ route(end($nav)[0]) }}"><x-avatar :u="$u"/><span><b>{{ $u->name }}</b><span class="rtag {{ $u->rol }}">{{ $u->rolNombre() }}</span></span></a>
        <x-avatar :u="$u" clase="mob" aria-hidden="true"/>
      </div>
    </div></header>
    <main class="content">
      <div class="print-h" aria-hidden="true"><b>PetCare</b><span>@yield('title') · impreso el {{ now()->format('d/m/Y H:i') }} por {{ $u->name }}</span></div>
      @hasSection('heading')
        <div class="ph">
          @hasSection('back')<a class="back mob" href="@yield('back')" aria-label="Volver"><x-icon name="back"/></a>@endif
          <div><h1>@yield('heading')</h1>@hasSection('sub')<div class="sub">@yield('sub')</div>@endif</div>
          @hasSection('acts')<div class="acts">@yield('acts')</div>@endif
        </div>
      @endif
      @yield('content')
    </main>
  </div>
  <nav class="bn" aria-label="Menú inferior">
    @foreach($movil as $n)<a class="{{ $on($n) ? 'on' : '' }}" href="{{ route($n[0]) }}"><x-icon :name="$n[1]"/>{{ $n[2] }}@if(! empty($n[4]))<span class="nav-n" title="{{ $n[4] }} por revisar">{{ $n[4] }}</span>@endif</a>@endforeach
    <button class="{{ collect($resto)->contains(fn ($n) => $on($n)) ? 'on' : '' }}" data-open="dlg-menu"><x-icon name="more"/>Más</button>
  </nav>
</div>

<x-dialog id="dlg-notif" title="Notificaciones" icon="bell">
  @forelse($notificaciones as $n)
    @if($loop->first)<div class="lst">@endif
    <a class="row" style="padding:12px 4px" href="{{ $n['url'] }}"><span class="ti {{ $n['color'] }}"><x-icon :name="$n['icono']"/></span><div class="tx"><b>{{ $n['titulo'] }}</b><span>{{ $n['detalle'] }}</span></div></a>
    @if($loop->last)</div>@endif
  @empty
    <x-empty icon="bell" title="Sin notificaciones" text="Te avisaremos sobre citas, vacunas y vencimientos."/>
  @endforelse
</x-dialog>

<x-dialog id="dlg-menu" title="Menú">
  <div class="sheet lst">
    @foreach($nav as $n)<a class="{{ $on($n) ? 'on' : '' }}" href="{{ route($n[0]) }}"><x-icon :name="$n[1]"/>{{ $n[2] }}@if(! empty($n[4]))<span class="nav-n" title="{{ $n[4] }} por revisar">{{ $n[4] }}</span>@endif</a>@endforeach
    <form method="POST" action="{{ route('logout') }}">@csrf<button class="lo"><x-icon name="out"/>Cerrar sesión</button></form>
  </div>
</x-dialog>

@if($u->esRol('propietario'))@include('partials.propietario-dialogs')@endif
@endsection
