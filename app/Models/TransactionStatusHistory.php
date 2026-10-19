<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransactionStatusHistory extends Model
{
    protected $table = 'transaksi_status_histories';

    protected $fillable = ['transaksi_id', 'jenis', 'status_dari', 'status_ke', 'alasan', 'user_id'];

    public function transaction(): BelongsTo { return $this->belongsTo(LaundryTransaction::class, 'transaksi_id'); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
