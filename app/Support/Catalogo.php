<?php

namespace App\Support;

/** Valores fijos que comparten la lógica de negocio y las vistas. */
final class Catalogo
{
    public const SERVICIOS = ['Consulta', 'Vacuna', 'Desparasitación', 'Tratamiento', 'Control de pulgas', 'Urgencia',
        'Cirugía / Castración', 'Post-operatorio', 'Corte de pelo y baño', 'Oncología y crónicos'];

    /** Servicios con periodo de espera desde la afiliación. */
    public const CIRUGIAS = ['Cirugía / Castración', 'Post-operatorio'];

    /** Servicio que solo cubre el adicional «Cobertura Grave». */
    public const GRAVE = 'Oncología y crónicos';

    /** Adicional «Cobertura Grave» (cáncer y enfermedades crónicas). */
    public const ADICIONAL_GRAVE = ['nombre' => 'Cobertura Grave', 'precio' => 30, 'porcentaje' => 50, 'tope_anual' => 5000, 'espera_dias' => 180];

    /** Categorías del catálogo de productos de cada veterinaria. */
    public const CATEGORIAS_PRODUCTO = ['medicamento' => 'Medicamentos', 'vacuna' => 'Vacunas', 'antiparasitario' => 'Antiparasitarios',
        'alimento' => 'Alimentos', 'accesorio' => 'Accesorios', 'higiene' => 'Higiene', 'juguete' => 'Juguetes', 'insumo' => 'Insumos clínicos'];

    /** Categorías que el plan puede descontar en la tienda (si la clínica las marca y el plan cubre medicamentos). */
    public const DESCUENTO_TIENDA = ['medicamento', 'vacuna', 'antiparasitario'];

    /** Categorías de tienda: nunca las cubren los planes. */
    public const PRODUCTOS_TIENDA = ['alimento', 'accesorio', 'higiene', 'juguete'];

    /** Qué medicamentos cubre cada plan. */
    public const REGLA_MEDICAMENTOS = ['todos' => 'Todos los medicamentos marcados por la clínica', 'urgencia' => 'Solo medicamentos usados en urgencias', 'ninguno' => 'No cubre medicamentos'];

    /** Modalidades de pago de los planes: en la anual se pagan 10 meses y se reciben 12. */
    public const MODALIDADES = ['mensual' => ['nombre' => 'Mensual', 'meses' => 1, 'cobra' => 1], 'anual' => ['nombre' => 'Anual (2 meses gratis)', 'meses' => 12, 'cobra' => 10]];

    /** Suscripciones de las veterinarias a PetCare. */
    public const SUSCRIPCIONES = [
        // La clave «inicial» se mantiene por compatibilidad con los datos; el plan se llama Básico.
        'inicial' => ['nombre' => 'Básico', 'precio' => 70, 'productos' => 10, 'atiende_planes' => true, 'orden' => 0,
            'beneficios' => ['Atiende mascotas con plan PetCare (PetCare le paga la cobertura)', 'Agenda de citas con avisos por WhatsApp', 'Hasta 10 productos en la tienda', 'Cartilla digital e historial clínico']],
        'profesional' => ['nombre' => 'Profesional', 'precio' => 100, 'productos' => 20, 'atiende_planes' => true, 'orden' => 1,
            'beneficios' => ['Todo lo del plan Básico', 'Aparece destacada en el buscador', 'Hasta 20 productos en la tienda', 'Galería de fotos y promoción del mes']],
        'plus' => ['nombre' => 'Clínica Plus', 'precio' => 200, 'productos' => null, 'atiende_planes' => true, 'orden' => 2,
            'beneficios' => ['Todo lo del plan Profesional', 'Productos ilimitados en la tienda', 'Primera en los resultados de búsqueda', 'Reportes completos de su clínica']],
    ];

    /** Días de prueba gratis al afiliarse una veterinaria. */
    public const DIAS_PRUEBA_VETERINARIA = 30;

    /** Servicios preventivos: llevan producto aplicado y fecha de próxima dosis. */
    public const PREVENTIVOS = ['Vacuna', 'Desparasitación', 'Control de pulgas'];

    /** Opción «Otro…» en el tipo de servicio (atención y cita): se describe en texto libre y no la cubre el plan. */
    public const OTRO_SERVICIO = 'Otro…';

    /**
     * Si se eligió «Otro…», valida la descripción y la pone como servicio. Devuelve true si es un servicio «otro».
     * No se acepta escribir un servicio que ya está en la lista (para elegirlo de la lista y que se calcule bien la cobertura).
     */
    public static function prepararServicioOtro(\Illuminate\Http\Request $request, array $deLaLista = [], string $bag = 'default'): bool
    {
        if ($request->input('servicio') !== self::OTRO_SERVICIO) {
            return false;
        }
        $txt = preg_replace('/\s+/u', ' ', trim((string) $request->input('servicio_otro')));
        $msg = null;
        if (mb_strlen($txt) < 3) {
            $msg = 'Describe el servicio (al menos 3 letras), por ejemplo: Limpieza dental.';
        } elseif (mb_strlen($txt) > 40) {
            $msg = 'La descripción del servicio admite como máximo 40 caracteres.';
        } elseif (! preg_match('/^[\pL\pN\s.,\/()\-]+$/u', $txt)) {
            $msg = 'Usa solo letras, números y espacios para describir el servicio.';
        } elseif (in_array(mb_strtolower($txt), array_map('mb_strtolower', array_merge(self::SERVICIOS, $deLaLista)), true)) {
            $msg = "«{$txt}» ya está en la lista: elígelo ahí en lugar de «Otro…».";
        }
        if ($msg) {
            throw \Illuminate\Validation\ValidationException::withMessages(['servicio_otro' => $msg])->errorBag($bag);
        }
        $request->merge(['servicio' => mb_strtoupper(mb_substr($txt, 0, 1)).mb_substr($txt, 1)]);

        return true;
    }

    public const ESPECIES = ['Perro', 'Gato', 'Otro'];

    public const SEXOS = ['Macho', 'Hembra'];

    public const HORAS = ['08:30', '09:00', '09:30', '10:00', '10:30', '11:00', '11:30', '12:00', '14:00', '14:30',
        '15:00', '15:30', '16:00', '16:30', '17:00', '17:30', '18:00'];

    public const ROLES = ['propietario' => 'Propietario', 'veterinaria' => 'Veterinaria', 'administrador' => 'Administrador'];

    /** Ícono y color de cada servicio en las listas. */
    public const SERVICIO_ICONO = [
        'Consulta' => ['doc', 'c-i'], 'Vacuna' => ['shield', 'c-p'], 'Desparasitación' => ['syr', 'c-ok'],
        'Tratamiento' => ['heart', 'c-w'], 'Control de pulgas' => ['bug', 'c-ok'], 'Urgencia' => ['alert', 'c-b'],
        'Cirugía / Castración' => ['steth', 'c-b'], 'Post-operatorio' => ['clip', 'c-w'], 'Corte de pelo y baño' => ['star', 'c-p'],
        'Oncología y crónicos' => ['heart', 'c-b'],
    ];

    public const ESTADO_CITA = ['pendiente' => 'warn', 'confirmada' => 'info', 'atendida' => 'ok', 'rechazada' => 'bad', 'cancelada' => 'mut'];

    /** Meses de vigencia de una afiliación. */
    public const VIGENCIA_MESES = 12;

    /** Días de anticipación para avisar próximas dosis. */
    public const DIAS_AVISO = 30;

    /** Días de anticipación para avisar que un plan (de pago mensual) está por vencer. */
    public const DIAS_AVISO_PLAN = 7;

    /**
     * Servicios de una clínica: los del catálogo (casillas) + «Otros» escritos por la clínica.
     * Deja en el request «servicios» con ambos, ya validados. Mensajes por casilla.
     */
    public static function prepararServicios(\Illuminate\Http\Request $request, string $bag = 'default'): void
    {
        $marcados = array_values(array_filter((array) $request->input('servicios', []), fn ($s) => $s !== 'Otros'));
        $otros = [];
        if (in_array('Otros', (array) $request->input('servicios', []), true)) {
            $texto = trim((string) $request->input('servicios_otros'));
            if ($texto === '') {
                throw \Illuminate\Validation\ValidationException::withMessages(['servicios_otros' => 'Escribe qué otros servicios ofreces, separados por comas (por ejemplo: Fisioterapia, Odontología).'])->errorBag($bag);
            }
            $estandar = array_map('mb_strtolower', self::SERVICIOS);
            foreach (preg_split('/[,;\n]+/', $texto) as $s) {
                $s = preg_replace('/\s+/', ' ', trim($s));
                if ($s === '') {
                    continue;
                }
                if (! preg_match('/^[\p{L}\d][\p{L}\d ()\/.-]{2,39}$/u', $s)) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['servicios_otros' => "«{$s}» no es válido: cada servicio debe tener de 3 a 40 letras o números."])->errorBag($bag);
                }
                $s = mb_strtoupper(mb_substr($s, 0, 1)).mb_substr($s, 1);
                if (! in_array(mb_strtolower($s), $estandar, true) && ! in_array(mb_strtolower($s), array_map('mb_strtolower', $otros), true)) {
                    $otros[] = $s;
                }
            }
            if (count($otros) > 8) {
                throw \Illuminate\Validation\ValidationException::withMessages(['servicios_otros' => 'Puedes agregar hasta 8 servicios adicionales.'])->errorBag($bag);
            }
        }
        foreach ($marcados as $m) {
            if (! in_array($m, self::SERVICIOS, true)) {
                throw \Illuminate\Validation\ValidationException::withMessages(['servicios' => 'Uno de los servicios marcados no es válido.'])->errorBag($bag);
            }
        }
        $request->merge(['servicios' => array_values(array_merge($marcados, $otros))]);
    }

    /** Servicios propios de la clínica que no están en el catálogo de PetCare. */
    public static function serviciosOtros(array $servicios): array
    {
        return array_values(array_diff($servicios, self::SERVICIOS));
    }
}
