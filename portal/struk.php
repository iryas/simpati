<?php
// ============================================================
//  PORTAL PELANGGAN — Struk / Bukti Pembayaran
// ============================================================
require_once __DIR__ . '/_data.php';
portal_auth_check();

$me  = portal_current();
$pid = (int)$me['id'];
$id  = (int)get('id', 0);

// Ambil pembayaran — WAJIB milik pelanggan yang login.
$py = db_row(
    "SELECT py.*, pk.nama AS nama_paket
     FROM pembayaran py
     LEFT JOIN paket pk ON pk.id = py.paket_id
     WHERE py.id = ? AND py.pelanggan_id = ? LIMIT 1",
    [$id, $pid]
);

$nama_isp = clean(app_setting('nama_isp', 'KahfiNet'));

$page_title = 'Struk Pembayaran';
$page_sub   = $py ? '#' . str_pad((string)$py['id'], 5, '0', STR_PAD_LEFT) : '';
$active     = 'tagihan';
$back_url   = PORTAL_URL . 'riwayat.php';

ob_start();
?>

<?php if (!$py): ?>
  <div class="pcard">
    <div class="empty">
      <i class="fas fa-file-excel"></i>
      <p>Struk tidak ditemukan.</p>
    </div>
  </div>
<?php else:
  $lunas = $py['status'] === 'lunas';
?>

  <div class="pcard" style="text-align:center;padding-top:22px;">
    <div style="width:60px;height:60px;border-radius:50%;margin:0 auto 12px;display:flex;align-items:center;justify-content:center;
                background:<?= $lunas ? '#dcfce7' : '#fee2e2' ?>;color:<?= $lunas ? '#16a34a' : '#dc2626' ?>;font-size:26px;">
      <i class="fas <?= $lunas ? 'fa-check-circle' : 'fa-clock' ?>"></i>
    </div>
    <div style="font-size:13px;color:var(--muted);"><?= $lunas ? 'Pembayaran berhasil' : 'Belum dibayar' ?></div>
    <div style="font-size:26px;font-weight:800;color:var(--navy);margin-top:2px;">
      <?= rupiah((int)($lunas ? $py['terbayar'] : portal_nominal((int)$py['jumlah'], (int)$py['potongan']))) ?>
    </div>
    <div style="margin-top:8px;">
      <?= $lunas ? badge_status('lunas') : badge_status('belum', (string)$py['bulan_tagihan']) ?>
    </div>
  </div>

  <div class="pcard">
    <div class="kv"><span class="k">No. Struk</span><span class="v">#<?= str_pad((string)$py['id'], 5, '0', STR_PAD_LEFT) ?></span></div>
    <div class="kv"><span class="k">Nama</span><span class="v"><?= clean($me['nama']) ?></span></div>
    <div class="kv"><span class="k">Periode</span><span class="v"><?= clean(portal_periode((string)$py['bulan_tagihan'])) ?></span></div>
    <div class="kv"><span class="k">Paket</span><span class="v"><?= $py['nama_paket'] ? clean($py['nama_paket']) : '—' ?></span></div>
    <div class="kv"><span class="k">Tagihan</span><span class="v"><?= rupiah((int)$py['jumlah']) ?></span></div>
    <?php if ((int)$py['potongan'] > 0): ?>
      <div class="kv"><span class="k">Potongan</span><span class="v" style="color:#16a34a;"><?= (int)$py['potongan'] ?> hari</span></div>
    <?php endif; ?>
    <?php if ($lunas): ?>
      <div class="kv"><span class="k">Dibayar</span><span class="v"><?= rupiah((int)$py['terbayar']) ?></span></div>
      <div class="kv"><span class="k">Tanggal bayar</span><span class="v"><?= tgl_indo((string)$py['tgl_bayar']) ?></span></div>
      <div class="kv"><span class="k">Metode</span><span class="v"><?= $py['metode'] === 'transfer' ? 'Transfer' : 'Tunai' ?></span></div>
    <?php endif; ?>
  </div>

  <div style="text-align:center;font-size:11.5px;color:var(--muted);margin:4px 0 8px;">
    Struk sah dari <strong><?= $nama_isp ?></strong> · <?= tgl_indo(date('Y-m-d'), true) ?>
  </div>

<?php endif; ?>

<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
