<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('layanans', function (Blueprint $table): void {
            $table->id();
            $table->string('kode', 32)->unique();
            $table->string('nama', 120);
            $table->string('jenis_hitung', 20);
            $table->decimal('harga', 12, 2);
            $table->unsignedSmallInteger('estimasi_jam')->default(24);
            $table->boolean('aktif')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('pelanggans', function (Blueprint $table): void {
            $table->id();
            $table->string('kode', 32)->unique();
            $table->string('nama', 150)->index();
            $table->string('no_hp', 32)->nullable()->index();
            $table->text('alamat')->nullable();
            $table->text('catatan')->nullable();
            $table->unsignedInteger('poin')->default(0);
            $table->boolean('aktif')->default(true)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pelanggans');
        Schema::dropIfExists('layanans');
    }
};
