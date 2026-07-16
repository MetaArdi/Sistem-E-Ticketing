<?php
$host = "localhost";
$user = "root";
$pass = "";
$db   = "halotiket_db";

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Koneksi Database Gagal: " . $conn->connect_error);
}

// Midtrans API keys will be populated dynamically from database


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

    // Midtrans Configuration
    define('MIDTRANS_SERVER_KEY', $global_settings['midtrans_server_key'] ?? '');
    define('MIDTRANS_CLIENT_KEY', $global_settings['midtrans_client_key'] ?? '');
    define('MIDTRANS_IS_PRODUCTION', (isset($global_settings['midtrans_is_production']) && $global_settings['midtrans_is_production'] == '1') ? true : false);
    
    // Maintenance Mode Check
    $is_maintenance = (isset($global_settings['maintenance_mode']) && $global_settings['maintenance_mode'] == '1');
    if ($is_maintenance) {
        $uri = $_SERVER['REQUEST_URI'];
        // Jika sedang maintenance, HANYA admin, auth, dan assets yang boleh diakses. 
        // Panitia & Validator akan diblokir.
        if (strpos($uri, '/admin/') === false && 
            strpos($uri, '/auth/') === false && 
            strpos($uri, '/assets/') === false && 
            strpos($uri, 'maintenance.php') === false) {
            header("Location: " . BASE_URL . "maintenance.php");
            exit;
        }
    }
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

// Fitur Auto Logout 10 Menit Inaktivitas
if (isset($_SESSION['user_id'])) {
    $timeout_duration = 600; // 10 menit dalam detik
    
    // Cek apakah mode maintenance aktif dan user adalah admin
    $is_admin = (isset($_SESSION['role']) && $_SESSION['role'] == 'admin');
    $bypass_timeout = (isset($is_maintenance) && $is_maintenance && $is_admin);

    if (!$bypass_timeout) {
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout_duration) {
            logActivity($conn, $_SESSION['user_id'], 'Auto Logout', 'Sistem melakukan logout otomatis karena pengguna tidak aktif selama 10 menit.');
            session_unset();
            session_destroy();
            header("Location: " . BASE_URL . "auth/login.php?msg=timeout");
            exit;
        }
    }

    // Update timestamp aktivitas terakhir
    $_SESSION['last_activity'] = time();
}
?>
