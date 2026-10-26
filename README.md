# Londrian Backend

REST API POS laundry Londrian memakai PHP 8.2, Laravel 12, Sanctum, dan MySQL 8.4. Backend menjadi sumber kebenaran untuk harga, total transaksi, pembayaran, nomor nota, role, dan status laundry.

## Menjalankan dengan Docker

Prasyarat: Docker Desktop dengan Docker Compose v2.

Nama database dan volume Docker masih memakai identifier penyimpanan lama agar instalasi yang sudah ada tetap memakai data yang sama. Jangan mengganti atau menghapus volume MySQL kecuali memang melakukan migrasi data.

1. Dari folder ini, salin `.env.example` menjadi `.env` dan ganti nilai password contoh dengan nilai lokal.
2. Build dan jalankan API serta MySQL:

   ```powershell
   docker compose up -d --build
   ```

3. Buat application key, migrasikan database, dan isi layanan awal serta pelanggan umum:

   ```powershell
   docker compose exec app php artisan key:generate
   docker compose up -d --force-recreate app
   docker compose exec app php artisan migrate --seed
   ```

4. Buat akun superadmin secara interaktif (akun ini mengelola dan memantau seluruh outlet):

   ```powershell
   docker compose exec app php artisan londrian:make-superadmin
   ```

5. Bila perlu, buat akun owner outlet awal secara interaktif:

   ```powershell
   docker compose exec app php artisan londrian:make-owner
   ```

6. API berjalan di `http://localhost:8020`; pemeriksaan Laravel tersedia di `http://localhost:8020/up`. MySQL dipetakan ke port `3308` agar tidak berbenturan dengan UDev. Port API dipublikasikan untuk akses LAN; agar dapat dipakai dari HP, set `FRONTEND_URL` dengan daftar origin dipisahkan koma, misalnya `http://localhost:5174,http://localhost:5175,http://192.168.1.40:5175`, lalu buat ulang service `app`. Pastikan komputer dan HP di jaringan Wi-Fi yang sama.

Hentikan service dengan `docker compose down`. Volume database tetap tersedia saat service dihentikan.

## Autentikasi

- Login memakai `POST /api/login` dengan username dan password minimal 8 karakter; respons menerbitkan personal access token Sanctum.
- Pendaftaran publik melalui `POST /api/register` membuat outlet `DEMO` tersendiri, akun owner, data layanan awal, dan token untuk langsung masuk. Masa demo berlaku dua hari; setelah kedaluwarsa akses diblokir sampai superadmin mengaktifkan outlet setelah pembayaran dikonfirmasi. Penagihan belum otomatis.
- Kirim token pada endpoint terlindungi melalui header `Authorization: Bearer <token>`. `POST /api/token` tetap tersedia untuk klien yang mengirim nama perangkat.
- Role adalah `SUPERADMIN`, `OWNER`, dan `KASIR`. Superadmin memilih outlet melalui `X-Outlet-ID` untuk mengakses modulnya. Data operasional dipisahkan menurut outlet.
- Superadmin melihat dan mengubah akses outlet melalui `/api/superadmin/outlets`; status yang dapat diatur adalah `AKTIF` dan `NONAKTIF`.
- Pengelolaan layanan, pengguna, pengaturan, laporan, pembatalan transaksi, dan void pembayaran tersedia untuk owner serta superadmin pada outlet aktif.
- Estimasi layanan minimal 72 jam (3 hari); seluruh layanan awal outlet baru memakai estimasi 72 jam.
- Pembuatan transaksi hanya mencatat pesanan. Pembayaran baru dapat dicatat setelah status laundry `SELESAI`; pengambilan mensyaratkan transaksi lunas.
- Transaksi menyimpan atau menautkan pelanggan menurut nomor HP. Promo Member diatur per outlet pada Pengaturan: aktif/nonaktif, jumlah transaksi, minimal subtotal, dan persentase diskon (bawaan 20%). Saat aktif, target yang tercapai memberi diskon sesuai persentase pengaturan pada transaksi berikutnya. Subtotal kelayakan dihitung sebelum diskon dan ongkir; transaksi penebus diskon menjadi progres pertama siklus selanjutnya bila memenuhi batas. Hanya transaksi aktif yang lunas dan diambil yang masuk hitungan. Persentase yang dipakai disimpan pada transaksi agar nota cetak ulang tetap sesuai.

## Kontrak API

Daftar endpoint, format transaksi, cursor scroll, dan aturan status ada di [docs/api.md](docs/api.md). API memakai JSON; validasi gagal mengembalikan HTTP 422 dan otorisasi gagal HTTP 403.

## Konfigurasi utama

`.env.example` berisi contoh lokal untuk `APP_KEY`, `APP_URL`, `FRONTEND_URL`, dan kredensial MySQL. Nilai password di dalamnya hanya contoh; jangan commit `.env` atau kredensial aktif.

Frontend Vite POS Londrian berjalan di port 5174 dan website publik outlet pada port 5175. `FRONTEND_URL` menerima satu atau beberapa origin CORS yang dipisahkan koma, tanpa kredensial cookie. Frontend POS mengakses API menggunakan Bearer token Sanctum. Website publik hanya membaca informasi outlet yang ditujukan bagi pelanggan; endpoint dan payload terperinci ada pada `docs/api.md`.

## Git dan release

Kerja harian selalu di branch `develop`; `main` adalah production. Setelah clone baru, pasang hook sekali:

```powershell
composer run hooks:install
```

Alur kerja harian:

```powershell
git switch develop
git pull --ff-only origin develop
# ubah kode, lalu jalankan pemeriksaan yang diperlukan
git add .
git commit -m "jelaskan perubahan"
git push origin develop
```

Untuk release backend jalankan `composer run release` (alias `composer run deploy`) dari `develop` setelah commit develop sudah di-push:

```powershell
git switch develop
git add .
git commit -m "jelaskan perubahan"
git push origin develop
composer run release
```

Script memeriksa working tree bersih, sinkronisasi `develop`, dan fast-forward aman dari `main`. Ia menjalankan `php artisan test` dengan SQLite in-memory di worktree sementara. Jika lolos, script mendorong hasil ke `main`; branch kerja tetap `develop`. Gunakan `composer run release -- -DryRun` untuk validasi tanpa push. Tidak ada Docker atau database aplikasi yang disentuh. Push manual ke branch produksi ditolak hook lokal.
