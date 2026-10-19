<?php

namespace App\Services\Transactions;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class TransactionNumberGenerator
{
    public function next(Carbon $date): string
    {
        $day = $date->toDateString();
        DB::table('transaction_sequences')->insertOrIgnore([
            'tanggal' => $day,
            'nomor_terakhir' => 0,
        ]);

        $sequence = DB::table('transaction_sequences')->where('tanggal', $day)->lockForUpdate()->first();
        $next = (int) $sequence->nomor_terakhir + 1;
        DB::table('transaction_sequences')->where('tanggal', $day)->update(['nomor_terakhir' => $next]);

        return 'LDY-'.$date->format('ymd').'-'.str_pad((string) $next, 3, '0', STR_PAD_LEFT);
    }
}
