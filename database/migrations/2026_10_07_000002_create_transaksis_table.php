<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('transaksis', function (Blueprint $table): void {
            $table->id();
            $table->string('notrans', 32)->unique();
            $table->dateTime('tanggal')->index();
            $table->foreignId('pelanggan_id')->nullable()->constrained('pelanggans')->nullOnDelete();
            $table->string('nama_pelanggan', 150);
            $table->string('no_hp', 32)->nullable();
            $table->dateTime('estimasi_selesai')->nullable()->index();
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('diskon', 12, 2)->default(0);
            $table->decimal('ongkir', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->decimal('dibayar', 12, 2)->default(0);
            $table->decimal('sisa', 12, 2)->default(0);
            $table->string('status_bayar', 20)->default('BELUM BAYAR')->index();
            $table->string('status_laundry', 20)->default('MASUK')->index();
            $table->string('status_transaksi', 20)->default('AKTIF')->index();
            $table->text('catatan')->nullable();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('dibatalkan_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('dibatalkan_pada')->nullable();
            $table->text('alasan_batal')->nullable();
            $table->timestamps();
        });

        Schema::create('transaksi_rincians', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('transaksi_id')->constrained('transaksis')->restrictOnDelete();
            $table->foreignId('layanan_id')->nullable()->constrained('layanans')->nullOnDelete();
            $table->string('nama_layanan', 120);
            $table->decimal('qty', 10, 2);
            $table->string('satuan', 20);
            $table->decimal('harga', 12, 2);
            $table->decimal('subtotal', 12, 2);
            $table->text('catatan')->nullable();
            $table->timestamps();
        });

        Schema::create('transaction_sequences', function (Blueprint $table): void {
            $table->date('tanggal')->primary();
            $table->unsignedInteger('nomor_terakhir')->default(0);
        });

        Schema::create('transaksi_status_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('transaksi_id')->constrained('transaksis')->cascadeOnDelete();
            $table->string('jenis', 20)->default('LAUNDRY');
            $table->string('status_dari', 20)->nullable();
            $table->string('status_ke', 20);
            $table->text('alasan')->nullable();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaksi_status_histories');
        Schema::dropIfExists('transaction_sequences');
        Schema::dropIfExists('transaksi_rincians');
        Schema::dropIfExists('transaksis');
    }
};
