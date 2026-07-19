<?php
// ============================================================
//  PORTAL PELANGGAN — Halaman Login (No HP + PIN)
// ============================================================
require_once __DIR__ . '/_auth.php';

if (portal_is_logged_in()) redirect(PORTAL_URL . 'index.php');

$nama_isp = clean(app_setting('nama_isp', 'KahfiNet'));
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <meta name="theme-color" content="#0f2744">
  <title>Masuk — Portal Pelanggan <?= $nama_isp ?></title>
  <link rel="manifest" href="<?= PORTAL_URL ?>manifest.php">
  <link rel="apple-touch-icon" href="<?= PORTAL_URL ?>assets/icon-192.png">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    *, *::before, *::after { box-sizing: border-box; }
    body {
      font-family: 'Inter', sans-serif; margin: 0; min-height: 100vh;
      background: linear-gradient(165deg, #0a1e3d 0%, #0f2744 45%, #1a3a6b 100%);
      display: flex; flex-direction: column; color: #fff;
      padding: env(safe-area-inset-top) 0 env(safe-area-inset-bottom);
    }
    .wrap { flex: 1; display: flex; flex-direction: column; justify-content: center;
            max-width: 440px; width: 100%; margin: 0 auto; padding: 32px 24px; }
    .brand { text-align: center; margin-bottom: 34px; }
    .brand-icon {
      width: 76px; height: 76px; margin: 0 auto 18px;
      background: linear-gradient(135deg, #f59e0b, #fbbf24); border-radius: 22px;
      display: flex; align-items: center; justify-content: center;
      font-size: 34px; color: #1a1a1a; box-shadow: 0 14px 34px rgba(245,158,11,.34);
    }
    .brand h1 { font-size: 22px; font-weight: 800; margin: 0; letter-spacing: .3px; }
    .brand p  { font-size: 13px; color: rgba(255,255,255,.55); margin: 6px 0 0; }
    .card {
      background: #fff; border-radius: 20px; padding: 26px 22px 28px;
      box-shadow: 0 20px 50px rgba(0,0,0,.32); color: #1e293b;
    }
    .card h2 { font-size: 17px; font-weight: 700; color: #0f2744; margin: 0 0 4px; }
    .card .sub { font-size: 12.5px; color: #94a3b8; margin: 0 0 22px; }
    label { font-size: 11px; font-weight: 700; color: #475569; letter-spacing: .5px;
            text-transform: uppercase; display: block; margin-bottom: 7px; }
    .field {
      display: flex; align-items: stretch; border: 1.5px solid #e2e8f0;
      border-radius: 12px; background: #f8fafc; overflow: hidden; margin-bottom: 18px;
      transition: border-color .2s, box-shadow .2s;
    }
    .field:focus-within { border-color: #f59e0b; background: #fff; box-shadow: 0 0 0 3px rgba(245,158,11,.13); }
    .field .ic { width: 46px; display: flex; align-items: center; justify-content: center; color: #94a3b8; }
    .field input {
      flex: 1; border: 0; background: transparent; outline: none;
      font-family: 'Inter', sans-serif; font-size: 15px; color: #1e293b; padding: 13px 12px 13px 0;
    }
    .field input::placeholder { color: #cbd5e1; }
    #inputPin { letter-spacing: 7px; font-weight: 700; }

    /* Netralkan warna kuning autofill Chrome agar field & ikon tetap rapi/seragam */
    .field input:-webkit-autofill,
    .field input:-webkit-autofill:hover,
    .field input:-webkit-autofill:focus {
      -webkit-text-fill-color: #1e293b;
      caret-color: #1e293b;
      transition: background-color 9999s ease-in-out 0s;
    }
    .field input:-webkit-autofill,
    .field input:-webkit-autofill:hover { -webkit-box-shadow: 0 0 0 1000px #f8fafc inset; }
    .field input:-webkit-autofill:focus { -webkit-box-shadow: 0 0 0 1000px #fff inset; }
    .toggle { border: 0; background: none; color: #94a3b8; padding: 0 14px; font-size: 15px; }
    .btn {
      width: 100%; border: 0; border-radius: 12px; padding: 14px; cursor: pointer;
      font-family: 'Inter', sans-serif; font-size: 15px; font-weight: 700; color: #1a1a1a;
      background: linear-gradient(135deg, #f59e0b, #fbbf24);
      box-shadow: 0 10px 26px rgba(245,158,11,.32); transition: opacity .2s;
    }
    .btn:disabled { opacity: .6; }
    .alert { border-radius: 10px; font-size: 13px; padding: 11px 14px; margin-bottom: 18px; display: none; }
    .alert.show { display: block; }
    .alert-danger  { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }
    .alert-warning { background: #fffbeb; color: #b45309; border: 1px solid #fde68a; }
    .foot { text-align: center; font-size: 11.5px; color: rgba(255,255,255,.45); margin-top: 26px; line-height: 1.7; }
    .foot i { color: #f59e0b; }
    .help { font-size: 12px; color: #94a3b8; text-align: center; margin-top: 16px; line-height: 1.6; }
    .help b { color: #475569; }
  </style>
</head>
<body>
  <div class="wrap">
    <div class="brand">
      <div class="brand-icon"><i class="fas fa-wifi"></i></div>
      <h1><?= $nama_isp ?></h1>
      <p>Portal Pelanggan</p>
    </div>

    <div class="card">
      <h2>Selamat datang 👋</h2>
      <p class="sub">Masuk untuk cek tagihan, pemakaian, & lapor gangguan</p>

      <div id="alert" class="alert"><i id="alertIcon" class="fas mr-2"></i> <span id="alertMsg"></span></div>

      <form id="loginForm" autocomplete="off">
        <?php csrf_field(); ?>
        <label>Nomor HP</label>
        <div class="field">
          <div class="ic"><i class="fas fa-mobile-alt"></i></div>
          <input type="tel" name="no_hp" id="inputHp" inputmode="numeric"
                 placeholder="08xxxxxxxxxx" maxlength="20" required autofocus>
        </div>

        <label>PIN</label>
        <div class="field">
          <div class="ic"><i class="fas fa-lock"></i></div>
          <input type="password" name="pin" id="inputPin" inputmode="numeric"
                 placeholder="••••••" maxlength="6" pattern="[0-9]*" required>
          <button type="button" class="toggle" id="togglePin" tabindex="-1"><i class="fas fa-eye"></i></button>
        </div>

        <button type="submit" class="btn" id="btnLogin">
          <i class="fas fa-sign-in-alt mr-1"></i> Masuk
        </button>
      </form>

      <p class="help">Belum punya PIN atau lupa PIN?<br>
        Hubungi admin <b><?= $nama_isp ?></b> untuk mendapatkannya.</p>
    </div>

    <div class="foot">
      &copy; <?= date('Y') ?> <?= $nama_isp ?> &middot; Dibuat dengan <i class="fas fa-heart"></i>
    </div>
  </div>

  <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
  <script>
    var BASE = '<?= PORTAL_URL ?>';

    $('#togglePin').on('click', function () {
      var i = $('#inputPin'), ic = $(this).find('i');
      if (i.attr('type') === 'password') { i.attr('type', 'text'); ic.attr('class', 'fas fa-eye-slash'); }
      else { i.attr('type', 'password'); ic.attr('class', 'fas fa-eye'); }
    });

    // Batasi input PIN & HP ke angka saja
    $('#inputPin').on('input', function () { this.value = this.value.replace(/\D/g, ''); });
    $('#inputHp').on('input',  function () { this.value = this.value.replace(/[^0-9+]/g, ''); });

    function showAlert(type, msg) {
      var icons = { danger: 'exclamation-circle', warning: 'clock' };
      $('#alert').attr('class', 'alert show alert-' + type);
      $('#alertIcon').attr('class', 'fas fa-' + (icons[type] || 'exclamation-circle') + ' mr-2');
      $('#alertMsg').text(msg);
    }

    $('#loginForm').on('submit', function (e) {
      e.preventDefault();
      $('#alert').removeClass('show');
      var $btn = $('#btnLogin');
      $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Memproses...');

      $.ajax({ url: BASE + 'act.php?action=login', method: 'POST', data: $(this).serialize(), dataType: 'json' })
        .done(function (res) {
          if (res.success) { window.location.href = res.data.redirect || BASE + 'index.php'; return; }
          showAlert(res.data && res.data.lockout ? 'warning' : 'danger', res.msg);
          if (!(res.data && res.data.lockout)) $btn.prop('disabled', false);
          $btn.html('<i class="fas fa-sign-in-alt mr-1"></i> Masuk');
        })
        .fail(function () {
          showAlert('danger', 'Terjadi kesalahan. Coba lagi.');
          $btn.prop('disabled', false).html('<i class="fas fa-sign-in-alt mr-1"></i> Masuk');
        });
    });
  </script>
</body>
</html>
