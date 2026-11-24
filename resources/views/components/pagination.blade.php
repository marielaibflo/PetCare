@props(['p'])
@if($p->hasPages())
<nav class="pag" aria-label="Paginación">
  @if($p->onFirstPage())<span class="off">‹</span>@else<a href="{{ $p->previousPageUrl() }}" aria-label="Anterior">‹</a>@endif
  @foreach(range(1, $p->lastPage()) as $n)
    @if($n === $p->currentPage())<span class="on">{{ $n }}</span>@else<a href="{{ $p->url($n) }}">{{ $n }}</a>@endif
  @endforeach
  @if($p->hasMorePages())<a href="{{ $p->nextPageUrl() }}" aria-label="Siguiente">›</a>@else<span class="off">›</span>@endif
</nav>
@endif
