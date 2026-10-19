<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

trait BelongsToOutlet
{
    protected static function bootBelongsToOutlet(): void
    {
        static::addGlobalScope('outlet', function (Builder $query): void {
            $user = Auth::user();
            if (! $user) return;

            $outletId = $user->role === 'SUPERADMIN'
                ? request()->header('X-Outlet-ID')
                : $user->outlet_id;

            if (! $outletId) {
                $query->whereRaw('1 = 0');
                return;
            }

            $query->where($query->getModel()->qualifyColumn('outlet_id'), $outletId);
        });

        static::creating(function ($model): void {
            if ($model->outlet_id) return;
            $user = Auth::user();
            $outletId = $user?->role === 'SUPERADMIN' ? request()->header('X-Outlet-ID') : $user?->outlet_id;
            if (! $outletId && app()->runningInConsole()) return;
            if (! $outletId) abort(403, 'Pilih outlet sebelum membuat data.');
            $model->outlet_id = $outletId;
        });
    }
}
