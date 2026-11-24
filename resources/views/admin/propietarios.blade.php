@extends('layouts.app')
@section('title', 'Propietarios')
@section('heading', 'Propietarios')
@section('sub', $propietarios->total().' registrados')
@section('acts')<x-exportar/><button class="btn" data-open="dlg-prop-nuevo"><x-icon name="plus"/>Nuevo propietario</button>@endsection
@section('content')
@include('admin.buscar', ['ph' => 'Buscar por nombre, correo o teléfono...'])
<div class="card tw"><table><thead><tr><th>Nombre</th><th>Correo</th><th>Teléfono</th><th>Mascotas</th><th>Registrado</th><th>Estado</th><th></th></tr></thead><tbody>
@forelse($propietarios as $u)
  <tr><td>{{ $u->name }}</td><td data-l="Correo"><x-correo :email="$u->email"/></td><td data-l="Celular"><x-wa :internacional="$u->telefonoInternacional()" :texto="$u->telefonoVisible()"/></td><td data-l="Mascotas">{{ $u->mascotas->pluck('nombre')->join(', ') ?: '—' }}</td><td data-l="Registrado">{{ $u->created_at->format('d/m/Y') }}<small class="blk">{{ $u->created_at->diffForHumans() }}</small></td>
    <td data-l="Estado">@if($u->activo)<span class="chip ok">Activo</span>@else<span class="chip mut">Inactivo</span>@endif</td>
    <td class="ac"><div class="acts">
      <button class="icb" title="Editar" aria-label="Editar" data-open="dlg-prop-{{ $u->id }}"><x-icon name="edit"/></button>
      <form method="POST" action="{{ route('admin.propietarios.toggle', $u) }}">@csrf @method('PATCH')<button class="icb {{ $u->activo ? '' : 'ok' }}" title="{{ $u->activo ? 'Desactivar' : 'Activar' }}" aria-label="{{ $u->activo ? 'Desactivar' : 'Activar' }}"><x-icon :name="$u->activo ? 'lock' : 'check'"/></button></form>
      <form method="POST" action="{{ route('admin.propietarios.destroy', $u) }}" data-confirm="Se eliminará la cuenta de {{ $u->name }}." data-confirm-title="Eliminar propietario" data-confirm-btn="Eliminar">@csrf @method('DELETE')<button class="icb bad" title="Eliminar" aria-label="Eliminar"><x-icon name="trash"/></button></form>
    </div></td></tr>
@empty
  <tr><td colspan="7"><x-empty icon="users" title="Sin resultados" text="Prueba con otra búsqueda."/></td></tr>
@endforelse
</tbody></table><x-pagination :p="$propietarios"/></div>
@push('dialogs')
@foreach(collect([null])->concat($propietarios->items()) as $u)
  @php($id = $u ? 'dlg-prop-'.$u->id : 'dlg-prop-nuevo')
  @php($v = fn ($k) => old('_form') === $id ? old($k) : $u?->{$k})
  <x-dialog :id="$id" :title="$u ? 'Editar propietario' : 'Nuevo propietario'" icon="user">
    <form class="f" method="POST" action="{{ $u ? route('admin.propietarios.update', $u) : route('admin.propietarios.store') }}" novalidate>
      @csrf @if($u) @method('PUT') @endif
      <x-form-errors :for="$id"/>
      <div><label>Nombre completo</label><input class="in" name="name" value="{{ $v('name') }}" required minlength="3" maxlength="100" pattern="[\p{L}\s'.-]+" data-msg="Escribe el nombre completo." data-msg-pattern="El nombre solo puede tener letras y espacios."></div>
      <div><label>Correo</label><input class="in" type="email" name="email" value="{{ $v('email') }}" required maxlength="120" data-msg="Escribe el correo electrónico."></div>
      <x-telefono :id="$id.'-tel'" :pais="$v('codigo_pais')" :numero="$v('telefono')"/>
      @unless($u)<div><label>Contraseña inicial</label><input class="in" type="password" name="password" autocomplete="new-password" data-fuerza required data-segura data-msg="Escribe una contraseña inicial."><div class="fuerza" aria-hidden="true"><span></span></div><div class="hint">8+ caracteres con mayúscula, minúscula, número y símbolo. El propietario podrá cambiarla desde su perfil.</div></div>@endunless
      <div class="md-f"><button type="button" class="btn ghost" data-close>Cancelar</button><button class="btn" type="submit">Guardar</button></div>
    </form>
  </x-dialog>
@endforeach
@endpush
@endsection
