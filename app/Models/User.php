<?php

namespace App\Models;

use App\Traits\HasSecurityPermissions;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\RolSistemaEnum;
use App\Models\Configuracion\PuntoVenta;
use App\Models\Configuracion\Sucursal;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, HasSecurityPermissions, Notifiable, TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'sucursal_id',
        'punto_venta_id',
        'estado',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'sucursal_id' => 'integer',
            'punto_venta_id' => 'integer',
            'estado' => 'boolean',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }

    public function puntoVenta(): BelongsTo
    {
        return $this->belongsTo(PuntoVenta::class);
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_user')
            ->withTimestamps();
    }

    public function hasRole(string $roleSlug): bool
    {
        return $this->roles()->where('slug', $roleSlug)->exists();
    }

    public function canManageAllOperationalContexts(): bool
    {
        return $this->hasRole(RolSistemaEnum::SUPERADMIN->value)
            || $this->hasRole(RolSistemaEnum::ADMINISTRADOR->value);
    }

    public function kardex(): HasMany
    {
        return $this->hasMany(Kardex::class);
    }

    public function ventas(): HasMany
    {
        return $this->hasMany(VentaCabecera::class);
    }
}
