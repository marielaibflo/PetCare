@props(['for'])
@if(old('_form') === $for && $errors->any())
<div class="note bad err-resumen" role="alert"><x-icon name="alert"/><span><b data-err-titulo>Revisa los datos marcados en rojo.</b><ul>@foreach($errors->getMessages() as $campo => $msgs)<li data-err="{{ $campo }}">{{ $msgs[0] }}</li>@endforeach</ul></span></div>
@endif
<input type="hidden" name="_form" value="{{ $for }}">
