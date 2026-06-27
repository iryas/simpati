<?php
// ============================================================
//  KAHFINET - menu.php
//  File ini bisa diinclude untuk mendapatkan daftar menu
//  berdasarkan role pengguna yang sedang login.
// ============================================================
if (!function_exists('get_menu')) {
    function get_menu(): array {
        $role = current_user()['role'];
        $base = BASE_URL;

        $menu = [
            [
                'label'  => 'Dashboard',
                'url'    => $base . 'index.php',
                'icon'   => 'fas fa-tachometer-alt',
                'key'    => 'dashboard',
                'roles'  => ['admin', 'teknisi', 'kasir'],
            ],
            [
                'label'  => 'Pelanggan',
                'url'    => $base . 'modules/pelanggan/views.php',
                'icon'   => 'fas fa-users',
                'key'    => 'pelanggan',
                'roles'  => ['admin', 'teknisi', 'kasir'],
            ],
            [
                'label'  => 'Paket Internet',
                'url'    => $base . 'modules/paket/views.php',
                'icon'   => 'fas fa-box-open',
                'key'    => 'paket',
                'roles'  => ['admin', 'teknisi', 'kasir'],
            ],
            [
                'label'  => 'Pembayaran',
                'url'    => $base . 'modules/pembayaran/views.php',
                'icon'   => 'fas fa-money-bill-wave',
                'key'    => 'pembayaran',
                'roles'  => ['admin', 'kasir'],
            ],
            [
                'label'  => 'Laporan',
                'url'    => $base . 'modules/laporan/views.php',
                'icon'   => 'fas fa-chart-bar',
                'key'    => 'laporan',
                'roles'  => ['admin', 'kasir'],
            ],
            [
                'label'  => 'Pengguna',
                'url'    => $base . 'modules/pengguna/views.php',
                'icon'   => 'fas fa-user-shield',
                'key'    => 'pengguna',
                'roles'  => ['admin'],
            ],
        ];

        return array_filter($menu, fn($m) => in_array($role, $m['roles'], true));
    }
}
