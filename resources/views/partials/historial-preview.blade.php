<div class="hist-prev">
  <div style="display:flex;gap:12px;align-items:center;margin-bottom:12px"><x-pet :m="$mascota" :s="52"/><div><h2>{{ $mascota->nombre }}</h2><div class="sub">{{ $mascota->resumen() }} · {{ $mascota->raza }} · {{ $mascota->peso }} kg</div>@if($mascota->alergias)<div class="chip bad" style="margin-top:4px">Alergias: {{ $mascota->alergias }}</div>@endif</div></div>
  @include('partials.historial', ['atenciones' => $mascota->atenciones])
</div>
