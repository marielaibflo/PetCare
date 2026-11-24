<?php

namespace App\Support;

/**
 * Texto del Contrato de afiliación al plan PetCare.
 * Lo usan la ventana flotante (con los datos que el dueño va eligiendo) y el PDF que se envía al aprobar el pago.
 * $d: dueno, ci, mascota, especie, plan, porcentaje, veterinaria, direccion_vet, precio, modalidad, inicio, permanencia, carencia,
 *     permanencia_meses, carencia_dias, tope_mensual, tope_anual, limite.
 */
final class Contrato
{
    public static function clausulas(array $d): array
    {
        return [
            ['Partes', "PetCare, plataforma de asistencia veterinaria (en adelante «PetCare»), y {$d['dueno']}, con CI/NIT {$d['ci']} (en adelante «el Titular»), "
                ."acuerdan la afiliación de la mascota {$d['mascota']} ({$d['especie']}) al {$d['plan']}."],
            ['Objeto', "PetCare pagará el {$d['porcentaje']} % de las atenciones y productos cubiertos por el plan, hasta {$d['tope_mensual']} al mes y {$d['tope_anual']} al año"
                .($d['limite'] ? ", con un máximo de {$d['limite']} atenciones cubiertas al año" : '').'. El Titular paga la diferencia en la clínica.'],
            ['Veterinaria del plan', "El plan se usa únicamente en {$d['veterinaria']}{$d['direccion_vet']}. Las atenciones en otras clínicas se pagan al precio completo. "
                .'La veterinaria no se puede cambiar mientras dure este contrato; el Titular podrá elegir otra al contratar su próximo plan. '
                .'Si la veterinaria deja de estar afiliada a PetCare, el Titular podrá elegir otra sin ninguna penalidad.'],
            ['Precio y forma de pago', "El precio es de {$d['precio']} en modalidad {$d['modalidad']}. En la modalidad mensual el pago se realiza mes a mes; "
                .'cada pago extiende el plan un mes. El plan se activa cuando PetCare verifica el pago.'],
            ['Permanencia mínima', "El contrato tiene una permanencia mínima de {$d['permanencia_meses']} meses, desde el {$d['inicio']} hasta el {$d['permanencia']}. "
                .'Durante ese tiempo no se puede cancelar el plan. Si el Titular deja de pagar algún mes de este periodo, el monto queda como deuda pendiente con PetCare.'],
            ['Periodo de carencia', "El plan se puede usar a partir del día ".($d['carencia_dias'] + 1)." de afiliación ({$d['carencia']}). "
                .'Durante la carencia no se cubre ninguna atención, tampoco las urgencias. Ningún plan cubre enfermedades que la mascota tenía antes de afiliarse.'],
            ['Cancelación', 'Cumplida la permanencia mínima, el Titular puede cancelar el plan en cualquier momento desde PetCare; la cobertura termina el mismo día.'],
            ['Datos y avisos', 'El Titular autoriza a PetCare a usar su correo y celular para enviarle la factura, este contrato y los avisos del plan.'],
            ['Aceptación', 'Al marcar «Acepto el contrato de afiliación» y pagar, el Titular declara haber leído y aceptado todas las cláusulas. '
                .'La fecha y hora de aceptación quedan registradas en PetCare.'],
        ];
    }

    /** Datos del contrato de una afiliación ya activa (para el PDF). */
    public static function datos(\App\Models\Afiliacion $a): array
    {
        $p = $a->plan;
        $m = $a->mascota;
        $pago = \App\Models\Pago::where('mascota_id', $m->id)->where('concepto', 'plan')->where('estado', 'aprobado')->latest('revisado_at')->first();
        $mensual = $p->precio + ($a->cobertura_grave ? Catalogo::ADICIONAL_GRAVE['precio'] : 0);

        return [
            'dueno' => $m->propietario?->name, 'ci' => $pago?->documento ?? '—', 'mascota' => $m->nombre, 'especie' => $m->especie,
            'plan' => $p->nombre.($a->cobertura_grave ? ' + Cobertura Grave' : ''), 'porcentaje' => $p->porcentaje,
            'veterinaria' => $a->veterinaria?->nombre ?? 'cualquier veterinaria afiliada', 'direccion_vet' => $a->veterinaria ? " ({$a->veterinaria->direccion}, {$a->veterinaria->zona})" : '',
            'precio' => $a->modalidad === 'anual' ? Formato::bs($p->precioAnual() + ($a->cobertura_grave ? Catalogo::ADICIONAL_GRAVE['precio'] * 10 : 0)).' al año' : Formato::bs($mensual).' al mes',
            'modalidad' => $a->modalidad,
            'inicio' => $a->fecha_inicio->format('d/m/Y'), 'permanencia' => $a->permanencia_hasta?->format('d/m/Y') ?? '—',
            'carencia' => $a->carencia_hasta ? 'desde el '.$a->carencia_hasta->format('d/m/Y') : 'sin carencia, por renovación',
            'permanencia_meses' => $p->permanencia_meses, 'carencia_dias' => $a->carencia_hasta ? $p->carencia_dias : 0,
            'tope_mensual' => Formato::bs($p->tope_mensual), 'tope_anual' => Formato::bs($p->tope_anual), 'limite' => $p->limite_atenciones,
        ];
    }
}
