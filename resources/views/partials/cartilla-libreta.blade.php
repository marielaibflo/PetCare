{{--
  Cartilla digital PetCare con diseño de libreta.
  $mascota, $carnet (vacunas y desparasitaciones), $acciones (true = el dueño puede ver detalles y adjuntar el certificado).
--}}
@php
  $dueno = $mascota->propietario;
  $vacunas = $carnet->where('servicio', 'Vacuna');
  $desp = $carnet->whereIn('servicio', ['Desparasitación', 'Control de pulgas']);
  $acciones = $acciones ?? false;
@endphp
<div class="libreta">
  {{-- Hoja 1 · Datos --}}
  <section class="hoja hoja-datos">
    <header class="hoja-h"><span class="lg"><svg viewBox="0 0 24 24" aria-hidden="true">{!! \App\Support\Iconos::PATA !!}</svg></span><div><b>PetCare</b><small>Cartilla de vacunación y desparasitación</small></div></header>
    <div class="ficha">
      <div class="ficha-foto"><x-pet :m="$mascota" :s="108"/><span class="nh">N.º {{ $mascota->numeroHistoria() }}</span></div>
      <dl class="ficha-datos">
        <div class="w2"><dt>Nombre</dt><dd>{{ $mascota->nombre }}</dd></div>
        <div><dt>Especie</dt><dd>{{ $mascota->especie }}</dd></div><div><dt>Raza</dt><dd>{{ $mascota->raza }}</dd></div>
        <div><dt>Sexo</dt><dd>{{ $mascota->sexo }}{{ $mascota->esterilizado ? ' · esterilizado/a' : '' }}</dd></div><div><dt>Color</dt><dd>{{ $mascota->color ?: '—' }}</dd></div>
        <div><dt>Nacimiento</dt><dd>{{ $mascota->fecha_nacimiento->format('d/m/Y') }}</dd></div><div><dt>Peso</dt><dd>{{ $mascota->peso ? rtrim(rtrim(number_format($mascota->peso, 1, ',', ''), '0'), ',').' kg' : '—' }}</dd></div>
        <div class="w2"><dt>Alergias</dt><dd>{{ $mascota->alergias ?: 'Ninguna conocida' }}</dd></div>
        <div class="w2"><dt>Propietario</dt><dd>{{ $dueno?->name }}</dd></div>
        <div class="w2"><dt>Dirección</dt><dd>{{ $dueno?->direccion ?: '—' }}</dd></div>
        <div class="w2"><dt>Celular</dt><dd>{{ $dueno?->telefonoVisible() }}</dd></div>
      </dl>
    </div>
    <p class="hoja-nota">Cumpla el plan de vacunación de su mascota. Lleve esta cartilla a cada control. Los registros tienen la firma digital del veterinario y el sello de la clínica que aplicó la dosis.</p>
  </section>

  {{-- Hoja 2 · Vacunas --}}
  @foreach([['Vacunas', 'vac', $vacunas, 'Vacuna'], ['Desparasitación', 'desp', $desp, 'Producto']] as [$titulo, $clase, $filas, $col])
  <section class="hoja hoja-{{ $clase }}">
    <h3 class="hoja-t">{{ $titulo }}</h3>
    <table class="tabla-libreta">
      <thead><tr><th>Fecha</th><th>{{ $col }}</th><th>Próxima</th><th>Firma y sello</th>@if($acciones)<th class="noprint"></th>@endif</tr></thead>
      <tbody>
        @forelse($filas->sortBy('fecha') as $c)
          <tr>
            <td data-l="Fecha">{{ $c->fecha->format('d/m/Y') }}</td>
            <td data-l="{{ $col }}"><b>{{ $c->producto ?: $c->servicio }}</b>@if($c->lote)<small>Lote {{ $c->lote }}</small>@endif @if($c->servicio === 'Control de pulgas')<small>Control de pulgas</small>@endif</td>
            <td data-l="Próxima">{{ $c->proxima_dosis?->format('d/m/Y') ?? '—' }}</td>
            <td data-l="Firma"><div class="firma"><x-sello :v="$c->veterinaria" :s="42"/><div><b>{{ \Illuminate\Support\Str::before($c->firmado_por ?? '—', ' · ') }}</b>
              @if($c->matricula)<small>Mat. {{ $c->matricula }}</small>@endif<small>{{ $c->veterinaria?->nombre ?? $c->clinica_externa ?? '—' }}</small></div></div></td>
            @if($acciones)
              <td class="noprint acc"><button type="button" class="btn sm ghost" data-open="dlg-reg-{{ $c->id }}">Ver detalles</button>
                @if($c->documento_dueno)<span class="chip ok adj"><x-icon name="check"/>Certificado</span>@endif</td>
            @endif
          </tr>
        @empty
          @foreach(range(1, 3) as $i)<tr class="vacia"><td>&nbsp;</td><td></td><td></td><td></td>@if($acciones)<td class="noprint"></td>@endif</tr>@endforeach
        @endforelse
      </tbody>
    </table>
  </section>
  @endforeach
</div>

