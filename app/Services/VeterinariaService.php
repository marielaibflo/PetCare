<?php

namespace App\Services;

use App\Models\User;
use App\Models\Veterinaria;
use App\Support\Archivos;
use App\Support\Catalogo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Veterinarias afiliadas. Dos formas de entrar a PetCare:
 *  - el administrador la afilia directamente (queda activa);
 *  - la clínica se registra en línea, sube su documento y espera la aprobación (primer mes gratis).
 */
class VeterinariaService
{
    use ReglaNegocio;

    public function __construct(private WhatsAppService $whatsapp) {}

    /** Afiliación hecha por el administrador: se crea también la cuenta de acceso del personal. */
    public function afiliar(array $datos): Veterinaria
    {
        return DB::transaction(function () use ($datos) {
            $vet = Veterinaria::create($this->datosClinica($datos) + [
                'activa' => true, 'estado' => 'aprobada', 'plan_suscripcion' => $datos['plan_suscripcion'] ?? 'profesional',
                'suscripcion_hasta' => Carbon::today()->addDays(Catalogo::DIAS_PRUEBA_VETERINARIA), 'color' => Veterinaria::count() % 5, 'aprobada_at' => Carbon::today(),
            ]);
            User::create([
                'name' => $datos['responsable'], 'email' => $datos['email'], 'password' => $datos['password'],
                'rol' => User::VETERINARIA, 'telefono' => $datos['telefono'], 'veterinaria_id' => $vet->id, 'activo' => true,
            ])->forceFill(['telefono_verificado_at' => now()])->save();   // el correo lo confirma la clínica con el enlace

            return $vet;
        });
    }

    /** Registro en línea de una clínica: queda pendiente de revisión del administrador. */
    public function solicitar(array $datos, UploadedFile $documento): Veterinaria
    {
        $vet = DB::transaction(function () use ($datos, $documento) {
            $vet = Veterinaria::create($this->datosClinica($datos) + [
                'activa' => false, 'estado' => 'pendiente', 'plan_suscripcion' => $datos['plan_suscripcion'], 'nit' => $datos['nit'], 'registro' => $datos['registro'] ?? null,
                'documento' => Archivos::guardar($documento, 'documentos'), 'terminos_at' => now(), 'color' => Veterinaria::count() % 5,
            ] + \App\Support\ReglasPago::datosCobro($datos));
            User::create([
                'name' => $datos['responsable'], 'email' => $datos['email'], 'password' => $datos['password'], 'rol' => User::VETERINARIA,
                'telefono' => $datos['whatsapp'] ?? $datos['telefono'], 'codigo_pais' => $datos['whatsapp_pais'] ?? 'BO',
                'veterinaria_id' => $vet->id, 'matricula' => $datos['matricula'] ?? null, 'activo' => false,
            ]);

            return $vet;
        });
        $this->whatsapp->enviarTexto($vet->whatsappInternacional(),
            "PetCare 🐾 Recibimos la solicitud de {$vet->nombre}. Confirma tu correo para que la revisemos.");

        return $vet;
    }

    /**
     * La clínica confirmó su correo: su solicitud llega al administrador (campana, correo y WhatsApp de PetCare).
     * Se avisa una sola vez.
     */
    public function avisarAdministrador(Veterinaria $vet): void
    {
        if ($vet->admin_avisado_at) {
            return;
        }
        $vet->update(['admin_avisado_at' => now()]);
        $u = $vet->personal()->first();
        $texto = "Nueva solicitud de afiliación: {$vet->nombre} (NIT {$vet->nit}) · {$vet->zona} · Suscripción {$vet->suscripcion()['nombre']}. "
            ."Responsable: {$u?->name} ({$u?->email}, correo confirmado). Revísala en PetCare → Veterinarias.";
        $this->whatsapp->enviarTexto(config('petcare.whatsapp_petcare'), "PetCare 🏥 Nueva solicitud de afiliación: {$vet->nombre} · {$vet->zona} · {$vet->suscripcion()['nombre']}. Revísala en Veterinarias.");
        foreach (User::where('rol', User::ADMINISTRADOR)->where('activo', true)->get() as $admin) {
            try {
                Mail::raw("Hola {$admin->primerNombre()},\n\n{$texto}\n\n".route('admin.veterinarias.index')."\n\nEquipo PetCare",
                    fn ($m) => $m->to($admin->email, $admin->name)->subject("PetCare · Nueva solicitud de afiliación: {$vet->nombre}"));
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning("[Correo] No se pudo avisar al administrador: {$e->getMessage()}");
            }
        }
    }

    /** La clínica cancela su suscripción: sigue activa hasta el fin del período pagado y ya no se cobra ni se avisa. */
    public function cancelarSuscripcion(Veterinaria $vet, ?string $motivo): void
    {
        $this->exigir(! $vet->suscripcion_cancelada_at, 'Tu suscripción ya está cancelada.');
        $this->exigir(! \App\Models\Pago::pendientes()->where('concepto', 'suscripcion')->where('veterinaria_id', $vet->id)->exists(),
            'Tienes un pago de suscripción pendiente de verificación: espera a que PetCare lo revise.');
        $vet->update(['suscripcion_cancelada_at' => now(), 'motivo_cancelacion' => $motivo ? mb_substr($motivo, 0, 200) : null]);
        $hasta = $vet->suscripcion_hasta?->format('d/m/Y');
        $this->whatsapp->enviarTexto(config('petcare.whatsapp_petcare'), "PetCare 🔕 {$vet->nombre} canceló su suscripción".($hasta ? " (activa hasta el {$hasta})" : '').'.'.($motivo ? " Motivo: {$motivo}" : ''));
    }

    public function reactivarSuscripcion(Veterinaria $vet): void
    {
        $this->exigir((bool) $vet->suscripcion_cancelada_at && ! $vet->baja_at, 'Tu suscripción no está cancelada.');
        $vet->update(['suscripcion_cancelada_at' => null, 'motivo_cancelacion' => null]);
    }

    /**
     * La clínica elimina su cuenta (solo después de cancelar la suscripción).
     * Se cancelan sus citas futuras (avisando a los dueños), los dueños con plan en esta clínica pueden elegir otra sin penalidad,
     * se cierra el acceso y se libera el correo. El historial clínico y los pagos se conservan.
     */
    public function eliminarCuenta(Veterinaria $vet, User $quien): void
    {
        $this->exigir((bool) $vet->suscripcion_cancelada_at, 'Primero cancela tu suscripción.');
        $this->exigir(! \App\Models\Pago::pendientes()->where('veterinaria_id', $vet->id)->whereIn('concepto', ['cita', 'pedido'])->exists(),
            'Tienes pagos de clientes pendientes de verificar. Revísalos antes de eliminar tu cuenta.');
        $this->exigir(! \App\Models\Pedido::where('veterinaria_id', $vet->id)->whereIn('estado', \App\Models\Pedido::ABIERTOS)->exists(),
            'Tienes pedidos de la tienda sin entregar. Entrégalos o cancélalos antes de eliminar tu cuenta.');
        DB::transaction(function () use ($vet, $quien) {
            foreach (\App\Models\Cita::where('veterinaria_id', $vet->id)->whereIn('estado', ['pendiente', 'confirmada'])->get() as $c) {
                $c->update(['estado' => 'cancelada']);
                $d = $c->mascota?->propietario;
                $this->whatsapp->enviarTexto($d?->telefonoInternacional(), "PetCare 🚫 {$vet->nombre} dejó PetCare y canceló la cita de {$c->mascota->nombre} ({$c->fecha->format('d/m')} {$c->hora}). Elige otra clínica en PetCare.", $d);
            }
            foreach (\App\Models\Afiliacion::vigentes()->with('mascota.propietario')->where('veterinaria_id', $vet->id)->get() as $a) {
                $d = $a->mascota?->propietario;
                $this->whatsapp->enviarTexto($d?->telefonoInternacional(), "PetCare ℹ️ {$vet->nombre} dejó PetCare. El plan de {$a->mascota->nombre} sigue vigente: elige otra veterinaria sin penalidad desde PetCare → Planes.", $d);
            }
            \App\Models\Producto::where('veterinaria_id', $vet->id)->update(['activo' => false, 'en_tienda' => false]);
            $vet->update(['activa' => false, 'estado' => 'baja', 'baja_at' => now(), 'cobro_marca' => null, 'cobro_ultimos' => null, 'cobro_titular' => null, 'cobro_vence' => null]);
            foreach ($vet->personal()->get() as $u) {
                $u->forceFill(['activo' => false, 'email' => 'baja-'.$u->id.'-'.now()->timestamp.'@petcare.invalid', 'email_nuevo' => null,
                    'password' => \Illuminate\Support\Str::random(40), 'remember_token' => null])->save();
            }
        });
        $this->whatsapp->enviarTexto(config('petcare.whatsapp_petcare'), "PetCare 🗑️ {$vet->nombre} eliminó su cuenta. Su historial clínico se conserva.");
    }

    public function aprobarSolicitud(Veterinaria $vet): void
    {
        $this->exigir($vet->estado === 'pendiente', 'Esta solicitud ya fue revisada.');
        $this->exigir((bool) $vet->personal()->whereNotNull('email_verified_at')->exists(), 'La clínica todavía no confirmó su correo: no se puede aprobar.');
        DB::transaction(function () use ($vet) {
            $vet->update(['estado' => 'aprobada', 'activa' => true, 'motivo_rechazo' => null,
                'suscripcion_hasta' => Carbon::today()->addDays(Catalogo::DIAS_PRUEBA_VETERINARIA), 'aprobada_at' => Carbon::today()]);
            $vet->personal()->get()->each(fn (User $u) => $u->forceFill(['activo' => true, 'telefono_verificado_at' => now()])->save());
        });
        $hasta = $vet->suscripcion_hasta->format('d/m/Y');
        $msg = "¡Bienvenida a PetCare! {$vet->nombre} fue aprobada. Tu primer mes es gratis (hasta el {$hasta}). Ingresa con tu correo, "
            .'registra tu tarifario y tu catálogo de medicamentos para empezar a recibir citas.';
        $this->whatsapp->enviarTexto($vet->whatsappInternacional(), "PetCare ✅ {$vet->nombre} aprobada. Mes gratis hasta el {$hasta}. Ya puedes ingresar.");
        $this->correo($vet, 'PetCare · Tu clínica fue aprobada', $msg);
    }

    public function rechazarSolicitud(Veterinaria $vet, string $motivo): void
    {
        $this->exigir($vet->estado === 'pendiente', 'Esta solicitud ya fue revisada.');
        $vet->update(['estado' => 'rechazada', 'motivo_rechazo' => $motivo]);
        $msg = "No pudimos aprobar la afiliación de {$vet->nombre}. Motivo: {$motivo}. Puedes escribirnos para completar la información.";
        $this->whatsapp->enviarTexto($vet->whatsappInternacional(), "PetCare ❌ Afiliación de {$vet->nombre} no aprobada. Motivo: {$motivo}.");
        $this->correo($vet, 'PetCare · Solicitud de afiliación', $msg);
    }

    public function actualizar(Veterinaria $vet, array $datos): Veterinaria
    {
        $vet->update(collect($datos)->only(['nombre', 'zona', 'direccion', 'telefono', 'whatsapp_pais', 'whatsapp', 'horario', 'servicios', 'plan_suscripcion',
            'registro', 'logo', 'qr_cobro', 'descripcion', 'servicios_especiales', 'metodos_pago', 'promocion', 'mapa_url'])
            ->filter(fn ($v, $k) => ! in_array($k, ['plan_suscripcion', 'logo', 'qr_cobro'], true) || $v)->all());

        return $vet;
    }

    /** Desactivar una veterinaria bloquea también las cuentas de su personal. */
    public function alternar(Veterinaria $vet): Veterinaria
    {
        $this->exigir(! $vet->baja_at, 'Esta clínica eliminó su cuenta: no se puede reactivar.');
        DB::transaction(function () use ($vet) {
            $vet->update(['activa' => ! $vet->activa]);
            $vet->personal()->update(['activo' => $vet->activa]);
        });

        return $vet;
    }

    private function datosClinica(array $d): array
    {
        return ['nombre' => $d['nombre'], 'zona' => $d['zona'], 'direccion' => $d['direccion'] ?? null, 'telefono' => $d['telefono'],
            'horario' => $d['horario'] ?? null, 'servicios' => $d['servicios'],
            'whatsapp_pais' => $d['whatsapp_pais'] ?? 'BO', 'whatsapp' => $d['whatsapp'] ?? null];
    }

    private function correo(Veterinaria $vet, string $asunto, string $texto): void
    {
        $u = $vet->personal()->first();
        if (! $u) {
            return;
        }
        try {
            Mail::raw("Hola {$u->primerNombre()},\n\n{$texto}\n\nEquipo PetCare 🐾", fn ($m) => $m->to($u->email, $u->name)->subject($asunto));
        } catch (Throwable $e) {
            Log::warning("[Correo] No se pudo enviar a {$u->email}: {$e->getMessage()}");
        }
    }
}
