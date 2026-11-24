<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class Mascota extends Model
{
    use SoftDeletes;

    protected $table = 'mascotas';

    protected $fillable = ['propietario_id', 'nombre', 'especie', 'raza', 'fecha_nacimiento', 'peso', 'sexo', 'esterilizado', 'alergias', 'foto', 'color'];

    protected function casts(): array
    {
        return ['fecha_nacimiento' => 'date', 'esterilizado' => 'boolean', 'peso' => 'float'];
    }

    public function propietario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'propietario_id');
    }

    public function afiliaciones(): HasMany
    {
        return $this->hasMany(Afiliacion::class);
    }

    /** Afiliación activa y vigente (como máximo una por mascota). */
    public function afiliacionActiva(): HasOne
    {
        return $this->hasOne(Afiliacion::class)->ofMany(['id' => 'max'], fn ($q) => $q->vigentes());
    }

    public function citas(): HasMany
    {
        return $this->hasMany(Cita::class);
    }

    /** Historial clínico. */
    public function atenciones(): HasMany
    {
        return $this->hasMany(Atencion::class)->orderByDesc('fecha')->orderByDesc('id');
    }

    public function edad(): string
    {
        $meses = (int) $this->fecha_nacimiento->diffInMonths(Carbon::today());
        if ($meses < 1) {
            return 'Menos de 1 mes';
        }
        if ($meses < 12) {
            return $meses.($meses === 1 ? ' mes' : ' meses');
        }
        $anios = intdiv($meses, 12);

        return $anios.($anios === 1 ? ' año' : ' años');
    }

    public function resumen(): string
    {
        return $this->especie.' · '.$this->edad();
    }

    /** Número de historia clínica en PetCare (aparece en la cartilla). */
    public function numeroHistoria(): string
    {
        return 'PC-'.str_pad((string) $this->id, 5, '0', STR_PAD_LEFT);
    }
}
