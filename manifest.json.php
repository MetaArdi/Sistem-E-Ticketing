<?php
header('Content-Type: application/manifest+json; charset=utf-8');

$manifest = [
    "id" => "halotiket-pwa",
    "name" => "HaloTiket - Platform E-Ticketing Event",
    "short_name" => "HaloTiket",
    "description" => "Platform E-Ticketing Konser, Seminar, Festival & Event Resmi",
    "start_url" => "./",
    "scope" => "./",
    "display" => "standalone",
    "orientation" => "any",
    "background_color" => "#ffffff",
    "theme_color" => "#0f1c3f",
    "lang" => "id",
    "categories" => ["events", "utilities", "entertainment"],
    "prefer_related_applications" => false,
    "icons" => [
        [
            "src" => "assets/images/pwa/icon-192.png",
            "sizes" => "192x192",
            "type" => "image/png",
            "purpose" => "any"
        ],
        [
            "src" => "assets/images/pwa/icon-192.png",
            "sizes" => "192x192",
            "type" => "image/png",
            "purpose" => "maskable"
        ],
        [
            "src" => "assets/images/pwa/icon-512.png",
            "sizes" => "512x512",
            "type" => "image/png",
            "purpose" => "any"
        ],
        [
            "src" => "assets/images/pwa/icon-512.png",
            "sizes" => "512x512",
            "type" => "image/png",
            "purpose" => "maskable"
        ]
    ]
];

echo json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
exit;

