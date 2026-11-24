@props(['icon' => 'info', 'title', 'text' => ''])
<div class="empty"><span class="ti c-p"><x-icon :name="$icon"/></span><b>{{ $title }}</b><div>{{ $text }}</div>@if(trim($slot))<div style="margin-top:14px">{{ $slot }}</div>@endif</div>
