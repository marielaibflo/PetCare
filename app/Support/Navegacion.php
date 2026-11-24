<?php

namespace App\Support;

use App\Models\User;

/** Menú de cada rol. Mismo diseño para los tres: barra lateral en computadora y barra inferior en celular. */
final class Navegacion
{
    public static function para(User $u): array
    {
        return match ($u->rol) {
            User::PROPIETARIO => [
                ['propietario.inicio', 'home', 'Inicio', ['propietario.inicio']],
                ['propietario.mascotas.index', 'pets', 'Mascotas', ['propietario.mascotas.*']],
                ['propietario.citas.index', 'cal', 'Citas', ['propietario.citas.*']],
                ['propietario.planes.index', 'shield', 'Planes', ['propietario.planes.*']],
                ['propietario.tienda.index', 'cart', 'Tienda', ['propietario.tienda.*'], app(\App\Services\TiendaService::class)->cantidadEnCarrito()],
                ['propietario.veterinarias.index', 'pin', 'Veterinarias', ['propietario.veterinarias.*']],
                ['propietario.pagos.index', 'doc', 'Mis pagos', ['propietario.pagos.*']],
                ['propietario.perfil', 'user', 'Perfil', ['propietario.perfil']],
            ],
            User::VETERINARIA => [
                ['veterinaria.inicio', 'home', 'Inicio', ['veterinaria.inicio']],
                ['veterinaria.agenda', 'cal', 'Agenda', ['veterinaria.agenda'], \App\Models\Pago::where('concepto', 'cita')->where('veterinaria_id', $u->veterinaria_id)->pendientes()->count()],
                ['veterinaria.atenciones.create', 'clip', 'Atención', ['veterinaria.atenciones.*']],
                ['veterinaria.pacientes.index', 'pets', 'Pacientes', ['veterinaria.pacientes.*']],
                ['veterinaria.productos.index', 'syr', 'Productos', ['veterinaria.productos.*']],
                ['veterinaria.pedidos', 'cart', 'Pedidos', ['veterinaria.pedidos*'], \App\Models\Pedido::where('veterinaria_id', $u->veterinaria_id)->whereIn('estado', ['pendiente_pago', 'pagado'])->count()],
                ['veterinaria.suscripcion', 'crown', 'Suscripción', ['veterinaria.suscripcion*']],
                ['veterinaria.clinica', 'store', 'Mi clínica', ['veterinaria.clinica*']],
            ],
            default => [
                ['admin.dashboard', 'grid', 'Dashboard', ['admin.dashboard']],
                ['admin.propietarios.index', 'users', 'Propietarios', ['admin.propietarios.*']],
                ['admin.mascotas.index', 'pets', 'Mascotas', ['admin.mascotas.*']],
                ['admin.veterinarias.index', 'store', 'Veterinarias', ['admin.veterinarias.*']],
                ['admin.planes.index', 'shield', 'Planes', ['admin.planes.*']],
                ['admin.pagos.index', 'doc', 'Pagos', ['admin.pagos.*'], \App\Models\Pago::dePetcare()->pendientes()->count()],
                ['admin.servicios', 'clip', 'Servicios', ['admin.servicios']],
                ['admin.reportes', 'chart', 'Reportes', ['admin.reportes*']],
                ['admin.configuracion', 'gear', 'Configuración', ['admin.configuracion', 'admin.respaldo']],
            ],
        };
    }

    public static function subtitulo(User $u): string
    {
        return match ($u->rol) {
            User::VETERINARIA => $u->veterinaria?->nombre ?? 'Veterinaria',
            User::ADMINISTRADOR => 'Administración',
            default => 'Asistencia veterinaria',
        };
    }
}
