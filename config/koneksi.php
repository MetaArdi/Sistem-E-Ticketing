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

// Helper Function untuk Mengambil SVG Ikon Kategori
if (!function_exists('getCategoryIconSvg')) {
    function getCategoryIconSvg($icon_key = '', $cat_name = '') {
        $icon_key = strtolower(trim($icon_key ?? ''));
        $cat_lower = strtolower(trim($cat_name ?? ''));

        $svgs = [
            'music' => '<path fill="currentColor" stroke="none" d="M12 3v10.55c-.59-.34-1.27-.55-2-.55-2.21 0-4 1.79-4 4s1.79 4 4 4 4-1.79 4-4V7h4V3h-6z"/>',
            'sports' => '<circle fill="none" stroke="currentColor" stroke-width="2" cx="12" cy="12" r="10"></circle><path fill="none" stroke="currentColor" stroke-width="2" d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path><path fill="none" stroke="currentColor" stroke-width="2" d="M2 12h20"></path>',
            'food' => '<path fill="none" stroke="currentColor" stroke-width="2" d="M18 8h1a4 4 0 0 1 0 8h-1"></path><path fill="none" stroke="currentColor" stroke-width="2" d="M2 8h16v9a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4V8z"></path><line fill="none" stroke="currentColor" stroke-width="2" x1="6" y1="1" x2="6" y2="4"></line>',
            'arts' => '<path fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/>',
            'seminar' => '<path fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path><path fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path>',
            'festival' => '<path fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M5.8 11.3L2 10l4.3-4.3L10 2l1.3 3.8L15 2l-1.3 4.3L18 10l-4.3 1.3L15 15l-3.8-1.3L10 18l-1.3-4.3L4.3 15l1.5-3.7z"/>',
            'comedy' => '<circle fill="none" stroke="currentColor" stroke-width="2" cx="12" cy="12" r="10"/><path fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" d="M8 14s1.5 2 4 2 4-2 4-2"/><line x1="9" y1="9" x2="9.01" y2="9" stroke="currentColor" stroke-width="3" stroke-linecap="round"/><line x1="15" y1="9" x2="15.01" y2="9" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>',
            'tech' => '<rect fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" x="2" y="3" width="20" height="14" rx="2" ry="2"></rect><line fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" x1="8" y1="21" x2="16" y2="21"></line><line fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" x1="12" y1="17" x2="12" y2="21"></line>',
            'automotive' => '<path fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M5 17h14M5 17a2 2 0 01-2-2V9a2 2 0 012-2h14a2 2 0 012 2v6a2 2 0 01-2 2M5 17a2 2 0 002 2h10a2 2 0 002-2M7 11h.01M17 11h.01"/>',
            'fashion' => '<path fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M6 2L2 7l4 3v12h12V10l4-3-4-5-4 2-4-2z"/>',
            'star' => '<polygon fill="none" stroke="currentColor" stroke-width="2" points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon>'
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
