@extends('layouts.app')
@section('title', 'Mis citas')
@section('heading', 'Mis citas')
@section('sub', 'La veterinaria confirma cada solicitud.')
@section('acts')<button class="btn" data-open="dlg-cita"><x-icon name="plus"/>Agendar cita</button>@endsection
@section('content')
@include('partials.pago-whatsapp')
<div class="grid">
  <section><h2 style="margin-bottom:10px">Próximas</h2><div class="card">@if($proximas->isEmpty())<x-empty icon="cal" title="No tienes citas próximas" text="Agenda una cita con una veterinaria afiliada."/>@else<div class="lst">@foreach($proximas as $c)@include('partials.cita-row', ['ctx' => 'owner'])@endforeach</div>@endif</div></section>
  <section><h2 style="margin-bottom:10px">Anteriores y canceladas</h2><div class="card">@if($anteriores->isEmpty())<x-empty icon="clock" title="Sin registros" text="Aquí verás las citas atendidas o canceladas."/>@else<div class="lst">@foreach($anteriores as $c)@include('partials.cita-row', ['ctx' => 'owner'])@endforeach</div>@endif</div></section>
</div>
@endsection
