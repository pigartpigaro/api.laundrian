<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('pembayarans', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('transaksi_id')->constrained('transaksis')->restrictOnDelete();
            $table->dateTime('tanggal')->index();
            $table->decimal('nominal', 12, 2);
            $table->string('metode', 20);
            $table->string('keterangan')->nullable();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('void_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('void_at')->nullable();
            $table->text('alasan_void')->nullable();
            $table->timestamps();
        });

        Schema::create('pengeluarans', function (Blueprint $table): void {
            $table->id();
            $table->date('tanggal')->index();
            $table->string('kategori', 40)->index();
            $table->string('keterangan', 255);
            $table->decimal('nominal', 12, 2);
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('settings', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 100)->unique();
            $table->json('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
        Schema::dropIfExists('pengeluarans');
        Schema::dropIfExists('pembayarans');
    }
};
