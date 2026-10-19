<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('transaksis', function (Blueprint $table): void {
            $table->boolean('member_promo_eligible')->default(false)->after('member_reward_redeemed');
            $table->index(['pelanggan_id', 'member_promo_eligible', 'status_transaksi', 'status_laundry', 'status_bayar'], 'transaksis_member_eligible_cycle_idx');
        });

        DB::table('transaksis')
            ->where('subtotal', '>=', 75000)
            ->where('status_transaksi', 'AKTIF')
            ->where('status_laundry', 'DIAMBIL')
            ->where('status_bayar', 'LUNAS')
            ->update(['member_promo_eligible' => true]);
    }

    public function down(): void
    {
        Schema::table('transaksis', function (Blueprint $table): void {
            $table->dropIndex('transaksis_member_eligible_cycle_idx');
            $table->dropColumn('member_promo_eligible');
        });
    }
};
