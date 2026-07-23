<?php
require_once __DIR__ . '/../config/koneksi.php';

$g_client_id = '';
$g_client_secret = '';
$g_redirect_uri = '';

// Kueri langsung dari database agar selalu 100% akurat & realtime
if (isset($conn) && $conn) {
    $sq = $conn->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('google_client_id', 'google_client_secret', 'google_redirect_uri')");
    if ($sq) {
        while ($row = $sq->fetch_assoc()) {
            if ($row['setting_key'] === 'google_client_id') $g_client_id = trim($row['setting_value'] ?? '');
            if ($row['setting_key'] === 'google_client_secret') $g_client_secret = trim($row['setting_value'] ?? '');
            if ($row['setting_key'] === 'google_redirect_uri') $g_redirect_uri = trim($row['setting_value'] ?? '');
        }
    }
}

// Fallback jika ada di $global_settings
if (empty($g_client_id) && isset($global_settings['google_client_id'])) {
    $g_client_id = trim($global_settings['google_client_id']);
}
if (empty($g_client_secret) && isset($global_settings['google_client_secret'])) {
    $g_client_secret = trim($global_settings['google_client_secret']);
}

if (!defined('GOOGLE_CLIENT_ID')) define('GOOGLE_CLIENT_ID', $g_client_id);
if (!defined('GOOGLE_CLIENT_SECRET')) define('GOOGLE_CLIENT_SECRET', $g_client_secret);

// Deteksi otomatis URL Callback tanpa subfolder /Halo_Tiket/ jika menggunakan domain live halotiket.com
if (empty($g_redirect_uri)) {
    $host_name = $_SERVER['HTTP_HOST'] ?? '';
    if (!empty($host_name) && str_contains($host_name, 'halotiket.com')) {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || ($_SERVER['SERVER_PORT'] ?? 80) == 443) ? "https://" : "http://";
        $g_redirect_uri = $protocol . $host_name . '/auth/google_callback.php';
    } else {
        $base = defined('BASE_URL') ? BASE_URL : 'https://halotiket.com/';
        $g_redirect_uri = rtrim($base, '/') . '/auth/google_callback.php';
    }
}

if (!defined('GOOGLE_REDIRECT_URI')) define('GOOGLE_REDIRECT_URI', $g_redirect_uri);

if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

// Initialize Google Client
$google_client = new Google\Client();
if (!empty(GOOGLE_CLIENT_ID)) {
    $google_client->setClientId(GOOGLE_CLIENT_ID);
}
if (!empty(GOOGLE_CLIENT_SECRET)) {
    $google_client->setClientSecret(GOOGLE_CLIENT_SECRET);
}
$google_client->setRedirectUri(GOOGLE_REDIRECT_URI);
$google_client->addScope("email");
$google_client->addScope("profile");
?>
