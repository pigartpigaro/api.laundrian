<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Concerns\BelongsToOutlet;

class Expense extends Model
{
    use BelongsToOutlet;
    protected $table = 'pengeluarans';

    protected $fillable = ['outlet_id', 'tanggal', 'kategori', 'keterangan', 'nominal', 'user_id'];

    protected function casts(): array
    {
        return ['tanggal' => 'date', 'nominal' => 'decimal:2'];
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
