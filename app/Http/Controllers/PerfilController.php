<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\VerificacionService;
use App\Support\ReglasCuenta;
use Illuminate\Http\Request;

class PerfilController extends Controller
{
    public function show(Request $request)
    {
        return view('propietario.perfil', ['user' => $request->user()]);
    }

    /** Si cambia el correo o el celular de un propietario, debe volver a verificarlo con un código. */
    public function update(Request $request, VerificacionService $verificacion)
    {
        $user = $request->user();
        $propietario = $user->esRol(User::PROPIETARIO);
        ReglasCuenta::normalizar($request);
        $datos = $request->validateWithBag('perfil', [
            'name' => ['required', 'string', 'min:3', 'max:100'],
            'email' => ReglasCuenta::correo($user),
            'codigo_pais' => ReglasCuenta::codigoPais(),
            // El personal de las veterinarias y el administrador pueden registrar un teléfono fijo.
            'telefono' => $propietario ? ReglasCuenta::celular($request, $user) : ['nullable', 'regex:/^\d{7,10}$/'],
            'direccion' => ['nullable', 'string', 'max:150'],
            'foto' => array_merge(['nullable'], \App\Support\Archivos::IMAGEN),
            'quitar_foto' => ['nullable', 'boolean'],
        ], ['foto.image' => 'La foto debe ser una imagen (JPG, PNG o WEBP).', 'foto.max' => 'La foto no puede pesar más de 10 MB.']);
        $quitarFoto = ! empty($datos['quitar_foto']);
        unset($datos['foto'], $datos['quitar_foto']);
        if ($request->hasFile('foto')) {
            \App\Support\Archivos::borrar($user->foto);
            $datos['foto'] = \App\Support\Archivos::guardar($request->file('foto'), 'usuarios');
        } elseif ($quitarFoto && $user->foto) {
            \App\Support\Archivos::borrar($user->foto);
            $datos['foto'] = null;
        }
        $nuevoCorreo = $datos['email'] !== $user->email && ! $user->esRol(User::ADMINISTRADOR) ? $datos['email'] : null;
        if ($nuevoCorreo) {
            unset($datos['email']);   // el correo anterior sigue valiendo hasta confirmar el nuevo
        }
        $user->fill($datos);
        $cambios = [];
        if ($nuevoCorreo) {
            $user->email_nuevo = $nuevoCorreo;
        }
        if ($propietario && $user->isDirty(['telefono', 'codigo_pais'])) {
            $user->telefono_verificado_at = null;
            $cambios[] = 'whatsapp';
        }
        $user->save();

        if ($nuevoCorreo) {
            VerificacionController::recordarDemo($request, ['email_nuevo' => $verificacion->enviar($user, 'email')]);
        }
        if ($cambios) {
            foreach ($cambios as $canal) {
                VerificacionController::recordarDemo($request, [$canal => $verificacion->enviar($user, $canal)]);
            }

            return redirect()->route('verificacion.show')->with('ok', 'Datos guardados. Confirma el cambio con el código que te enviamos.');
        }

        return back()->with('ok', $nuevoCorreo ? "Te enviamos un correo a {$nuevoCorreo}: pulsa «Confirmar mi correo» para usarlo. Mientras tanto sigues entrando con {$user->email}." : 'Perfil actualizado');
    }

    /** Confirmar el correo nuevo con el código (además del botón del correo). */
    public function confirmarCorreo(Request $request, VerificacionService $verificacion)
    {
        $datos = $request->validateWithBag('perfil', ['codigo_email' => ['required', 'digits:6']],
            ['codigo_email.required' => 'Escribe el código de 6 dígitos que te enviamos al correo nuevo.', 'codigo_email.digits' => 'El código tiene 6 dígitos.']);
        $verificacion->comprobar($request->user(), 'email', $datos['codigo_email'], 'perfil');

        return back()->with('ok', 'Correo actualizado: ahora entras con '.$request->user()->fresh()->email);
    }

    public function cancelarCorreo(Request $request)
    {
        $request->user()->forceFill(['email_nuevo' => null])->save();

        return back()->with('ok', 'Cambio de correo cancelado');
    }

    public function password(Request $request)
    {
        $datos = $request->validateWithBag('clave', [
            'current_password' => ['required', 'current_password'],
            'password' => ReglasCuenta::password(),
        ]);
        $request->user()->update(['password' => $datos['password']]);

        return back()->with('ok', 'Contraseña actualizada');
    }
}
