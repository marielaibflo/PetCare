<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\ReglasCuenta;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PropietarioController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q'));
        $lista = User::where('rol', User::PROPIETARIO)->with('mascotas')
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->whereLike('name', "%{$q}%")->orWhereLike('email', "%{$q}%")->orWhereLike('telefono', "%{$q}%")))
            ->latest('id')->paginate(20)->withQueryString();

        return view('admin.propietarios', ['propietarios' => $lista, 'q' => $q]);
    }

    public function store(Request $request)
    {
        $datos = $this->validar($request, null);
        // El administrador registra a la persona por llamada (celular confirmado); el correo lo confirma la persona con el botón.
        $u = User::create($datos + ['rol' => User::PROPIETARIO, 'activo' => true]);
        $u->forceFill(['telefono_verificado_at' => now()])->save();
        app(\App\Services\VerificacionService::class)->enviar($u, 'email');

        return back()->with('ok', "Propietario registrado. Le enviamos a {$u->email} el correo para confirmar su cuenta.");
    }

    public function update(Request $request, User $usuario)
    {
        $this->esPropietario($usuario);
        $usuario->update($this->validar($request, $usuario));

        return back()->with('ok', 'Datos actualizados');
    }

    public function toggle(User $usuario)
    {
        $this->esPropietario($usuario);
        $usuario->update(['activo' => ! $usuario->activo]);

        return back()->with('ok', $usuario->activo ? 'Cuenta activada' : 'Cuenta desactivada');
    }

    public function destroy(User $usuario)
    {
        $this->esPropietario($usuario);
        if ($usuario->mascotas()->withTrashed()->exists()) {
            throw ValidationException::withMessages(['regla' => 'El propietario tiene mascotas registradas. Desactiva la cuenta en lugar de eliminarla.']);
        }
        $usuario->delete();

        return back()->with('ok', 'Propietario eliminado');
    }

    private function esPropietario(User $u): void
    {
        abort_unless($u->rol === User::PROPIETARIO, 404);
    }

    private function validar(Request $request, ?User $u): array
    {
        ReglasCuenta::normalizar($request);
        $reglas = [
            'name' => ['required', 'string', 'min:3', 'max:100'],
            'email' => ReglasCuenta::correo($u),
            'codigo_pais' => ReglasCuenta::codigoPais(),
            'telefono' => ReglasCuenta::celular($request, $u),
        ];
        if (! $u) {
            $reglas['password'] = ReglasCuenta::password(false);
        }

        return $request->validate($reglas);
    }
}
