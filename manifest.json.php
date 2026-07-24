<?php
header('Content-Type: application/manifest+json; charset=utf-8');
require_once 'config/koneksi.php';

$app_name = $global_settings['site_name'] ?? 'HaloTiket';
$app_short_name = 'HaloTiket';
$base_url = BASE_URL;

$icon_192 = file_exists(__DIR__ . '/assets/images/pwa/icon-192.png') 
    ? $base_url . 'assets/images/pwa/icon-192.png' 
    : (file_exists(__DIR__ . '/assets/images/icon-192.png') ? $base_url . 'assets/images/icon-192.png' : $base_url . 'assets/images/logo/1783841669_Logo_HaloTiket_nobg.png');

$icon_512 = file_exists(__DIR__ . '/assets/images/pwa/icon-512.png') 
    ? $base_url . 'assets/images/pwa/icon-512.png' 
    : (file_exists(__DIR__ . '/assets/images/icon-512.png') ? $base_url . 'assets/images/icon-512.png' : $base_url . 'assets/images/logo/1783841669_Logo_HaloTiket_nobg.png');

$manifest = [
    "id" => "halotiket-app",
    "name" => $app_name . " - Platform E-Ticketing Event",
    "short_name" => $app_short_name,
    "description" => "Platform E-Ticketing Konser, Seminar, Festival & Event Resmi",
    "start_url" => $base_url,
    "scope" => $base_url,
    "display" => "standalone",
    "orientation" => "any",
    "background_color" => "#ffffff",
    "theme_color" => "#0f1c3f",
    "lang" => "id",
    "categories" => ["events", "utilities", "entertainment"],
    "prefer_related_applications" => false,
    "icons" => [
        [
            "src" => $icon_192,
            "sizes" => "192x192",
            "type" => "image/png",
            "purpose" => "any maskable"
        ],
        [
            "src" => $icon_512,
            "sizes" => "512x512",
            "type" => "image/png",
            "purpose" => "any maskable"
        ]
    ]
];

echo json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
exit;
