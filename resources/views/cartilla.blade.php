@extends('layouts.base')
@section('title', 'Cartilla de '.$mascota->nombre)
@section('body')
<div class="cartilla">
  <div class="noprint cartilla-acc">
    <a class="btn ghost" href="{{ $volver }}"><x-icon name="back"/>Volver</a>
    <button class="btn" type="button" data-print><x-icon name="print"/>Imprimir o guardar PDF</button>
  </div>
  @include('partials.cartilla-libreta', ['mascota' => $mascota, 'carnet' => $carnet, 'acciones' => false])
  <p class="hint" style="margin-top:18px">Copia digital emitida por PetCare el {{ \App\Support\Formato::fechaLarga(today()) }}. Respaldo de la cartilla física.</p>
</div>
@endsection
