@extends('layouts.app')
@section('title', 'Solicitar '.$plan->nombre)
@section('heading', 'Solicitar '.$plan->nombre)
@section('sub', 'Cobertura del '.$plan->porcentaje.' % en la veterinaria que elijas · se activa cuando verificamos tu pago · se usa desde el día '.($plan->carencia_dias + 1).'.')
@section('back', route('propietario.planes.index'))
@section('content')
@if($mascotas->isEmpty())
  <div class="card"><x-empty icon="pets" title="Primero registra a tu mascota" text="El plan se asigna a una mascota específica."><button type="button" class="btn" data-open="dlg-mascota-nueva">Agregar mascota</button></x-empty></div>
@else
@php($sel = old('mascota_id', $mascotaElegida ?? $mascotas->first()->id))
<form class="cols" method="POST" action="{{ route('propietario.planes.pagar', $plan) }}" enctype="multipart/form-data" novalidate id="fpago" data-validar
  data-montos='@json($montos)' data-contratos='@json($contratos)'>
  @csrf
  <div class="grid" style="gap:16px">
    <x-errors/>
    <section class="card pad f">
      <h2>1. Datos del plan</h2>
      <div><label for="sp-m">Mascota</label><select class="in" id="sp-m" name="mascota_id" required data-msg="Selecciona la mascota que tendrá el plan.">@foreach($mascotas as $m)<option value="{{ $m->id }}" @selected((string) $sel === (string) $m->id)>{{ $m->nombre }} · {{ $m->especie }}{{ $m->afiliacionActiva ? ' (plan actual: '.$m->afiliacionActiva->plan->nombre.' hasta '.$m->afiliacionActiva->fecha_fin->format('d/m/Y').')' : ' (sin plan)' }}</option>@endforeach</select>
        <div class="hint">Si la mascota ya tiene este plan, el pago lo renueva. Si tiene otro (y ya cumplió su permanencia), se reemplaza desde el día en que se aprueba el pago.</div>
        <div class="note warn" data-mascota-aviso hidden><x-icon name="alert"/><span></span></div></div>
      <div><label>Modalidad de pago</label><div class="opciones">
        @foreach(\App\Support\Catalogo::MODALIDADES as $k => $mo)
          <label class="opc"><input type="radio" name="modalidad" value="{{ $k }}" required data-msg="Elige si pagarás mensual o anual." @checked(old('modalidad', 'mensual') === $k)><span><b>{{ $mo['nombre'] }}</b><small>{{ $mo['meses'] }} {{ $mo['meses'] === 1 ? 'mes' : 'meses' }} de cobertura · pagas {{ $mo['cobra'] }}</small></span></label>
        @endforeach
      </div></div>
      @if($plan->permite_grave)
        <label class="cb"><input type="checkbox" name="cobertura_grave" value="1" @checked(old('cobertura_grave'))><span>Agregar <b>Cobertura Grave</b> (+{{ \App\Support\Formato::bs(\App\Support\Catalogo::ADICIONAL_GRAVE['precio']) }}/mes): cáncer y enfermedades crónicas al {{ \App\Support\Catalogo::ADICIONAL_GRAVE['porcentaje'] }} %, después de {{ \App\Support\Catalogo::ADICIONAL_GRAVE['espera_dias'] }} días.</span></label>
      @endif
    </section>

    <section class="card pad f" data-campo="veterinaria_id">
      <h2>2. Veterinaria del plan</h2>
      <p class="sub" style="margin-top:-6px">El plan <b>solo cubre en la veterinaria que elijas</b> y no se puede cambiar mientras dure el contrato. En tu próximo plan podrás elegir otra.</p>
      <div class="note info" data-vet-fija hidden><x-icon name="lock"/><span></span></div>
      @if($veterinarias->isEmpty())
        <div class="note warn"><x-icon name="alert"/><span>Todavía no hay veterinarias que atiendan planes. Vuelve a intentarlo más tarde.</span></div>
      @else
      <div class="vet-ops">@foreach($veterinarias as $vt)
        <label class="vet-op"><input type="radio" name="veterinaria_id" value="{{ $vt->id }}" required data-msg="Elige la veterinaria donde usarás el plan." data-nombre="{{ $vt->nombre }}" data-dir="{{ $vt->direccion }}, {{ $vt->zona }}" @checked((string) old('veterinaria_id') === (string) $vt->id)>
          <span class="vo-in"><x-sello :v="$vt" :s="40"/><span><b>{{ $vt->nombre }}</b><small>{{ $vt->zona }} · {{ $vt->horario }}</small>
            <small>@if($vt->resenas)<span class="estrella">★</span> {{ number_format($vt->valoracion, 1) }} ({{ $vt->resenas }}) · @endif{{ count($vt->servicios ?? []) }} servicios</small></span></span></label>
      @endforeach</div>
      @endif
    </section>

    <section class="card pad f">
      <h2>3. Datos para la factura</h2>
      <div class="fr"><div><label for="sp-fa">Nombre o razón social</label><input class="in" id="sp-fa" name="facturar_a" value="{{ old('facturar_a', $user->name) }}" required minlength="3" maxlength="100" data-msg="Escribe el nombre o razón social para la factura."></div>
        <div><label for="sp-doc">CI o NIT</label><input class="in" id="sp-doc" name="documento" inputmode="numeric" value="{{ old('documento') }}" placeholder="Ej.: 6543210" required pattern="\d{5,15}" maxlength="15" data-msg="Escribe tu CI o NIT para la factura." data-msg-pattern="El CI o NIT debe tener solo números (5 a 15 dígitos)."></div></div>
      <div class="hint">Te enviaremos la factura a <b>{{ $user->email }}</b>.</div>
    </section>

    <section class="card pad f">
      <h2>4. Forma de pago</h2>
      @include('partials.metodo-pago', ['qr' => route('archivos.qr'), 'banco' => $banco, 'destino' => 'PetCare', 'monto' => $montos['mensual']['sin']])
    </section>
  </div>

  <aside class="card pad resumen-pago">
    <h2>Resumen</h2>
    <div class="kv" style="padding:0">
      <div><span>Plan</span><b>{{ $plan->nombre }} ({{ $plan->porcentaje }} %)</b></div>
      <div><span>Precio mensual</span><b>{{ \App\Support\Formato::bs($plan->precio) }}</b></div>
      <div><span>Tope de cobertura</span><b>{{ \App\Support\Formato::bs($plan->tope_mensual) }}/mes · {{ \App\Support\Formato::bs($plan->tope_anual) }}/año</b></div>
      <div><span>Atenciones cubiertas</span><b>{{ $plan->limite_atenciones ? $plan->limite_atenciones.' al año' : 'Sin límite' }}</b></div>
      <div><span>Veterinaria</span><b data-ct="veterinaria">Elige una</b></div>
      <div><span>Permanencia mínima</span><b>{{ $plan->permanencia_meses }} meses</b></div>
      <div><span>Se puede usar desde</span><b>{{ today()->addDays($plan->carencia_dias)->format('d/m/Y') }}</b></div>
    </div>
    <div class="total">Total a pagar <b id="sp-total">{{ \App\Support\Formato::bs($montos['mensual']['sin']) }}</b></div>
    <x-terminos para="propietario"/>
    <x-contrato :plan="$plan" :user="$user"/>
    <button class="btn full" type="submit" data-btn-pago><x-icon name="card"/><span data-txt="tarjeta">Pagar ahora</span><span data-txt="qr" hidden>Enviar comprobante</span><span data-txt="whatsapp" hidden>Pedir QR por WhatsApp</span></button>
    <p class="hint">PetCare verifica el pago, activa el plan y te envía la factura en PDF a tu correo.</p>
  </aside>
</form>
@endif
@endsection
