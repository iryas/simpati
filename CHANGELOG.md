# Changelog — SIMPATI (KahfiNet ISP Management)

Semua perubahan signifikan pada aplikasi ini didokumentasikan di sini.
Format mengikuti [Keep a Changelog](https://keepachangelog.com/id/1.0.0/).

---

## [1.9.0] — 2026-07-19

### Ditambahkan — Portal Pelanggan (PWA) 🎉
- **Portal Pelanggan** baru di folder `/portal/` — area terpisah, mobile-first, bisa di-*install* ke home screen (PWA: `manifest.php` + `sw.js` + ikon).
- **Banner "Pasang Aplikasi"** (`_pwa_install.php`) di halaman login & seluruh halaman portal: tombol install otomatis (Android/`beforeinstallprompt`), panduan *Add to Home Screen* (iPhone), deteksi in-app browser WhatsApp dengan arahan "buka di Chrome". Service worker & meta iOS ditambahkan ke `login.php` agar installable sejak layar pertama.
- **Login pelanggan**: No HP + PIN (6 digit), sesi terpisah dari staf, proteksi brute-force (reuse lockout).
- **Beranda**: ringkasan tagihan periode berjalan, status langganan, paket, pemakaian bulan ini, menu cepat.
- **Tagihan**: total harus dibayar (tagihan berjalan + tunggakan), rincian per periode, tombol konfirmasi bayar ke kasir via WhatsApp.
- **Riwayat pembayaran**: dikelompokkan per tahun + halaman **struk** bukti bayar (dengan cek kepemilikan).
- **Pemakaian bandwidth**: data `usage_pppoe` per periode + riwayat 6 bulan (bar chart CSS, tanpa dependensi JS).
- **Lapor gangguan**: pelanggan buat tiket (kategori + deskripsi), lihat status & tanggapan; batas 3 tiket aktif.
- **Akun**: detail langganan + ganti PIN sendiri.

### Ditambahkan — Sisi Admin
- **Modul Tiket Gangguan** (`modules/tiket/`): daftar + filter status, ringkasan per status, modal tanggapi (ubah status + balasan), opsi kirim update ke pelanggan via WhatsApp. Menu grup **LAYANAN** untuk Admin & Teknisi dengan badge jumlah tiket baru.
- **Set/Reset PIN Portal** di modal Detail Pelanggan (Admin): generate PIN 6 digit acak, tersimpan ter-hash (`password_hash`), opsi kirim PIN ke pelanggan via WhatsApp.

### Basis Data
- Migrasi **014**: kolom `pin`, `pin_updated_at`, `portal_last_login` pada tabel `pelanggan`.
- Migrasi **015**: tabel `tiket_gangguan`.

### Keamanan
- PIN pelanggan disimpan ter-hash, tidak pernah dikirim ke klien (`get_detail` disanitasi).
- Struk & data pembayaran di portal selalu diverifikasi kepemilikannya terhadap pelanggan yang login.

---

## [1.3.0] — 2026-07-05

### Ditambahkan
- **Halaman Tentang Aplikasi**: menampilkan info versi dan riwayat perubahan per versi
- **Role-based access control (RBAC)** diperketat — setiap role hanya bisa akses menu sesuai tugasnya
- **Sidebar dinamis**: menu yang tampil menyesuaikan role pengguna yang login

### Perubahan Akses per Role
- **Admin**: akses penuh ke semua menu
- **Kasir**: Dashboard, Daftar Pelanggan (read-only), Pembayaran, Pengeluaran, Laporan, Tentang
- **Teknisi**: Dashboard, Daftar Pelanggan (read-only), Mikrotik, ACS, Tentang
- Pelanggan CRUD (tambah/edit/hapus/ubah status) dibatasi hanya Admin
- Paket Internet dan Data Area dibatasi hanya Admin
- Mikrotik & ACS: Teknisi bisa akses sync & lihat data, tapi pengaturan koneksi hanya Admin
- Label sidebar "JARINGAN" ditambahkan untuk grup Mikrotik & ACS

---

## [1.2.0] — 2026-07-05

### Ditambahkan
- **Module Pengaturan**: setting nama ISP dan tanggal mulai tagihan bulanan
- **Tabel `app_settings`** (migrasi 002): penyimpanan konfigurasi aplikasi berbasis key-value
- **Helper `app_setting()`**: baca setting dari DB dengan static cache
- **Helper `label_periode_tagihan()`**: menghasilkan label periode "20 Jun – 19 Jul 2026" secara konsisten
- **Label periode tagihan** ditampilkan di header halaman Pembayaran dan widget Dashboard
- **Blokir generate tagihan** sebelum tanggal yang ditentukan di Pengaturan
- **Auto-fill `tgl_jatuh_tempo`** saat generate tagihan berdasarkan setting tanggal mulai
- **DataTables server-side** untuk Mikrotik PPP Secret (lengkap dengan pencarian & pagination)
- **DataTables server-side** untuk ACS Device ONU (lengkap dengan pencarian & pagination)
- **CSS pagination DataTables** diseragamkan secara global di `assets/css/style.css`
- **Summary card** di halaman Pembayaran: Lunas + Belum Lunas

### Diperbaiki
- Format label periode tidak konsisten ("Jun 2026 – 19 Jul 2026") — sekarang selalu tampil hari mulai: "20 Jun – 19 Jul 2026"
- Layout form Pengaturan yang berantakan diganti dengan `d-flex align-items-center`
- Nama bulan Tunggakan tampil dalam bahasa Inggris (May, Jun) — diganti ke Indonesia (Mei, Jun)

---

## [1.1.0] — 2026-06-27

### Ditambahkan
- **Label status baru**: "Belum Lunas" untuk tagihan bulan berjalan, "Tunggakan" untuk tagihan bulan lampau
- **Helper `badge_status()`** diperbarui dengan parameter opsional `$bulan_tagihan` untuk membedakan label
- **Dashboard widget 3-tab**: Terbaru | Belum Lunas | Tunggakan
- **Tab Tunggakan** dikelompokkan per pelanggan menggunakan `rowspan`, menampilkan total tunggakan per pelanggan
- **Select2** pada dropdown pelanggan di form modal "Catat Pembayaran" (dengan `dropdownParent` untuk z-index modal)
- **Hitung total tunggakan** memperhitungkan potongan: `jumlah - (jumlah/30 * potongan)`

### Diperbaiki
- **Bug pembayaran manual status "Belum"**: `tgl_bayar` dan `kasir_id` tidak lagi diisi saat status Belum Lunas
- **Total tunggakan salah**: sebelumnya menjumlah `jumlah` mentah tanpa memperhitungkan potongan

---

## [1.0.0] — 2026-06-01

### Rilis Awal

#### Autentikasi & Keamanan
- Login dengan proteksi brute force (maks. 5 percobaan, lockout 15 menit)
- Fitur Remember Me (cookie 30 hari)
- CSRF protection pada semua form
- Role-based access control: Admin, Kasir, Teknisi
- Enkripsi password PPPoE

#### Module Dashboard
- Stat card: Total Pelanggan, Aktif, Isolir, Pendapatan Bulan Ini
- Widget Pembayaran Terbaru
- Widget Tunggakan

#### Module Pelanggan
- CRUD pelanggan lengkap
- Status: Aktif, Nonaktif, Isolir
- DataTables server-side dengan pencarian dan filter status
- Mapping ke PPP Secret Mikrotik

#### Module Paket
- CRUD paket internet (nama, harga, kecepatan)
- DataTables server-side

#### Module Area
- CRUD area layanan
- DataTables server-side

#### Module Pembayaran
- Generate tagihan massal untuk semua pelanggan aktif
- Catat pembayaran manual
- Bayar tagihan dengan potongan (hari)
- Bayar massal & potongan massal
- DataTables server-side dengan filter bulan dan status

#### Module Laporan
- Laporan pendapatan bulanan
- Laporan keuntungan (pendapatan - pengeluaran)

#### Module Pengeluaran
- CRUD pengeluaran operasional

#### Module Pengguna
- CRUD akun pengguna (Admin, Kasir, Teknisi)

#### Module Mikrotik
- Konfigurasi koneksi API Mikrotik
- Tes koneksi router
- Sync PPP Profile dari Mikrotik
- Sync PPP Secret dari Mikrotik (mirror read-only)

#### Module ACS (GenieACS)
- Konfigurasi koneksi GenieACS
- Tes koneksi ACS
- Sync Device ONU dari GenieACS (mirror read-only)
- Mapping manual Device ONU ke PPP Secret

#### Infrastruktur
- Sistem migrasi database (CLI runner `migrasi/run.php`)
- Helper functions: `rupiah()`, `tgl_indo()`, `clean()`, `flash()`, `redirect()`, `paginate()`, dll.
- Bootstrap 4 + FontAwesome + Select2 + DataTables + Toastr
- Dark sidebar layout dengan collapsible menu
