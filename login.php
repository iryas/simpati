<?php
// ============================================================
//  KAHFINET - Halaman Login
// ============================================================
require_once __DIR__ . '/system/init.php';

if (is_logged_in()) redirect(BASE_URL . 'index.php');
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login — <?= APP_NAME ?></title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/4.6.2/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    *, *::before, *::after { box-sizing: border-box; }

    body {
      font-family: 'Inter', sans-serif;
      min-height: 100vh;
      display: flex;
      align-items: stretch;
      margin: 0;
      background: #0f2744;
    }

    /* ── Panel Kiri ─────────────────────────────────────── */
    .login-left {
      flex: 1;
      background: linear-gradient(160deg, #0a1e3d 0%, #0f2744 40%, #1a3a6b 75%, #1e4a8a 100%);
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding: 48px 40px;
      position: relative;
      overflow: hidden;
    }

    .deco-circle {
      position: absolute;
      border-radius: 50%;
      border: 1px solid rgba(245,158,11,.1);
      pointer-events: none;
    }
    .deco-circle-1 { width: 380px; height: 380px; top: -100px; left: -100px; }
    .deco-circle-2 { width: 260px; height: 260px; bottom: -60px; right: -60px; }
    .deco-circle-3 { width: 140px; height: 140px; bottom: 80px; left: 40px; border-color: rgba(245,158,11,.06); }

    .brand-icon {
      width: 72px; height: 72px;
      background: linear-gradient(135deg, #f59e0b, #fbbf24);
      border-radius: 20px;
      display: flex; align-items: center; justify-content: center;
      font-size: 32px; color: #1a1a1a;
      margin-bottom: 22px;
      box-shadow: 0 12px 32px rgba(245,158,11,.3);
    }

    .brand-name {
      font-size: 28px; font-weight: 700; color: #fff;
      letter-spacing: .5px; text-align: center;
    }

    .brand-sub {
      font-size: 13px; color: rgba(255,255,255,.4);
      text-align: center; margin-top: 6px;
    }

    .brand-divider {
      width: 44px; height: 3px;
      background: linear-gradient(90deg, #f59e0b, #fbbf24);
      border-radius: 2px;
      margin: 22px auto;
    }

    .feature-item {
      display: flex; align-items: center; gap: 12px;
      margin-bottom: 12px;
    }

    .feature-dot {
      width: 7px; height: 7px;
      border-radius: 50%;
      background: #f59e0b;
      flex-shrink: 0;
    }

    .feature-text {
      font-size: 13px; color: rgba(255,255,255,.5);
    }

    /* ── Panel Kanan ─────────────────────────────────────── */
    .login-right {
      width: 420px;
      flex-shrink: 0;
      background: #fff;
      display: flex;
      flex-direction: column;
      justify-content: center;
      padding: 48px 40px;
    }

    .form-heading { font-size: 22px; font-weight: 700; color: #0f2744; margin-bottom: 4px; }
    .form-subhead { font-size: 13px; color: #94a3b8; margin-bottom: 30px; }

    .form-label-custom {
      font-size: 11px; font-weight: 700;
      color: #475569; letter-spacing: .6px;
      text-transform: uppercase; margin-bottom: 6px;
      display: block;
    }

    .input-wrap {
      border: 1.5px solid #e2e8f0;
      border-radius: 9px;
      overflow: hidden;
      background: #f8fafc;
      display: flex;
      align-items: stretch;
      transition: border-color .2s, box-shadow .2s;
      margin-bottom: 18px;
    }

    .input-wrap:focus-within {
      border-color: #f59e0b;
      box-shadow: 0 0 0 3px rgba(245,158,11,.12);
      background: #fff;
    }

    .input-icon {
      display: flex; align-items: center; justify-content: center;
      width: 42px; color: #94a3b8; font-size: 14px;
      flex-shrink: 0;
    }

    .input-wrap input {
      flex: 1; border: none; background: transparent;
      font-size: 13.5px; color: #1e293b;
      padding: 10px 10px 10px 0;
      outline: none;
      font-family: 'Inter', sans-serif;
    }

    .input-wrap input::placeholder { color: #cbd5e1; }

    .toggle-pass {
      display: flex; align-items: center; padding: 0 12px;
      color: #94a3b8; cursor: pointer; background: none; border: none;
      font-size: 14px;
    }

    .toggle-pass:hover { color: #475569; }

    .remember-row {
      display: flex; align-items: center; justify-content: space-between;
      margin-bottom: 22px;
    }

    .custom-control-label { font-size: 13px; color: #64748b; cursor: pointer; }
    .custom-checkbox .custom-control-input:checked ~ .custom-control-label::before {
      background-color: #f59e0b; border-color: #f59e0b;
    }

    .btn-login {
      width: 100%;
      background: linear-gradient(135deg, #0f2744 0%, #1a3a6b 100%);
      color: #fff;
      border: none;
      border-radius: 9px;
      padding: 12px;
      font-size: 14px;
      font-weight: 700;
      letter-spacing: .3px;
      cursor: pointer;
      position: relative;
      overflow: hidden;
      transition: opacity .2s;
    }

    .btn-login::after {
      content: '';
      position: absolute;
      right: 0; top: 0; bottom: 0;
      width: 5px;
      background: linear-gradient(180deg, #f59e0b, #d97706);
    }

    .btn-login:hover { opacity: .9; }
    .btn-login:disabled { opacity: .6; cursor: not-allowed; }

    .login-footer {
      margin-top: 22px; text-align: center;
      font-size: 11px; color: #94a3b8;
    }

    .login-footer strong { color: #0f2744; }

    /* Alert */
    #loginAlert {
      border-radius: 8px;
      font-size: 13px;
      padding: 10px 14px;
      margin-bottom: 18px;
    }

    /* Responsive */
    @media (max-width: 768px) {
      .login-left { display: none; }
      .login-right { width: 100%; padding: 40px 28px; }
    }
  </style>
</head>
<body>

  <!-- Panel Kiri -->
  <div class="login-left">
    <div class="deco-circle deco-circle-1"></div>
    <div class="deco-circle deco-circle-2"></div>
    <div class="deco-circle deco-circle-3"></div>

    <div class="brand-icon"><i class="fas fa-broadcast-tower"></i></div>
    <div class="brand-name"><?= APP_NAME ?></div>
    <div class="brand-sub">ISP Management System</div>
    <div class="brand-divider"></div>

    <div class="feature-item">
      <div class="feature-dot"></div>
      <div class="feature-text">Manajemen pelanggan &amp; pembayaran</div>
    </div>
    <div class="feature-item">
      <div class="feature-dot"></div>
      <div class="feature-text">Integrasi Mikrotik &amp; GenieACS</div>
    </div>
    <div class="feature-item">
      <div class="feature-dot"></div>
      <div class="feature-text">Laporan keuangan real-time</div>
    </div>
    <div class="feature-item">
      <div class="feature-dot"></div>
      <div class="feature-text">Role-based access control</div>
    </div>
  </div>

  <!-- Panel Kanan -->
  <div class="login-right">
    <div class="form-heading">Selamat datang</div>
    <div class="form-subhead">Masuk ke akun Anda untuk melanjutkan</div>

    <div id="loginAlert" class="alert d-none">
      <i id="loginAlertIcon" class="fas mr-2"></i><span id="loginAlertMsg"></span>
    </div>

    <form method="POST" autocomplete="off" id="loginForm">
      <?php csrf_field(); ?>

      <label class="form-label-custom">Username</label>
      <div class="input-wrap">
        <div class="input-icon"><i class="fas fa-user fa-sm"></i></div>
        <input type="text" name="username" placeholder="Masukkan username" maxlength="50" required autofocus>
      </div>

      <label class="form-label-custom">Password</label>
      <div class="input-wrap">
        <div class="input-icon"><i class="fas fa-lock fa-sm"></i></div>
        <input type="password" name="password" id="inputPassword" placeholder="Masukkan password" maxlength="255" required>
        <button type="button" class="toggle-pass" id="togglePassword" tabindex="-1">
          <i class="fas fa-eye fa-sm"></i>
        </button>
      </div>

      <div class="remember-row" style="flex-direction:column;align-items:flex-start;gap:11px">
        <div class="custom-control custom-checkbox">
          <input type="checkbox" class="custom-control-input" id="remember_me" name="remember_me">
          <label class="custom-control-label" for="remember_me">Ingat saya selama <?= REMEMBER_ME_DAYS ?> hari</label>
        </div>
        <div class="custom-control custom-checkbox">
          <input type="checkbox" class="custom-control-input" id="akses_monitoring" name="akses_monitoring">
          <label class="custom-control-label" for="akses_monitoring">
            <i class="fas fa-heartbeat mr-1" style="color:#f59e0b"></i>Buka Monitoring Jaringan setelah masuk
            <span style="display:block;font-size:11px;color:#94a3b8;margin-top:1px">Khusus Admin &amp; Teknisi</span>
          </label>
        </div>
      </div>

      <button type="submit" class="btn-login" id="btnLogin">
        <i class="fas fa-sign-in-alt mr-2"></i>Masuk
      </button>
    </form>

    <div class="login-footer">
      &copy; <?= date('Y') ?> <strong><?= clean(app_setting('nama_isp', 'KahfiNet')) ?></strong>
      &nbsp;&middot;&nbsp; <?= APP_NAME ?> v<?= APP_VERSION ?>
    </div>
  </div>

  <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
  <script>
    $('#togglePassword').on('click', function () {
      const input = $('#inputPassword');
      const icon  = $(this).find('i');
      if (input.attr('type') === 'password') {
        input.attr('type', 'text');
        icon.removeClass('fa-eye').addClass('fa-eye-slash');
      } else {
        input.attr('type', 'password');
        icon.removeClass('fa-eye-slash').addClass('fa-eye');
      }
    });

    function showAlert(type, msg) {
      const icons = { danger: 'exclamation-circle', warning: 'clock' };
      $('#loginAlert')
        .removeClass('d-none alert-danger alert-warning')
        .addClass('alert-' + type);
      $('#loginAlertIcon').removeClass().addClass('fas fa-' + (icons[type] || 'exclamation-circle') + ' mr-2');
      $('#loginAlertMsg').text(msg);
    }

    function hideAlert()              { $('#loginAlert').addClass('d-none'); }
    function setFormDisabled(disabled){ $('#loginForm').find('input, button[type=submit]').prop('disabled', disabled); }
    function resetSubmitBtn()         { $('#btnLogin').html('<i class="fas fa-sign-in-alt mr-2"></i>Masuk'); }

    $('#loginForm').on('submit', function (e) {
      e.preventDefault();
      hideAlert();

      const $btn = $('#btnLogin');
      $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-2"></i>Memproses...');

      $.ajax({
        url: '<?= BASE_URL ?>act.php?action=login',
        method: 'POST',
        data: $(this).serialize(),
        dataType: 'json'
      }).done(function (res) {
        if (res.success) {
          window.location.href = res.data.redirect || '<?= BASE_URL ?>index.php';
          return;
        }
        if (res.data && res.data.lockout) {
          showAlert('warning', res.msg);
          setFormDisabled(true);
        } else {
          showAlert('danger', res.msg);
          $btn.prop('disabled', false);
        }
        resetSubmitBtn();
      }).fail(function () {
        showAlert('danger', 'Terjadi kesalahan. Silakan coba lagi.');
        $btn.prop('disabled', false);
        resetSubmitBtn();
      });
    });
  </script>
</body>
</html>
