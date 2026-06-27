<?php
// ============================================================
//  KAHFINET - Halaman Login (Diperkuat)
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
    :root {
      --brand: #2563eb;
    }

    *,
    *::before,
    *::after {
      box-sizing: border-box;
    }

    body {
      font-family: 'Inter', sans-serif;
      background: linear-gradient(135deg, #1a1f2e 0%, #2d3748 100%);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .login-wrap {
      width: 100%;
      max-width: 420px;
      padding: 16px;
    }

    .login-logo {
      text-align: center;
      margin-bottom: 28px;
    }

    .logo-icon {
      width: 64px;
      height: 64px;
      background: var(--brand);
      border-radius: 18px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      font-size: 28px;
      color: #fff;
      margin-bottom: 12px;
      box-shadow: 0 8px 24px rgba(37, 99, 235, .4);
    }

    .login-logo h4 {
      color: #fff;
      font-weight: 700;
      margin: 0;
    }

    .login-logo p {
      color: #94a3b8;
      font-size: 13px;
      margin: 0;
    }

    .login-card {
      background: #fff;
      border-radius: 14px;
      padding: 32px 28px;
      box-shadow: 0 20px 60px rgba(0, 0, 0, .3);
    }

    .login-card h5 {
      font-size: 18px;
      font-weight: 700;
      color: #1e293b;
      margin-bottom: 22px;
    }

    .form-group label {
      font-size: 13px;
      font-weight: 600;
      color: #374151;
    }

    /* Input group — border nyambung semua sisi */
    .input-group {
      border: 1.5px solid #cbd5e1;
      border-radius: 8px;
      overflow: hidden;
      background: #fff;
      transition: border-color .2s;
    }

    .input-group:focus-within {
      border-color: var(--brand);
      box-shadow: 0 0 0 3px rgba(37, 99, 235, .12);
    }

    .input-group-text {
      background: #f8fafc;
      border: none;
      color: #94a3b8;
      padding: 0 12px;
    }

    .input-group .form-control {
      border: none;
      background: #fff;
      font-size: 13.5px;
      padding: .5rem .6rem;
      box-shadow: none !important;
      outline: none;
    }

    .input-group .form-control:focus {
      background: #fff;
    }

    /* Tombol toggle password */
    .input-group .btn-outline-secondary {
      border: none;
      border-left: 1px solid #e2e8f0;
      background: #f8fafc;
      color: #94a3b8;
      border-radius: 0;
      padding: 0 12px;
    }

    .input-group .btn-outline-secondary:hover {
      background: #f1f5f9;
      color: #475569;
    }

    .input-group .btn-outline-secondary:focus {
      box-shadow: none;
    }

    .btn-login {
      background: var(--brand);
      border: none;
      font-weight: 600;
      font-size: 14px;
      padding: 10px;
      border-radius: 8px;
      letter-spacing: .3px;
    }

    .btn-login:hover {
      background: #1d4ed8;
    }

    .btn-login:disabled {
      opacity: .65;
      cursor: not-allowed;
    }

    .custom-control-label {
      font-size: 13px;
    }

    .version-note {
      text-align: center;
      color: #64748b;
      font-size: 12px;
      margin-top: 18px;
    }
  </style>
</head>

<body>
  <div class="login-wrap">
    <div class="login-logo">
      <div class="logo-icon"><i class="fas fa-wifi"></i></div>
      <h4><?= APP_NAME ?></h4>
      <p>Portal Manajemen Pelanggan</p>
    </div>

    <div class="login-card">
      <h5>Masuk ke Sistem</h5>

      <div id="loginAlert" class="alert py-2 px-3 d-none" style="font-size:13px;">
        <i id="loginAlertIcon" class="fas mr-1"></i>
        <span id="loginAlertMsg"></span>
      </div>

      <form method="POST" autocomplete="off" id="loginForm">
        <?php csrf_field(); ?>

        <div class="form-group">
          <label>Username</label>
          <div class="input-group">
            <div class="input-group-prepend">
              <span class="input-group-text"><i class="fas fa-user fa-sm"></i></span>
            </div>
            <input type="text" name="username" class="form-control"
              placeholder="Masukkan username"
              maxlength="50"
              required autofocus>
          </div>
        </div>

        <div class="form-group">
          <label>Password</label>
          <div class="input-group">
            <div class="input-group-prepend">
              <span class="input-group-text"><i class="fas fa-lock fa-sm"></i></span>
            </div>
            <input type="password" name="password" id="inputPassword"
              class="form-control" placeholder="Masukkan password"
              maxlength="255"
              required>
            <div class="input-group-append">
              <button type="button" class="btn btn-outline-secondary border-left-0"
                id="togglePassword" tabindex="-1">
                <i class="fas fa-eye fa-sm"></i>
              </button>
            </div>
          </div>
        </div>

        <div class="form-group">
          <div class="custom-control custom-checkbox">
            <input type="checkbox" class="custom-control-input" id="remember_me" name="remember_me">
            <label class="custom-control-label" for="remember_me">Ingat saya selama <?= REMEMBER_ME_DAYS ?> hari</label>
          </div>
        </div>

        <button type="submit" class="btn btn-primary btn-login btn-block" id="btnLogin">
          <i class="fas fa-sign-in-alt mr-2"></i>Masuk
        </button>
      </form>
    </div>

    <p class="version-note">&copy; <?= date('Y') ?> <?= APP_NAME ?> v<?= APP_VERSION ?></p>
  </div>

  <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
  <script>
    // Toggle show/hide password
    $('#togglePassword').on('click', function() {
      const input = $('#inputPassword');
      const icon = $(this).find('i');
      if (input.attr('type') === 'password') {
        input.attr('type', 'text');
        icon.removeClass('fa-eye').addClass('fa-eye-slash');
      } else {
        input.attr('type', 'password');
        icon.removeClass('fa-eye-slash').addClass('fa-eye');
      }
    });

    // Tampilkan alert (success belum dipakai di sini, hanya warning/danger)
    function showAlert(type, msg) {
      const icons = { danger: 'exclamation-circle', warning: 'clock' };
      $('#loginAlert')
        .removeClass('d-none alert-danger alert-warning')
        .addClass('alert-' + type)
        .show();
      $('#loginAlertIcon').removeClass().addClass('fas fa-' + (icons[type] || 'exclamation-circle') + ' mr-1');
      $('#loginAlertMsg').text(msg);
    }

    function hideAlert() {
      $('#loginAlert').addClass('d-none');
    }

    function setFormDisabled(disabled) {
      $('#loginForm').find('input, button[type=submit]').prop('disabled', disabled);
    }

    function resetSubmitBtn() {
      $('#btnLogin').html('<i class="fas fa-sign-in-alt mr-2"></i>Masuk');
    }

    // Submit login via AJAX
    $('#loginForm').on('submit', function(e) {
      e.preventDefault();
      hideAlert();

      const $btn = $('#btnLogin');
      $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-2"></i>Memproses...');

      $.ajax({
        url: '<?= BASE_URL ?>act.php?action=login',
        method: 'POST',
        data: $(this).serialize(),
        dataType: 'json'
      }).done(function(res) {
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
      }).fail(function() {
        showAlert('danger', 'Terjadi kesalahan. Silakan coba lagi.');
        $btn.prop('disabled', false);
        resetSubmitBtn();
      });
    });
  </script>
</body>

</html>