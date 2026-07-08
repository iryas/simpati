<?php
// ============================================================
//  KAHFINET - Modul Template WA
// ============================================================
require_once __DIR__ . '/../../system/init.php';
auth_check();
auth_role([ROLE_ADMIN]);

$templates = db_rows("SELECT * FROM wa_templates ORDER BY id ASC");

$page_title  = 'Template WhatsApp';
$active_menu = 'template_wa';

ob_start();
?>

<div class="page-header">
  <h5><i class="fab fa-whatsapp mr-2 text-success"></i>Template Pesan WhatsApp</h5>
</div>

<div class="row">
  <?php foreach ($templates as $tpl): ?>
  <div class="col-lg-6 mb-4">
    <div class="card h-100" style="border-top: 3px solid <?= $tpl['aktif'] ? '#25d366' : '#adb5bd' ?>">
      <div class="card-header d-flex align-items-center justify-content-between">
        <div>
          <span class="font-weight-bold"><?= clean($tpl['nama']) ?></span>
          <span class="badge badge-secondary ml-2" style="font-size:11px;font-weight:400">
            <?= clean($tpl['kode']) ?>
          </span>
        </div>
        <div class="d-flex" style="gap:6px">
          <!-- Toggle Aktif -->
          <form method="POST" action="<?= BASE_URL ?>modules/template_wa/act.php" class="d-inline">
            <?php csrf_field(); ?>
            <input type="hidden" name="action" value="toggle">
            <input type="hidden" name="id" value="<?= $tpl['id'] ?>">
            <button type="submit" class="btn btn-sm <?= $tpl['aktif'] ? 'btn-success' : 'btn-outline-secondary' ?>"
                    title="<?= $tpl['aktif'] ? 'Nonaktifkan' : 'Aktifkan' ?>">
              <i class="fas <?= $tpl['aktif'] ? 'fa-toggle-on' : 'fa-toggle-off' ?>"></i>
              <?= $tpl['aktif'] ? 'Aktif' : 'Nonaktif' ?>
            </button>
          </form>
          <!-- Edit -->
          <button type="button" class="btn btn-sm btn-primary btn-edit-tpl"
                  data-id="<?= $tpl['id'] ?>">
            <i class="fas fa-edit"></i> Edit
          </button>
        </div>
      </div>
      <div class="card-body">
        <!-- Preview konten -->
        <pre style="font-size:12px;white-space:pre-wrap;word-break:break-word;
                    background:#f8f9fa;border-radius:6px;padding:12px;
                    max-height:280px;overflow-y:auto;color:#2d3748;line-height:1.6"><?= clean($tpl['konten']) ?></pre>

        <?php if ($tpl['placeholder']): ?>
        <div class="mt-2">
          <small class="text-muted d-block mb-1">Placeholder tersedia:</small>
          <?php foreach (explode(',', $tpl['placeholder']) as $ph): ?>
            <code class="mr-1" style="font-size:11px;background:#e2e8f0;padding:2px 6px;border-radius:4px">
              <?= clean(trim($ph)) ?>
            </code>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if ($tpl['updated_at']): ?>
        <small class="text-muted d-block mt-2">
          <i class="fas fa-clock mr-1"></i>Diperbarui: <?= tgl_indo($tpl['updated_at'], true) ?>
        </small>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Modal Edit Template -->
<div class="modal fade" id="modalEditTpl" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="POST" action="<?= BASE_URL ?>modules/template_wa/act.php">
        <?php csrf_field(); ?>
        <input type="hidden" name="action" value="update">
        <input type="hidden" name="id" id="edit_tpl_id">
        <div class="modal-header">
          <h5 class="modal-title"><i class="fas fa-edit mr-2"></i>Edit Template: <span id="edit_tpl_judul"></span></h5>
          <button type="button" class="close" data-dismiss="modal">&times;</button>
        </div>
        <div class="modal-body">
          <div class="form-group">
            <label class="form-label font-weight-bold">Nama Template</label>
            <input type="text" name="nama" id="edit_tpl_nama" class="form-control" maxlength="100" required>
          </div>
          <div class="form-group mb-1">
            <label class="form-label font-weight-bold">Isi Pesan</label>
            <textarea name="konten" id="edit_tpl_konten" class="form-control"
                      rows="12" required
                      style="font-family:monospace;font-size:13px;line-height:1.6"></textarea>
          </div>
          <div id="edit_tpl_ph_wrap" class="mb-2" style="display:none">
            <small class="text-muted">Placeholder: </small>
            <span id="edit_tpl_ph"></span>
          </div>
          <small class="text-muted">
            <i class="fas fa-info-circle mr-1"></i>
            Gunakan <code>{placeholder}</code> sesuai daftar di atas. Teks dikirim apa adanya ke WhatsApp.
          </small>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-primary btn-sm">
            <i class="fas fa-save mr-1"></i>Simpan
          </button>
        </div>
      </form>
    </div>
  </div>
</div>


<?php
$content  = ob_get_clean();
$base_url = BASE_URL;
$extra_js = <<<JS
<script>
$(document).on('click', '.btn-edit-tpl', function () {
  const id  = $(this).data('id');
  const btn = $(this);
  btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');

  $.getJSON('{$base_url}modules/template_wa/act.php', { action: 'get_json', id: id })
    .done(function (res) {
      if (!res.success) { toastr.error(res.message); return; }
      const d = res.data;
      \$('#edit_tpl_id').val(d.id);
      \$('#edit_tpl_judul').text(d.nama);
      \$('#edit_tpl_nama').val(d.nama);
      \$('#edit_tpl_konten').val(d.konten);

      if (d.placeholder) {
        \$('#edit_tpl_ph_wrap').show();
        const badges = d.placeholder.split(',').map(p =>
          '<code style="font-size:11px;background:#e2e8f0;padding:2px 6px;border-radius:4px;margin-right:4px">' +
          p.trim() + '</code>'
        ).join('');
        \$('#edit_tpl_ph').html(badges);
      } else {
        \$('#edit_tpl_ph_wrap').hide();
      }

      \$('#modalEditTpl').modal('show');
    })
    .fail(function () { toastr.error('Gagal memuat data template.'); })
    .always(function () {
      btn.prop('disabled', false).html('<i class="fas fa-edit"></i> Edit');
    });
});
</script>
JS;
require_once __DIR__ . '/../../template.php';
