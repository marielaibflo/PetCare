@extends('layouts.app')
@section('title', 'Mis mascotas')
@section('heading', 'Mis mascotas')
@section('sub', 'Toca una mascota para ver su ficha, vacunas, historial y plan.')
@section('acts')<button class="btn pc" data-open="dlg-mascota-nueva"><x-icon name="plus"/>Agregar mascota</button>@endsection
@section('content')
<div class="pets">
  @foreach($mascotas as $m)<div class="pet-w"><a class="card pet-c" href="{{ route('propietario.mascotas.show', $m) }}"><x-pet :m="$m" :s="80"/><b>{{ $m->nombre }}</b><span>{{ $m->resumen() }}</span><span>{{ $m->raza }}</span><x-plan-chip :m="$m"/></a>
    <x-libreta-visor :mascota="$m" :carnet="$carnets[$m->id]" :imprimir="route('propietario.mascotas.cartilla', $m)" boton="Cartilla" clase="btn sm ghost"/></div>@endforeach
  <button class="add-c" data-open="dlg-mascota-nueva"><span class="ti c-p" style="background:#fff"><x-icon name="plus"/></span>Agregar mascota</button>
</div>
@endsection
