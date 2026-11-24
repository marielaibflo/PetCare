@props(['estado'])
<span class="chip {{ \App\Support\Catalogo::ESTADO_CITA[$estado] ?? 'mut' }}">{{ ucfirst($estado) }}</span>
