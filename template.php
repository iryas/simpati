<?php
// ============================================================
//  KAHFINET - Template Layout Utama
//  Dipanggil dari setiap views.php modul
//  Variabel wajib: $page_title, $content, $active_menu
// ============================================================
require_once __DIR__ . '/system/init.php';
auth_check();
$user = current_user();
?>
<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= clean($page_title ?? 'Dashboard') ?> — <?= APP_NAME ?></title>
  <link rel="icon" type="image/svg+xml" href="<?= BASE_URL ?>assets/img/logo-icon.svg">
  <!-- Bootstrap 4 -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/4.6.2/css/bootstrap.min.css">
  <!-- Font Awesome 5 -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <!-- Toastr -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/2.1.4/toastr.min.css">
  <!-- DataTables (Bootstrap 4) -->
  <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap4.min.css">
  <!-- Select2 -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.1.0-rc.0/css/select2.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/select2-bootstrap-theme/0.1.0-beta.10/select2-bootstrap.min.css">
  <!-- Custom CSS -->
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css?v=<?= filemtime(__DIR__ . '/assets/css/style.css') ?>">
</head>

<body>

  <!-- ── Preloader ─────────────────────────────────────────────── -->
  <div id="preloader">
    <div class="preloader-content">
      <div class="logo-pulse">
        <svg viewBox="0 0 200 200" width="46" height="46" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round">
          <path d="M 26 92 L 93 25 Q 100 18 107 25 L 174 92" stroke-width="14"/>
          <path d="M 44 88 L 44 148 Q 44 160 56 160" stroke-width="14"/>
          <path d="M 156 88 L 156 148 Q 156 160 144 160" stroke-width="14"/>
          <path d="M 84 128 Q 100 110 116 128" stroke-width="11"/>
          <path d="M 68 106 Q 100 72 132 106" stroke-width="11"/>
          <circle cx="100" cy="150" r="8" fill="currentColor" stroke="none"/>
        </svg>
      </div>
      <div class="preloader-appname"><?= APP_NAME ?></div>
      <div class="loading-bar">
        <div class="loading-progress"></div>
      </div>
      <p class="loading-text">Memuat...</p>
    </div>
  </div>

  <!-- ── Sidebar ──────────────────────────────────────────────── -->
  <nav id="sidebar">
    <div class="sidebar-brand">
      <div class="brand-icon">
        <svg viewBox="0 0 200 200" width="20" height="20" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round">
          <path d="M 26 92 L 93 25 Q 100 18 107 25 L 174 92" stroke-width="14"/>
          <path d="M 44 88 L 44 148 Q 44 160 56 160" stroke-width="14"/>
          <path d="M 156 88 L 156 148 Q 156 160 144 160" stroke-width="14"/>
          <path d="M 84 128 Q 100 110 116 128" stroke-width="11"/>
          <path d="M 68 106 Q 100 72 132 106" stroke-width="11"/>
          <circle cx="100" cy="150" r="8" fill="currentColor" stroke="none"/>
        </svg>
      </div>
      <div class="brand-text">
        <span class="brand-name">SIMPATI</span>
        <span class="brand-sub">Manajemen ISP</span>
      </div>
    </div>

    <div class="sidebar-user">
      <div class="user-avatar">
        <i class="fas fa-user-circle"></i>
      </div>
      <div class="user-info">
        <div class="user-name"><?= clean($user['nama']) ?></div>
        <?= badge_role($user['role']) ?>
      </div>
    </div>

    <ul class="sidebar-nav">
      <li class="nav-label">MENU UTAMA</li>

      <li class="nav-item <?= ($active_menu ?? '') === 'dashboard' ? 'active' : '' ?>">
        <a href="<?= BASE_URL ?>index.php">
          <i class="fas fa-tachometer-alt"></i><span>Dashboard</span>
        </a>
      </li>

      <?php if ($user['role'] === ROLE_ADMIN): ?>
        <?php $masterActive = in_array($active_menu ?? '', ['area', 'pelanggan', 'status_pelanggan'], true); ?>
        <li class="nav-item nav-item-dropdown <?= $masterActive ? 'active open' : '' ?>">
          <a href="#submenuMaster" data-toggle="collapse" class="nav-link-dropdown"
            aria-expanded="<?= $masterActive ? 'true' : 'false' ?>">
            <i class="fas fa-database"></i><span>Data Master</span>
            <i class="fas fa-chevron-down nav-dropdown-caret"></i>
          </a>
          <ul class="collapse nav-submenu <?= $masterActive ? 'show' : '' ?>" id="submenuMaster">
            <li class="<?= ($active_menu ?? '') === 'area' ? 'active' : '' ?>">
              <a href="<?= BASE_URL ?>modules/area/views.php">Data Area</a>
            </li>
            <li class="<?= ($active_menu ?? '') === 'pelanggan' ? 'active' : '' ?>">
              <a href="<?= BASE_URL ?>modules/pelanggan/views.php">Daftar Pelanggan</a>
            </li>
            <li class="<?= ($active_menu ?? '') === 'status_pelanggan' ? 'active' : '' ?>">
              <a href="<?= BASE_URL ?>modules/pelanggan/status.php">Status Pelanggan</a>
            </li>
          </ul>
        </li>
        <li class="nav-item <?= ($active_menu ?? '') === 'paket' ? 'active' : '' ?>">
          <a href="<?= BASE_URL ?>modules/paket/views.php">
            <i class="fas fa-box-open"></i><span>Paket Internet</span>
          </a>
        </li>
      <?php else: ?>
        <li class="nav-item <?= ($active_menu ?? '') === 'pelanggan' ? 'active' : '' ?>">
          <a href="<?= BASE_URL ?>modules/pelanggan/views.php">
            <i class="fas fa-users"></i><span>Daftar Pelanggan</span>
          </a>
        </li>
      <?php endif; ?>

      <?php if (in_array($user['role'], [ROLE_ADMIN, ROLE_KEUANGAN])): ?>
        <li class="nav-label">KEUANGAN</li>
        <li class="nav-item <?= ($active_menu ?? '') === 'pembayaran' ? 'active' : '' ?>">
          <a href="<?= BASE_URL ?>modules/pembayaran/views.php">
            <i class="fas fa-money-bill-wave"></i><span>Pembayaran</span>
          </a>
        </li>
        <li class="nav-item <?= ($active_menu ?? '') === 'pengeluaran' ? 'active' : '' ?>">
          <a href="<?= BASE_URL ?>modules/pengeluaran/views.php">
            <i class="fas fa-receipt"></i><span>Pengeluaran</span>
          </a>
        </li>
        <?php $laporanActive = in_array($active_menu ?? '', ['laporan_pendapatan', 'laporan_keuntungan'], true); ?>
        <li class="nav-item nav-item-dropdown <?= $laporanActive ? 'active open' : '' ?>">
          <a href="#submenuLaporan" data-toggle="collapse" class="nav-link-dropdown"
            aria-expanded="<?= $laporanActive ? 'true' : 'false' ?>">
            <i class="fas fa-chart-bar"></i><span>Laporan</span>
            <i class="fas fa-chevron-down nav-dropdown-caret"></i>
          </a>
          <ul class="collapse nav-submenu <?= $laporanActive ? 'show' : '' ?>" id="submenuLaporan">
            <li class="<?= ($active_menu ?? '') === 'laporan_pendapatan' ? 'active' : '' ?>">
              <a href="<?= BASE_URL ?>modules/laporan/views.php">Pendapatan</a>
            </li>
            <li class="<?= ($active_menu ?? '') === 'laporan_keuntungan' ? 'active' : '' ?>">
              <a href="<?= BASE_URL ?>modules/laporan/keuntungan.php">Keuntungan</a>
            </li>
          </ul>
        </li>
      <?php endif; ?>

      <?php if (in_array($user['role'], [ROLE_ADMIN, ROLE_TEKNISI])): ?>
        <li class="nav-label">JARINGAN</li>
        <li class="nav-item <?= ($active_menu ?? '') === 'monitoring' ? 'active' : '' ?>">
          <a href="<?= BASE_URL ?>monitoring/index.php">
            <i class="fas fa-heartbeat"></i><span>Monitoring Jaringan</span>
          </a>
        </li>
        <?php $mikrotikActive = in_array($active_menu ?? '', ['mikrotik_pengaturan', 'mikrotik_profile', 'mikrotik_secret'], true); ?>
        <li class="nav-item nav-item-dropdown <?= $mikrotikActive ? 'active open' : '' ?>">
          <a href="#submenuMikrotik" data-toggle="collapse" class="nav-link-dropdown"
            aria-expanded="<?= $mikrotikActive ? 'true' : 'false' ?>">
            <i class="fas fa-network-wired"></i><span>Mikrotik</span>
            <i class="fas fa-chevron-down nav-dropdown-caret"></i>
          </a>
          <ul class="collapse nav-submenu <?= $mikrotikActive ? 'show' : '' ?>" id="submenuMikrotik">
            <?php if ($user['role'] === ROLE_ADMIN): ?>
            <li class="<?= ($active_menu ?? '') === 'mikrotik_pengaturan' ? 'active' : '' ?>">
              <a href="<?= BASE_URL ?>modules/mikrotik/views.php">Pengaturan</a>
            </li>
            <?php endif; ?>
            <li class="<?= ($active_menu ?? '') === 'mikrotik_profile' ? 'active' : '' ?>">
              <a href="<?= BASE_URL ?>modules/mikrotik/profile.php">Profile</a>
            </li>
            <li class="<?= ($active_menu ?? '') === 'mikrotik_secret' ? 'active' : '' ?>">
              <a href="<?= BASE_URL ?>modules/mikrotik/secret.php">Secret</a>
            </li>
          </ul>
        </li>
        <?php $acsActive = in_array($active_menu ?? '', ['acs_pengaturan', 'acs_device'], true); ?>
        <li class="nav-item nav-item-dropdown <?= $acsActive ? 'active open' : '' ?>">
          <a href="#submenuAcs" data-toggle="collapse" class="nav-link-dropdown"
            aria-expanded="<?= $acsActive ? 'true' : 'false' ?>">
            <i class="fas fa-satellite-dish"></i><span>ACS</span>
            <i class="fas fa-chevron-down nav-dropdown-caret"></i>
          </a>
          <ul class="collapse nav-submenu <?= $acsActive ? 'show' : '' ?>" id="submenuAcs">
            <?php if ($user['role'] === ROLE_ADMIN): ?>
            <li class="<?= ($active_menu ?? '') === 'acs_pengaturan' ? 'active' : '' ?>">
              <a href="<?= BASE_URL ?>modules/acs/views.php">Pengaturan</a>
            </li>
            <?php endif; ?>
            <li class="<?= ($active_menu ?? '') === 'acs_device' ? 'active' : '' ?>">
              <a href="<?= BASE_URL ?>modules/acs/device.php">Device ONU</a>
            </li>
          </ul>
        </li>
      <?php endif; ?>

      <?php if (in_array($user['role'], [ROLE_ADMIN, ROLE_TEKNISI])): ?>
        <li class="nav-label">LAYANAN</li>
        <?php $tiketBaru = (int)(db_row("SELECT COUNT(*) c FROM tiket_gangguan WHERE status = 'baru'")['c'] ?? 0); ?>
        <li class="nav-item <?= ($active_menu ?? '') === 'tiket' ? 'active' : '' ?>">
          <a href="<?= BASE_URL ?>modules/tiket/views.php">
            <i class="fas fa-headset"></i><span>Tiket Gangguan</span>
            <?php if ($tiketBaru > 0): ?>
              <span class="badge badge-danger" style="margin-left:auto"><?= $tiketBaru ?></span>
            <?php endif; ?>
          </a>
        </li>
        <?php if ($user['role'] === ROLE_ADMIN): ?>
        <li class="nav-item <?= ($active_menu ?? '') === 'pengumuman' ? 'active' : '' ?>">
          <a href="<?= BASE_URL ?>modules/pengumuman/views.php">
            <i class="fas fa-bullhorn"></i><span>Pengumuman</span>
          </a>
        </li>
        <?php endif; ?>
      <?php endif; ?>

      <?php if ($user['role'] === ROLE_ADMIN): ?>
        <li class="nav-label">PENGATURAN</li>
        <li class="nav-item <?= ($active_menu ?? '') === 'pengaturan' ? 'active' : '' ?>">
          <a href="<?= BASE_URL ?>modules/pengaturan/views.php">
            <i class="fas fa-cog"></i><span>Pengaturan</span>
          </a>
        </li>
        <li class="nav-item <?= ($active_menu ?? '') === 'pengguna' ? 'active' : '' ?>">
          <a href="<?= BASE_URL ?>modules/pengguna/views.php">
            <i class="fas fa-user-shield"></i><span>Pengguna</span>
          </a>
        </li>
        <li class="nav-item <?= ($active_menu ?? '') === 'template_wa' ? 'active' : '' ?>">
          <a href="<?= BASE_URL ?>modules/template_wa/views.php">
            <i class="fab fa-whatsapp"></i><span>Template WA</span>
          </a>
        </li>
      <?php endif; ?>

        <li class="nav-item <?= ($active_menu ?? '') === 'tentang' ? 'active' : '' ?>">
          <a href="<?= BASE_URL ?>modules/tentang/views.php">
            <i class="fas fa-info-circle"></i><span>Tentang Aplikasi</span>
          </a>
        </li>
    </ul>

    <div class="sidebar-footer">
      <a href="<?= BASE_URL ?>act.php?action=logout" class="btn-logout">
        <i class="fas fa-sign-out-alt"></i><span>Keluar</span>
      </a>
    </div>
  </nav>

  <!-- ── Wrapper Konten ─────────────────────────────────────────── -->
  <div id="content-wrapper">

    <!-- Topbar -->
    <nav class="topbar">
      <button id="sidebar-toggle" class="btn btn-link">
        <i class="fas fa-bars"></i>
      </button>
      <div class="topbar-title"><?= clean($page_title ?? 'Dashboard') ?></div>
      <div class="topbar-right">

        <!-- Jam & Tanggal -->
        <div class="topbar-clock d-none d-md-flex align-items-center" style="gap:6px">
          <i class="far fa-calendar-alt"></i>
          <span><?= date('d M Y') ?></span>
          <span class="text-muted mx-1">|</span>
          <i class="far fa-clock"></i>
          <span id="topbar-jam">--:--:--</span>
        </div>

        <!-- Indikator Status Mikrotik & ACS (Admin & Teknisi) -->
        <?php if (in_array($user['role'], [ROLE_ADMIN, ROLE_TEKNISI])): ?>
        <div class="topbar-netstatus d-none d-lg-flex" id="topbarNetStatus" title="Status jaringan">
          <span class="netstatus-item" title="Mikrotik">
            <span class="netstatus-dot" id="nsDotMikrotik"></span>
            <span>Mikrotik</span>
          </span>
          <span class="netstatus-item" title="ACS / GenieACS">
            <span class="netstatus-dot" id="nsDotAcs"></span>
            <span>ACS</span>
          </span>
        </div>
        <?php endif; ?>

        <!-- Profile Dropdown -->
        <div class="dropdown">
          <button class="topbar-profile-btn dropdown-toggle" data-toggle="dropdown"
                  aria-haspopup="true" aria-expanded="false" id="dropdownProfil">
            <div class="topbar-avatar"><i class="fas fa-user-circle"></i></div>
            <span class="topbar-username d-none d-sm-inline"><?= clean($user['nama']) ?></span>
            <i class="fas fa-chevron-down topbar-caret"></i>
          </button>
          <div class="dropdown-menu dropdown-menu-right topbar-dropdown-menu"
               aria-labelledby="dropdownProfil">
            <div class="dropdown-header">
              <div class="font-weight-bold" style="font-size:14px;color:#1e293b"><?= clean($user['nama']) ?></div>
              <div class="mt-1"><?= badge_role($user['role']) ?></div>
            </div>
            <div class="dropdown-divider"></div>
            <a class="dropdown-item" href="#"
               data-toggle="modal" data-target="#modalGantiPassword">
              <i class="fas fa-key mr-2 text-warning"></i>Ganti Password
            </a>
            <div class="dropdown-divider"></div>
            <a class="dropdown-item text-danger"
               href="<?= BASE_URL ?>act.php?action=logout">
              <i class="fas fa-sign-out-alt mr-2"></i>Keluar
            </a>
          </div>
        </div>

      </div>
    </nav>

    <!-- Konten Halaman -->
    <main class="main-content">
      <?php render_flash(); ?>
      <?= $content ?? '' ?>
    </main>

    <footer class="main-footer">
      <small>&copy; <?= date('Y') ?> <?= APP_NAME ?> — v<?= APP_VERSION ?></small>
    </footer>
  </div>

  <!-- jQuery & Bootstrap JS -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/4.6.2/js/bootstrap.bundle.min.js"></script>
  <!-- DataTables (Bootstrap 4) -->
  <script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap4.min.js"></script>
  <!-- Select2 -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.1.0-rc.0/js/select2.min.js"></script>
  <!-- Toastr -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/2.1.4/toastr.min.js"></script>
  <script>
    toastr.options = {
      closeButton: true,
      progressBar: true,
      positionClass: 'toast-top-right',
      timeOut: 4000,
      newestOnTop: true
    };
    if (window.__flash && typeof toastr[window.__flash.type] === 'function') {
      toastr[window.__flash.type](window.__flash.msg);
    }
  </script>
  <!-- Custom JS -->
  <script src="<?= BASE_URL ?>assets/js/app.js?v=<?= filemtime(__DIR__ . '/assets/js/app.js') ?>"></script>
  <!-- Extra JS per-halaman (dirender SETELAH jQuery siap) -->
  <?= $extra_js ?? '' ?>

  <!-- Modal Ganti Password -->
  <div class="modal fade" id="modalGantiPassword" tabindex="-1">
    <div class="modal-dialog modal-sm">
      <div class="modal-content">
        <form method="POST" action="<?= BASE_URL ?>act.php" id="formGantiPassword">
          <?php csrf_field(); ?>
          <input type="hidden" name="action" value="ganti_password">
          <div class="modal-header">
            <h6 class="modal-title font-weight-bold">
              <i class="fas fa-key mr-2 text-warning"></i>Ganti Password
            </h6>
            <button type="button" class="close" data-dismiss="modal">&times;</button>
          </div>
          <div class="modal-body">
            <div class="form-group">
              <label class="form-label font-weight-bold" style="font-size:13px">Password Lama</label>
              <input type="password" name="password_lama" class="form-control form-control-sm" required>
            </div>
            <div class="form-group">
              <label class="form-label font-weight-bold" style="font-size:13px">Password Baru</label>
              <input type="password" name="password_baru" id="inputPasswordBaru"
                     class="form-control form-control-sm" required minlength="6">
              <small class="text-muted">Minimal 6 karakter.</small>
            </div>
            <div class="form-group mb-0">
              <label class="form-label font-weight-bold" style="font-size:13px">Konfirmasi Password Baru</label>
              <input type="password" name="password_konfirmasi" id="inputPasswordKonfirmasi"
                     class="form-control form-control-sm" required>
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

  <script>
    // Real-time clock
    (function tick() {
      var now = new Date();
      var p   = function(n){ return String(n).padStart(2,'0'); };
      var el  = document.getElementById('topbar-jam');
      if (el) el.textContent = p(now.getHours())+':'+p(now.getMinutes())+':'+p(now.getSeconds());
      setTimeout(tick, 1000);
    })();

    // Validasi konfirmasi password sebelum submit
    $('#formGantiPassword').on('submit', function() {
      if ($('#inputPasswordBaru').val() !== $('#inputPasswordKonfirmasi').val()) {
        toastr.error('Konfirmasi password tidak cocok.');
        return false;
      }
    });

    // Reset form saat modal ditutup
    $('#modalGantiPassword').on('hidden.bs.modal', function() {
      $(this).find('form')[0].reset();
    });

    <?php if (in_array($user['role'], [ROLE_ADMIN, ROLE_TEKNISI])): ?>
    // Cek status Mikrotik & ACS via AJAX (non-blocking)
    (function checkNetStatus() {
      function setDot(id, online) {
        var dot = document.getElementById(id);
        if (dot) dot.className = 'netstatus-dot ' + (online ? 'online' : 'offline');
      }
      $.getJSON('<?= BASE_URL ?>api/net_status.php')
        .done(function(res) {
          if (res.success) {
            setDot('nsDotMikrotik', res.data.mikrotik);
            setDot('nsDotAcs', res.data.acs);
          }
        })
        .fail(function() {
          setDot('nsDotMikrotik', false);
          setDot('nsDotAcs', false);
        });
      setTimeout(checkNetStatus, 60000);
    })();
    <?php endif; ?>

  // Preloader: sembunyikan saat halaman selesai load
  $(window).on('load', function () {
    setTimeout(function () {
      $('#preloader').addClass('hide');
    }, 300);
  });
  </script>
</body>

</html>