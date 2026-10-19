<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Concerns\BelongsToOutlet;

class Customer extends Model
{
    use BelongsToOutlet;
    protected $table = 'pelanggans';

    protected $fillable = ['outlet_id', 'kode', 'nama', 'no_hp', 'alamat', 'catatan', 'poin', 'aktif'];

    protected function casts(): array
    {
        return ['poin' => 'integer', 'aktif' => 'boolean'];
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(LaundryTransaction::class, 'pelanggan_id');
    }
}
