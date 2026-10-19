<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outlets', function (Blueprint $table): void {
            $table->id();
            $table->string('nama', 150);
            $table->string('status', 20)->default('DEMO')->index();
            $table->timestamp('demo_mulai')->nullable();
            $table->timestamp('demo_selesai')->nullable();
            $table->string('paket', 50)->nullable();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->foreignId('outlet_id')->nullable()->after('id')->constrained('outlets')->restrictOnDelete();
            $table->string('username', 40)->nullable()->after('name');
        });
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['email']);
            $table->string('email')->nullable()->change();
        });

        $outletId = DB::table('outlets')->insertGetId([
            'nama' => 'Londrian Laundry', 'status' => 'DEMO', 'demo_mulai' => now(), 'demo_selesai' => now()->addDays(2), 'created_at' => now(), 'updated_at' => now(),
        ]);
        foreach (DB::table('users')->get(['id', 'name', 'email', 'role']) as $user) {
            DB::table('users')->where('id', $user->id)->update([
                'outlet_id' => $outletId,
                'username' => Str::limit(Str::slug($user->name ?: 'pengguna').$user->id, 40, ''),
                'role' => $user->role === 'KARYAWAN' ? 'KASIR' : $user->role,
            ]);
        }
        Schema::table('users', function (Blueprint $table): void {
            $table->string('role', 20)->default('KASIR')->change();
        });

        foreach (['layanans', 'pelanggans', 'transaksis', 'pengeluarans', 'settings', 'pembayarans'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->foreignId('outlet_id')->nullable()->after('id')->constrained('outlets')->restrictOnDelete();
            });
            DB::table($tableName)->whereNull('outlet_id')->update(['outlet_id' => $outletId]);
        }

        DB::table('pembayarans')->update([
            'outlet_id' => DB::raw('(select outlet_id from transaksis where transaksis.id = pembayarans.transaksi_id)'),
        ]);
        Schema::table('settings', function (Blueprint $table): void {
            $table->dropUnique(['key']);
            $table->unique(['outlet_id', 'key']);
        });
        Schema::table('users', function (Blueprint $table): void {
            $table->unique('username');
            $table->index('outlet_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['username']);
            $table->dropForeign(['outlet_id']);
            $table->dropColumn(['username', 'outlet_id']);
            $table->string('email')->nullable(false)->change();
            $table->unique('email');
        });
        foreach (['layanans', 'pelanggans', 'transaksis', 'pengeluarans', 'settings', 'pembayarans'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                if ($tableName === 'settings') $table->dropUnique(['outlet_id', 'key']);
                $table->dropForeign(['outlet_id']);
                $table->dropColumn('outlet_id');
            });
        }
        Schema::dropIfExists('outlets');
    }
};
