<?php

namespace App\Support;

use Illuminate\Support\Carbon;

final class Formato
{
    public static function fecha(?Carbon $f): string
    {
        return $f ? $f->format('d/m/Y') : '—';
    }

    public static function fechaLarga(?Carbon $f): string
    {
        return $f ? $f->locale('es')->translatedFormat('j \d\e F \d\e Y') : '—';
    }

    public static function bs(float|int|null $monto): string
    {
        $n = (float) $monto;

        return 'Bs '.(fmod($n, 1.0) == 0.0 ? number_format($n, 0, ',', '.') : number_format($n, 2, ',', '.'));
    }
}
