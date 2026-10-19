<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('transaksis', function (Blueprint $table): void {
            $table->decimal('diskon_member', 12, 2)->default(0)->after('diskon');
            $table->boolean('member_reward_redeemed')->default(false)->after('diskon_member');
            $table->index(['pelanggan_id', 'member_reward_redeemed', 'status_transaksi'], 'transaksis_member_reward_cycle_idx');
        });
    }

    public function down(): void
    {
        Schema::table('transaksis', function (Blueprint $table): void {
            $table->dropIndex('transaksis_member_reward_cycle_idx');
            $table->dropColumn(['diskon_member', 'member_reward_redeemed']);
        });
    }
};
