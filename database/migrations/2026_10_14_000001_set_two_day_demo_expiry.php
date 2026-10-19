<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('outlets')->where('status', 'DEMO')->whereNull('demo_selesai')->update([
            'demo_selesai' => now()->addDays(2),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Keep assigned deadlines so rolling back code cannot restore unexpired demo access.
    }
};
