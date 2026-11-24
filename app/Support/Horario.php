<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Horario de atención estructurado: día desde/hasta y hora de apertura/cierre.
 * Se guarda como texto legible ("Lun–Sáb · 08:30 a 18:00" o "Lun–Dom · 24 horas").
 */
final class Horario
{
    public const DIAS = ['lun' => 'Lunes', 'mar' => 'Martes', 'mie' => 'Miércoles', 'jue' => 'Jueves', 'vie' => 'Viernes', 'sab' => 'Sábado', 'dom' => 'Domingo'];

    public const CORTOS = ['lun' => 'Lun', 'mar' => 'Mar', 'mie' => 'Mié', 'jue' => 'Jue', 'vie' => 'Vie', 'sab' => 'Sáb', 'dom' => 'Dom'];

    /** Horas cada 30 minutos, de 05:00 a 23:30. */
    public static function horas(): array
    {
        $h = [];
        for ($m = 5 * 60; $m <= 23 * 60 + 30; $m += 30) {
            $h[] = sprintf('%02d:%02d', intdiv($m, 60), $m % 60);
        }

        return $h;
    }

    /** Descompone el texto guardado para rellenar el formulario. */
    public static function partes(?string $texto): array
    {
        $def = ['dia_desde' => 'lun', 'dia_hasta' => 'sab', 'hora_desde' => '08:30', 'hora_hasta' => '18:00', 'h24' => false];
        if (! $texto) {
            return $def;
        }
        $inv = array_flip(self::CORTOS);
        if (preg_match('/^(\p{L}{3})(?:[–-](\p{L}{3}))?\s*·\s*(?:(\d{2}:\d{2}) a (\d{2}:\d{2})|24 horas)$/u', trim($texto), $m)) {
            return [
                'dia_desde' => $inv[$m[1]] ?? 'lun', 'dia_hasta' => $inv[$m[2] ?: $m[1]] ?? 'sab',
                'hora_desde' => $m[3] ?? '00:00', 'hora_hasta' => $m[4] ?? '23:30', 'h24' => ! isset($m[3]) || $m[3] === '',
            ];
        }

        return $def;
    }

    /**
     * Valida los campos horario_* del formulario y deja en el request el texto final en «horario».
     * Mensajes específicos por casilla.
     */
    public static function componer(Request $request, string $bag = 'default'): void
    {
        $d = $request->only(['horario_dia_desde', 'horario_dia_hasta', 'horario_hora_desde', 'horario_hora_hasta', 'horario_24']);
        if (! array_filter($d)) {
            return; // el formulario no usa el horario estructurado
        }
        $err = [];
        $dias = array_keys(self::DIAS);
        if (! in_array($d['horario_dia_desde'] ?? null, $dias, true)) {
            $err['horario_dia_desde'] = 'Elige desde qué día atiende la clínica.';
        }
        if (! in_array($d['horario_dia_hasta'] ?? null, $dias, true)) {
            $err['horario_dia_hasta'] = 'Elige hasta qué día atiende la clínica.';
        }
        if (! $err && array_search($d['horario_dia_hasta'], $dias) < array_search($d['horario_dia_desde'], $dias)) {
            $err['horario_dia_hasta'] = 'El día final no puede ser anterior al día inicial (por ejemplo: de lunes a sábado).';
        }
        $h24 = (bool) ($d['horario_24'] ?? false);
        if (! $h24) {
            $horas = self::horas();
            if (! in_array($d['horario_hora_desde'] ?? null, $horas, true)) {
                $err['horario_hora_desde'] = 'Elige la hora de apertura.';
            }
            if (! in_array($d['horario_hora_hasta'] ?? null, $horas, true)) {
                $err['horario_hora_hasta'] = 'Elige la hora de cierre.';
            }
            if (! isset($err['horario_hora_desde'], $err['horario_hora_hasta']) && ($d['horario_hora_hasta'] ?? '') <= ($d['horario_hora_desde'] ?? '')) {
                $err['horario_hora_hasta'] = 'La hora de cierre debe ser posterior a la de apertura.';
            } elseif (! $err && self::minutos($d['horario_hora_hasta']) - self::minutos($d['horario_hora_desde']) < 120) {
                $err['horario_hora_hasta'] = 'La clínica debe atender al menos 2 horas al día.';
            }
        }
        if ($err) {
            throw ValidationException::withMessages($err)->errorBag($bag);
        }
        $dias = self::CORTOS[$d['horario_dia_desde']].($d['horario_dia_desde'] !== $d['horario_dia_hasta'] ? '–'.self::CORTOS[$d['horario_dia_hasta']] : '');
        $request->merge(['horario' => $dias.' · '.($h24 ? '24 horas' : $d['horario_hora_desde'].' a '.$d['horario_hora_hasta'])]);
    }

    private static function minutos(string $h): int
    {
        [$a, $b] = array_map('intval', explode(':', $h));

        return $a * 60 + $b;
    }
}
