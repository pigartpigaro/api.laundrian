# Website outlet

Pemilik mengelola website publik per outlet melalui menu **Pengaturan → Website laundry** di frontend POS. Tab pengaturan memisahkan konten, tampilan dan foto, kontak dan jam operasional, serta SEO.

## Data yang dapat diatur

- Status publikasi, judul dan deskripsi pembuka, label CTA WhatsApp, dan pesan WhatsApp (placeholder `{nama}` diganti dengan nama outlet).
- Tampilkan/sembunyikan bagian layanan, harga, tentang, galeri, dan kontak; judul tiap bagian; deskripsi tentang/kontak; teks footer.
- Pilih sendiri layanan aktif yang ditampilkan di bagian Layanan website. Tidak ada jumlah pilihan maksimum; tautan navigasi Daftar harga terpisah sudah dihapus.
- Tema dasar kunyit terang dengan warna primer dan sekunder yang dapat disesuaikan. Default primer `#d4a600` dan sekunder `#fff0b8`.
- Tata letak foto utama: `horizontal` (default, foto penuh dengan teks di atasnya) atau `vertical` (teks di samping foto).
- Teks pembuka untuk ringkasan singkat; teks Tentang diisi sebagai cerita/profil yang berbeda. Website menyembunyikan paragraf Tentang jika isinya sama dengan pembuka.
- Bagian lokasi menampilkan alamat dan peta pencarian berdasarkan alamat. URL peta khusus tetap dipakai untuk tautan petunjuk arah.
- Footer memiliki tiga kolom: identitas outlet, dukungan Laundrian beserta maksimal enam logo pembayaran yang diterima outlet, serta kontak outlet. Pemilik dapat mengunggah logo footer terpisah dari logo profil, memilih susunan logo+tagline/logo/tagline, dan mengatur tinggi logonya dari 32 sampai 240 px. Alamat, telepon, WhatsApp, dan media sosial mengambil data profil/kontak outlet. Semua gambar diunggah sendiri oleh pemilik outlet.
- Foto hero dan maksimal empat foto galeri JPG, PNG, atau WebP, masing-masing maksimal 5 MB.
- Link Google Maps, Instagram, Facebook, dan jam buka per hari.
- Judul/description SEO dan izin indeks mesin pencari.

Nama outlet, logo, telepon, alamat, layanan aktif, dan harga tetap bersumber dari menu Profil outlet dan Layanan & harga. Data ditampilkan oleh endpoint publik hanya jika website diterbitkan dan outlet masih memiliki akses aktif.

## API dan penyimpanan

- `GET /api/pengaturan` mengembalikan nilai pengaturan outlet dan URL gambar.
- `POST /api/pengaturan` dengan `_method=PUT` menyimpan field teks, boolean, jam, serta unggahan foto. Backend memvalidasi setiap field dan menyimpan foto pada disk `public` di folder per outlet.
- Logo pembayaran menggunakan `website_footer_payment_logos[]` dan dihapus dengan indeks melalui `website_footer_payment_logo_remove[]`; nama file tanpa ekstensi menjadi teks alternatif.
- Logo identitas footer menggunakan `website_footer_logo`, susunannya diatur dengan `website_footer_logo_mode`, ukurannya dengan `website_footer_logo_size`, dan penghapusan menggunakan `website_clear_footer_logo`.
- `GET /api/public/outlets/{outlet}/website` mengirim data yang dibutuhkan situs publik. Tidak ada data pelanggan maupun transaksi.
- `GET /api/public/outlets/{outlet}/website-media/{filename}` mengirim foto hanya selama outlet aktif dan website diterbitkan.

Data konfigurasi menggunakan tabel `settings` yang sudah ada; tidak perlu migrasi baru. Pengaturan SEO hanya mengisi metadata runtime. Domain publik, kemampuan crawler membaca halaman, dan kualitas konten tetap diperlukan agar website bisa tampil di mesin pencari; indeksasi atau peringkat tidak dijamin.
