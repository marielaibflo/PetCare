{{-- Ayuda bajo el campo de correo cuando ya existe una cuenta con ese correo. --}}
@if(session('correo_pendiente'))
  <div class="note warn" data-campo-ayuda><x-icon name="mail"/><span>¿No te llegó el correo de confirmación?
    <button class="lnk" type="submit" form="form-reenviar-enlace" style="background:none;border:0;padding:0;font-weight:700">Envíame el enlace otra vez</button></span></div>
  @push('dialogs')<form id="form-reenviar-enlace" method="POST" action="{{ route('registro.reenviar') }}" hidden>@csrf<input type="hidden" name="email" value="{{ session('correo_pendiente') }}"></form>@endpush
@elseif(session('correo_registrado'))
  <div class="note info" data-campo-ayuda><x-icon name="info"/><span>¿Es tu cuenta? <a class="lnk" href="{{ route('login', session('correo_registrado')) }}"><b>Inicia sesión</b></a>. Si olvidaste tu contraseña, escríbenos al WhatsApp de PetCare.</span></div>
@endif
