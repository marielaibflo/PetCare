{{-- Detalle de cada registro de la cartilla y adjuntar el certificado físico (propietario). --}}
@push('dialogs')
@foreach($carnet as $c)
  <x-dialog :id="'dlg-reg-'.$c->id" :title="($c->producto ?: $c->servicio).' · '.$c->fecha->format('d/m/Y')" icon="shield">
    <div class="grid" style="gap:14px">
      <div class="firma grande"><x-sello :v="$c->veterinaria" :s="58"/><div><b>{{ $c->veterinaria?->nombre ?? $c->clinica_externa ?? 'Registro anterior' }}</b><small>{{ $c->veterinaria ? $c->veterinaria->direccion.', '.$c->veterinaria->zona : '' }}</small>@if($c->registro_clinica)<small>Registro de la clínica: {{ $c->registro_clinica }}</small>@endif</div></div>
      <div class="kv" style="padding:0">
        <div><span>Tipo</span><b>{{ $c->servicio }}</b></div>
        <div><span>Vacuna / producto</span><b>{{ $c->producto ?: '—' }}</b></div>
        <div><span>Lote</span><b>{{ $c->lote ?: '—' }}</b></div>
        <div><span>Fecha de aplicación</span><b>{{ $c->fecha->format('d/m/Y') }}</b></div>
        <div><span>Próxima dosis</span><b>{{ $c->proxima_dosis?->format('d/m/Y') ?? '—' }}</b></div>
        <div><span>Veterinario</span><b>{{ \Illuminate\Support\Str::before($c->firmado_por ?? '—', ' · ') }}</b></div>
        <div><span>Matrícula profesional</span><b>{{ $c->matricula ?: '—' }}</b></div>
        @if($c->peso)<div><span>Peso registrado</span><b>{{ $c->peso }} kg</b></div>@endif
      </div>
      @if($c->foto_etiqueta)<div><b style="font-size:14px">Foto de la etiqueta (subida por la clínica)</b><a href="{{ route('archivos.etiqueta', $c) }}" target="_blank" rel="noopener"><img class="det-img" src="{{ route('archivos.etiqueta', $c) }}" alt="Etiqueta de {{ $c->producto }}"></a></div>@endif
      <div class="adjunto">
        <b style="font-size:14px">Certificado físico</b>
        @if($c->documento_dueno)
          <p class="sub">Ya adjuntaste el certificado. <a class="lnk" href="{{ route('archivos.certificado', $c) }}" target="_blank" rel="noopener">Ver archivo</a></p>
        @else
          <p class="sub">Adjunta la foto o el escaneo del certificado que te dio el veterinario, con sellos y firma.</p>
        @endif
        <form class="f" method="POST" action="{{ route('propietario.mascotas.cartilla.documento', [$mascota, $c]) }}" enctype="multipart/form-data">@csrf
          <x-form-errors :for="'dlg-reg-'.$c->id"/>
          <input class="in" type="file" name="documento" accept="image/jpeg,image/png,image/webp,application/pdf" capture="environment" required data-max-mb="10" data-msg="Elige la foto o el PDF del certificado.">
          <button class="btn sm sec" type="submit"><x-icon name="plus"/>{{ $c->documento_dueno ? 'Reemplazar archivo' : 'Adjuntar archivo' }}</button>
        </form>
      </div>
    </div>
  </x-dialog>
@endforeach
@endpush
