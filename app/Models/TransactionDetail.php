<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransactionDetail extends Model
{
    protected $table = 'transaksi_rincians';

    protected $fillable = ['transaksi_id', 'layanan_id', 'nama_layanan', 'qty', 'satuan', 'harga', 'subtotal', 'catatan'];

    protected function casts(): array
    {
        return ['qty' => 'decimal:2', 'harga' => 'decimal:2', 'subtotal' => 'decimal:2'];
    }

    public function transaction(): BelongsTo { return $this->belongsTo(LaundryTransaction::class, 'transaksi_id'); }
    public function service(): BelongsTo { return $this->belongsTo(Service::class, 'layanan_id'); }
}
