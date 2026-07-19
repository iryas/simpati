<?php
// ============================================================
//  PORTAL PELANGGAN — Komponen "Pasang Aplikasi" (PWA install)
//  Self-contained (HTML + CSS + JS vanilla). Di-include sebelum </body>
//  di login.php & layout.php. Menangani 3 kasus:
//    - Android/Chrome : tombol Pasang -> native install prompt
//    - iPhone/Safari  : panduan "Add to Home Screen"
//    - In-app browser : arahan "Buka di Chrome dulu"
// ============================================================
?>
<div id="pwaInstall" class="pwaib" hidden>
  <div class="pwaib-main">
    <div class="pwaib-ic"><i class="fas fa-download"></i></div>
    <div class="pwaib-txt">
      <div class="pwaib-title">Pasang Aplikasi</div>
      <div class="pwaib-sub" id="pwaibSub">Akses lebih cepat, langsung dari layar HP.</div>
    </div>
    <button type="button" class="pwaib-cta" id="pwaibCta">Pasang</button>
    <button type="button" class="pwaib-x" id="pwaibX" aria-label="Tutup"><i class="fas fa-times"></i></button>
  </div>
  <div class="pwaib-guide" id="pwaibGuide" hidden></div>
</div>

<style>
  .pwaib {
    position: fixed; left: 12px; right: 12px; bottom: 12px; z-index: 9999;
    font-family: 'Inter', sans-serif;
    transform: translateY(150%); opacity: 0;
    transition: transform .3s ease, opacity .3s ease;
  }
  .pwaib.pwaib-in { transform: translateY(0); opacity: 1; }
  .pwaib.pwaib-hasnav { bottom: calc(66px + env(safe-area-inset-bottom, 0px)); }
  .pwaib-main {
    display: flex; align-items: center; gap: 12px;
    max-width: 440px; margin: 0 auto;
    background: #fff; border: 1px solid #e2e8f0; border-radius: 16px;
    padding: 11px 12px 11px 13px; box-shadow: 0 14px 38px rgba(15,39,68,.30);
  }
  .pwaib-ic {
    width: 40px; height: 40px; flex-shrink: 0; border-radius: 11px;
    display: flex; align-items: center; justify-content: center;
    color: #1a1a1a; font-size: 17px;
    background: linear-gradient(135deg, #f59e0b, #fbbf24);
  }
  .pwaib-txt { flex: 1; min-width: 0; }
  .pwaib-title { font-size: 14px; font-weight: 800; color: #0f2744; line-height: 1.2; }
  .pwaib-sub { font-size: 11.5px; color: #64748b; margin-top: 2px; line-height: 1.35; }
  .pwaib-cta {
    flex-shrink: 0; border: 0; border-radius: 10px; padding: 9px 15px; cursor: pointer;
    font-family: inherit; font-size: 13px; font-weight: 800; color: #1a1a1a;
    background: linear-gradient(135deg, #f59e0b, #fbbf24);
  }
  .pwaib-x { flex-shrink: 0; border: 0; background: none; color: #94a3b8; font-size: 15px; padding: 6px; cursor: pointer; line-height: 1; }
  .pwaib-guide {
    max-width: 440px; margin: 8px auto 0; background: #0f2744; color: #e2e8f0;
    border-radius: 12px; padding: 11px 14px; font-size: 12px; line-height: 1.6;
    box-shadow: 0 14px 38px rgba(15,39,68,.30);
  }
  .pwaib-guide b { color: #fbbf24; font-weight: 700; }
</style>

<script>
(function () {
  var el = document.getElementById('pwaInstall');
  if (!el) return;
  var sub   = document.getElementById('pwaibSub');
  var cta   = document.getElementById('pwaibCta');
  var xBtn  = document.getElementById('pwaibX');
  var guide = document.getElementById('pwaibGuide');

  var DISMISS_KEY = 'pwaib_dismiss', DISMISS_DAYS = 7;
  var ua = navigator.userAgent || '';
  var isIOS   = /iphone|ipad|ipod/i.test(ua);
  var isInApp = /\bwv\b/.test(ua) || /FBAN|FBAV|Instagram|Line\/|MicroMessenger/i.test(ua);

  function isStandalone() {
    return window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
  }
  function dismissedRecently() {
    try {
      var t = parseInt(localStorage.getItem(DISMISS_KEY) || '0', 10);
      return t && (Date.now() - t) < DISMISS_DAYS * 864e5;
    } catch (e) { return false; }
  }
  function show() { el.hidden = false; requestAnimationFrame(function () { el.classList.add('pwaib-in'); }); }
  function hide() { el.classList.remove('pwaib-in'); setTimeout(function () { el.hidden = true; }, 300); }

  // Beri jarak dari bottom-nav bila ada (halaman dalam)
  if (document.querySelector('.bottomnav')) el.classList.add('pwaib-hasnav');

  // Sudah terpasang / baru saja ditutup -> jangan tampilkan
  if (isStandalone() || dismissedRecently()) return;

  var deferred = null;

  // Android / Chrome: tangkap event, tampilkan tombol Pasang asli
  window.addEventListener('beforeinstallprompt', function (e) {
    e.preventDefault();
    deferred = e;
    sub.textContent = 'Akses lebih cepat, langsung dari layar HP.';
    cta.textContent = 'Pasang';
    show();
  });

  window.addEventListener('appinstalled', function () {
    try { localStorage.setItem(DISMISS_KEY, String(Date.now())); } catch (e) {}
    hide();
  });

  cta.addEventListener('click', function () {
    if (deferred) {
      deferred.prompt();
      deferred.userChoice.then(function () { deferred = null; hide(); });
      return;
    }
    guide.hidden = !guide.hidden; // iOS / in-app: tampilkan panduan
  });

  xBtn.addEventListener('click', function () {
    try { localStorage.setItem(DISMISS_KEY, String(Date.now())); } catch (e) {}
    hide();
  });

  // In-app browser (WA/IG/FB/Line) -> tak bisa install, arahkan ke Chrome
  if (isInApp) {
    sub.textContent = 'Buka di browser (Chrome/Safari) untuk memasang.';
    cta.textContent = 'Caranya';
    guide.innerHTML = 'Ketuk menu <b>&#8942;</b> (titik tiga) di pojok kanan atas, lalu pilih ' +
                      '<b>“Buka di Chrome”</b>. Setelah terbuka di Chrome, tombol <b>Pasang</b> akan muncul otomatis.';
    show();
  } else if (isIOS) {
    // iPhone Safari: tak ada prompt otomatis -> panduan manual
    sub.textContent = 'Tambahkan ke Layar Utama iPhone-mu.';
    cta.textContent = 'Caranya';
    guide.innerHTML = 'Ketuk ikon <b>Bagikan</b> (kotak dengan panah ke atas) di bar bawah Safari, ' +
                      'lalu pilih <b>“Add to Home Screen”</b> / <b>“Ke Layar Utama”</b>.';
    show();
  }
  // Android non-inapp: menunggu 'beforeinstallprompt' (baru ditampilkan saat siap)
})();
</script>
