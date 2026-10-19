<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Outlet extends Model
{
    protected $fillable = ['nama', 'status', 'demo_mulai', 'demo_selesai', 'paket', 'logo_path'];

    protected function casts(): array
    {
        return ['demo_mulai' => 'datetime', 'demo_selesai' => 'datetime'];
    }

    public function users(): HasMany { return $this->hasMany(User::class); }

    public function hasActiveAccess(): bool
    {
        if ($this->status === 'AKTIF') return true;
        return $this->status === 'DEMO' && $this->demo_selesai?->isFuture() === true;
    }
}
