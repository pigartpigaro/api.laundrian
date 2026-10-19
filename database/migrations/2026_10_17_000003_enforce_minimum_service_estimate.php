<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('layanans')->where('estimasi_jam', '<', 72)->update(['estimasi_jam' => 72]);
    }

    public function down(): void
    {
        // Existing service estimates are intentionally not reduced on rollback.
    }
};
