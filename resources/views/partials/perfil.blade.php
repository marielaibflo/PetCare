<div class="g2" style="align-items:start">
  <div class="card pad"><h2 style="margin-bottom:14px">Datos personales</h2>
    <form class="f" method="POST" action="{{ route('perfil.update') }}" enctype="multipart/form-data" novalidate>
      @csrf @method('PUT')
      <x-errors bag="perfil"/>
      <div class="perfil-foto"><span class="foto-prev perfil-av" id="pf-prev"><x-avatar :u="$user" style="width:96px;height:96px;font-size:32px"/></span>
        <div style="flex:1;min-width:0"><label for="pf">Foto de perfil (opcional)</label><input class="in" id="pf" type="file" name="foto" accept="image/jpeg,image/png,image/webp" data-preview="pf-prev" data-max-mb="10" @if($user->foto) data-quitar-guardada="pf-q" @endif>
          @if($user->foto)<input type="hidden" name="quitar_foto" id="pf-q" value="0">@endif
          <div class="hint">JPG, PNG o WEBP, hasta 10 MB. Se muestra en el menú y la ven tus veterinarias.</div></div></div>
      <div><label for="pn">Nombre completo</label><input class="in" id="pn" name="name" value="{{ old('name', $user->name) }}" required minlength="3" maxlength="100" pattern="[\p{L}\s'.-]+" data-msg="Escribe tu nombre completo." data-msg-pattern="El nombre solo puede tener letras y espacios."></div>
      <div><label for="pe">Correo electrónico</label><input class="in" id="pe" name="email" type="email" value="{{ old('email', $user->email) }}" required maxlength="120" data-msg="Escribe tu correo electrónico."></div>
      @if($user->esRol(\App\Models\User::PROPIETARIO))
        <x-telefono id="pt" :pais="old('codigo_pais', $user->codigo_pais)" :numero="old('telefono', $user->telefono)"/>
        <div class="hint" style="margin-top:-8px">Si cambias tu correo te enviaremos un botón para confirmarlo; si cambias tu celular, un código por WhatsApp.</div>
        <div><label for="pdir">Dirección (aparece en la cartilla de tus mascotas)</label><input class="in" id="pdir" name="direccion" value="{{ old('direccion', $user->direccion) }}" placeholder="Zona, calle y número" maxlength="150"></div>
      @else
        <div><label for="pt">Teléfono</label><input class="in" id="pt" name="telefono" inputmode="numeric" maxlength="10" pattern="\d{7,10}" data-msg-pattern="El teléfono debe tener de 7 a 10 dígitos." value="{{ old('telefono', $user->telefono) }}"><input type="hidden" name="codigo_pais" value="{{ $user->codigo_pais ?: 'BO' }}"></div>
      @endif
      <button class="btn" type="submit">Guardar cambios</button>
    </form>
    @if($user->email_nuevo)
      <div class="note warn" style="margin-top:14px"><x-icon name="mail"/><span>Cambio de correo pendiente: <b>{{ $user->email_nuevo }}</b>. Pulsa «Confirmar mi correo» en el mensaje que te enviamos o escribe aquí el código. Mientras tanto sigues entrando con <b>{{ $user->email }}</b>.
        @if(session('codigos_demo.email_nuevo'))<br><b>Modo demostración:</b> código {{ session('codigos_demo.email_nuevo') }}.@endif</span></div>
      <form class="f" method="POST" action="{{ route('perfil.correo') }}" style="margin-top:10px" novalidate>
        @csrf
        <div class="fr"><div><label for="pcc">Código del correo nuevo</label><input class="in codigo" id="pcc" name="codigo_email" inputmode="numeric" maxlength="6" placeholder="000000" required pattern="\d{6}" data-msg="Escribe el código de 6 dígitos que te enviamos al correo nuevo." data-msg-pattern="El código tiene 6 números."></div>
          <div><label>&nbsp;</label><button class="btn sec full" type="submit">Confirmar correo</button></div></div>
      </form>
      <form method="POST" action="{{ route('perfil.correo.cancelar') }}">@csrf @method('DELETE')<button class="lnk" style="background:none;border:0;padding:0" type="submit">Cancelar el cambio de correo</button></form>
    @endif
  </div>
  <div class="grid">
    <div class="card pad"><h2 style="margin-bottom:14px">Cambiar contraseña</h2>
      <form class="f" method="POST" action="{{ route('perfil.password') }}" novalidate>
        @csrf @method('PUT')
        <x-errors bag="clave"/>
        <div><label for="c0">Contraseña actual</label><input class="in" id="c0" name="current_password" type="password" autocomplete="current-password" required data-msg="Escribe tu contraseña actual."></div>
        <div class="fr"><div><label for="c1">Nueva contraseña</label><input class="in" id="c1" name="password" type="password" autocomplete="new-password" data-fuerza required minlength="8" data-msg="Escribe la nueva contraseña." data-segura><div class="fuerza" aria-hidden="true"><span></span></div><div class="hint">8+ caracteres con mayúscula, minúscula, número y símbolo.</div></div>
          <div><label for="c2">Repetir nueva</label><input class="in" id="c2" name="password_confirmation" type="password" autocomplete="new-password" required data-igual="password" data-msg="Repite la nueva contraseña."></div></div>
        <button class="btn sec" type="submit">Actualizar contraseña</button>
      </form>
    </div>
    <form method="POST" action="{{ route('logout') }}">@csrf<button class="btn danger full"><x-icon name="out"/>Cerrar sesión</button></form>
  </div>
</div>
