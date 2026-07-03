<?php
// ============================================================
//  KAHFINET - Modul Pengaturan Aplikasi
// ============================================================
require_once __DIR__ . '/../../system/init.php';
auth_check();
auth_role([ROLE_ADMIN]);

$tgl_mulai = app_setting('tgl_mulai_tagihan', '1');
$nama_isp  = app_setting('nama_isp', 'KahfiNet');

$page_title  = 'Pengaturan Aplikasi';
$active_menu = 'pengaturan';

ob_start();
?>

<div class="page-header">
  <h5><i class="fas fa-cog mr-2 text-primary"></i>Pengaturan Aplikasi</h5>
</div>

<div class="row">
  <div class="col-lg-6">
    <div class="card">
      <div class="card-header">
        <span><i class="fas fa-sliders-h mr-2 text-primary"></i>Pengaturan Umum</span>
      </div>
      <div class="card-body">
        <form method="POST" action="<?= BASE_URL ?>modules/pengaturan/act.php">
          <?php csrf_field(); ?>
          <input type="hidden" name="action" value="save">

          <div class="form-group">
            <label class="form-label font-weight-bold">Nama ISP</label>
            <input type="text" name="nama_isp" class="form-control"
                   value="<?= clean($nama_isp) ?>" maxlength="100" required>
            <small class="text-muted">Nama perusahaan/ISP yang muncul di aplikasi.</small>
          </div>

          <div class="form-group">
            <label class="form-label font-weight-bold">Tanggal Mulai Tagihan</label>
            <div class="d-flex align-items-center" style="gap:10px">
              <input type="number" name="tgl_mulai_tagihan" class="form-control"
                     value="<?= (int)$tgl_mulai ?>" min="1" max="28" required
                     style="width:90px">
              <span class="text-muted" style="font-size:14px;white-space:nowrap">setiap bulan (maks. tgl 28)</span>
            </div>
            <small class="text-muted mt-1 d-block">
              Generate tagihan hanya bisa dilakukan mulai tanggal ini.
              Contoh: diset <strong><?= (int)$tgl_mulai ?></strong> → tagihan Juli digenerate mulai
              <strong><?= (int)$tgl_mulai ?> Juli</strong>,
              berlaku hingga <strong><?= (int)$tgl_mulai - 1 ?> Agustus</strong>.
            </small>
          </div>

          <?php
            $tgl        = (int)$tgl_mulai;
            $now        = (int)date('j');
            $bln_indo   = ['','Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
            $bln_label  = $bln_indo[(int)date('n')] . ' ' . date('Y');
            $lbl_periode = label_periode_tagihan(date('Y-m'), $tgl);
            $boleh      = $now >= $tgl;
          ?>
          <div class="alert <?= $boleh ? 'alert-success' : 'alert-warning' ?> py-2" style="font-size:13px">
            <i class="fas <?= $boleh ? 'fa-check-circle' : 'fa-clock' ?> mr-1"></i>
            <?php if ($boleh): ?>
              Generate tagihan bulan <strong><?= $bln_label ?></strong>
              sudah <strong>boleh</strong> dilakukan.
              Periode layanan: <strong><?= $lbl_periode ?></strong>.
            <?php else: ?>
              Generate tagihan bulan <strong><?= $bln_label ?></strong>
              baru boleh mulai <strong>tgl <?= $tgl ?></strong>.
              Hari ini masih tgl <?= $now ?>.
            <?php endif; ?>
          </div>

          <button type="submit" class="btn btn-primary btn-sm">
            <i class="fas fa-save mr-1"></i>Simpan Pengaturan
          </button>
        </form>
      </div>
    </div>
  </div>
</div>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../../template.php';
