<?php
// ============================================================
//  PORTAL PELANGGAN — Lapor Gangguan
// ============================================================
require_once __DIR__ . '/_data.php';
portal_auth_check();

$me  = portal_current();
$pid = (int)$me['id'];

$tiket = db_rows(
    "SELECT * FROM tiket_gangguan WHERE pelanggan_id = ? ORDER BY id DESC LIMIT 30",
    [$pid]
);

// Badge status tiket
function tiket_badge(string $s): string {
    $map = [
        'baru'     => ['badge-info',      'Baru'],
        'diproses' => ['badge-warning',   'Diproses'],
        'selesai'  => ['badge-success',   'Selesai'],
        'ditutup'  => ['badge-secondary', 'Ditutup'],
    ];
    [$c, $l] = $map[$s] ?? ['badge-secondary', ucfirst($s)];
    return '<span class="badge ' . $c . '">' . $l . '</span>';
}

$kategori_opt = [
    'koneksi_lambat' => 'Koneksi lambat',
    'tidak_konek'    => 'Tidak bisa konek sama sekali',
    'perangkat'      => 'Perangkat / router bermasalah',
    'tagihan'        => 'Pertanyaan tagihan',
    'lainnya'        => 'Lainnya',
];

$page_title = 'Lapor Gangguan';
$page_sub   = 'Sampaikan keluhan Anda';
$active     = 'lapor';

ob_start();
?>

<!-- Form lapor -->
<div class="section-title">Buat laporan baru</div>
<div class="pcard">
  <form method="POST" action="<?= PORTAL_URL ?>act.php?action=lapor" id="formLapor">
    <?php csrf_field(); ?>

    <label class="plabel">Jenis gangguan</label>
    <select name="kategori" class="pselect" required>
      <?php foreach ($kategori_opt as $val => $lbl): ?>
        <option value="<?= $val ?>"><?= $lbl ?></option>
      <?php endforeach; ?>
    </select>

    <label class="plabel">Ceritakan keluhan Anda</label>
    <textarea name="deskripsi" class="ptextarea" maxlength="1000" required
              placeholder="Contoh: Internet mati sejak pagi tadi, lampu router merah semua..."></textarea>

    <button type="submit" class="pbtn pbtn-amber" id="btnLapor">
      <i class="fas fa-paper-plane"></i> Kirim Laporan
    </button>
  </form>
</div>

<!-- Riwayat tiket -->
<div class="section-title">Laporan saya</div>
<?php if (empty($tiket)): ?>
  <div class="pcard">
    <div class="empty" style="padding:24px 10px;">
      <i class="fas fa-clipboard-check"></i>
      <p>Belum ada laporan. Semoga koneksi Anda lancar terus! 😊</p>
    </div>
  </div>
<?php else: ?>
  <?php foreach ($tiket as $t): ?>
    <div class="pcard">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
        <span style="font-size:14px;font-weight:700;color:var(--navy);"><?= clean(tiket_kategori_label((string)$t['kategori'])) ?></span>
        <?= tiket_badge((string)$t['status']) ?>
      </div>
      <div style="font-size:13px;color:var(--ink);line-height:1.5;"><?= nl2br(clean((string)$t['deskripsi'])) ?></div>
      <div style="font-size:11.5px;color:var(--muted);margin-top:8px;">
        <i class="far fa-clock"></i> <?= tgl_indo((string)$t['created_at'], true) ?>
      </div>
      <?php if (!empty($t['balasan'])): ?>
        <div style="margin-top:10px;padding:10px 12px;background:#f0f9ff;border-radius:10px;border-left:3px solid #38bdf8;">
          <div style="font-size:11px;font-weight:700;color:#0369a1;margin-bottom:3px;"><i class="fas fa-reply mr-1"></i>Tanggapan petugas</div>
          <div style="font-size:12.5px;color:#0c4a6e;line-height:1.5;"><?= nl2br(clean((string)$t['balasan'])) ?></div>
        </div>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
<?php endif; ?>

<?php
$content = ob_get_clean();
$body_extra = <<<HTML
<script>
  document.getElementById('formLapor').addEventListener('submit', function () {
    var b = document.getElementById('btnLapor');
    b.disabled = true;
    b.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Mengirim...';
  });
</script>
HTML;
require __DIR__ . '/layout.php';
