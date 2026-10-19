<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Concerns\BelongsToOutlet;

class Service extends Model
{
    use BelongsToOutlet;
    protected $table = 'layanans';

    protected $fillable = ['outlet_id', 'kode', 'nama', 'jenis_hitung', 'harga', 'estimasi_jam', 'aktif'];

    protected function casts(): array
    {
        return ['harga' => 'decimal:2', 'estimasi_jam' => 'integer', 'aktif' => 'boolean'];
    }

    public function details(): HasMany
    {
        return $this->hasMany(TransactionDetail::class, 'layanan_id');
    }
}
