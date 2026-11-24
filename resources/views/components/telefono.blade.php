{{-- Celular con código de país (para WhatsApp). Uso: <x-telefono :pais="$user->codigo_pais" :numero="$user->telefono"/> --}}
@props(['pais' => null, 'numero' => null, 'id' => 'tel', 'label' => 'Celular (WhatsApp)', 'requerido' => true,
  'campoPais' => 'codigo_pais', 'campoNumero' => 'telefono', 'ayuda' => 'Debe ser un celular con WhatsApp: ahí recibirás el código de verificación y los avisos de tus citas.'])
@php($pais = $pais ?: \App\Support\Paises::PREDETERMINADO)
<div>
  <label for="{{ $id }}">{{ $label }}</label>
  <div class="tel" data-tel>
    <select class="in" name="{{ $campoPais }}" aria-label="Código de país">
      @foreach(\App\Support\Paises::LISTA as $iso => $p)
        <option value="{{ $iso }}" data-ejemplo="{{ $p['ejemplo'] }}" data-movil="{{ trim($p['movil'], '/^$') }}" data-pais="{{ $p['nombre'] }}" @selected($pais === $iso)>{{ $p['bandera'] }} +{{ $p['codigo'] }}</option>
      @endforeach
    </select>
    <input class="in" id="{{ $id }}" name="{{ $campoNumero }}" type="tel" inputmode="numeric" autocomplete="tel-national" maxlength="15"
      placeholder="{{ \App\Support\Paises::LISTA[$pais]['ejemplo'] ?? '' }}" value="{{ $numero }}" @if($requerido) required data-msg="Escribe tu número de celular con WhatsApp." @endif data-label="número de celular">
  </div>
  @if($ayuda)<div class="hint">{{ $ayuda }}</div>@endif
</div>
