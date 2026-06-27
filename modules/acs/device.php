<?php
// ============================================================
//  KAHFINET - Modul ACS / Device ONU (Tampilan)
//  Mirror read-only dari /devices GenieACS. Mapping ke Secret PPP
//  dilakukan MANUAL (bukan auto-match), supaya admin tetap pegang
//  kendali kalau username PPPoE di device tidak konsisten.
// ============================================================
require_once __DIR__ . '/../../system/init.php';
auth_role([ROLE_ADMIN]);

$devices = db_rows(
  "SELECT gdc.*, msc.id as secret_id, msc.name as secret_name, pl.nama as nama_pelanggan
   FROM genieacs_devices_cache gdc
   LEFT JOIN mikrotik_secrets_cache msc ON msc.genieacs_device_id = gdc.id
   LEFT JOIN pelanggan pl ON pl.mikrotik_secrets_id = msc.id
   ORDER BY gdc.tag ASC, gdc.device_id ASC"
);

$secrets = db_rows(
  "SELECT msc.id, msc.name, msc.genieacs_device_id, pl.nama as nama_pelanggan
   FROM mikrotik_secrets_cache msc
   LEFT JOIN pelanggan pl ON pl.mikrotik_secrets_id = msc.id
   ORDER BY msc.name ASC"
);

$lastSync = db_row("SELECT MAX(synced_at) as t FROM genieacs_devices_cache")['t'] ?? null;

$page_title  = 'ACS — Device ONU';
$active_menu = 'acs_device';

ob_start();
?>

<div class="page-header">
  <h5><i class="fas fa-router mr-2 text-primary"></i>Device ONU</h5>
  <form method="POST" action="<?= BASE_URL ?>modules/acs/act.php" class="d-inline">
    <?php csrf_field(); ?>
    <input type="hidden" name="action" value="sync_devices">
    <button type="submit" class="btn btn-primary btn-sm">
      <i class="fas fa-sync mr-1"></i>Sync dari ACS
    </button>
  </form>
</div>

<p class="text-muted" style="font-size:13px">
  Data ini mirror dari GenieACS — bukan untuk diedit di sini. Mapping ke Secret PPP/pelanggan dipilih manual lewat tombol di kolom Mapping.
  <?= $lastSync ? 'Terakhir sync: ' . tgl_indo($lastSync, true) . '.' : 'Belum pernah sync.' ?>
</p>

<div class="card">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead>
          <tr>
            <th>#</th>
            <th>Tag</th>
            <th>Model</th>
            <th>Last Inform</th>
            <th>Mapping</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$devices): ?>
            <tr><td colspan="5" class="text-center text-muted py-4">Belum ada data. Klik "Sync dari ACS" dulu.</td></tr>
          <?php endif; ?>
          <?php foreach ($devices as $i => $d): ?>
            <tr>
              <td><?= $i + 1 ?></td>
              <td><?= clean($d['tag'] ?? '—') ?></td>
              <td class="text-muted"><?= clean(trim(($d['manufacturer'] ?? '') . ' ' . ($d['product_class'] ?? '')) ?: '—') ?></td>
              <td class="text-muted"><?= $d['last_inform'] ? tgl_indo($d['last_inform'], true) : '—' ?></td>
              <td>
                <?php if ($d['secret_id']): ?>
                  <div class="font-weight-bold" style="font-size:13px"><?= clean($d['nama_pelanggan'] ?? '—') ?></div>
                  <div class="text-muted" style="font-size:12px">secret: <?= clean($d['secret_name']) ?></div>
                  <button type="button" class="btn btn-outline-secondary btn-xs mt-1 btn-mapping"
                    data-device-id="<?= (int)$d['id'] ?>" data-tag="<?= clean($d['tag'] ?? '—') ?>"
                    data-model="<?= clean(trim(($d['manufacturer'] ?? '') . ' ' . ($d['product_class'] ?? ''))) ?>"
                    data-pppoe="<?= clean($d['pppoe_username'] ?? '') ?>"
                    data-secret-id="<?= (int)$d['secret_id'] ?>">Ubah Mapping</button>
                <?php else: ?>
                  <button type="button" class="btn btn-outline-info btn-xs btn-mapping"
                    data-device-id="<?= (int)$d['id'] ?>" data-tag="<?= clean($d['tag'] ?? '—') ?>"
                    data-model="<?= clean(trim(($d['manufacturer'] ?? '') . ' ' . ($d['product_class'] ?? ''))) ?>"
                    data-pppoe="<?= clean($d['pppoe_username'] ?? '') ?>"
                    data-secret-id=""><i class="fas fa-link mr-1"></i>Mapping ke...</button>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Modal Mapping -->
<div class="modal fade" id="modalMapping" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-link mr-2"></i>Mapping Device ONU</h5>
        <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
      </div>
      <form method="POST" action="<?= BASE_URL ?>modules/acs/act.php" id="formMapping">
        <?php csrf_field(); ?>
        <input type="hidden" name="action" value="set_mapping">
        <input type="hidden" name="device_id" id="mapping_device_id">
        <div class="modal-body">
          <table class="w-100 mb-3" style="font-size:13px">
            <tr><td class="text-muted">Tag device</td><td class="text-right font-weight-bold" id="mapping_tag"></td></tr>
            <tr><td class="text-muted">Model</td><td class="text-right" id="mapping_model"></td></tr>
          </table>
          <div class="form-group">
            <label class="form-label">Pilih Secret PPP / Pelanggan</label>
            <select name="secret_id" id="mapping_secret_id" class="form-control" style="width:100%">
              <option value="">— Tidak dimapping —</option>
            </select>
            <small class="text-muted">Opsi yang ditandai "username cocok" berarti username PPPoE sama dengan yang dilaporkan device — bukan otomatis dipilih, tetap perlu dikonfirmasi manual.</small>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-primary btn-sm">
            <i class="fas fa-save mr-1"></i>Simpan Mapping
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<style>.btn-xs{padding:3px 7px;font-size:12px;border-radius:4px}</style>

<?php
$content      = ob_get_clean();
$base_url     = BASE_URL;
$secrets_json = json_encode(array_map(fn($s) => [
  'id'           => $s['id'],
  'name'         => $s['name'],
  'nama_pelanggan' => $s['nama_pelanggan'],
  'genieacs_device_id' => $s['genieacs_device_id'],
], $secrets));

$extra_js = <<<HTML
<script>
\$('#mapping_secret_id').select2({
  theme: 'bootstrap',
  dropdownParent: \$('#modalMapping'),
  placeholder: '— Tidak dimapping —',
  width: '100%',
});

var secretOptions = {$secrets_json};

\$(document).on('click', '.btn-mapping', function () {
  var deviceId         = \$(this).data('device-id');
  var tag              = \$(this).data('tag');
  var model            = \$(this).data('model');
  var pppoeUsername    = \$(this).data('pppoe');
  var currentSecretId  = \$(this).data('secret-id');

  \$('#mapping_device_id').val(deviceId);
  \$('#mapping_tag').text(tag);
  \$('#mapping_model').text(model || '—');

  var \$select = \$('#mapping_secret_id');
  \$select.find('option:not(:first)').remove();

  \$.each(secretOptions, function (i, s) {
    var isMine   = s.id == currentSecretId;
    var isUsed   = s.genieacs_device_id && s.genieacs_device_id != deviceId && !isMine;
    var isMatch  = pppoeUsername && s.name === pppoeUsername;
    var label    = s.name + (s.nama_pelanggan ? ' — ' + s.nama_pelanggan : '');
    if (isMatch) label += ' ✓ username cocok';
    if (isUsed) label += ' (sudah dipakai device lain)';
    var \$opt = \$('<option></option>').val(s.id).text(label);
    if (isUsed) \$opt.prop('disabled', true);
    \$select.append(\$opt);
  });

  \$select.val(currentSecretId || '').trigger('change');
  \$('#modalMapping').modal('show');
});

\$('#modalMapping').on('hidden.bs.modal', function () {
  \$('#mapping_secret_id').val('').trigger('change');
});
</script>
HTML;

require_once __DIR__ . '/../../template.php';
