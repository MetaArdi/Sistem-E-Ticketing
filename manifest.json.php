<?php
header('Content-Type: application/manifest+json; charset=utf-8');
require_once 'config/koneksi.php';

$app_name = $global_settings['site_name'] ?? 'HaloTiket';
$app_short_name = 'HaloTiket';

$manifest = [
    'id' => './',
    'name' => $app_name . ' - Platform E-Ticketing Event',
    'short_name' => $app_short_name,
    'description' => 'Platform E-Ticketing Konser, Seminar, Festival & Event Resmi',
    'start_url' => './index.php',
    'scope' => './',
    'display' => 'standalone',
    'orientation' => 'portrait',
    'background_color' => '#ffffff',
    'theme_color' => '#0f1c3f',
    'icons' => [
        [
            'src' => 'assets/images/pwa/icon-192.png',
            'sizes' => '192x192',
            'type' => 'image/png',
            'purpose' => 'any maskable'
        ],
        [
            'src' => 'assets/images/pwa/icon-512.png',
            'sizes' => '512x512',
            'type' => 'image/png',
            'purpose' => 'any maskable'
        ]
    ]
];

echo json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
exit;
