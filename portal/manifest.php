<?php
// ============================================================
//  PORTAL PELANGGAN — Web App Manifest (dinamis)
// ============================================================
require_once __DIR__ . '/_auth.php';

header('Content-Type: application/manifest+json; charset=utf-8');

$nama_isp = app_setting('nama_isp', 'KahfiNet');

echo json_encode([
    'name'             => 'Portal Pelanggan ' . $nama_isp,
    'short_name'       => $nama_isp,
    'description'      => 'Cek tagihan, pemakaian internet, dan lapor gangguan.',
    'lang'             => 'id',
    'start_url'        => PORTAL_URL . 'index.php',
    'scope'            => PORTAL_URL,
    'display'          => 'standalone',
    'orientation'      => 'portrait',
    'background_color' => '#0f2744',
    'theme_color'      => '#0f2744',
    'icons'            => [
        [
            'src'     => PORTAL_URL . 'assets/icon-192.png',
            'sizes'   => '192x192',
            'type'    => 'image/png',
            'purpose' => 'any maskable',
        ],
        [
            'src'     => PORTAL_URL . 'assets/icon-512.png',
            'sizes'   => '512x512',
            'type'    => 'image/png',
            'purpose' => 'any maskable',
        ],
    ],
], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
