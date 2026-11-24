@php($id = $m ? 'dlg-mascota-'.$m->id : 'dlg-mascota-nueva')
@php($v = fn ($k, $d = null) => old('_form') === $id ? old($k, $d) : ($m?->{$k} ?? $d))
<x-dialog :id="$id" :title="$m ? 'Editar mascota' : 'Agregar mascota'" icon="pets">
  <form class="f" method="POST" action="{{ $m ? route('propietario.mascotas.update', $m) : route('propietario.mascotas.store') }}" enctype="multipart/form-data" novalidate>
    @csrf @if($m) @method('PUT') @endif
    <x-form-errors :for="$id"/>
    <div class="foto-in"><span class="foto-prev" id="{{ $id }}-fp">@if($m)<x-pet :m="$m" :s="64"/>@else<x-icon name="pets"/>@endif</span>
      <div><label for="{{ $id }}-foto">Foto (opcional)</label><input class="in" id="{{ $id }}-foto" type="file" name="foto" accept="image/jpeg,image/png,image/webp" data-preview="{{ $id }}-fp" data-max-mb="10" @if($m?->foto) data-quitar-guardada="{{ $id }}-qf" @endif>
        @if($m?->foto)<input type="hidden" name="quitar_foto" id="{{ $id }}-qf" value="{{ old('_form') === $id ? old('quitar_foto', 0) : 0 }}">@endif
        <div class="hint">Ayuda a reconocer a tu mascota en la clínica. JPG, PNG o WEBP, hasta 10 MB.</div></div></div>
    <div class="fr"><div><label for="{{ $id }}-n">Nombre</label><input class="in" id="{{ $id }}-n" name="nombre" value="{{ $v('nombre') }}" required minlength="2" maxlength="60" pattern="[\p{L}\s'.-]+" data-msg="Escribe el nombre de tu mascota." data-msg-pattern="El nombre solo puede tener letras y espacios."></div>
      @php($espActual = $v('especie', 'Perro'))
      @php($esOtra = old('_form') === $id ? old('especie') === 'Otro' : ($m && ! in_array($m->especie, ['Perro', 'Gato'], true)))
      @php($otraTxt = old('_form') === $id ? old('especie_otro') : ($esOtra ? $m?->especie : ''))
      <div><label for="{{ $id }}-e">Especie</label><select class="in" id="{{ $id }}-e" name="especie" required data-otro="{{ $id }}-eo-box">@foreach(\App\Support\Catalogo::ESPECIES as $e)<option @selected($esOtra ? $e === 'Otro' : $espActual === $e)>{{ $e }}</option>@endforeach</select>
        <div id="{{ $id }}-eo-box" class="otro-campo" @unless($esOtra) hidden @endunless><label class="req" for="{{ $id }}-eo">¿Qué especie?</label><input class="in" id="{{ $id }}-eo" name="especie_otro" value="{{ $otraTxt }}" required minlength="3" maxlength="30" pattern="[\p{L}\s]+" placeholder="Ej.: Hámster, Conejo, Tortuga" data-msg="Escribe qué especie es tu mascota (ej.: Hámster)." data-msg-pattern="La especie solo puede tener letras." data-msg-min="La especie debe tener al menos 3 letras."></div></div></div>
    <div class="fr"><div><label for="{{ $id }}-r">Raza</label><input class="in" id="{{ $id }}-r" name="raza" value="{{ $v('raza') }}" placeholder="Mestizo" maxlength="60"></div>
      <div><label for="{{ $id }}-f">Fecha de nacimiento</label><input class="in" id="{{ $id }}-f" type="date" name="fecha_nacimiento" max="{{ today()->format('Y-m-d') }}" min="{{ today()->subYears(35)->format('Y-m-d') }}" value="{{ old('_form') === $id ? old('fecha_nacimiento') : $m?->fecha_nacimiento?->format('Y-m-d') }}" required data-msg="Indica la fecha de nacimiento (aunque sea aproximada)." data-msg-max="La fecha de nacimiento no puede ser posterior a hoy."><div class="hint">Si no la conoces, usa una fecha aproximada.</div></div></div>
    <div class="fr"><div><label for="{{ $id }}-p">Peso (kg)</label><input class="in" id="{{ $id }}-p" name="peso" inputmode="decimal" value="{{ $v('peso') }}" required data-numero min="0.1" max="150" data-msg="Escribe el peso en kilogramos, por ejemplo 12.5." data-msg-min="El peso debe ser mayor a 0 kg." data-msg-max="El peso máximo permitido es 150 kg."></div>
      <div><label for="{{ $id }}-s">Sexo</label><select class="in" id="{{ $id }}-s" name="sexo" required>@foreach(\App\Support\Catalogo::SEXOS as $s)<option @selected($v('sexo') === $s)>{{ $s }}</option>@endforeach</select></div></div>
    @php($est = old('_form') === $id ? old('esterilizado') : ($m?->esterilizado ? 'si' : 'no'))
    <div class="fr"><div><label for="{{ $id }}-t">¿Está esterilizado/a?</label><select class="in" id="{{ $id }}-t" name="esterilizado" required><option value="si" @selected($est === 'si')>Sí</option><option value="no" @selected($est !== 'si')>No</option></select></div>
      <div><label for="{{ $id }}-c">Color (opcional)</label><input class="in" id="{{ $id }}-c" name="color" value="{{ $v('color') }}" placeholder="Ej.: Dorado, atigrado" maxlength="40"></div></div>
    <div><label for="{{ $id }}-a">Alergias (opcional)</label><input class="in" id="{{ $id }}-a" name="alergias" value="{{ $v('alergias') }}" placeholder="Ninguna conocida" maxlength="150"></div>
    <div class="md-f"><button type="button" class="btn ghost" data-close>Cancelar</button><button class="btn" type="submit">{{ $m ? 'Guardar cambios' : 'Registrar mascota' }}</button></div>
  </form>
</x-dialog>
