@props(['id', 'title', 'icon' => null, 'clase' => ''])
<dialog class="md {{ $clase }}" id="{{ $id }}" aria-label="{{ $title }}">
  <div class="md-h">@if($icon)<span class="ti c-p"><x-icon :name="$icon"/></span>@endif<h2>{{ $title }}</h2><button type="button" class="icb" data-close aria-label="Cerrar"><x-icon name="x"/></button></div>
  {{ $slot }}
</dialog>
