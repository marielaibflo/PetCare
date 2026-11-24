@props(['m'])
@if($m->afiliacionActiva)<span class="chip ok">{{ $m->afiliacionActiva->plan->nombre }} · {{ $m->afiliacionActiva->plan->porcentaje }} %</span>@else<span class="chip mut">Sin plan</span>@endif
