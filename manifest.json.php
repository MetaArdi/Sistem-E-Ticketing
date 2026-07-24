<?php
header('Content-Type: application/json; charset=utf-8');
require_once 'config/koneksi.php';

$app_name = $global_settings['site_name'] ?? 'HaloTiket';
$app_short_name = 'HaloTiket';

$icon_192 = 'assets/images/pwa/icon-192.png';
$icon_512 = 'assets/images/pwa/icon-512.png';

$manifest = [
    'id' => 'index.php',
    'name' => $app_name . ' - Platform E-Ticketing Event',
    'short_name' => $app_short_name,
    'description' => 'Platform E-Ticketing Konser, Seminar, Festival & Event Resmi',
    'start_url' => 'index.php',
    'scope' => './',
    'display' => 'standalone',
    'orientation' => 'portrait',
    'background_color' => '#ffffff',
    'theme_color' => '#0f1c3f',
    'icons' => [
        [
            'src' => $icon_192,
            'sizes' => '192x192',
            'type' => 'image/png',
            'purpose' => 'any'
        ],
        [
            'src' => $icon_192,
            'sizes' => '192x192',
            'type' => 'image/png',
            'purpose' => 'maskable'
        ],
        [
            'src' => $icon_512,
            'sizes' => '512x512',
            'type' => 'image/png',
            'purpose' => 'any'
        ],
        [
            'src' => $icon_512,
            'sizes' => '512x512',
            'type' => 'image/png',
            'purpose' => 'maskable'
        ]
    ]
];

echo json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
exit;
