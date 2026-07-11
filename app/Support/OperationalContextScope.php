<?php

namespace App\Support;

use App\Enums\RolSistemaEnum;
use App\Models\Configuracion\PuntoVenta;
use App\Models\Configuracion\Sucursal;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class OperationalContextScope
{
    public static function isGlobal(?User $user): bool
    {
        return (bool) ($user?->hasRole(RolSistemaEnum::SUPERADMIN->value) ?? false);
    }

    public static function apply(Builder $query, ?User $user): void
    {
        if (! $user || self::isGlobal($user)) {
            return;
        }

        if ($user->punto_venta_id) {
            $query->where('punto_venta_id', $user->punto_venta_id);

            return;
        }

        if ($user->sucursal_id) {
            $query->where('sucursal_id', $user->sucursal_id);
        }
    }

    public static function mergeFilters(array $filters, ?User $user): array
    {
        if (! $user || self::isGlobal($user)) {
            return $filters;
        }

        if ($user->sucursal_id) {
            $filters['sucursal_id'] = (int) $user->sucursal_id;
        }

        if ($user->punto_venta_id) {
            $filters['punto_venta_id'] = (int) $user->punto_venta_id;
        }

        return $filters;
    }

    public static function authorize(?User $user, int $sucursalId, int $puntoVentaId): void
    {
        if (! $user || self::isGlobal($user)) {
            return;
        }

        if ($user->sucursal_id && (int) $user->sucursal_id !== $sucursalId) {
            abort(403, 'No tienes permiso para operar en esta sucursal.');
        }

        if ($user->punto_venta_id && (int) $user->punto_venta_id !== $puntoVentaId) {
            abort(403, 'No tienes permiso para operar en este punto de venta.');
        }
    }

    public static function sucursalesQuery(?User $user): Builder
    {
        return Sucursal::query()
            ->where('estado', true)
            ->when(
                $user && ! self::isGlobal($user) && $user->sucursal_id,
                fn (Builder $query) => $query->whereKey($user->sucursal_id),
            )
            ->orderBy('codigo');
    }

    public static function puntosVentaQuery(?User $user): Builder
    {
        return PuntoVenta::query()
            ->where('estado', true)
            ->when(
                $user && ! self::isGlobal($user) && $user->punto_venta_id,
                fn (Builder $query) => $query->whereKey($user->punto_venta_id),
            )
            ->when(
                $user && ! self::isGlobal($user) && ! $user->punto_venta_id && $user->sucursal_id,
                fn (Builder $query) => $query->where('sucursal_id', $user->sucursal_id),
            )
            ->orderBy('sucursal_id')
            ->orderBy('codigo');
    }
}
