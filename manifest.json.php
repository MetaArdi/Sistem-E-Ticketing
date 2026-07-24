<?php
header('Content-Type: application/json; charset=utf-8');
require_once 'config/koneksi.php';

$app_name = $global_settings['site_name'] ?? 'HaloTiket';
$app_short_name = 'HaloTiket';

// Get logo URL from database settings or default asset
$logo_url = '';
if (!empty($global_site_favicon)) {
    $logo_url = $global_site_favicon;
} elseif (!empty($global_site_logo)) {
    $logo_url = $global_site_logo;
} else {
    $logo_url = BASE_URL . 'assets/images/favicon/1783855109_Logo_HaloTiket_favicon.png';
}

$manifest = [
    'name' => $app_name . ' - Platform E-Ticketing Event',
    'short_name' => $app_short_name,
    'description' => 'Platform E-Ticketing Konser, Seminar, Festival & Event Resmi',
    'start_url' => './index.php',
    'scope' => './',
    'display' => 'standalone',
    'orientation' => 'portrait-primary',
    'background_color' => '#ffffff',
    'theme_color' => '#0f1c3f',
    'icons' => [
        [
            'src' => $logo_url,
            'sizes' => '192x192',
            'type' => 'image/png',
            'purpose' => 'any maskable'
        ],
        [
            'src' => $logo_url,
            'sizes' => '512x512',
            'type' => 'image/png',
            'purpose' => 'any maskable'
        ]
    ]
];

echo json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
exit;
