<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Concerns\BelongsToOutlet;

class LaundryTransaction extends Model
{
    use BelongsToOutlet;
    protected $table = 'transaksis';

    protected $fillable = [
        'outlet_id', 'notrans', 'tanggal', 'pelanggan_id', 'nama_pelanggan', 'no_hp', 'estimasi_selesai',
        'subtotal', 'diskon', 'diskon_member', 'diskon_member_persen', 'member_reward_redeemed', 'member_promo_eligible', 'ongkir', 'total', 'dibayar', 'sisa', 'status_bayar',
        'status_laundry', 'status_transaksi', 'catatan', 'user_id', 'dibatalkan_oleh',
        'dibatalkan_pada', 'alasan_batal',
    ];

    protected function casts(): array
    {
        return [
            'tanggal' => 'datetime', 'estimasi_selesai' => 'datetime', 'dibatalkan_pada' => 'datetime',
            'subtotal' => 'decimal:2', 'diskon' => 'decimal:2', 'diskon_member' => 'decimal:2', 'diskon_member_persen' => 'decimal:2', 'member_reward_redeemed' => 'boolean', 'member_promo_eligible' => 'boolean', 'ongkir' => 'decimal:2',
            'total' => 'decimal:2', 'dibayar' => 'decimal:2', 'sisa' => 'decimal:2',
        ];
    }

    public function customer(): BelongsTo { return $this->belongsTo(Customer::class, 'pelanggan_id'); }
    public function cashier(): BelongsTo { return $this->belongsTo(User::class, 'user_id'); }
    public function details(): HasMany { return $this->hasMany(TransactionDetail::class, 'transaksi_id'); }
    public function payments(): HasMany { return $this->hasMany(Payment::class, 'transaksi_id'); }
    public function statusHistory(): HasMany { return $this->hasMany(TransactionStatusHistory::class, 'transaksi_id'); }
}
