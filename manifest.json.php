<?php
header('Content-Type: application/manifest+json; charset=utf-8');

$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$script_name = $_SERVER['SCRIPT_NAME'] ?? '/index.php';
$dir = str_replace('\\', '/', dirname($script_name));
$base_url = $protocol . $host . ($dir === '/' ? '/' : $dir . '/');

$manifest = [
    "id" => "halotiket-app",
    "name" => "HaloTiket - Platform E-Ticketing Event",
    "short_name" => "HaloTiket",
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
            "src" => $base_url . "assets/images/icon-192.png",
            "sizes" => "192x192",
            "type" => "image/png",
            "purpose" => "any maskable"
        ],
        [
            "src" => $base_url . "assets/images/icon-512.png",
            "sizes" => "512x512",
            "type" => "image/png",
            "purpose" => "any maskable"
        ]
    ]
];

echo json_encode($manifest, JSON_UNESCAPED_SLASHES);
exit;
