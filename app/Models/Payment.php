<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Concerns\BelongsToOutlet;

class Payment extends Model
{
    use BelongsToOutlet;
    protected $table = 'pembayarans';

    protected $fillable = ['outlet_id', 'transaksi_id', 'tanggal', 'nominal', 'metode', 'keterangan', 'user_id', 'void_by', 'void_at', 'alasan_void'];

    protected function casts(): array
    {
        return ['tanggal' => 'datetime', 'nominal' => 'decimal:2', 'void_at' => 'datetime'];
    }

    public function transaction(): BelongsTo { return $this->belongsTo(LaundryTransaction::class, 'transaksi_id'); }
    public function user(): BelongsTo { return $this->belongsTo(User::class, 'user_id'); }
}
