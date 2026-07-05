<?php
// ============================================================
//  KAHFINET - Modul Mikrotik / PPP Profile (Tampilan)
//  Mirror read-only dari /ppp/profile di Mikrotik.
// ============================================================
require_once __DIR__ . '/../../system/init.php';
auth_role([ROLE_ADMIN, ROLE_TEKNISI]);

$rows      = db_rows("SELECT * FROM mikrotik_profiles_cache ORDER BY name ASC");
$lastSync  = db_row("SELECT MAX(synced_at) as t FROM mikrotik_profiles_cache")['t'] ?? null;

$page_title  = 'Mikrotik — PPP Profile';
$active_menu = 'mikrotik_profile';

ob_start();
?>

<div class="page-header">
  <h5><i class="fas fa-sliders-h mr-2 text-primary"></i>PPP Profile (Mikrotik)</h5>
  <form method="POST" action="<?= BASE_URL ?>modules/mikrotik/act.php" class="d-inline">
    <?php csrf_field(); ?>
    <input type="hidden" name="action" value="sync_profiles">
    <button type="submit" class="btn btn-primary btn-sm">
      <i class="fas fa-sync mr-1"></i>Sync dari Mikrotik
    </button>
  </form>
</div>

<p class="text-muted" style="font-size:13px">
  Data ini cuma mirror dari <code>/ppp profile print</code> di Mikrotik untuk dilihat — bukan untuk diedit di sini.
  <?= $lastSync ? 'Terakhir sync: ' . tgl_indo($lastSync, true) . '.' : 'Belum pernah sync.' ?>
</p>

<div class="card">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead>
          <tr>
            <th>#</th>
            <th>Nama Profile</th>
            <th>Rate Limit</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($rows): foreach ($rows as $i => $r): ?>
            <tr>
              <td><?= $i + 1 ?></td>
              <td class="font-weight-bold"><?= clean($r['name']) ?></td>
              <td><?= clean($r['rate_limit'] ?? '—') ?></td>
            </tr>
          <?php endforeach; else: ?>
            <tr>
              <td colspan="3" class="text-center text-muted py-4">
                Belum ada data. Klik "Sync dari Mikrotik" untuk menarik data PPP profile.
              </td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../../template.php';
