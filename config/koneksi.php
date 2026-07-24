<?php
$host = "localhost";
$user = "root";
$pass = '';
$db   = "halotiket_db";

// Nonaktifkan exception otomatis MySQLi agar tidak menyebabkan HTTP 500 saat koneksi gagal
mysqli_report(MYSQLI_REPORT_OFF);

$conn = @new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Koneksi Database Gagal: " . $conn->connect_error . "<br><br><small>Silakan periksa hak akses user di cPanel <b>MySQL Databases</b>.</small>");
}

// Auto Migration: Pastikan kolom snap_token & snap_redirect_url ada di tabel tickets
$checkCol1 = $conn->query("SHOW COLUMNS FROM tickets LIKE 'snap_token'");
if ($checkCol1 && $checkCol1->num_rows === 0) {
    @$conn->query("ALTER TABLE tickets ADD COLUMN snap_token VARCHAR(255) NULL AFTER order_id");
}
$checkCol2 = $conn->query("SHOW COLUMNS FROM tickets LIKE 'snap_redirect_url'");
if ($checkCol2 && $checkCol2->num_rows === 0) {
    @$conn->query("ALTER TABLE tickets ADD COLUMN snap_redirect_url TEXT NULL AFTER snap_token");
}

// Global Settings Configuration (Dynamic BASE_URL dengan support HTTPS)
if (!defined('BASE_URL')) {
    $is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') 
                || ($_SERVER['SERVER_PORT'] ?? 80) == 443 
                || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') 
                || (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on');
    $protocol = $is_https ? "https://" : "http://";
    $host_name = $_SERVER['HTTP_HOST'] ?? 'localhost';
    
    if (isset($_SERVER['SCRIPT_NAME']) && PHP_SAPI !== 'cli') {
        $dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
        $dir = preg_replace('#/(admin|panitia|validator|user|auth|api|actions)$#i', '', $dir);
        $dir = rtrim($dir, '/') . '/';
    } else {
        $dir = '/Halo_Tiket/';
    }
    define('BASE_URL', $protocol . $host_name . $dir);
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
    $global_contact_address = $global_settings['contact_address'] ?? "Garung Lor,Kec. Kaliwungu, Kabupaten Kudus, Jawa Tengah";
    $global_contact_cs = $global_settings['contact_cs'] ?? "6281234567890";
    $global_link_ig = $global_settings['link_ig'] ?? "https://instagram.com";
    $global_link_tiktok = $global_settings['link_tiktok'] ?? "https://tiktok.com";

    // Midtrans Configuration & Environment Detection
    $m_server_key  = trim($global_settings['midtrans_server_key'] ?? '');
    $m_client_key  = trim($global_settings['midtrans_client_key'] ?? '');
    $m_merchant_id = trim($global_settings['midtrans_merchant_id'] ?? '');
    $m_is_production = (isset($global_settings['midtrans_is_production']) && $global_settings['midtrans_is_production'] == '1');

    define('MIDTRANS_MERCHANT_ID', $m_merchant_id);
    define('MIDTRANS_SERVER_KEY', $m_server_key);
    define('MIDTRANS_CLIENT_KEY', $m_client_key);
    define('MIDTRANS_IS_PRODUCTION', $m_is_production);
    define('MIDTRANS_SNAP_URL', MIDTRANS_IS_PRODUCTION ? 'https://app.midtrans.com/snap/snap.js' : 'https://app.sandbox.midtrans.com/snap/snap.js');
    
    // Maintenance Mode Check
    $is_maintenance = (isset($global_settings['maintenance_mode']) && $global_settings['maintenance_mode'] == '1');
    if ($is_maintenance) {
        $uri = $_SERVER['REQUEST_URI'];
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

// Helper Function untuk Mengambil SVG Ikon Kategori
if (!function_exists('getCategoryIconSvg')) {
    function getCategoryIconSvg($icon_key = '', $cat_name = '') {
        $icon_key = strtolower(trim($icon_key ?? ''));
        $cat_lower = strtolower(trim($cat_name ?? ''));

        $svgs = [
            'music' => '<path fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M9 18V5l12-2v13M9 9l12-2M6 21a3 3 0 100-6 3 3 0 000 6zm12 0a3 3 0 100-6 3 3 0 000 6z"/>',
            'sports' => '<path fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M6 9H4.5a2.5 2.5 0 010-5H6M18 9h1.5a2.5 2.5 0 000-5H18M4 22h16M10 14.66V17c0 .55-.45 1-1 1H7M14 14.66V17c0 .55.45 1 1 1h2M18 4H6v7a6 6 0 0012 0V4z"/>',
            'food' => '<path fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M18 8h1a4 4 0 010 8h-1M2 8h16v9a4 4 0 01-4 4H6a4 4 0 01-4-4V8zM6 1v3M10 1v3M14 1v3"/>',
            'arts' => '<path fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M12 2a10 10 0 00-10 10c0 5.523 4.477 10 10 10 1.38 0 2.5-1.12 2.5-2.5 0-.62-.23-1.2-.62-1.64a.8.8 0 01.56-1.36h2.56a5 5 0 005-5c0-4.97-4.477-9.5-10-9.5zM6.5 11.5a1.5 1.5 0 110-3 1.5 1.5 0 010 3zm4-4a1.5 1.5 0 110-3 1.5 1.5 0 010 3zm5 0a1.5 1.5 0 110-3 1.5 1.5 0 010 3zm4 4a1.5 1.5 0 110-3 1.5 1.5 0 010 3z"/>',
            'seminar' => '<path fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M22 10v6M2 10l10-5 10 5-10 5zM6 12v5c0 2 6 3 6 3s6-1 6-3v-5"/>',
            'festival' => '<path fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M2 9a3 3 0 010 6v2a2 2 0 002 2h16a2 2 0 002-2v-2a3 3 0 010-6V7a2 2 0 00-2-2H4a2 2 0 00-2 2v2zM13 5v14M9 9h.01M9 15h.01"/>',
            'comedy' => '<path fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M12 22c5.523 0 10-4.477 10-10S17.523 2 12 2 2 6.477 2 12s4.477 10 10 10zM8 14s1.5 2 4 2 4-2 4-2M9 9h.01M15 9h.01"/>',
            'tech' => '<path fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M20 16V6a2 2 0 00-2-2H6a2 2 0 00-2 2v10m-2 4h20m-4 0v-4H6v4"/>',
            'automotive' => '<path fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M5 17h14M5 17a2 2 0 01-2-2V9a2 2 0 012-2h14a2 2 0 012 2v6a2 2 0 01-2 2M5 17a2 2 0 002 2h10a2 2 0 002-2M7 11h.01M17 11h.01"/>',
            'fashion' => '<path fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M6 2L2 7l4 3v12h12V10l4-3-4-5-4 2-4-2z"/>',
            'star' => '<path fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>'
        ];

        if (!empty($icon_key) && isset($svgs[$icon_key])) {
            return $svgs[$icon_key];
        }

        $search = $icon_key . ' ' . $cat_lower;
        if (str_contains($search, 'music') || str_contains($search, 'musik') || str_contains($search, 'konser') || str_contains($search, 'lagu')) {
            return $svgs['music'];
        } elseif (str_contains($search, 'sport') || str_contains($search, 'olahraga') || str_contains($search, 'pertandingan') || str_contains($search, 'bola')) {
            return $svgs['sports'];
        } elseif (str_contains($search, 'food') || str_contains($search, 'makanan') || str_contains($search, 'kuliner') || str_contains($search, 'bazaar')) {
            return $svgs['food'];
        } elseif (str_contains($search, 'art') || str_contains($search, 'seni') || str_contains($search, 'pameran') || str_contains($search, 'lukis') || str_contains($search, 'budaya')) {
            return $svgs['arts'];
        } elseif (str_contains($search, 'seminar') || str_contains($search, 'edukasi') || str_contains($search, 'pendidikan') || str_contains($search, 'workshop') || str_contains($search, 'webinar')) {
            return $svgs['seminar'];
        } elseif (str_contains($search, 'festival') || str_contains($search, 'pesta') || str_contains($search, 'carnival')) {
            return $svgs['festival'];
        } elseif (str_contains($search, 'comedy') || str_contains($search, 'komedi') || str_contains($search, 'humor') || str_contains($search, 'standup')) {
            return $svgs['comedy'];
        } elseif (str_contains($search, 'tech') || str_contains($search, 'teknologi') || str_contains($search, 'game') || str_contains($search, 'esport') || str_contains($search, 'digital')) {
            return $svgs['tech'];
        } elseif (str_contains($search, 'otomotif') || str_contains($search, 'automotive') || str_contains($search, 'motor') || str_contains($search, 'mobil')) {
            return $svgs['automotive'];
        } elseif (str_contains($search, 'fashion') || str_contains($search, 'gaya') || str_contains($search, 'busana')) {
            return $svgs['fashion'];
        }

        return $svgs['star'];
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
