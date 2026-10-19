# Kontrak API Londrian V1

Semua endpoint operasional memakai prefix `/api`. Respons memakai JSON UTF-8. Daftar data memakai cursor pagination agar frontend dapat memuat lebih banyak saat pengguna menggulir tanpa nomor halaman.

## Autentikasi, pendaftaran, dan role

| Method | Endpoint | Akses | Tujuan |
|---|---|---|---|
| POST | `/api/register` | Publik, 3 percobaan/menit/IP | Daftarkan outlet DEMO dan akun owner; mengembalikan token Bearer |
| POST | `/api/login` | Publik, 5 percobaan/menit/username+IP | Login dan menerbitkan token Bearer |
| POST | `/api/token` | Publik, 5 percobaan/menit/username+IP | Menerbitkan token dengan nama perangkat |
| POST | `/api/logout` | Bearer | Keluar dan mencabut token yang digunakan |
| GET | `/api/me` | Bearer | User aktif |
| GET | `/api/superadmin/outlets` | SUPERADMIN | Pantau outlet yang terdaftar |
| PUT | `/api/superadmin/outlets/{id}` | SUPERADMIN | Aktifkan atau nonaktifkan akses outlet |

Role: `SUPERADMIN`, `OWNER`, dan `KASIR`. Pendaftaran publik membuat satu outlet terpisah berstatus `DEMO` dan satu akun `OWNER`; masa demo berakhir dua hari setelah pendaftaran. Setelah demo berakhir, outlet diblokir sampai superadmin mengaktifkannya setelah pembayaran dikonfirmasi. Penagihan belum otomatis. Superadmin mengubah status ke `AKTIF` atau `NONAKTIF` dengan `PUT /api/superadmin/outlets/{id}` dan payload `{ "status": "AKTIF" }`. Backend menolak user nonaktif dan outlet nonaktif atau demo kedaluwarsa. Owner-only mencakup laporan, pengaturan, karyawan, pengelolaan layanan, pembatalan transaksi, dan void pembayaran; superadmin juga dapat memilih outlet melalui header `X-Outlet-ID` untuk memeriksa atau mengoperasikan fiturnya. Endpoint logout tetap tersedia untuk akun yang akses outletnya sudah kedaluwarsa.

Login mengirim `{ "username", "password", "device_name"? }`. Username dinormalisasi menjadi huruf kecil dan password minimal 8 karakter. Login dan pendaftaran mengembalikan `{ "data": { "token", "token_type": "Bearer", "user" } }`. Untuk seluruh endpoint terlindungi, kirim `Authorization: Bearer <token>`. Frontend menyimpan token pada `sessionStorage` dan mengirim header tersebut melalui Axios. Logout mencabut token aktif. `/api/token` tetap tersedia untuk klien yang menentukan `device_name`.

Pendaftaran mengirim `{ "outlet_name", "name", "username", "password", "password_confirmation" }`; username harus unik dan password minimal 8 karakter. Setiap pendaftaran membuat data awal layanan dan pelanggan umum yang terikat ke outlet, lalu menerbitkan token Bearer untuk akun owner. Seluruh data operasional dibatasi per outlet. Akun superadmin dibuat dari server dengan `php artisan londrian:make-superadmin`, tidak melalui pendaftaran publik.

## Dashboard dan master

| Method | Endpoint | Akses | Tujuan |
|---|---|---|---|
| GET | `/api/dashboard` | Bearer | Ringkasan hari ini, beban kerja, piutang, laundry terlambat |
| GET/POST | `/api/layanan` | Login / Owner | Daftar layanan / tambah layanan |
| PUT | `/api/layanan/{id}` | Owner | Ubah harga, satuan, estimasi, atau status aktif |

Estimasi layanan dikirim dalam jam dan minimal 72 jam (3 hari). Layanan bawaan outlet baru memakai estimasi 72 jam.
| GET/POST | `/api/pelanggan` | Bearer | Daftar pelanggan / tambah pelanggan |
| PUT | `/api/pelanggan/{id}` | Bearer | Ubah data atau status aktif pelanggan |

Layanan memakai satuan `KG`, `PCS`, `PASANG`, atau `METER`. Daftar pelanggan menerima `q`, `per_page`, dan `cursor`.

## Transaksi dan pembayaran

| Method | Endpoint | Akses | Tujuan |
|---|---|---|---|
| GET/POST | `/api/transaksi` | Bearer | Daftar dengan filter / buat transaksi |
| GET | `/api/transaksi/{id}` | Bearer | Detail transaksi, rincian, pembayaran, dan histori status |
| POST | `/api/transaksi/{id}/proses` | Bearer | `MASUK` ke `PROSES` |
| POST | `/api/transaksi/{id}/selesai` | Bearer | `PROSES` ke `SELESAI` |
| POST | `/api/transaksi/{id}/diambil` | Bearer | `SELESAI` ke `DIAMBIL`; harus lunas |
| POST | `/api/transaksi/{id}/batal` | Owner | Batalkan dengan alasan |
| POST | `/api/transaksi/{id}/koreksi-status` | Owner | Koreksi status dengan alasan |
| POST | `/api/transaksi/{id}/pembayaran` | Bearer | Catat pembayaran setelah laundry berstatus `SELESAI` |
| POST | `/api/pembayaran/{id}/void` | Owner | Void pembayaran dengan alasan |

Contoh membuat transaksi:

```json
{
  "pelanggan_id": 1,
  "rincian": [
    { "layanan_id": 1, "qty": 4.5 },
    { "layanan_id": 4, "qty": 1 }
  ],
  "diskon": 1500,
  "ongkir": 0,
  "catatan": ""
}
```

Atau kirim `nama_pelanggan` dan `no_hp` tanpa `pelanggan_id`. Backend menautkan pelanggan berdasarkan nomor HP dalam outlet aktif atau membuat rekam pelanggan baru agar dapat dipakai kembali. Transaksi baru hanya mencatat pesanan dan selalu dimulai dengan status bayar `BELUM BAYAR`; pembayaran tidak diterima saat pembuatan transaksi. Pembayaran dicatat kemudian melalui endpoint pembayaran setelah laundry berstatus `SELESAI`. Laundry hanya dapat diserahkan (`DIAMBIL`) setelah tagihan lunas. Untuk tunai, kasir boleh memasukkan uang diterima lebih besar dari sisa tagihan; frontend membukukan hanya sisa tagihan dan mencatat uang diterima serta kembalian di `keterangan`. Transfer dan QRIS tidak boleh melebihi sisa tagihan. Harga dan subtotal rincian diambil dari layanan aktif di database; frontend tidak mengirim harga. Nomor nota dibuat backend dengan format `LDY-YYMMDD-NNN`. Status bayar dihitung dari seluruh pembayaran non-void: `BELUM BAYAR`, `DP`, atau `LUNAS`. Status laundry terpisah: `MASUK`, `PROSES`, `SELESAI`, `DIAMBIL`.

Promo Member dikonfigurasi per outlet melalui `GET/PUT /api/pengaturan` dengan `member_promo_active` (boolean), `member_promo_required_transactions` (1–100), `member_promo_minimum_subtotal` (rupiah), dan `member_promo_discount_percent` (1–100). Nilai bawaan: nonaktif, 10 transaksi, Rp75.000, dan diskon 20%. Saat promo aktif, progres dihitung dari transaksi aktif yang subtotalnya saat dibuat memenuhi batas, lalu sudah lunas dan diambil. Setelah target tercapai, transaksi berikutnya otomatis mendapat diskon sesuai persentase tersimpan dari subtotal setelah diskon manual; ongkir tidak didiskon. Backend mencatat nominal dan persentase diskon member pada transaksi agar nota lama tetap akurat. Transaksi penebus menjadi progres pertama siklus selanjutnya bila subtotalnya memenuhi batas; progres itu masuk setelah transaksi lunas dan diambil. Pembatalan transaksi tidak menambah progres. Rekam pelanggan mengirim `member_eligible_count`, `member_promo_active`, `member_promo_required_transactions`, `member_promo_minimum_subtotal`, dan `member_promo_discount_percent`.

Daftar transaksi menerima `q`, `status_laundry`, `status_bayar`, `status_transaksi`, `from`, `to`, `per_page`, dan `cursor`. Penghapusan transaksi/pembayaran permanen tidak disediakan.

## Pengeluaran, laporan, pengaturan, dan karyawan

| Method | Endpoint | Akses | Tujuan |
|---|---|---|---|
| GET/POST | `/api/pengeluaran` | Bearer | Daftar dengan rentang tanggal / catat pengeluaran |
| GET | `/api/laporan/penjualan` | Owner | Ringkasan penjualan periode, rincian per transaksi, dan rekap harian |
| GET | `/api/laporan/pembayaran` | Owner | Ringkasan menurut metode/tanggal dan rincian setiap pembayaran |
| GET | `/api/laporan/piutang` | Owner | Ringkasan dan daftar transaksi yang belum lunas |
| GET | `/api/laporan/pengeluaran` | Owner | Ringkasan kategori dan rincian setiap pengeluaran |
| GET | `/api/laporan/laba-rugi` | Owner | Ringkasan laba bersih, transaksi pendapatan, dan rincian pengeluaran |
| GET | `/api/laporan/layanan-terlaris` | Owner | Peringkat layanan dengan kuantitas dan nilai penjualan tiap layanan |
| GET/PUT | `/api/pengaturan` | Owner | Profil outlet dan footer/ukuran nota |
| GET | `/api/public/outlets/{id}/logo` | Publik | Menampilkan logo outlet jika tersedia |
| GET | `/api/public/outlets/{id}/website` | Publik | Profil outlet dan layanan aktif untuk website; hanya saat outlet aktif dan publikasi website dinyalakan |
| GET/POST | `/api/karyawan` | Owner | Daftar atau buat user |
| PUT | `/api/karyawan/{id}` | Owner | Ubah user, role, status aktif, atau password |

Profil outlet menerima unggahan `logo` sebagai JPG, PNG, atau WebP maksimal 2 MB pada `PUT /api/pengaturan` (multipart form, gunakan `_method=PUT`). Logo ditampilkan lewat endpoint publik outlet dan file disimpan pada disk `public` backend.

Website outlet dikelola dari submenu **Pengaturan → Website laundry**. `PUT /api/pengaturan` menyimpan status tayang; judul dan deskripsi hero; tema dasar kunyit terang serta warna primer dan sekunder (format `#RRGGBB`, default `#d4a600` dan `#fff0b8`); isi dan visibilitas bagian layanan, tentang, galeri, dan kontak; pesan serta label WhatsApp; tautan peta dan media sosial; jam operasional; dan metadata SEO. Kartu layanan website diatur mandiri sebagai konten pemasaran, terpisah dari master tarif transaksi.

Endpoint website publik mengirim konfigurasi yang diperlukan oleh halaman outlet, tanpa data pelanggan atau transaksi. Foto utama menggunakan field `website_hero_image`; galeri menerima sampai empat `website_gallery_images[]`. Berkas JPG, PNG, atau WebP dibatasi 5 MB per gambar dan dikirim memakai multipart POST dengan `_method=PUT`. Foto galeri yang sudah ada dapat dihapus dengan mengirim indeksnya pada `website_gallery_remove[]`. Foto disajikan oleh `GET /api/public/outlets/{outlet}/website-media/{filename}`.

Jam operasional dikirim sebagai `website_hours_active` dan array `website_hours[day][closed|open|close]`, dengan ID hari berbahasa Inggris huruf kecil (`monday` sampai `sunday`) dan jam `HH:mm`. Metadata SEO meliputi `website_seo_title` (maksimal 70 karakter), `website_seo_description` (maksimal 160 karakter), dan `website_search_index`. Pengaturan metadata tidak menjamin indeks atau peringkat pencarian; website harus tersedia di domain publik agar crawler dapat mengaksesnya.

Endpoint laporan menerima `from` dan `to` dengan format `YYYY-MM-DD`. Daftar menggunakan bentuk cursor pagination Laravel (`data`, `next_page_url`, `next_cursor`, `prev_cursor`, `per_page`).

## Kesalahan

- `401`: Bearer token tidak ada, tidak valid, atau sudah dicabut.
- `403`: user nonaktif atau role tidak berhak.
- `422`: validasi input atau transisi bisnis tidak valid; respons berisi `message` dan `errors`.
- `404`: resource tidak ditemukan.

Tata letak hero website memakai `website_hero_layout`: `horizontal` menampilkan foto penuh dengan teks di atasnya, sedangkan `vertical` menempatkan teks di samping foto. Nilai bawaan adalah `horizontal`.

Kartu layanan website dikirim sebagai JSON pada `website_services`, berisi paling banyak 30 objek `{name, description, icon}`. `name` wajib (maksimal 80 karakter), `description` opsional (maksimal 400 karakter), dan `icon` salah satu dari `shirt`, `person`, `shoes`, `bedding`, `bag`, atau `iron`. Judul bagian memakai `website_services_title` dan keterangan pengantar memakai `website_services_intro` (maksimal 240 karakter). Endpoint publik mengembalikan konten ini sebagai `services_title`, `services_intro`, dan `services`. Kartu ini tidak mengambil harga, satuan, atau estimasi dari master layanan transaksi.

Pengaturan footer website menyimpan logo identitas outlet (`website_footer_logo`, maksimal 2 MB), pilihan susunan `website_footer_logo_mode` (`logo_tagline`, `logo`, atau `tagline`), dan tinggi logo 32–240 px melalui `website_footer_logo_size`. Logo lama dihapus dengan `website_clear_footer_logo`. Footer juga menyimpan tagline, judul/teks/tautan dukungan Laundrian, judul kolom kontak, serta sakelar kolom dukungan/kontak. Logo pembayaran diunggah sebagai `website_footer_payment_logos[]` (maksimal enam berkas JPG/PNG/WebP, 2 MB per berkas); logo lama dihapus dengan indeks pada `website_footer_payment_logo_remove[]`. Endpoint publik mengembalikan URL logo pembayaran sebagai `footer_payment_logos: [{name, url}]` dan logo footer sebagai `footer_logo_url` bersama kontak outlet.
