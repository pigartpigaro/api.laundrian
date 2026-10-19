<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'username',
        'outlet_id',
        'email',
        'password',
        'role',
        'is_active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
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
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function transactions(): HasMany { return $this->hasMany(LaundryTransaction::class, 'user_id'); }
    public function payments(): HasMany { return $this->hasMany(Payment::class, 'user_id'); }
    public function expenses(): HasMany { return $this->hasMany(Expense::class, 'user_id'); }

    public function outlet(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(Outlet::class); }
    public function isOwner(): bool { return in_array($this->role, ['OWNER', 'SUPERADMIN'], true); }
    public function hasOutletAccess(): bool { return $this->role === 'SUPERADMIN' || ($this->outlet?->hasActiveAccess() ?? false); }
}
