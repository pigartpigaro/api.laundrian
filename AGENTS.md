# Aturan Backend Londrian

Backend memakai PHP 8.2, Laravel 12, Sanctum, dan MySQL 8.4. Perintah Docker Compose dijalankan dari folder `api.laundrian/`; API menggunakan port host 8020 dan MySQL 3308 agar tidak berbenturan dengan UDev.

- Ikuti `D:\APP\Docker\AGENTS.md`, `../AGENTS.md`, dan blueprint `../laundry-pos-blueprint-v1.md`.
- Kelompokkan controller, request/service, route, dan dokumentasi menurut menu: autentikasi, layanan, pelanggan, transaksi/pembayaran, pengeluaran, laporan, dan pengaturan.
- Backend menjadi sumber kebenaran untuk transaksi, total, pembayaran, nomor transaksi, role, serta perpindahan status.
- Autentikasi memakai username dan personal access token Sanctum melalui Bearer; kata sandi baru minimal 8 karakter. Role yang berlaku: `SUPERADMIN`, `OWNER`, dan `KASIR`.
- Pendaftaran publik membuat satu outlet terpisah berstatus `DEMO`; semua data bisnis harus dibatasi per outlet. Superadmin mengakses outlet terpilih melalui `X-Outlet-ID`.
- Outlet demo berlaku dua hari sejak pendaftaran. Akses kedaluwarsa diblokir sampai superadmin mengaktifkan outlet secara manual setelah pembayaran dikonfirmasi; pembayaran bulanan belum diotomatisasi. Promo Member diatur per outlet (status, target, minimal subtotal, dan diskon persen dengan bawaan 20%); backend menghitung progres dan menyimpan nilai diskon yang dipakai pada transaksi.
- Validasi input dan otorisasi setiap operasi di server. Operasi owner-only wajib dibatasi middleware backend.
- Simpan transaksi, perubahan status, dan pembayaran secara auditabel. Gunakan pembatalan/void, bukan penghapusan finansial permanen.
- Simpan rahasia hanya di `.env`; `.env.example` hanya berisi nilai contoh lokal.
- Perbarui `README.md` dan `docs/api.md` jika setup, konfigurasi, endpoint, atau format request/response berubah.
- Jangan menyatakan backend siap operasional sebelum dependency, migrasi, akun owner, dan pemeriksaan dasar disiapkan.

## Setup lokal dari clone bersih

```powershell
Copy-Item .env.example .env
# Ganti DB_PASSWORD dan DB_ROOT_PASSWORD di .env dengan nilai lokal.
docker compose up -d --build
docker compose exec app php artisan key:generate
docker compose up -d --force-recreate app
docker compose exec app php artisan migrate --seed
docker compose exec app php artisan londrian:make-superadmin
docker compose exec app php artisan londrian:make-owner
```

API: `http://localhost:8020`; health check: `http://localhost:8020/up`. Hentikan dengan `docker compose down`.

## Git dan release

- Kerjakan perubahan pada `develop`; `main` adalah production. `master` adalah branch lama yang tetap diproteksi sampai penghapusannya selesai di GitHub.
- Aktifkan hook sekali pada clone baru: `composer run hooks:install`. Hook menolak commit dan push manual ke `main`/`master`, namun mengizinkan push `main` dari script release tervalidasi dan push biasa ke `develop`. Penghapusan branch lama `master` hanya diizinkan dengan variabel satu kali `LAUNDRIAN_ALLOW_DELETE_LEGACY_MASTER=1` setelah default branch GitHub sudah `main`.
- Jalankan `composer run release` (alias `composer run deploy`) dari branch `develop`. Script mensyaratkan working tree bersih, branch sinkron, dan fast-forward yang aman; ia membuat APP_KEY sementara pada `.env` di worktree, lalu menjalankan `php artisan test` dengan SQLite in-memory dan mendorong hasil ke `main` jika lolos. Tidak menjalankan Docker atau database aplikasi dan branch kerja tetap `develop`. Gunakan `composer run release -- -DryRun` untuk validasi tanpa push.
- Push manual ke `main`/`master` ditolak hook lokal; hanya push `main` dari script release yang diizinkan. Script tidak force push dan berhenti bila production divergen. Jangan menghapus `master` otomatis.
