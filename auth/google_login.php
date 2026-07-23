<?php
session_start();
require_once '../config/google_oauth.php';

if (empty(GOOGLE_CLIENT_ID) || empty(GOOGLE_CLIENT_SECRET) || GOOGLE_CLIENT_ID === 'YOUR_GOOGLE_CLIENT_ID') {
    $_SESSION['error'] = "Google Client ID atau Client Secret belum tersimpan. Silakan buka menu Admin Panel -> Settings, masukkan Google Client ID & Client Secret, lalu klik tombol 'Simpan Pengaturan'.";
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
