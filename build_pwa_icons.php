<?php
require_once __DIR__ . '/config/koneksi.php';

echo "Searching for best available logo...\n";

$logo_file = null;

// Check database setting for logo/favicon
if (isset($global_settings['site_favicon']) && !empty($global_settings['site_favicon'])) {
    $f = __DIR__ . '/assets/images/favicon/' . $global_settings['site_favicon'];
    if (file_exists($f)) $logo_file = $f;
}
if (!$logo_file && isset($global_settings['site_logo']) && !empty($global_settings['site_logo'])) {
    $f = __DIR__ . '/assets/images/logo/' . $global_settings['site_logo'];
    if (file_exists($f)) $logo_file = $f;
}

// Fallback search
if (!$logo_file) {
    $favicons = glob(__DIR__ . '/assets/images/favicon/*.png');
    if (!empty($favicons)) $logo_file = end($favicons);
}
if (!$logo_file) {
    $logos = glob(__DIR__ . '/assets/images/logo/*.png');
    if (!empty($logos)) $logo_file = end($logos);
}

echo "Selected logo file: " . ($logo_file ?: "None") . "\n";

if ($logo_file && file_exists($logo_file)) {
    list($src_w, $src_h, $type) = getimagesize($logo_file);
    echo "Logo dimensions: {$src_w}x{$src_h}\n";

    $src_img = null;
    if ($type === IMAGETYPE_PNG) {
        $src_img = imagecreatefrompng($logo_file);
    } elseif ($type === IMAGETYPE_JPEG) {
        $src_img = imagecreatefromjpeg($logo_file);
    }

    if ($src_img) {
        // Generate 512x512 and 192x192 icons
        $sizes = [192, 512];
        foreach ($sizes as $size) {
            $canvas = imagecreatetruecolor($size, $size);
            
            // Fill background with solid #0f1c3f (theme color)
            $bg = imagecolorallocate($canvas, 15, 28, 63);
            imagefill($canvas, 0, 0, $bg);

            // Calculate scaled dimensions to preserve aspect ratio with safe margin (70% of canvas)
            $target_max = $size * 0.70;
            $scale = min($target_max / $src_w, $target_max / $src_h);
            $dst_w = (int)round($src_w * $scale);
            $dst_h = (int)round($src_h * $scale);

            $dst_x = (int)round(($size - $dst_w) / 2);
            $dst_y = (int)round(($size - $dst_h) / 2);

            imagealphablending($canvas, true);
            imagesavealpha($canvas, true);

            imagecopyresampled($canvas, $src_img, $dst_x, $dst_y, 0, 0, $dst_w, $dst_h, $src_w, $src_h);

            $dest_dir = __DIR__ . '/assets/images/pwa';
            if (!is_dir($dest_dir)) mkdir($dest_dir, 0777, true);

            $dest_file = $dest_dir . "/icon-{$size}.png";
            imagepng($canvas, $dest_file, 9);
            echo "Successfully generated: {$dest_file} ({$size}x{$size})\n";

            // Also copy to root assets/images/
            copy($dest_file, __DIR__ . "/assets/images/icon-{$size}.png");

            imagedestroy($canvas);
        }
        imagedestroy($src_img);
    }
}
