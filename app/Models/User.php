<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const PROPIETARIO = 'propietario';
    public const VETERINARIA = 'veterinaria';
    public const ADMINISTRADOR = 'administrador';

    protected $fillable = ['email_nuevo', 'name', 'email', 'password', 'rol', 'telefono', 'codigo_pais', 'veterinaria_id', 'matricula', 'direccion', 'activo', 'foto'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'telefono_verificado_at' => 'datetime',
            'password' => 'hashed',
            'activo' => 'boolean',
        ];
    }

    public function mascotas(): HasMany
    {
        return $this->hasMany(Mascota::class, 'propietario_id');
    }

    public function veterinaria(): BelongsTo
    {
        return $this->belongsTo(Veterinaria::class);
    }

    public function esRol(string $rol): bool
    {
        return $this->rol === $rol;
    }

    public function rolNombre(): string
    {
        return \App\Support\Catalogo::ROLES[$this->rol] ?? $this->rol;
    }

    /** Iniciales para el avatar (sin títulos como «Dra.»). */
    public function iniciales(): string
    {
        $nombre = preg_replace('/^(Dra?\.|Lic\.)\s*/u', '', $this->name);
        $partes = preg_split('/\s+/u', trim($nombre));

        return mb_strtoupper(collect($partes)->take(2)->map(fn ($p) => mb_substr($p, 0, 1))->implode(''));
    }

    public function primerNombre(): string
    {
        return explode(' ', preg_replace('/^(Dra?\.|Lic\.)\s*/u', '', $this->name))[0];
    }

    /** Correo y celular confirmados con código. */
    /**
     * Todos confirman su correo con el enlace o el código; el propietario además confirma su WhatsApp.
     * El administrador es una cuenta interna.
     */
    public function verificado(): bool
    {
        return array_diff($this->canalesVerificacion(), $this->canalesVerificados()) === [];
    }

    public function canalesVerificacion(): array
    {
        return match ($this->rol) {
            self::ADMINISTRADOR => [],
            self::PROPIETARIO => ['email', 'whatsapp'],
            default => ['email'],
        };
    }

    public function canalesVerificados(): array
    {
        return array_keys(array_filter(['email' => $this->email_verified_at, 'whatsapp' => $this->telefono_verificado_at]));
    }

    /** Número para WhatsApp: 59171234567 */
    public function telefonoInternacional(): ?string
    {
        return \App\Support\Paises::internacional($this->codigo_pais, $this->telefono);
    }

    /** Número para mostrar: +591 71234567 */
    public function telefonoVisible(): string
    {
        return \App\Support\Paises::mostrar($this->codigo_pais, $this->telefono);
    }

    public function rutaInicio(): string
    {
        return match ($this->rol) {
            self::VETERINARIA => route('veterinaria.inicio'),
            self::ADMINISTRADOR => route('admin.dashboard'),
            default => route('propietario.inicio'),
        };
    }
}
