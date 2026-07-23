<?php
require_once __DIR__ . '/../config/koneksi.php';

// Ambil Google OAuth Credentials dari database settings jika ada
$g_client_id = trim($global_settings['google_client_id'] ?? '');
$g_client_secret = trim($global_settings['google_client_secret'] ?? '');

if (empty($g_client_id)) {
    $g_client_id = 'YOUR_GOOGLE_CLIENT_ID';
}
if (empty($g_client_secret)) {
    $g_client_secret = 'YOUR_GOOGLE_CLIENT_SECRET';
}

if (!defined('GOOGLE_CLIENT_ID')) define('GOOGLE_CLIENT_ID', $g_client_id);
if (!defined('GOOGLE_CLIENT_SECRET')) define('GOOGLE_CLIENT_SECRET', $g_client_secret);

// Custom atau Auto Redirect URI
$g_custom_redirect = trim($global_settings['google_redirect_uri'] ?? '');
if (!empty($g_custom_redirect)) {
    $redirect_uri = $g_custom_redirect;
} else {
    $redirect_uri = (defined('BASE_URL') ? BASE_URL : 'https://halotiket.com/') . 'auth/google_callback.php';
}

if (!defined('GOOGLE_REDIRECT_URI')) define('GOOGLE_REDIRECT_URI', $redirect_uri);

if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

// Initialize Google Client
$google_client = new Google\Client();
$google_client->setClientId(GOOGLE_CLIENT_ID);
$google_client->setClientSecret(GOOGLE_CLIENT_SECRET);
$google_client->setRedirectUri(GOOGLE_REDIRECT_URI);
$google_client->addScope("email");
$google_client->addScope("profile");
?>
