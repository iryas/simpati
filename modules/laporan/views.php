<?php
// ============================================================
//  KAHFINET - Modul Laporan > Pendapatan
// ============================================================
require_once __DIR__ . '/../../system/init.php';
auth_check();
auth_role([ROLE_ADMIN, ROLE_KEUANGAN]);

$tahun    = (int)get('tahun', date('Y'));
$bulan    = get('bulan', date('Y-m'));
$tipe     = get('tipe');
$paket_id = (int)get('paket_id', 0);
$metode   = get('metode', '');
$q        = trim(get('q', ''));

// Daftar paket untuk dropdown filter "Kategori Paket".
$daftar_paket = db_rows("SELECT id, nama FROM paket ORDER BY nama");

// Pendapatan per bulan (12 bulan tahun ini) — pakai bulan_tagihan (periode
// tagihan) + terbayar (uang yg beneran diterima), SAMA seperti kartu "Total
// Lunas" & tabel Detail Tagihan di bawah. Sebelumnya pakai tgl_bayar (tanggal
// dibayar) + jumlah (tagihan kotor), bikin grafik beda angka dari tabel di
// halaman yang sama untuk transaksi telat bayar / kena potongan.
$per_bulan = db_rows(
  "SELECT RIGHT(bulan_tagihan, 2) as bln,
            SUM(terbayar) as total,
            COUNT(*) as n
     FROM pembayaran
     WHERE status='lunas' AND LEFT(bulan_tagihan, 4) = ?
     GROUP BY bln
     ORDER BY bln ASC",
  [(string)$tahun]
);
$bulan_map = array_column($per_bulan, null, 'bln');

// Rekap bulan dipilih
$rekap = db_row(
  "SELECT COALESCE(SUM(CASE WHEN status='lunas' THEN terbayar ELSE 0 END),0) as total_lunas,
            COUNT(CASE WHEN status='lunas' THEN 1 END) as n_lunas,
            COUNT(CASE WHEN status='belum' THEN 1 END) as n_belum,
            COALESCE(SUM(CASE WHEN status='lunas' AND potongan = 0 THEN terbayar ELSE 0 END),0) as total_tanpa_potongan,
            COUNT(CASE WHEN status='lunas' AND potongan = 0 THEN 1 END) as n_tanpa_potongan,
            COALESCE(SUM(CASE WHEN status='lunas' AND potongan > 0 THEN terbayar ELSE 0 END),0) as total_dengan_potongan,
            COUNT(CASE WHEN status='lunas' AND potongan > 0 THEN 1 END) as n_dengan_potongan,
            COALESCE(SUM(CASE WHEN status='belum' THEN jumlah ELSE 0 END),0) as total_tunggakan,
            COUNT(CASE WHEN status='belum' THEN 1 END) as n_tunggakan
     FROM pembayaran WHERE bulan_tagihan = ?",
  [$bulan]
);

// Top 5 paket by pendapatan — konsisten pakai bulan_tagihan + terbayar.
$top_paket = db_rows(
  "SELECT pk.nama, COUNT(*) as n, SUM(py.terbayar) as total
     FROM pembayaran py
     JOIN paket pk ON pk.id = py.paket_id
     WHERE py.status='lunas' AND LEFT(py.bulan_tagihan, 4) = ?
     GROUP BY py.paket_id ORDER BY total DESC LIMIT 5",
  [(string)$tahun]
);

// Detail bulan dipilih (tanpa potongan/dengan potongan/tunggakan) kini
// ditampilkan lewat DataTable server-side — lihat modules/laporan/act.php
// case 'datatable_detail'. Filter Tahun/Bulan/Kategori Paket/Tipe/Metode
// di halaman ini cuma dikirim sebagai parameter AJAX ke situ.

$nama_bulan = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agt', 'Sep', 'Okt', 'Nov', 'Des'];

$page_title  = 'Laporan Pendapatan';
$active_menu = 'laporan_pendapatan';

ob_start();
?>

<div class="page-header">
  <h5><i class="fas fa-chart-bar mr-2 text-primary"></i>Laporan Pendapatan</h5>
  <a href="<?= BASE_URL ?>modules/laporan/act.php?action=export_csv&bulan=<?= urlencode($bulan) ?>&tipe=<?= urlencode($tipe ?? '') ?>&paket_id=<?= $paket_id ?>&metode=<?= urlencode($metode) ?>&q=<?= urlencode($q) ?>"
    class="btn btn-success btn-sm">
    <i class="fas fa-file-csv mr-1"></i>Export CSV
  </a>
</div>

<!-- Rekap Cards -->
<div class="row mb-4">
  <div class="col-md-4 mb-3">
    <div class="stat-card bg-success-grad">
      <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
      <div>
        <div class="stat-label">Total Lunas (<?= date('M Y', strtotime($bulan . '-01')) ?>)</div>
        <div class="stat-value" style="font-size:18px"><?= rupiah((int)$rekap['total_lunas']) ?></div>
      </div>
    </div>
  </div>
  <div class="col-md-4 mb-3">
    <div class="stat-card bg-primary-grad">
      <div class="stat-icon"><i class="fas fa-receipt"></i></div>
      <div>
        <div class="stat-label">Transaksi Lunas</div>
        <div class="stat-value"><?= $rekap['n_lunas'] ?></div>
      </div>
    </div>
  </div>
  <div class="col-md-4 mb-3">
    <div class="stat-card bg-danger-grad">
      <div class="stat-icon"><i class="fas fa-times-circle"></i></div>
      <div>
        <div class="stat-label">Belum Bayar</div>
        <div class="stat-value"><?= $rekap['n_belum'] ?></div>
      </div>
    </div>
  </div>
</div>

<!-- Rincian: Tanpa Potongan / Dengan Potongan / Tunggakan -->
<div class="row mb-4">
  <div class="col-md-4 mb-3">
    <div class="card h-100" style="border-left:4px solid #10b981">
      <div class="card-body py-3">
        <div class="text-muted" style="font-size:12px">Lunas Tanpa Potongan</div>
        <div class="font-weight-bold text-success" style="font-size:18px">
          <?= rupiah((int)$rekap['total_tanpa_potongan']) ?>
        </div>
        <small class="text-muted"><?= $rekap['n_tanpa_potongan'] ?> transaksi</small>
      </div>
    </div>
  </div>
  <div class="col-md-4 mb-3">
    <div class="card h-100" style="border-left:4px solid #f59e0b">
      <div class="card-body py-3">
        <div class="text-muted" style="font-size:12px">Lunas Dengan Potongan</div>
        <div class="font-weight-bold" style="font-size:18px;color:#f59e0b">
          <?= rupiah((int)$rekap['total_dengan_potongan']) ?>
        </div>
        <small class="text-muted"><?= $rekap['n_dengan_potongan'] ?> transaksi</small>
      </div>
    </div>
  </div>
  <div class="col-md-4 mb-3">
    <div class="card h-100" style="border-left:4px solid #ef4444">
      <div class="card-body py-3">
        <div class="text-muted" style="font-size:12px">Tunggakan</div>
        <div class="font-weight-bold text-danger" style="font-size:18px">
          <?= rupiah((int)$rekap['total_tunggakan']) ?>
        </div>
        <small class="text-muted"><?= $rekap['n_tunggakan'] ?> tagihan</small>
      </div>
    </div>
  </div>
</div>

<div class="row mb-4">
  <!-- Grafik Bar Pendapatan -->
  <div class="col-lg-8 mb-4">
    <div class="card h-100">
      <div class="card-header">
        <span><i class="fas fa-chart-bar mr-2 text-primary"></i>Pendapatan Tahun <?= $tahun ?></span>
      </div>
      <div class="card-body">
        <canvas id="chartPendapatan" height="220"></canvas>
      </div>
    </div>
  </div>

  <!-- Top Paket -->
  <div class="col-lg-4 mb-4">
    <div class="card h-100">
      <div class="card-header">
        <span><i class="fas fa-trophy mr-2 text-warning"></i>Top Paket Terlaris</span>
      </div>
      <div class="card-body p-0">
        <ul class="list-group list-group-flush">
          <?php if ($top_paket): foreach ($top_paket as $i => $tp): ?>
              <li class="list-group-item px-4 py-3">
                <div class="d-flex align-items-center">
                  <span class="badge badge-primary mr-3"><?= $i + 1 ?></span>
                  <div style="flex:1">
                    <div style="font-size:13.5px;font-weight:600"><?= clean($tp['nama']) ?></div>
                    <small class="text-muted"><?= $tp['n'] ?> transaksi</small>
                  </div>
                  <span class="text-success font-weight-bold" style="font-size:13px">
                    <?= rupiah((int)$tp['total']) ?>
                  </span>
                </div>
              </li>
            <?php endforeach;
          else: ?>
            <li class="list-group-item text-center text-muted py-4">Belum ada data.</li>
          <?php endif; ?>
        </ul>
      </div>
    </div>
  </div>
</div>

<!-- Filter (khusus tabel Detail Tagihan & Export CSV di bawah — tidak
     mempengaruhi kartu ringkasan/grafik di atas yang selalu 1 bulan/tahun
     penuh) -->
<div class="card mb-3">
  <div class="card-body py-2">
    <form method="GET" style="display:flex;flex-wrap:wrap;align-items:flex-end;gap:14px 16px">
      <div>
        <label class="d-block font-weight-bold mb-1" style="font-size:12px"><i class="fas fa-calendar-alt mr-1 text-primary"></i>Tahun</label>
        <input type="number" name="tahun" class="form-control form-control-sm"
          value="<?= $tahun ?>" min="2020" max="<?= date('Y') ?>" style="width:90px">
      </div>
      <div>
        <label class="d-block font-weight-bold mb-1" style="font-size:12px"><i class="fas fa-calendar-day mr-1 text-primary"></i>Bulan Detail</label>
        <input type="month" name="bulan" class="form-control form-control-sm" value="<?= $bulan ?>" style="width:150px">
      </div>
      <div>
        <label class="d-block font-weight-bold mb-1" style="font-size:12px"><i class="fas fa-search mr-1 text-primary"></i>Cari Pelanggan</label>
        <input type="text" name="q" class="form-control form-control-sm"
          placeholder="Nama / No HP…" value="<?= clean($q) ?>" style="width:170px">
      </div>
      <div>
        <label class="d-block font-weight-bold mb-1" style="font-size:12px"><i class="fas fa-box-open mr-1 text-primary"></i>Kategori Paket</label>
        <select name="paket_id" class="form-control form-control-sm" style="width:170px">
          <option value="0">Semua Paket</option>
          <?php foreach ($daftar_paket as $p): ?>
            <option value="<?= $p['id'] ?>" <?= $paket_id === (int)$p['id'] ? 'selected' : '' ?>><?= clean($p['nama']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="d-block font-weight-bold mb-1" style="font-size:12px"><i class="fas fa-tags mr-1 text-primary"></i>Tipe</label>
        <select name="tipe" class="form-control form-control-sm" style="width:170px">
          <option value="">Semua Tipe</option>
          <option value="tanpa_potongan" <?= $tipe === 'tanpa_potongan' ? 'selected' : '' ?>>Tanpa Potongan</option>
          <option value="dengan_potongan" <?= $tipe === 'dengan_potongan' ? 'selected' : '' ?>>Dengan Potongan</option>
          <option value="tunggakan" <?= $tipe === 'tunggakan' ? 'selected' : '' ?>>Tunggakan</option>
        </select>
      </div>
      <div>
        <label class="d-block font-weight-bold mb-1" style="font-size:12px"><i class="fas fa-money-bill-wave mr-1 text-primary"></i>Metode Pembayaran</label>
        <select name="metode" class="form-control form-control-sm" style="width:170px">
          <option value="">Semua Metode</option>
          <option value="tunai" <?= $metode === 'tunai' ? 'selected' : '' ?>>Tunai</option>
          <option value="transfer" <?= $metode === 'transfer' ? 'selected' : '' ?>>Transfer</option>
        </select>
      </div>
      <div style="display:flex;gap:8px">
        <button type="submit" class="btn btn-primary btn-sm">
          <i class="fas fa-filter mr-1"></i>Terapkan
        </button>
        <a href="<?= BASE_URL ?>modules/laporan/views.php?tahun=<?= $tahun ?>&bulan=<?= urlencode($bulan) ?>" class="btn btn-secondary btn-sm">Reset Filter</a>
      </div>
    </form>
  </div>
</div>

<!-- Detail Transaksi -->
<div class="card">
  <div class="card-header">
    <span><i class="fas fa-list mr-2"></i>Detail Tagihan — <?= date('F Y', strtotime($bulan . '-01')) ?></span>
  </div>
  <div class="card-body p-2">
    <div class="d-flex flex-wrap align-items-center justify-content-between mb-2" style="gap:8px">
      <div class="d-flex align-items-center" style="gap:6px">
        <label class="mb-0 text-muted" style="font-size:13px">Tampilkan</label>
        <select id="lengthDetailTagihan" class="form-control form-control-sm" style="width:70px">
          <option value="15">15</option>
          <option value="25">25</option>
          <option value="50">50</option>
          <option value="100">100</option>
        </select>
        <label class="mb-0 text-muted" style="font-size:13px">entri</label>
      </div>
    </div>
    <div class="table-responsive">
      <table id="tabelDetailTagihan" class="table table-hover mb-0 w-100">
        <thead>
          <tr>
            <th>#</th>
            <th>Pelanggan</th>
            <th>Paket</th>
            <th>Jumlah</th>
            <th>Potongan</th>
            <th>Terbayar</th>
            <th>Metode</th>
            <th>Tgl Bayar</th>
            <th>Tipe</th>
          </tr>
        </thead>
        <tbody></tbody>
      </table>
    </div>
  </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
<script>
  const labels = <?= json_encode(array_values($nama_bulan)) ?>.slice(1);
  const dataSet = <?php
                  $d = [];
                  for ($m = 1; $m <= 12; $m++) {
                    $key = str_pad($m, 2, '0', STR_PAD_LEFT);
                    $d[] = (int)($bulan_map[$key]['total'] ?? 0);
                  }
                  echo json_encode($d);
                  ?>;

  new Chart(document.getElementById('chartPendapatan'), {
    type: 'bar',
    data: {
      labels,
      datasets: [{
        label: 'Pendapatan (Rp)',
        data: dataSet,
        backgroundColor: 'rgba(37,99,235,0.75)',
        borderRadius: 6,
      }]
    },
    options: {
      responsive: true,
      plugins: {
        legend: {
          display: false
        }
      },
      scales: {
        y: {
          ticks: {
            callback: v => 'Rp ' + v.toLocaleString('id-ID'),
            font: {
              size: 11
            }
          }
        }
      }
    }
  });
</script>

<?php
$content     = ob_get_clean();
$bulan_json  = json_encode($bulan);
$tipe_json   = json_encode($tipe ?? '');
$paket_json  = json_encode($paket_id);
$metode_json = json_encode($metode);
$q_json      = json_encode($q);

// DataTable server-side init HARUS lewat $extra_js (dirender template.php
// setelah jQuery/DataTables dimuat) — bukan inline di $content, yang
// dirender SEBELUM library JS-nya (baru kemudian ketauan sumber error
// "$ is not defined" yang sempat muncul di halaman lain).
// Toolbar & konfigurasi (dom, searching:false, custom "Tampilkan X entri")
// disamakan persis dengan pola DataTable di modul Pembayaran/Pelanggan.
$extra_js = <<<HTML
<script>
var tabelDetailTagihan = \$('#tabelDetailTagihan').DataTable({
  serverSide: true,
  processing: true,
  searching: false,
  dom: 'rt<"d-flex justify-content-between align-items-center mt-2 flex-wrap"ip>',
  pageLength: 15,
  order: [[7, 'desc']],
  language: { emptyTable:'Tidak ada data tagihan pada bulan ini.',info:'Menampilkan _START_-_END_ dari _TOTAL_ entri',infoEmpty:'0 entri',infoFiltered:'(dari _MAX_ total)',lengthMenu:'Tampilkan _MENU_ entri',loadingRecords:'Memuat...',processing:'Memproses...',zeroRecords:'Data tidak ditemukan',paginate:{first:'Pertama',last:'Terakhir',next:'›',previous:'‹'} },
  ajax: {
    url: 'act.php',
    data: function (d) {
      d.action   = 'datatable_detail';
      d.bulan    = {$bulan_json};
      d.tipe     = {$tipe_json};
      d.paket_id = {$paket_json};
      d.metode   = {$metode_json};
      d.q        = {$q_json};
    }
  },
  columns: [
    { data: 'no', orderable: false },
    { data: 'pelanggan' },
    { data: 'paket', orderable: false },
    { data: 'jumlah', orderable: true },
    { data: 'potongan', orderable: false },
    { data: 'terbayar' },
    { data: 'metode', orderable: false },
    { data: 'tgl_bayar' },
    { data: 'tipe', orderable: false },
  ],
});

\$('#lengthDetailTagihan').on('change', function () {
  tabelDetailTagihan.page.len(parseInt(\$(this).val())).draw();
});
</script>
HTML;

require_once __DIR__ . '/../../template.php';
