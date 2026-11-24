@if($atenciones->isEmpty())
  <x-empty icon="doc" title="Sin atenciones registradas" text="Las atenciones aparecerán aquí cuando una veterinaria las registre."/>
@else
<div class="lst">
  @foreach($atenciones as $a)
  <div class="row"><x-svc :servicio="$a->servicio"/>
    <div class="tx"><b>{{ $a->fecha->format('d/m/Y') }} · {{ $a->servicio }}{{ $a->producto ? ' · '.$a->producto : '' }}</b>
      <span>{{ $a->diagnostico ? 'Diagnóstico: '.$a->diagnostico.' · ' : '' }}{{ $a->descripcion }}@if($a->tratamiento && $a->tratamiento !== 'Ninguno') · Tratamiento: {{ $a->tratamiento }}@endif @if($a->proxima_dosis) · Próxima dosis: {{ $a->proxima_dosis->format('d/m/Y') }}@endif · {{ $a->lugar() }}</span>
      @if($a->total > 0)
        <span class="costo">Total {{ \App\Support\Formato::bs($a->total) }}@if($a->monto_cubierto > 0) · PetCare cubrió {{ \App\Support\Formato::bs($a->monto_cubierto) }} ({{ $a->porcentaje }} %)@endif · pagó el dueño {{ \App\Support\Formato::bs($a->paga_dueno) }}@if($a->nota_cobertura)<br><i>{{ $a->nota_cobertura }}</i>@endif</span>
      @endif
    </div>
    <div class="ra">@if($a->registrado_por === 'propietario')<span class="chip info">Cartilla física</span>@elseif($a->monto_cubierto > 0)<span class="chip ok">Cubierto {{ $a->porcentaje }} %</span>@else<span class="chip mut">Particular</span>@endif</div>
  </div>
  @endforeach
</div>
@endif
