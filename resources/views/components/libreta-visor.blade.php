{{-- Cartilla como libreta física de cartulina, en ventana flotante: tapa, datos, vacunas, desparasitaciones,
     otros tratamientos y contratapa. Páginas casi cuadradas de tamaño fijo (los registros se reparten en varias hojas).
     Uso: <x-libreta-visor :mascota="$mascota" :carnet="$carnet" :imprimir="route(...)" boton="Abrir cartilla" clase="btn"/> --}}
@props(['mascota', 'carnet', 'imprimir' => null, 'boton' => 'Abrir cartilla', 'clase' => 'btn sm'])
@php
  $id = 'dlg-libreta-'.$mascota->id;
  $dueno = $mascota->propietario;
  $POR_HOJA = 6;
  $vacunas = $carnet->where('servicio', 'Vacuna')->sortBy('fecha')->values();
  $desp = $carnet->whereIn('servicio', ['Desparasitación', 'Control de pulgas'])->sortBy('fecha')->values();
  $otros = $mascota->atenciones()->with('veterinaria')->with('autor')->whereNotIn('servicio', \App\Support\Catalogo::PREVENTIVOS)->orderBy('fecha')->get();
  // Cada sección ocupa al menos una hoja; si hay muchos registros, continúa en otra hoja.
  $hojas = function ($filas) use ($POR_HOJA) { return $filas->isEmpty() ? collect([collect()]) : $filas->chunk($POR_HOJA); };
  $secciones = [
    ['Vacunas', 'vac', $hojas($vacunas), ['Fecha', 'Vacuna / lote', 'Próxima', 'Firma y sello']],
    ['Desparasitación', 'desp', $hojas($desp), ['Fecha', 'Producto', 'Próxima', 'Firma y sello']],
    ['Otros tratamientos', 'otr', $hojas($otros), ['Fecha', 'Atención', 'Tratamiento', 'Firma y sello']],
  ];
  $firma = fn ($c) => \Illuminate\Support\Str::before($c->firmado_por ?? ($c->autor?->name ?? '—'), ' · ');
@endphp
<button type="button" class="{{ $clase }}" data-open="{{ $id }}"><x-icon name="doc"/>{{ $boton }}</button>
@pushOnce('dialogs', $id)
<dialog class="md win libro-win" id="{{ $id }}" aria-label="Cartilla de {{ $mascota->nombre }}">
  <div class="md-h"><span class="ti c-p"><x-icon name="doc"/></span><h2>Cartilla de {{ $mascota->nombre }}</h2><button type="button" class="icb" data-close aria-label="Cerrar"><x-icon name="x"/></button></div>
  <div class="libro-mesa" data-libro-mesa>
    <button type="button" class="libro-flecha ant" data-libro="prev" aria-label="Página anterior"><x-icon name="back"/></button>
    <div class="libro" data-libro>
      {{-- Tapa --}}
      <section class="libro-p tapa">
        <div class="tapa-in">
          <span class="tapa-logo"><svg viewBox="0 0 24 24" aria-hidden="true">{!! \App\Support\Iconos::PATA !!}</svg></span>
          <b class="tapa-marca">PetCare</b>
          <h3>Cartilla de vacunación</h3>
          <div class="tapa-foto"><x-pet :m="$mascota" :s="112"/></div>
          <b class="tapa-nom">{{ $mascota->nombre }}</b>
          <small>{{ $mascota->especie }} · {{ $mascota->raza }}</small>
          <span class="tapa-num">N.º {{ $mascota->numeroHistoria() }}</span>
          <span class="tapa-pie">Asistencia veterinaria · La Paz – Bolivia</span>
        </div>
      </section>

      {{-- Datos de la mascota y del dueño --}}
      <section class="libro-p carton" data-nombre="Datos">
        <header class="carton-h"><span class="lg"><svg viewBox="0 0 24 24" aria-hidden="true">{!! \App\Support\Iconos::PATA !!}</svg></span><div><b>Datos de la mascota</b><small>Cartilla N.º {{ $mascota->numeroHistoria() }}</small></div></header>
        <div class="carton-ficha">
          <div class="carton-foto"><x-pet :m="$mascota" :s="92"/></div>
          <dl class="renglones">
            <div><dt>Nombre</dt><dd>{{ $mascota->nombre }}</dd></div>
            <div><dt>Especie</dt><dd>{{ $mascota->especie }}</dd></div>
            <div><dt>Raza</dt><dd>{{ $mascota->raza }}</dd></div>
            <div><dt>Sexo</dt><dd>{{ $mascota->sexo }}{{ $mascota->esterilizado ? ' · esterilizado/a' : '' }}</dd></div>
          </dl>
        </div>
        <dl class="renglones dos">
          <div><dt>Nacimiento</dt><dd>{{ $mascota->fecha_nacimiento->format('d/m/Y') }}</dd></div>
          <div><dt>Color</dt><dd>{{ $mascota->color ?: '—' }}</dd></div>
          <div><dt>Peso</dt><dd>{{ $mascota->peso ? rtrim(rtrim(number_format($mascota->peso, 1, ',', ''), '0'), ',').' kg' : '—' }}</dd></div>
          <div><dt>Alergias</dt><dd>{{ $mascota->alergias ?: 'Ninguna' }}</dd></div>
        </dl>
        <h4 class="carton-sub">Propietario</h4>
        <dl class="renglones">
          <div><dt>Nombre</dt><dd>{{ $dueno?->name }}</dd></div>
          <div><dt>Dirección</dt><dd>{{ $dueno?->direccion ?: '—' }}</dd></div>
          <div><dt>Celular</dt><dd>{{ $dueno?->telefonoVisible() }}</dd></div>
        </dl>
      </section>

      {{-- Registros: vacunas, desparasitaciones y otros tratamientos --}}
      @foreach($secciones as [$titulo, $clase, $paginas, $cols])
        @foreach($paginas as $k => $filas)
          <section class="libro-p carton hoja-{{ $clase }}" data-nombre="{{ $titulo }}{{ $paginas->count() > 1 ? ' ('.($k + 1).'/'.$paginas->count().')' : '' }}">
            <h3 class="carton-t">{{ $titulo }}{{ $paginas->count() > 1 ? ' · hoja '.($k + 1) : '' }}</h3>
            <table class="carton-tabla">
              <thead><tr>@foreach($cols as $c)<th>{{ $c }}</th>@endforeach</tr></thead>
              <tbody>
                @foreach($filas as $c)
                  <tr>
                    <td class="f">{{ $c->fecha->format('d/m/Y') }}</td>
                    @if($clase === 'otr')
                      <td><b>{{ $c->servicio }}</b>@if($c->diagnostico)<small>{{ \Illuminate\Support\Str::limit($c->diagnostico, 40) }}</small>@endif</td>
                      <td><small>{{ \Illuminate\Support\Str::limit($c->tratamiento && $c->tratamiento !== 'Ninguno' ? $c->tratamiento : '—', 50) }}</small></td>
                    @else
                      <td><b>{{ $c->producto ?: $c->servicio }}</b>@if($c->lote)<small>Lote {{ $c->lote }}</small>@endif</td>
                      <td class="f prox">{{ $c->proxima_dosis?->format('d/m/Y') ?? '—' }}</td>
                    @endif
                    <td><div class="firma"><x-sello :v="$c->veterinaria" :s="34"/><div><b>{{ $firma($c) }}</b>@if($c->matricula)<small>Mat. {{ $c->matricula }}</small>@endif</div></div></td>
                  </tr>
                @endforeach
                @for($i = $filas->count(); $i < $POR_HOJA; $i++)<tr class="vacia"><td></td><td></td><td></td><td></td></tr>@endfor
              </tbody>
            </table>
          </section>
        @endforeach
      @endforeach

      {{-- Contratapa --}}
      <section class="libro-p contra" data-nombre="Contratapa">
        <div><b>Recomendaciones</b>
          <ul><li>Lleve esta cartilla a cada control veterinario.</li><li>Cumpla las fechas de la próxima dosis: PetCare le avisa 7 días antes.</li>
            <li>Cada registro lleva la firma del veterinario (matrícula) y el sello de la clínica.</li></ul></div>
        <span class="tapa-pie">PetCare · Asistencia veterinaria</span>
      </section>
    </div>
    <button type="button" class="libro-flecha sig" data-libro="next" aria-label="Página siguiente"><x-icon name="back"/></button>
  </div>
  <div class="libro-bar">
    <span class="libro-pag" data-libro-pag>Tapa</span>
    <span class="libro-puntos" data-libro-puntos></span>
    @if($imprimir)<a class="btn sm ghost" href="{{ $imprimir }}" target="_blank" rel="noopener"><x-icon name="print"/>Imprimir / PDF</a>@endif
    <button type="button" class="btn sm" data-close>Cerrar</button>
  </div>
</dialog>
@endPushOnce
