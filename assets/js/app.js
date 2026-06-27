/* ============================================================
   KAHFINET — app.js
   ============================================================ */

$(function () {

  /* ── Sidebar Toggle ─────────────────────────────────────── */
  const $body    = $('body');
  const isMobile = () => window.innerWidth <= 768;

  $('#sidebar-toggle').on('click', function () {
    if (isMobile()) {
      $body.toggleClass('sidebar-open');
    } else {
      $body.toggleClass('sidebar-collapsed');
      localStorage.setItem('sidebar_collapsed', $body.hasClass('sidebar-collapsed') ? '1' : '0');
    }
  });

  // Restore collapsed state on desktop
  if (!isMobile() && localStorage.getItem('sidebar_collapsed') === '1') {
    $body.addClass('sidebar-collapsed');
  }

  // Close sidebar on overlay click (mobile)
  $(document).on('click', function (e) {
    if (isMobile() && $body.hasClass('sidebar-open') &&
        !$(e.target).closest('#sidebar, #sidebar-toggle').length) {
      $body.removeClass('sidebar-open');
    }
  });

  /* ── Confirm Delete ─────────────────────────────────────── */
  $(document).on('click', '.btn-hapus', function (e) {
    e.preventDefault();
    const url   = $(this).attr('href') || $(this).data('url');
    const label = $(this).data('label') || 'data ini';
    if (confirm('Yakin ingin menghapus ' + label + '?\nTindakan ini tidak dapat dibatalkan.')) {
      window.location.href = url;
    }
  });

  /* ── Auto-dismiss Alert ─────────────────────────────────── */
  setTimeout(function () {
    $('.alert').fadeOut(400, function () { $(this).remove(); });
  }, 4000);

  /* ── DataTable default init ─────────────────────────────── */
  if ($.fn.DataTable) {
    $('.datatable').DataTable({
      language: {
        url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/id.json'
      },
      pageLength: 10,
      responsive: true,
      order: [[0, 'desc']],
    });
  }

  /* ── Format Rupiah on input ─────────────────────────────── */
  $(document).on('input', '.input-rupiah', function () {
    let val = $(this).val().replace(/\D/g, '');
    $(this).val(val ? parseInt(val).toLocaleString('id-ID') : '');
  });

  /* ── Tooltip ────────────────────────────────────────────── */
  $('[data-toggle="tooltip"]').tooltip();

  /* ── Popover ────────────────────────────────────────────── */
  $('[data-toggle="popover"]').popover();

});
