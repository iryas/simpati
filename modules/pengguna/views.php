<?php
// ============================================================
//  KAHFINET - Modul Pengguna (Tampilan) — Admin Only
// ============================================================
require_once __DIR__ . '/../../system/init.php';
auth_check();
auth_role([ROLE_ADMIN]);

$rows = db_rows("SELECT * FROM pengguna ORDER BY id ASC");

$page_title  = 'Manajemen Pengguna';
$active_menu = 'pengguna';

ob_start();
?>

<div class="page-header">
  <h5><i class="fas fa-user-shield mr-2 text-primary"></i>Manajemen Pengguna</h5>
  <button class="btn btn-primary btn-sm" data-toggle="modal" data-target="#modalTambah">
    <i class="fas fa-user-plus mr-1"></i>Tambah Pengguna
  </button>
</div>

<div class="card">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead>
          <tr>
            <th>#</th>
            <th>Nama</th>
            <th>Username</th>
            <th>Role</th>
            <th>Status</th>
            <th>Terakhir Login</th>
            <th>Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $i => $r): ?>
            <tr>
              <td><?= $i + 1 ?></td>
              <td class="font-weight-bold"><?= clean($r['nama']) ?></td>
              <td><code><?= clean($r['username']) ?></code></td>
              <td><?= badge_role($r['role']) ?></td>
              <td><?= badge_status($r['status']) ?></td>
              <td><?= $r['last_login'] ? tgl_indo($r['last_login'], true) : '<span class="text-muted">Belum pernah</span>' ?></td>
              <td>
                <?php if ($r['id'] !== current_user()['id']): ?>
                  <button class="btn btn-warning btn-xs btn-edit-user"
                    data-id="<?= $r['id'] ?>"
                    data-toggle="modal" data-target="#modalEdit">
                    <i class="fas fa-edit"></i>
                  </button>
                  <a href="<?= BASE_URL ?>modules/pengguna/act.php?action=delete&id=<?= $r['id'] ?>"
                    class="btn btn-danger btn-xs btn-hapus"
                    data-label="pengguna <?= clean($r['nama']) ?>">
                    <i class="fas fa-trash"></i>
                  </a>
                <?php else: ?>
                  <button class="btn btn-warning btn-xs btn-edit-user"
                    data-id="<?= $r['id'] ?>"
                    data-toggle="modal" data-target="#modalEdit">
                    <i class="fas fa-edit"></i> Edit Profil
                  </button>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Modal Tambah -->
<div class="modal fade" id="modalTambah" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-user-plus mr-2"></i>Tambah Pengguna</h5>
        <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
      </div>
      <form method="POST" action="<?= BASE_URL ?>modules/pengguna/act.php">
        <?php csrf_field(); ?>
        <input type="hidden" name="action" value="create">
        <div class="modal-body">
          <div class="form-group">
            <label class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
            <input type="text" name="nama" class="form-control" required>
          </div>
          <div class="form-group">
            <label class="form-label">Username <span class="text-danger">*</span></label>
            <input type="text" name="username" class="form-control" required autocomplete="off">
          </div>
          <div class="form-group">
            <label class="form-label">Password <span class="text-danger">*</span></label>
            <input type="password" name="password" class="form-control" required autocomplete="new-password"
              minlength="6">
            <small class="text-muted">Minimal 6 karakter</small>
          </div>
          <div class="form-group">
            <label class="form-label">Role</label>
            <select name="role" class="form-control">
              <option value="admin">Admin</option>
              <option value="teknisi">Teknisi</option>
              <option value="kasir" selected>Kasir</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Status</label>
            <select name="status" class="form-control">
              <option value="aktif">Aktif</option>
              <option value="nonaktif">Non-aktif</option>
            </select>
          </div>
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

<!-- Modal Edit -->
<div class="modal fade" id="modalEdit" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-edit mr-2"></i>Edit Pengguna</h5>
        <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
      </div>
      <form method="POST" action="<?= BASE_URL ?>modules/pengguna/act.php">
        <?php csrf_field(); ?>
        <input type="hidden" name="action" value="update">
        <input type="hidden" name="id" id="edit_id">
        <div class="modal-body" id="editBody">
          <div class="text-center py-4"><i class="fas fa-spinner fa-spin"></i></div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-warning btn-sm">
            <i class="fas fa-save mr-1"></i>Update
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<style>
  .btn-xs {
    padding: 3px 7px;
    font-size: 12px;
    border-radius: 4px
  }
</style>

<?php
$content  = ob_get_clean();
$base_url = BASE_URL;

$extra_js = <<<HTML
<script>
$(document).on('click', '.btn-edit-user', function () {
  var id = $(this).data('id');
  $('#edit_id').val(id);
  $('#editBody').html('<div class="text-center py-4"><i class="fas fa-spinner fa-spin"></i> Memuat&hellip;</div>');
  $.getJSON('{$base_url}modules/pengguna/act.php', { action: 'get_json', id: id }, function (res) {
    if (!res.success) { $('#editBody').html('<p class="text-danger p-3">Gagal memuat data.</p>'); return; }
    var d   = res.data;
    var esc = function(s) { return $('<div>').text(s || '').html(); };
    $('#editBody').html(
      '<div class="form-group">' +
        '<label class="form-label">Nama Lengkap <span class="text-danger">*</span></label>' +
        '<input type="text" name="nama" class="form-control" value="' + esc(d.nama) + '" required>' +
      '</div>' +
      '<div class="form-group">' +
        '<label class="form-label">Username <span class="text-danger">*</span></label>' +
        '<input type="text" name="username" class="form-control" value="' + esc(d.username) + '" required>' +
      '</div>' +
      '<div class="form-group">' +
        '<label class="form-label">Password Baru</label>' +
        '<input type="password" name="password" class="form-control" placeholder="Kosongkan jika tidak diubah" minlength="8">' +
        '<small class="text-muted">Minimal 8 karakter</small>' +
      '</div>' +
      '<div class="form-group">' +
        '<label class="form-label">Role</label>' +
        '<select name="role" class="form-control">' +
          '<option value="admin"'   + (d.role === 'admin'   ? ' selected' : '') + '>Admin</option>' +
          '<option value="teknisi"' + (d.role === 'teknisi' ? ' selected' : '') + '>Teknisi</option>' +
          '<option value="kasir"'   + (d.role === 'kasir'   ? ' selected' : '') + '>Kasir</option>' +
        '</select>' +
      '</div>' +
      '<div class="form-group">' +
        '<label class="form-label">Status</label>' +
        '<select name="status" class="form-control">' +
          '<option value="aktif"'    + (d.status === 'aktif'    ? ' selected' : '') + '>Aktif</option>' +
          '<option value="nonaktif"' + (d.status === 'nonaktif' ? ' selected' : '') + '>Non-aktif</option>' +
        '</select>' +
      '</div>'
    );
  });
});
</script>
HTML;

require_once __DIR__ . '/../../template.php';
