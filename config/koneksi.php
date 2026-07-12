<?php
$host = "localhost";
$user = "root";
$pass = "";
$db   = "halotiket_db";

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Koneksi Database Gagal: " . $conn->connect_error);
}

// Konfigurasi API Defaults (Akan dioverride oleh database jika ada)
$midtrans_server_key = 'SB-Mid-server-YOUR_SERVER_KEY';
$midtrans_client_key = 'SB-Mid-client-YOUR_CLIENT_KEY';
$midtrans_is_production = false;
define('MIDTRANS_CLIENT_KEY', 'SB-Mid-client-YOUR_CLIENT_KEY');


// Global Settings Configuration
$base_url = "http://" . $_SERVER['HTTP_HOST'] . "/Halo_Tiket/";
if (!defined('BASE_URL')) {
    define('BASE_URL', $base_url);
}

$global_site_logo = null;
$global_site_favicon = null;

$settings_query = $conn->query("SELECT setting_key, setting_value FROM settings");
if ($settings_query) {
    $global_settings = [];
    while($row = $settings_query->fetch_assoc()) {
        $global_settings[$row['setting_key']] = $row['setting_value'];
    }
    if (isset($global_settings['site_logo']) && $global_settings['site_logo'] != '') {
        $global_site_logo = BASE_URL . 'assets/images/logo/' . $global_settings['site_logo'];
    }
    if (isset($global_settings['site_favicon']) && $global_settings['site_favicon'] != '') {
        $global_site_favicon = BASE_URL . 'assets/images/favicon/' . $global_settings['site_favicon'];
    }
    
    // Informasi Kontak & Sosial Media (dengan fallback bawaan)
    $global_contact_address = $global_settings['contact_address'] ?? "HaloTiket Tower Lt. 5\nJl. Jend. Sudirman No. 123, Senayan\nJakarta Selatan, DKI Jakarta 12190";
    $global_contact_cs = $global_settings['contact_cs'] ?? "6281234567890";
    $global_link_ig = $global_settings['link_ig'] ?? "https://instagram.com";
    $global_link_tiktok = $global_settings['link_tiktok'] ?? "https://tiktok.com";
}

// Helper Function untuk System Logging
if (!function_exists('logActivity')) {
    function logActivity($conn, $user_id, $action, $description) {
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
        $stmt = $conn->prepare("INSERT INTO system_logs (user_id, action, description, ip_address) VALUES (?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("isss", $user_id, $action, $description, $ip_address);
            $stmt->execute();
            $stmt->close();
        }
    }
}
?>
