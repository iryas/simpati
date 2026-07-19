<?php
// ============================================================
//  KAHFINET - Tentang Aplikasi & Riwayat Versi
// ============================================================
require_once __DIR__ . '/../../system/init.php';
auth_check();

$page_title  = 'Tentang Aplikasi';
$active_menu = 'tentang';

$changelog = [
    [
        'versi'    => '1.9.0',
        'tanggal'  => '19 Jul 2026',
        'label'    => 'Portal Pelanggan (PWA)',
        'warna'    => 'primary',
        'sections' => [
            'Ditambahkan — Portal Pelanggan' => [
                'Portal Pelanggan baru (/portal/): area khusus pelanggan, tampilan mobile-first, bisa di-install ke home screen HP layaknya aplikasi (PWA)',
                'Banner "Pasang Aplikasi" di portal: tombol install otomatis di Android, panduan Add to Home Screen untuk iPhone, dan arahan "buka di Chrome" bila dibuka dari dalam WhatsApp',
                'Login pelanggan dengan Nomor HP + PIN (6 digit), sesi terpisah dari staf, dengan proteksi brute-force',
                'Beranda: ringkasan tagihan periode berjalan, status langganan, paket, dan pemakaian bulan ini',
                'Tagihan: total harus dibayar (tagihan berjalan + tunggakan), rincian per periode, tombol konfirmasi bayar ke kasir via WhatsApp',
                'Riwayat pembayaran dikelompokkan per tahun + halaman struk bukti bayar (diverifikasi kepemilikannya)',
                'Pemakaian bandwidth: data terpakai per periode berjalan + grafik 6 bulan terakhir',
                'Lapor gangguan: pelanggan membuat tiket keluhan dan memantau status serta tanggapan petugas',
                'Akun: lihat detail langganan dan ganti PIN sendiri',
            ],
            'Ditambahkan — Sisi Admin' => [
                'Modul Tiket Gangguan (grup menu LAYANAN, untuk Admin & Teknisi): daftar & filter status, ringkasan per status, tanggapi keluhan (ubah status + balasan), opsi kirim update ke pelanggan via WhatsApp, badge jumlah tiket baru di sidebar',
                'Set/Reset PIN Portal di detail pelanggan: generate PIN 6 digit acak (tersimpan ter-hash), ditampilkan sekali dengan tombol Salin, opsi kirim PIN ke pelanggan via WhatsApp',
            ],
            'Basis Data' => [
                'Migrasi 014: kolom pin, pin_updated_at, dan portal_last_login pada tabel pelanggan',
                'Migrasi 015: tabel tiket_gangguan',
            ],
            'Keamanan' => [
                'PIN pelanggan disimpan ter-hash (password_hash) dan tidak pernah dikirim kembali ke klien',
                'Struk & data pembayaran di portal selalu diverifikasi kepemilikannya terhadap pelanggan yang login',
            ],
            'Diperbaiki' => [
                'Kartu "Tagihan periode ini" di portal kini mengikuti tagihan periode terbaru pelanggan, agar tanggal bayar & status konsisten dengan tampilan admin (sebelumnya bisa berbeda saat ada tagihan periode berikutnya yang sudah dibayar)',
            ],
        ],
    ],
    [
        'versi'    => '1.8.0',
        'tanggal'  => '14 Jul 2026',
        'label'    => 'WA Background Queue & Peningkatan Topbar',
        'warna'    => 'success',
        'sections' => [
            'Ditambahkan' => [
                'Sistem antrian WA berbasis database (tabel wa_queue): semua pengiriman WA diproses di belakang layar',
                'CLI Worker (php worker.php wa:work): proses antrian WA secara background dari terminal, kompatibel Windows/Mac/Linux',
                'Jeda pengiriman random 10–30 detik antar pesan untuk mengurangi risiko pemblokiran nomor WA',
                'Retry otomatis maksimal 3x dengan interval meningkat: gagal ke-1 → 5 menit, gagal ke-2 → 15 menit',
                'Reset job stuck otomatis saat worker startup (job processing > 5 menit dikembalikan ke pending)',
                'Perintah wa:status (lihat statistik antrian) dan wa:reset (kembalikan job gagal ke pending) via terminal',
                'Dashboard Admin: card peringatan WA gagal permanen dengan tabel detail (pelanggan, nomor, tipe, error, waktu)',
                'Tombol "Reset Semua ke Pending" di dashboard untuk coba ulang pengiriman yang gagal via AJAX',
                'Topbar: jam real-time yang diperbarui setiap detik',
                'Topbar: dropdown profil dengan nama pengguna, badge role, ganti password, dan tombol logout',
                'Topbar: indikator status koneksi Mikrotik dan ACS (dot hijau/merah, polling setiap 60 detik) — hanya Admin & Teknisi',
                'Fitur Ganti Password langsung dari topbar tanpa perlu ke halaman pengaturan pengguna',
                'Kategori Transport di modul Pengeluaran untuk pencatatan biaya perjalanan ke lokasi',
            ],
            'Perubahan' => [
                'Konfirmasi pembayaran (bayar): pengiriman WA berubah dari langsung ke antrian queue',
                'Tombol Kirim/Kirim Ulang WA di halaman Pembayaran: berubah dari kirim langsung ke antrian queue',
                'Aksi isolir satu-satu dan massal: pengiriman WA pemberitahuan melalui antrian queue',
            ],
        ],
    ],
    [
        'versi'    => '1.7.0',
        'tanggal'  => '09 Jul 2026',
        'label'    => 'Fonnte WA Gateway',
        'warna'    => 'success',
        'sections' => [
            'Ditambahkan' => [
                'Integrasi Fonnte WhatsApp Gateway sebagai alternatif Wablas: endpoint api.fonnte.com, header Authorization tunggal (tanpa secret key)',
                'Pilihan gateway di Pengaturan WA: Fonnte (rekomendasi) atau Wablas — bisa diganti kapan saja tanpa restart',
                'Fungsi kirim_wa() sebagai unified wrapper: secara otomatis routing ke gateway yang aktif',
                'Setting No. CS / WhatsApp Admin (placeholder {no_cs}) untuk dicantumkan di pesan isolir',
                'Placeholder {no_cs} ditambahkan ke template WA Isolir: info nomor konfirmasi pembayaran',
            ],
            'Perubahan' => [
                'Semua pemanggilan kirim_wa_wablas() diganti ke kirim_wa() — gateway dipilih dari Pengaturan, bukan hardcode di kode',
                'Form Pengaturan WA didesain ulang: satu form untuk semua gateway, JS toggle tampilkan bagian Fonnte atau Wablas sesuai pilihan',
                'Teks pesan isolir diperbaiki: "Setelah bayar, kabari kami..." → "Setelah transfer, silakan konfirmasi ke: {no_cs}"',
                'Script inline di halaman Pengaturan dipindah ke $extra_js agar tidak konflik dengan loading jQuery',
            ],
        ],
    ],
    [
        'versi'    => '1.6.0',
        'tanggal'  => '08 Jul 2026',
        'label'    => 'Modul Template WA',
        'warna'    => 'success',
        'sections' => [
            'Ditambahkan' => [
                'Modul Template WhatsApp: kelola isi pesan WA langsung dari UI tanpa edit kode',
                'Template "Bukti Pembayaran" — mendukung placeholder {nama_isp} dan {struk} (tabel monospace auto-generate)',
                'Template "Pemberitahuan Isolir" — placeholder {nama_isp}, {nama}, {paket}, {periode}, {jumlah}, info rekening BRI sudah terisi default',
                'Fungsi wa_render() dan wa_template() sebagai engine render template berbasis DB',
                'Kirim WA pemberitahuan isolir otomatis saat aksi isolir satu-satu maupun massal dijalankan',
            ],
            'Perubahan' => [
                'format_pesan_bukti_bayar() direfaktor: bagian header & footer diambil dari template DB, struk monospace tetap di-generate PHP',
            ],
        ],
    ],
    [
        'versi'    => '1.5.0',
        'tanggal'  => '07 Jul 2026',
        'label'    => 'Widget Isolir Dashboard',
        'warna'    => 'warning',
        'sections' => [
            'Ditambahkan' => [
                'Widget "Kandidat Isolir" di dashboard: muncul otomatis setelah grace period terlewati, tampil untuk Admin dan Keuangan',
                'Tombol isolir satu-satu dan isolir massal (checkbox + tombol "Isolir Semua Terpilih") — hanya Admin',
                'Cek konektivitas Mikrotik dan ACS sebelum aksi isolir; proses dibatalkan jika salah satu offline',
                'Aksi isolir: ubah PPP profile ke profile-Isolir di Mikrotik + reboot ONT via ACS + update status pelanggan ke isolir di DB',
                'Setting "Grace Period Isolir" di Pengaturan (default 3 hari setelah tgl mulai tagihan)',
            ],
        ],
    ],
    [
        'versi'    => '1.4.0',
        'tanggal'  => '06 Jul 2026',
        'label'    => 'WhatsApp Gateway & Perbaikan Data',
        'warna'    => 'success',
        'sections' => [
            'Ditambahkan' => [
                'Integrasi WhatsApp Gateway via Wablas: kirim bukti pembayaran otomatis ke pelanggan setelah kasir konfirmasi',
                'Format pesan WA monospace dengan alignment kolom rapi: No. Bayar, Tgl. Bayar, Pelanggan, Paket, Periode, Tagihan, Potongan, Total Bayar',
                'Pengaturan WA Gateway di modul Pengaturan: aktifkan/nonaktifkan, isi Token & Secret Key Wablas',
                'Log pengiriman WA (tabel wa_log): mencatat status terkirim/gagal, nomor tujuan, dan waktu kirim per transaksi pembayaran',
                'Kolom status WA di tabel Pembayaran: badge terkirim/gagal/belum dengan tombol kirim ulang',
                'Tombol kirim ulang WA via AJAX tanpa reload halaman',
                'Migrasi 005: sinkronisasi nomor HP pelanggan antar komputer',
            ],
            'Diperbaiki' => [
                'Bug jam pembayaran selalu tampil 00:00 di bukti WA — kolom tgl_bayar diubah dari DATE ke DATETIME (Migrasi 004)',
                'Nomor HP pelanggan diformat otomatis ke format 62xxx sebelum dikirim ke Wablas',
            ],
            'Perubahan' => [
                'Role "Kasir" diubah menjadi "Keuangan" di seluruh sistem — konstanta, label, query, dan data DB (Migrasi 006)',
                'ENUM kolom role di tabel pengguna diperbarui: admin, teknisi, keuangan',
            ],
        ],
    ],
    [
        'versi'    => '1.3.0',
        'tanggal'  => '05 Jul 2026',
        'label'    => 'Role-Based Access Control',
        'warna'    => 'primary',
        'sections' => [
            'Ditambahkan' => [
                'Halaman Tentang Aplikasi dengan riwayat versi',
                'Sidebar dinamis: menu menyesuaikan role pengguna yang login',
                'Label sidebar "JARINGAN" untuk grup Mikrotik & ACS',
            ],
            'Perubahan Akses' => [
                'Admin: akses penuh ke semua menu',
                'Kasir: Dashboard, Daftar Pelanggan (read-only), Pembayaran, Pengeluaran, Laporan, Tentang',
                'Teknisi: Dashboard, Daftar Pelanggan (read-only), Mikrotik, ACS, Tentang',
                'Pelanggan CRUD dibatasi hanya Admin',
                'Paket Internet dan Data Area dibatasi hanya Admin',
                'Mikrotik & ACS: Teknisi bisa sync & lihat, pengaturan koneksi hanya Admin',
            ],
        ],
    ],
    [
        'versi'    => '1.2.0',
        'tanggal'  => '05 Jul 2026',
        'label'    => 'Pengaturan & DataTables',
        'warna'    => 'primary',
        'sections' => [
            'Ditambahkan' => [
                'Module Pengaturan: setting nama ISP dan tanggal mulai tagihan bulanan',
                'Tabel app_settings: penyimpanan konfigurasi berbasis key-value',
                'Label periode tagihan (contoh: "20 Jun – 19 Jul 2026") di halaman Pembayaran dan Dashboard',
                'Blokir generate tagihan sebelum tanggal yang ditentukan di Pengaturan',
                'Auto-fill tanggal jatuh tempo saat generate tagihan',
                'DataTables server-side untuk Mikrotik PPP Secret',
                'DataTables server-side untuk ACS Device ONU',
                'CSS pagination DataTables diseragamkan secara global',
                'Summary card Lunas + Belum Lunas di halaman Pembayaran',
            ],
            'Diperbaiki' => [
                'Format label periode tidak konsisten — sekarang selalu tampil hari mulai: "20 Jun – 19 Jul 2026"',
                'Layout form Pengaturan yang berantakan',
                'Nama bulan Tunggakan tampil bahasa Inggris — diganti ke Indonesia',
            ],
        ],
    ],
    [
        'versi'    => '1.1.0',
        'tanggal'  => '27 Jun 2026',
        'label'    => 'Penyempurnaan Pembayaran & Dashboard',
        'warna'    => 'success',
        'sections' => [
            'Ditambahkan' => [
                'Label status baru: "Belum Lunas" (bulan berjalan) dan "Tunggakan" (bulan lampau)',
                'Dashboard widget 3-tab: Terbaru | Belum Lunas | Tunggakan',
                'Tab Tunggakan dikelompokkan per pelanggan dengan total per pelanggan',
                'Select2 pada dropdown pelanggan di form modal Catat Pembayaran',
                'Perhitungan total tunggakan memperhitungkan potongan hari',
            ],
            'Diperbaiki' => [
                'Bug pembayaran manual status "Belum": tgl_bayar dan kasir tidak lagi terisi otomatis',
                'Total tunggakan salah — sebelumnya tidak memperhitungkan potongan',
            ],
        ],
    ],
    [
        'versi'    => '1.0.0',
        'tanggal'  => '01 Jun 2026',
        'label'    => 'Rilis Awal',
        'warna'    => 'secondary',
        'sections' => [
            'Module' => [
                'Dashboard: stat card pelanggan, pendapatan, widget pembayaran terbaru & tunggakan',
                'Pelanggan: CRUD lengkap, status aktif/nonaktif/isolir, mapping PPP Secret',
                'Paket: CRUD paket internet (nama, harga, kecepatan)',
                'Area: CRUD area layanan',
                'Pembayaran: generate massal, catat manual, bayar, potongan, bayar massal',
                'Laporan: pendapatan bulanan dan laporan keuntungan',
                'Pengeluaran: CRUD pengeluaran operasional',
                'Pengguna: manajemen akun Admin, Kasir, Teknisi',
                'Mikrotik: konfigurasi API, sync PPP Profile & Secret',
                'ACS: konfigurasi GenieACS, sync Device ONU, mapping manual ke PPP Secret',
            ],
            'Infrastruktur' => [
                'Autentikasi dengan proteksi brute force dan Remember Me',
                'CSRF protection pada semua form POST',
                'Role-based access control: Admin, Kasir, Teknisi',
                'Sistem migrasi database (CLI runner migrasi/run.php)',
                'DataTables server-side untuk Pelanggan, Paket, Area, Pembayaran',
                'Bootstrap 4 + FontAwesome + Select2 + DataTables + Toastr',
                'Dark sidebar layout dengan collapsible menu',
            ],
        ],
    ],
];

ob_start();
?>

<div class="page-header">
  <h5><i class="fas fa-info-circle mr-2 text-primary"></i>Tentang Aplikasi</h5>
</div>

<!-- Info Aplikasi -->
<div class="row mb-4">
  <div class="col-lg-5">
    <div class="card h-100">
      <div class="card-body d-flex flex-column justify-content-center" style="gap:12px">
        <div class="d-flex align-items-center" style="gap:16px">
          <div style="width:56px;height:56px;background:linear-gradient(135deg,#2563eb,#3b82f6);
                      border-radius:14px;display:flex;align-items:center;justify-content:center;
                      font-size:26px;color:#fff;flex-shrink:0">
            <i class="fas fa-network-wired"></i>
          </div>
          <div>
            <div class="font-weight-bold" style="font-size:20px;color:#1e293b"><?= APP_NAME ?></div>
            <div class="text-muted" style="font-size:13px">ISP Management System</div>
          </div>
        </div>
        <hr class="my-2">
        <table style="font-size:13px;width:100%">
          <tr>
            <td class="text-muted" style="width:130px;padding:4px 0">Versi</td>
            <td><span class="badge badge-primary" style="font-size:13px">v<?= APP_VERSION ?></span></td>
          </tr>
          <tr>
            <td class="text-muted" style="padding:4px 0">Nama Aplikasi</td>
            <td class="font-weight-bold"><?= clean(app_setting('nama_isp', 'KahfiNet')) ?></td>
          </tr>
          <tr>
            <td class="text-muted" style="padding:4px 0">Platform</td>
            <td>PHP <?= phpversion() ?></td>
          </tr>
          <tr>
            <td class="text-muted" style="padding:4px 0">Dikembangkan</td>
            <td>KahfiNet Dev Team</td>
          </tr>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- Riwayat Versi -->
<h6 class="font-weight-bold mb-3" style="font-size:15px;color:#1e293b">
  <i class="fas fa-history mr-2 text-primary"></i>Riwayat Perubahan
</h6>

<?php foreach ($changelog as $log): ?>
<div class="card mb-3">
  <div class="card-header" style="cursor:pointer" data-toggle="collapse"
       data-target="#log-<?= str_replace('.', '-', $log['versi']) ?>">
    <div class="d-flex align-items-center justify-content-between w-100">
      <div class="d-flex align-items-center" style="gap:10px">
        <span class="badge badge-<?= $log['warna'] ?>" style="font-size:13px">v<?= $log['versi'] ?></span>
        <span class="font-weight-bold" style="font-size:14px"><?= $log['label'] ?></span>
      </div>
      <div class="d-flex align-items-center" style="gap:12px">
        <small class="text-muted"><?= $log['tanggal'] ?></small>
        <i class="fas fa-chevron-down text-muted" style="font-size:12px"></i>
      </div>
    </div>
  </div>
  <div class="collapse <?= $log['versi'] === APP_VERSION ? 'show' : '' ?>"
       id="log-<?= str_replace('.', '-', $log['versi']) ?>">
    <div class="card-body py-3">
      <?php foreach ($log['sections'] as $judul => $items): ?>
        <div class="mb-3">
          <div class="font-weight-bold mb-2" style="font-size:12px;text-transform:uppercase;
               letter-spacing:.6px;color:#64748b"><?= $judul ?></div>
          <ul class="mb-0 pl-4" style="font-size:13.5px">
            <?php foreach ($items as $item): ?>
              <li class="mb-1"><?= clean($item) ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<?php endforeach; ?>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../../template.php';
