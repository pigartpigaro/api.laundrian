<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transaksis', function (Blueprint $table): void {
            $table->decimal('diskon_member_persen', 5, 2)->default(0)->after('diskon_member');
        });

        DB::table('transaksis')->where('member_reward_redeemed', true)->where('diskon_member', '>', 0)->update(['diskon_member_persen' => 20]);
    }

    public function down(): void
    {
        Schema::table('transaksis', function (Blueprint $table): void {
            $table->dropColumn('diskon_member_persen');
        });
    }
};
