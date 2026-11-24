<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Afiliacion;
use App\Models\Ajuste;
use App\Models\Atencion;
use App\Models\Cita;
use App\Models\Mascota;
use App\Models\MensajeWhatsapp;
use App\Models\Pago;
use App\Models\Plan;
use App\Models\Producto;
use App\Models\User;
use App\Models\Veterinaria;
use App\Support\Archivos;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ConfiguracionController extends Controller
{
    public function index(Request $request)
    {
        return view('admin.configuracion', [
            'user' => $request->user(), 'usuarios' => User::count(),
            'banco' => ['banco' => Ajuste::valor('pago_banco'), 'titular' => Ajuste::valor('pago_titular'), 'cuenta' => Ajuste::valor('pago_cuenta')],
            'mensajes' => MensajeWhatsapp::with('user')->latest('id')->limit(30)->get(),
            'driver' => config('petcare.whatsapp.driver'),
            'redes' => ['tiktok' => Ajuste::valor('red_tiktok'), 'instagram' => Ajuste::valor('red_instagram')],
        ]);
    }

    /** Datos de cobro: banco, titular, cuenta y la imagen del QR (los QR de los bancos suelen tener fecha de vencimiento). */
    public function pago(Request $request)
    {
        $datos = $request->validateWithBag('pago', [
            'pago_banco' => ['required', 'string', 'max:60'],
            'pago_titular' => ['required', 'string', 'max:100'],
            'pago_cuenta' => ['required', 'regex:/^[\d-]{6,25}$/'],
            'pago_qr' => array_merge(['nullable'], Archivos::IMAGEN),
        ], ['pago_cuenta.regex' => 'El número de cuenta solo puede tener dígitos y guiones.']);
        foreach (['pago_banco', 'pago_titular', 'pago_cuenta'] as $k) {
            Ajuste::guardar($k, $datos[$k]);
        }
        if ($request->hasFile('pago_qr')) {
            Archivos::borrar(Ajuste::valor('pago_qr'));
            Ajuste::guardar('pago_qr', Archivos::guardar($request->file('pago_qr'), 'qr'));
        }

        return back()->with('ok', 'Datos de cobro actualizados');
    }

    /**
     * Redes sociales que se muestran en la pantalla de inicio. Acepta el enlace completo o solo el usuario (@petcare).
     * Si el campo queda vacío, el botón de esa red no se muestra.
     */
    public function redes(Request $request)
    {
        $dominios = ['red_tiktok' => 'tiktok.com', 'red_instagram' => 'instagram.com'];
        $nombres = ['red_tiktok' => 'TikTok', 'red_instagram' => 'Instagram'];
        $errores = [];
        foreach ($dominios as $campo => $dominio) {
            $v = trim((string) $request->input($campo));
            if ($v === '') {
                Ajuste::guardar($campo, '');   // vacío = ocultar el botón
                continue;
            }
            if (preg_match('/^@?([A-Za-z0-9._]{2,30})$/', $v, $m)) {
                $v = 'https://www.'.$dominio.'/'.($campo === 'red_tiktok' ? '@' : '').$m[1];
            } elseif (! preg_match('#^https?://#i', $v)) {
                $v = 'https://'.$v;
            }
            $host = strtolower((string) parse_url($v, PHP_URL_HOST));
            if (strlen($v) > 200 || ! filter_var($v, FILTER_VALIDATE_URL) || ! ($host === $dominio || str_ends_with($host, '.'.$dominio))) {
                $errores[$campo] = "Escribe el enlace de la cuenta de {$nombres[$campo]} (por ejemplo, https://www.{$dominio}/".($campo === 'red_tiktok' ? '@' : '')."petcare) o solo el usuario.";
                continue;
            }
            Ajuste::guardar($campo, $v);
        }
        if ($errores) {
            return back()->withErrors($errores, 'redes')->withInput();
        }

        return back()->with('ok', 'Redes sociales actualizadas');
    }

    /** Respaldo en JSON de toda la información (sin contraseñas). */
    public function respaldo()
    {
        $datos = [
            'generado' => now()->toIso8601String(),
            'usuarios' => User::all()->makeHidden(['password', 'remember_token']),
            'veterinarias' => Veterinaria::all(), 'productos' => Producto::all(), 'planes' => Plan::all(),
            'mascotas' => Mascota::withTrashed()->get(), 'afiliaciones' => Afiliacion::all(),
            'citas' => Cita::all(), 'atenciones' => Atencion::with('productos')->get(), 'pagos' => Pago::all(),
        ];

        return response()->streamDownload(fn () => print(json_encode($datos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)),
            'petcare_respaldo_'.Carbon::today()->format('Y-m-d').'.json', ['Content-Type' => 'application/json']);
    }
}
