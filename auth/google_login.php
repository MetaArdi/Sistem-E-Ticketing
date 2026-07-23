<?php
session_start();
require_once '../config/google_oauth.php';

if (GOOGLE_CLIENT_ID === 'YOUR_GOOGLE_CLIENT_ID' || empty(GOOGLE_CLIENT_ID)) {
    $_SESSION['error'] = "Login Google belum dikonfigurasi. Silakan lengkapi Google Client ID & Client Secret di menu Admin Settings.";
    header("Location: login.php");
    exit();
}

try {
    // Generate a URL to request access from Google's OAuth 2.0 server
    $auth_url = $google_client->createAuthUrl();
    header('Location: ' . filter_var($auth_url, FILTER_SANITIZE_URL));
    exit();
} catch (\Throwable $e) {
    $_SESSION['error'] = "Gagal menghubungkan ke Google OAuth: " . $e->getMessage();
    header("Location: login.php");
    exit();
}
?>
