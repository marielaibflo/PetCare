<?php

namespace App\Http\Controllers\Veterinaria;

use App\Http\Controllers\Controller;
use App\Models\Veterinaria;
use App\Models\VeterinariaFoto;
use App\Services\VeterinariaService;
use App\Support\Archivos;
use App\Support\Catalogo;
use App\Support\ReglasCuenta;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ClinicaController extends Controller
{
    public function edit(Request $request)
    {
        return view('veterinaria.clinica', ['vet' => $request->user()->veterinaria, 'user' => $request->user()]);
    }

    public function update(Request $request, VeterinariaService $servicio)
    {
        \App\Support\Catalogo::prepararServicios($request, 'clinica');
        \App\Support\Horario::componer($request, 'clinica');
        $datos = $request->validateWithBag('clinica', ReglasCuenta::whatsappClinica($request) + [
            'nombre' => ['required', 'string', 'min:3', 'max:100'],
            'zona' => ['required', 'string', 'max:60'],
            'direccion' => ['nullable', 'string', 'max:150'],
            'telefono' => ['required', 'regex:/^\d{7,10}$/'],
            'horario' => ['nullable', 'string', 'max:80'],
            'servicios' => ['required', 'array', 'min:1', 'max:20'],
            'servicios.*' => ['string', 'max:40'],
            'registro' => ['nullable', 'string', 'max:30', 'regex:/^[A-Za-z0-9\/\-. ]+$/'],
            'matricula' => ['nullable', 'string', 'max:30', 'regex:/^[A-Za-z0-9\/\-. ]+$/'],
            'descripcion' => ['nullable', 'string', 'max:600'],
            'servicios_especiales' => ['nullable', 'array'],
            'servicios_especiales.*' => [Rule::in(Veterinaria::SERVICIOS_ESPECIALES)],
            'metodos_pago' => ['nullable', 'array'],
            'metodos_pago.*' => [Rule::in(Veterinaria::METODOS_PAGO)],
            'promocion' => ['nullable', 'string', 'max:200'],
            'mapa_url' => ['nullable', 'url', 'max:500', 'regex:/^https:\/\/(www\.)?(google\.[a-z.]+\/maps|maps\.app\.goo\.gl|goo\.gl\/maps|maps\.google\.[a-z.]+)/i'],
            'logo' => array_merge(['nullable'], Archivos::IMAGEN),
            'qr_cobro' => array_merge(['nullable'], Archivos::IMAGEN),
        ], ['registro.regex' => 'El registro solo puede tener letras, números, guiones y barras.', 'matricula.regex' => 'La matrícula solo puede tener letras, números, guiones y barras.',
            'mapa_url.regex' => 'Pega un enlace de Google Maps (por ejemplo https://maps.app.goo.gl/...).']);
        $vet = $request->user()->veterinaria;
        $request->user()->update(['matricula' => $datos['matricula'] ?? null]);
        if ($request->hasFile('qr_cobro')) {
            Archivos::borrar($vet->qr_cobro);
            $datos['qr_cobro'] = Archivos::guardar($request->file('qr_cobro'), 'qr');
        }
        if ($request->hasFile('logo')) {
            Archivos::borrar($vet->logo);
            $datos['logo'] = Archivos::guardar($request->file('logo'), 'logos');
        }
        $datos['servicios_especiales'] = $datos['servicios_especiales'] ?? [];
        $datos['metodos_pago'] = $datos['metodos_pago'] ?? [];
        $servicio->actualizar($vet, $datos);

        return back()->with('ok', 'Datos de la clínica actualizados');
    }

    /** Galería de fotos de las instalaciones (aparece en la página de la veterinaria). */
    public function agregarFoto(Request $request)
    {
        $vet = $request->user()->veterinaria;
        $request->validateWithBag('fotos', [
            'fotos' => ['required', 'array', 'min:1', 'max:6'],
            'fotos.*' => Archivos::IMAGEN,
            'titulo' => ['nullable', 'string', 'max:80'],
        ], ['fotos.required' => 'Elige al menos una foto.']);
        $this->exigirLimiteFotos($vet, count($request->file('fotos')));
        foreach ($request->file('fotos') as $f) {
            $vet->fotos()->create(['ruta' => Archivos::guardar($f, 'veterinarias'), 'titulo' => $request->input('titulo') ?: null]);
        }

        return back()->with('ok', 'Fotos agregadas a tu página');
    }

    public function quitarFoto(Request $request, VeterinariaFoto $foto)
    {
        abort_unless($foto->veterinaria_id === $request->user()->veterinaria_id, 404);
        Archivos::borrar($foto->ruta);
        $foto->delete();

        return back()->with('ok', 'Foto eliminada');
    }

    private function exigirLimiteFotos(Veterinaria $vet, int $nuevas): void
    {
        if ($vet->fotos()->count() + $nuevas > 12) {
            throw \Illuminate\Validation\ValidationException::withMessages(['fotos' => 'Puedes tener hasta 12 fotos en tu galería.'])->errorBag('fotos');
        }
    }

    /** Tarifario: precio de cada servicio que ofrece la clínica (cada clínica maneja sus propios precios). */
    public function tarifario(Request $request)
    {
        $vet = $request->user()->veterinaria;
        foreach ((array) $request->input('precios', []) as $s => $p) {
            $request->merge(['precios' => array_merge((array) $request->input('precios'), [$s => $p === null ? null : str_replace(',', '.', (string) $p)])]);
        }
        $datos = $request->validateWithBag('tarifario', [
            'precios' => ['required', 'array'],
            'precios.*' => ['nullable', 'numeric', 'min:0', 'max:100000'],
        ], ['precios.*.numeric' => 'Los precios deben ser números.']);
        $precios = collect($datos['precios'])->only($vet->servicios)->map(fn ($p) => $p === null || $p === '' ? null : round((float) $p, 2))->all();
        $vet->update(['precios' => $precios]);

        return back()->with('ok', 'Tarifario actualizado');
    }
}
