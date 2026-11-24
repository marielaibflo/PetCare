<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Registro del historial clínico de una mascota. */
class Atencion extends Model
{
    protected $table = 'atenciones';

    protected $fillable = ['mascota_id', 'veterinaria_id', 'user_id', 'cita_id', 'plan_id', 'fecha', 'servicio', 'producto', 'diagnostico',
        'descripcion', 'tratamiento', 'peso', 'temperatura', 'proxima_dosis', 'cubierto_por_plan', 'costo_servicio', 'costo_productos',
        'total', 'porcentaje', 'monto_cubierto', 'paga_dueno', 'preexistente', 'nota_cobertura', 'foto_etiqueta', 'registrado_por',
        'clinica_externa', 'firmado_por', 'lote', 'matricula', 'registro_clinica', 'documento_dueno', 'recordatorio_at'];

    protected function casts(): array
    {
        return ['fecha' => 'date', 'proxima_dosis' => 'date', 'cubierto_por_plan' => 'boolean', 'peso' => 'float', 'temperatura' => 'float',
            'costo_servicio' => 'float', 'costo_productos' => 'float', 'total' => 'float', 'porcentaje' => 'integer',
            'monto_cubierto' => 'float', 'paga_dueno' => 'float', 'preexistente' => 'boolean', 'recordatorio_at' => 'datetime'];
    }

    public function mascota(): BelongsTo
    {
        return $this->belongsTo(Mascota::class)->withTrashed();
    }

    public function veterinaria(): BelongsTo
    {
        return $this->belongsTo(Veterinaria::class);
    }

    public function autor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function productos(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(AtencionProducto::class);
    }

    /** Nombre de quien aplicó (clínica afiliada o la clínica externa que indicó el dueño). */
    public function lugar(): string
    {
        return $this->veterinaria?->nombre ?? ($this->clinica_externa ? $this->clinica_externa.' (registrado por el propietario)' : 'Registrado por el propietario');
    }

    public function cita(): BelongsTo
    {
        return $this->belongsTo(Cita::class);
    }
}
