<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../auth/login.php");
    exit;
}
require_once '../config/koneksi.php';

$success_msg = '';

// Handle AJAX Test WhatsApp Fonnte
if (isset($_GET['action']) && $_GET['action'] === 'test_fonnte') {
    header('Content-Type: application/json');
    $token = trim($_POST['test_token'] ?? $global_settings['fonnte_token'] ?? 'ep63gV3wUjrZ1RB4NXnW');
    $group = trim($_POST['test_group'] ?? $global_settings['fonnte_wa_group'] ?? '120363412788674882@g.us');

    if (empty($token) || empty($group)) {
        echo json_encode(['status' => 'error', 'message' => 'Token Fonnte dan ID Group WA tidak boleh kosong.']);
        exit;
    }

    $msg = "🤖 *TES NOTIFIKASI WHATSAPP HALOTIKET*\n";
    $msg .= "----------------------------------------\n";
    $msg .= "Koneksi Fonnte WhatsApp API berhasil terhubung!\n";
    $msg .= "Group ID: " . $group . "\n";
    $msg .= "Waktu Tes: " . date('d/m/Y H:i:s') . " WIB\n";
    $msg .= "----------------------------------------\n";
    $msg .= "HaloTiket System Ready 🚀";

    // Panggil fungsi kirim
    $result = sendFonnteWA($group, $msg);

    if ($result['status']) {
        echo json_encode(['status' => 'success', 'message' => 'Pesan tes berhasil dikirim ke Group WA! Silakan periksa WhatsApp Anda.']);
    } else {
        $reason = $result['reason'] ?? 'Unknown error';
        if (is_array($result['details'] ?? null)) {
            foreach ($result['details'] as $det) {
                if (isset($det['response']['reason'])) {
                    $reason = $det['response']['reason'];
                    break;
                }
            }
        }
        if (str_contains($reason, 'disconnected device')) {
            $reason = "Perangkat WhatsApp Fonnte Anda sedang Terputus (Disconnected). Silakan login ke fonnte.com -> Menu Device -> Scan QR Code terlebih dahulu.";
        }
        echo json_encode(['status' => 'error', 'message' => 'Gagal mengirim pesan WA: ' . $reason]);
    }
    exit;
}

// Handle Manual Trigger Cron Job via AJAX
if (isset($_GET['action']) && $_GET['action'] === 'run_cron') {
    header('Content-Type: application/json');
    $_GET['json'] = 1;
    require_once '../cron/run_cron.php';
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    $qris_filename = trim($_POST['qris_image_current'] ?? 'assets/images/QRIS/QRIS.jpeg');

    // Handle Upload QRIS Baru jika ada
    if (isset($_FILES['qris_image']) && $_FILES['qris_image']['error'] === UPLOAD_ERR_OK) {
        $fileTmp = $_FILES['qris_image']['tmp_name'];
        $fileName = $_FILES['qris_image']['name'];
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        if (in_array($ext, $allowed)) {
            $targetDir = '../assets/images/QRIS/';
            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0777, true);
            }
            $newFileName = 'QRIS_' . time() . '.' . $ext;
            if (move_uploaded_file($fileTmp, $targetDir . $newFileName)) {
                $qris_filename = 'assets/images/QRIS/' . $newFileName;
            }
        }
    }

    $settings_to_update = [
        'payment_active_method' => trim($_POST['payment_active_method'] ?? 'both'),
        'bank_nama' => trim($_POST['bank_nama'] ?? 'Bank BCA'),
        'bank_norek' => trim($_POST['bank_norek'] ?? '1234567890'),
        'bank_atas_nama' => trim($_POST['bank_atas_nama'] ?? 'HaloTiket Admin'),
        'qris_image' => $qris_filename,
        'fonnte_token' => trim($_POST['fonnte_token'] ?? 'ep63gV3wUjrZ1RB4NXnW'),
        'fonnte_wa_group' => trim($_POST['fonnte_wa_group'] ?? '120363427898241334@g.us'),
        'fonnte_enable_group_notif' => isset($_POST['fonnte_enable_group_notif']) ? '1' : '0',
        'fonnte_enable_buyer_notif' => isset($_POST['fonnte_enable_buyer_notif']) ? '1' : '0',
        'cron_secret_key' => trim($_POST['cron_secret_key'] ?? 'HTK_CRON_SECRET_KEY_2026'),
        'cron_expiry_hours' => (int)($_POST['cron_expiry_hours'] ?? 24),
        'cron_enable_auto_cancel' => isset($_POST['cron_enable_auto_cancel']) ? '1' : '0',
        'cron_enable_reminder_payment' => isset($_POST['cron_enable_reminder_payment']) ? '1' : '0',
        'cron_enable_reminder_event' => isset($_POST['cron_enable_reminder_event']) ? '1' : '0',
        'google_client_id' => trim($_POST['google_client_id'] ?? ''),
        'google_client_secret' => trim($_POST['google_client_secret'] ?? ''),
        'google_redirect_uri' => trim($_POST['google_redirect_uri'] ?? ''),
        'midtrans_merchant_id' => trim($_POST['midtrans_merchant_id'] ?? ''),
        'midtrans_server_key' => trim($_POST['midtrans_server_key'] ?? ''),
        'midtrans_client_key' => trim($_POST['midtrans_client_key'] ?? ''),
        'midtrans_is_production' => (isset($_POST['midtrans_is_production']) && $_POST['midtrans_is_production'] == '1') ? '1' : '0'
    ];

    $stmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
    foreach ($settings_to_update as $key => $val) {
        $stmt->bind_param("sss", $key, $val, $val);
        $stmt->execute();
    }
    $stmt->close();

    logActivity($conn, $_SESSION['user_id'], 'Update API Config', 'Admin mengubah konfigurasi Metode Pembayaran, Fonnte WhatsApp API, & Google OAuth.');

    $success_msg = "Konfigurasi Metode Pembayaran, WhatsApp Fonnte, & API berhasil disimpan.";
}

// Ambil nilai saat ini
$api_keys = [
    'payment_active_method', 'bank_nama', 'bank_norek', 'bank_atas_nama', 'qris_image',
    'fonnte_token', 'fonnte_wa_group', 'fonnte_enable_group_notif', 'fonnte_enable_buyer_notif',
    'cron_secret_key', 'cron_expiry_hours', 'cron_enable_auto_cancel', 'cron_enable_reminder_payment', 'cron_enable_reminder_event',
    'google_client_id', 'google_client_secret', 'google_redirect_uri', 
    'midtrans_merchant_id', 'midtrans_server_key', 'midtrans_client_key', 'midtrans_is_production'
];
$current_api = [];

$query = "SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('" . implode("','", $api_keys) . "')";
$result = $conn->query($query);
if ($result) {
    while($row = $result->fetch_assoc()) {
        $current_api[$row['setting_key']] = $row['setting_value'];
    }
}

// Fallbacks if not set
$defaults = [
    'payment_active_method' => 'both',
    'bank_nama' => 'Bank BCA',
    'bank_norek' => '1234567890',
    'bank_atas_nama' => 'HaloTiket Admin',
    'qris_image' => 'assets/images/QRIS/QRIS.jpeg',
    'fonnte_token' => 'ep63gV3wUjrZ1RB4NXnW',
    'fonnte_wa_group' => '120363427898241334@g.us',
    'fonnte_enable_group_notif' => '1',
    'fonnte_enable_buyer_notif' => '1',
    'cron_secret_key' => 'HTK_CRON_SECRET_KEY_2026',
    'cron_expiry_hours' => '24',
    'cron_enable_auto_cancel' => '1',
    'cron_enable_reminder_payment' => '1',
    'cron_enable_reminder_event' => '1',
    'google_client_id' => '',
    'google_client_secret' => '',
    'google_redirect_uri' => '',
    'midtrans_merchant_id' => '',
    'midtrans_server_key' => '',
    'midtrans_client_key' => '',
    'midtrans_is_production' => '0'
];

foreach($defaults as $key => $default_val) {
    if(!isset($current_api[$key]) || $current_api[$key] === '') {
        $current_api[$key] = $default_val;
    }
}

$default_redirect_uri = defined('BASE_URL') ? BASE_URL . 'auth/google_callback.php' : 'http://localhost/Halo_Tiket/auth/google_callback.php';
$redirect_uri_val = !empty($current_api['google_redirect_uri']) ? $current_api['google_redirect_uri'] : $default_redirect_uri;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Konfigurasi API - HaloTiket Admin</title>
    <?php if (isset($global_site_favicon) && $global_site_favicon): ?>
        <link rel="icon" href="<?= $global_site_favicon ?>">
    <?php endif; ?>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: { extend: { fontFamily: { sans: ['Outfit','sans-serif'] }, colors: { primary: '#00c2cb', secondary: '#0f1c3f', dark: '#0a1020' } } }
        }
    </script>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
</head>
<body class="bg-slate-50 font-sans antialiased text-slate-800 selection:bg-primary selection:text-white overflow-hidden">
<div class="flex h-screen w-full">
    <div id="sidebarOverlay" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-40 hidden md:hidden transition-opacity"></div>
    <?php $active_menu = 'api'; include 'components/sidebar.php'; ?>

    <!-- Main Content Area -->
    <div class="flex-1 flex flex-col h-screen overflow-hidden bg-slate-50 relative z-10 w-full transition-all duration-300">
        <!-- Unified Top Header -->
        <header class="h-20 bg-white border-b border-slate-200 flex items-center justify-between px-4 lg:px-8 shrink-0 shadow-sm z-20">
            <div class="flex items-center gap-4">
                <button id="hamburgerBtn" class="text-slate-500 hover:text-slate-700 focus:outline-none p-2 rounded-xl hover:bg-slate-100 transition-colors bg-slate-50 border border-slate-200">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                </button>
                <span class="text-xl font-extrabold text-slate-800 md:hidden">API Config</span>
            </div>
            
            <div class="flex items-center gap-4">
                <a href="profile.php" class="hidden md:flex items-center gap-3 mr-2 px-3 py-1.5 rounded-full border border-slate-100 bg-slate-50 hover:bg-slate-100 hover:border-slate-200 transition-colors group cursor-pointer">
                    <?php if (isset($_SESSION['foto_profil']) && !empty($_SESSION['foto_profil']) && (str_starts_with($_SESSION['foto_profil'], 'http') || file_exists('../assets/images/profil/'.$_SESSION['foto_profil']))): ?>
                        <img src="<?= str_starts_with($_SESSION['foto_profil'], 'http') ? htmlspecialchars($_SESSION['foto_profil']) : '../assets/images/profil/'.htmlspecialchars($_SESSION['foto_profil']) ?>" class="w-8 h-8 rounded-full object-cover shadow-sm">
                    <?php else: ?>
                        <div class="w-8 h-8 rounded-full bg-gradient-to-br from-primary to-secondary text-white flex items-center justify-center font-bold text-sm shadow-sm group-hover:shadow transition-all">
                            <?= strtoupper(substr($_SESSION['nama_lengkap'] ?? 'A', 0, 1)) ?>
                        </div>
                    <?php endif; ?>
                    <span class="text-sm font-bold text-slate-700 pr-2 group-hover:text-primary transition-colors"><?= htmlspecialchars($_SESSION['nama_lengkap'] ?? 'Admin') ?></span>
                </a>
            </div>
        </header>

        <main class="flex-1 overflow-y-auto p-4 md:p-6 lg:p-10">
            <div class="max-w-4xl mx-auto">
                <div class="mb-8">
                    <h1 class="text-2xl md:text-3xl font-extrabold text-slate-900 tracking-tight">Konfigurasi API</h1>
                    <p class="text-slate-500 mt-1 font-medium text-sm">Kelola integrasi sistem dengan layanan pihak ketiga seperti Midtrans Payment Gateway dan Google OAuth 2.0.</p>
                </div>

                <?php if($success_msg): ?>
                <div class="auto-dismiss-alert mb-6 bg-emerald-50 text-emerald-700 px-5 py-4 rounded-xl text-sm font-bold flex items-center gap-3 border border-emerald-200 transition-all duration-500">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                    <?= htmlspecialchars($success_msg) ?>
                </div>
                <?php endif; ?>

                <form method="POST" action="" enctype="multipart/form-data" class="space-y-8">
                    
                    <!-- Mode Pembayaran Aktif System -->
                    <div class="bg-white rounded-3xl shadow-sm border border-slate-200 overflow-hidden relative group hover:shadow-md transition-all duration-300">
                        <div class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-emerald-500 to-teal-500"></div>
                        <div class="px-6 py-4 border-b border-slate-100 bg-slate-50/50 flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-white border border-slate-200 flex items-center justify-center text-slate-700 shadow-sm">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                                </svg>
                            </div>
                            <div>
                                <h3 class="font-extrabold text-slate-900 text-sm">Metode Pembayaran Aktif Sistem</h3>
                                <p class="text-xs text-slate-500 font-medium mt-0.5">Pilih metode pembayaran yang diizinkan untuk digunakan pembeli saat checkout.</p>
                            </div>
                        </div>
                        <div class="p-6 space-y-6">
                            <div>
                                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Mode Opsi Pembayaran Pembeli</label>
                                <select name="payment_active_method" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all text-sm font-bold outline-none text-slate-700">
                                    <option value="both" <?= $current_api['payment_active_method'] == 'both' ? 'selected' : '' ?>>Keduanya (Midtrans & Transfer Bank / QRIS Manual)</option>
                                    <option value="midtrans" <?= $current_api['payment_active_method'] == 'midtrans' ? 'selected' : '' ?>>Hanya Midtrans Payment Gateway (Otomatis)</option>
                                    <option value="manual" <?= $current_api['payment_active_method'] == 'manual' ? 'selected' : '' ?>>Hanya Transfer Bank & QRIS Manual (Unggah Bukti Bayar)</option>
                                </select>
                                <p class="text-[11px] text-slate-500 mt-1.5">
                                    💡 <b>Petunjuk:</b> Jika akun Midtrans Production Anda masih status <i>Under Review</i>, Anda bisa memilih opsi <b>"Keduanya"</b> atau <b>"Hanya Transfer Bank & QRIS Manual"</b> agar transaksi pembeli tetap berjalan.
                                </p>
                            </div>

                            <!-- Form Detail Bank & QRIS -->
                            <div class="pt-4 border-t border-slate-100">
                                <h4 class="font-bold text-slate-800 text-xs uppercase tracking-wider mb-4">Informasi Rekening Bank & QRIS Manual</h4>
                                
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                                    <div>
                                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Nama Bank / E-Wallet</label>
                                        <input type="text" name="bank_nama" value="<?= htmlspecialchars($current_api['bank_nama']) ?>" placeholder="Contoh: Bank BCA" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 text-slate-800 text-sm rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary block transition-colors font-medium">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Nomor Rekening</label>
                                        <input type="text" name="bank_norek" value="<?= htmlspecialchars($current_api['bank_norek']) ?>" placeholder="Contoh: 1234567890" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 text-slate-800 text-sm rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary block transition-colors font-medium">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Atas Nama (Owner)</label>
                                        <input type="text" name="bank_atas_nama" value="<?= htmlspecialchars($current_api['bank_atas_nama']) ?>" placeholder="Contoh: PT Halo Tiket Indonesia" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 text-slate-800 text-sm rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary block transition-colors font-medium">
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 items-start">
                                    <div>
                                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Gambar QRIS (File Gambar)</label>
                                        <input type="hidden" name="qris_image_current" value="<?= htmlspecialchars($current_api['qris_image']) ?>">
                                        <input type="file" name="qris_image" accept="image/*" class="w-full text-xs text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-slate-100 file:text-slate-700 hover:file:bg-slate-200 transition-all cursor-pointer">
                                        <p class="text-[10px] text-slate-400 mt-1">Default file: <code><?= htmlspecialchars($current_api['qris_image']) ?></code></p>
                                    </div>
                                    <div class="flex items-center gap-4 bg-slate-50 p-4 rounded-2xl border border-slate-200">
                                        <?php 
                                        $qris_preview_url = BASE_URL . (str_starts_with($current_api['qris_image'], 'assets/') ? $current_api['qris_image'] : 'assets/images/QRIS/QRIS.jpeg');
                                        ?>
                                        <img src="<?= $qris_preview_url ?>" alt="QRIS Preview" class="w-20 h-20 object-contain rounded-lg border border-slate-200 shadow-sm bg-white p-1">
                                        <div class="text-xs">
                                            <span class="font-bold text-slate-800 block">Preview QRIS Aktif</span>
                                            <span class="text-slate-500 text-[11px] block mt-0.5">Gambar ini yang akan ditampilkan ke pembeli saat memilih pembayaran QRIS Manual.</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                    <!-- Fonnte WhatsApp API Config -->
                    <div class="bg-white rounded-3xl shadow-sm border border-slate-200 overflow-hidden relative group hover:shadow-md transition-all duration-300">
                        <div class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-emerald-600 to-green-500"></div>
                        <div class="px-6 py-4 border-b border-slate-100 bg-slate-50/50 flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-xl bg-emerald-50 border border-emerald-200 flex items-center justify-center text-emerald-600 shadow-sm font-bold text-lg">
                                    💬
                                </div>
                                <div>
                                    <h3 class="font-extrabold text-slate-900 text-sm">Pengaturan Notifikasi WhatsApp (Fonnte API)</h3>
                                    <p class="text-xs text-slate-500 font-medium mt-0.5">Kirim pesan WhatsApp otomatis ke Group WA Admin & Nomor HP Pembeli.</p>
                                </div>
                            </div>
                            <button type="button" onclick="testFonnteWA()" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs rounded-xl shadow-md transition-all flex items-center gap-1.5 active:scale-95">
                                <span>📲 Tes Kirim Pesan WA</span>
                            </button>
                        </div>
                        <div class="p-6 space-y-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Fonnte API Token</label>
                                    <input type="text" id="fonnte_token_input" name="fonnte_token" value="<?= htmlspecialchars($current_api['fonnte_token']) ?>" placeholder="Token Fonnte Anda" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 text-slate-800 text-sm rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary block transition-colors font-mono font-bold">
                                    <p class="text-[10px] text-slate-400 mt-1">Token default: <code>ep63gV3wUjrZ1RB4NXnW</code></p>
                                </div>

                                <div>
                                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Target ID Group WA Admin</label>
                                    <input type="text" id="fonnte_wa_group_input" name="fonnte_wa_group" value="<?= htmlspecialchars($current_api['fonnte_wa_group']) ?>" placeholder="Contoh: 120363412788674882@g.us" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 text-slate-800 text-sm rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary block transition-colors font-mono font-bold">
                                    <p class="text-[10px] text-slate-400 mt-1">Group ID: <code>120363412788674882@g.us</code></p>
                                </div>
                            </div>

                            <div class="pt-4 border-t border-slate-100 grid grid-cols-1 md:grid-cols-2 gap-4">
                                <label class="flex items-center gap-3 p-3.5 bg-slate-50 rounded-2xl border border-slate-200 cursor-pointer hover:bg-slate-100 transition-colors">
                                    <input type="checkbox" name="fonnte_enable_group_notif" value="1" <?= $current_api['fonnte_enable_group_notif'] == '1' ? 'checked' : '' ?> class="w-4 h-4 text-emerald-600 rounded focus:ring-emerald-500 accent-emerald-600">
                                    <div class="text-xs">
                                        <span class="font-bold text-slate-800 block">Kirim Notifikasi ke Group WA Admin</span>
                                        <span class="text-slate-500 text-[11px]">Kirim pesan setiap ada pesanan baru & pembayaran lunas ke Group WA.</span>
                                    </div>
                                </label>

                                <label class="flex items-center gap-3 p-3.5 bg-slate-50 rounded-2xl border border-slate-200 cursor-pointer hover:bg-slate-100 transition-colors">
                                    <input type="checkbox" name="fonnte_enable_buyer_notif" value="1" <?= $current_api['fonnte_enable_buyer_notif'] == '1' ? 'checked' : '' ?> class="w-4 h-4 text-emerald-600 rounded focus:ring-emerald-500 accent-emerald-600">
                                    <div class="text-xs">
                                        <span class="font-bold text-slate-800 block">Kirim Notifikasi Langsung ke WA Pembeli</span>
                                        <span class="text-slate-500 text-[11px]">Kirim link bayar & E-Ticket langsung ke nomor WhatsApp pembeli.</span>
                                    </div>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Cron Job System Automation Config -->
                    <div class="bg-white rounded-3xl shadow-sm border border-slate-200 overflow-hidden relative group hover:shadow-md transition-all duration-300">
                        <div class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-amber-500 to-orange-500"></div>
                        <div class="px-6 py-4 border-b border-slate-100 bg-slate-50/50 flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-xl bg-amber-50 border border-amber-200 flex items-center justify-center text-amber-600 shadow-sm font-bold text-lg">
                                    ⚙️
                                </div>
                                <div>
                                    <h3 class="font-extrabold text-slate-900 text-sm">Pengaturan Cron Job & Otomatisasi Sistem</h3>
                                    <p class="text-xs text-slate-500 font-medium mt-0.5">Auto-cancel tiket kadaluarsa, pengingat pembayaran, & pengingat H-1 event.</p>
                                </div>
                            </div>
                            <button type="button" onclick="runCronJobNow()" class="px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white font-bold text-xs rounded-xl shadow-md transition-all flex items-center gap-1.5 active:scale-95">
                                <span>⚡ Jalankan Cron Sekarang</span>
                            </button>
                        </div>
                        <div class="p-6 space-y-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Batas Waktu Kadaluarsa Pembayaran (Jam)</label>
                                    <input type="number" min="1" max="168" name="cron_expiry_hours" value="<?= htmlspecialchars($current_api['cron_expiry_hours']) ?>" placeholder="24" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 text-slate-800 text-sm rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary block transition-colors font-bold">
                                    <p class="text-[10px] text-slate-400 mt-1">Tiket pending yang berumur lebih dari jumlah jam ini akan dibatalkan otomatis.</p>
                                </div>

                                <div>
                                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Cron Secret Key (Keamanan Cron)</label>
                                    <input type="text" name="cron_secret_key" value="<?= htmlspecialchars($current_api['cron_secret_key']) ?>" placeholder="Kunci Rahasia Cron" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 text-slate-800 text-sm rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary block transition-colors font-mono font-bold">
                                    <p class="text-[10px] text-slate-400 mt-1">Digunakan untuk otentikasi eksekusi cron dari luar server.</p>
                                </div>
                            </div>

                            <div class="pt-4 border-t border-slate-100 grid grid-cols-1 md:grid-cols-3 gap-4">
                                <label class="flex items-center gap-3 p-3 bg-slate-50 rounded-2xl border border-slate-200 cursor-pointer hover:bg-slate-100 transition-colors">
                                    <input type="checkbox" name="cron_enable_auto_cancel" value="1" <?= $current_api['cron_enable_auto_cancel'] == '1' ? 'checked' : '' ?> class="w-4 h-4 text-amber-600 rounded focus:ring-amber-500 accent-amber-600">
                                    <div class="text-xs">
                                        <span class="font-bold text-slate-800 block">Auto-Cancel Kadaluarsa</span>
                                        <span class="text-slate-500 text-[11px]">Batalkan & kembalikan stok tiket pending kadaluarsa.</span>
                                    </div>
                                </label>

                                <label class="flex items-center gap-3 p-3 bg-slate-50 rounded-2xl border border-slate-200 cursor-pointer hover:bg-slate-100 transition-colors">
                                    <input type="checkbox" name="cron_enable_reminder_payment" value="1" <?= $current_api['cron_enable_reminder_payment'] == '1' ? 'checked' : '' ?> class="w-4 h-4 text-amber-600 rounded focus:ring-amber-500 accent-amber-600">
                                    <div class="text-xs">
                                        <span class="font-bold text-slate-800 block">WA Pengingat Pembayaran</span>
                                        <span class="text-slate-500 text-[11px]">Kirim WA pengingat 1 jam setelah checkout.</span>
                                    </div>
                                </label>

                                <label class="flex items-center gap-3 p-3 bg-slate-50 rounded-2xl border border-slate-200 cursor-pointer hover:bg-slate-100 transition-colors">
                                    <input type="checkbox" name="cron_enable_reminder_event" value="1" <?= $current_api['cron_enable_reminder_event'] == '1' ? 'checked' : '' ?> class="w-4 h-4 text-amber-600 rounded focus:ring-amber-500 accent-amber-600">
                                    <div class="text-xs">
                                        <span class="font-bold text-slate-800 block">WA Pengingat H-1 Event</span>
                                        <span class="text-slate-500 text-[11px]">Kirim WA pengingat H-1 ke peserta terdaftar.</span>
                                    </div>
                                </label>
                            </div>

                            <!-- Petunjuk Pemasangan Cron Job cPanel -->
                            <div class="pt-4 border-t border-slate-100">
                                <details class="group">
                                    <summary class="flex items-center justify-between cursor-pointer font-bold text-xs text-amber-800 hover:text-amber-900 transition-colors">
                                        <span class="flex items-center gap-1.5">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                            📋 Panduan Perintah Pemasangan Cron Job di cPanel
                                        </span>
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 group-open:rotate-180 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                                    </summary>
                                    <div class="mt-3 text-xs text-slate-600 space-y-3 bg-amber-50/50 p-4 rounded-2xl border border-amber-200">
                                        <div>
                                            <p class="font-bold text-slate-800 mb-1">1. Opsi Command Line (CLI Command - Disarankan):</p>
                                            <code class="block bg-slate-900 text-emerald-400 p-2.5 rounded-xl font-mono text-[11px] select-all">php <?= str_replace('\\', '/', dirname(__DIR__)) ?>/cron/run_cron.php</code>
                                        </div>
                                        <div>
                                            <p class="font-bold text-slate-800 mb-1">2. Opsi URL Web Cron (wget / curl):</p>
                                            <code class="block bg-slate-900 text-amber-300 p-2.5 rounded-xl font-mono text-[11px] select-all">wget -O /dev/null "<?= BASE_URL ?>cron/run_cron.php?key=<?= htmlspecialchars($current_api['cron_secret_key']) ?>"</code>
                                        </div>
                                        <p class="text-[11px] text-slate-500">Setting interval rekomendasi cPanel: <b>Every 15 Minutes</b> (<code>*/15 * * * *</code>).</p>
                                    </div>
                                </details>
                            </div>
                        </div>
                    </div>

                    <!-- Midtrans API Config -->
                    <div class="bg-white rounded-3xl shadow-sm border border-slate-200 overflow-hidden relative group hover:shadow-md transition-all duration-300">
                        <div class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-blue-500 to-indigo-500"></div>
                        <div class="px-6 py-4 border-b border-slate-100 bg-slate-50/50 flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-white border border-slate-200 flex items-center justify-center text-slate-700 shadow-sm">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                                </svg>
                            </div>
                            <div>
                                <h3 class="font-extrabold text-slate-900 text-sm">Pengaturan Midtrans Payment Gateway</h3>
                                <p class="text-xs text-slate-500 font-medium mt-0.5">Konfigurasi sistem pembayaran otomatis via Midtrans Snap API.</p>
                            </div>
                        </div>
                        <div class="p-6 space-y-6">
                            <div>
                                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Environment Pembayaran</label>
                                <select name="midtrans_is_production" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all text-sm font-bold outline-none text-slate-700">
                                    <option value="0" <?= $current_api['midtrans_is_production'] == '0' ? 'selected' : '' ?>>Sandbox (Mode Testing / Uji Coba)</option>
                                    <option value="1" <?= $current_api['midtrans_is_production'] == '1' ? 'selected' : '' ?>>Production (Mode Live / Transaksi Asli)</option>
                                </select>
                                <p class="text-[10px] text-slate-400 mt-1">Pastikan kunci yang dimasukkan di bawah sesuai dengan environment yang dipilih.</p>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                <div>
                                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Merchant ID</label>
                                    <input type="text" name="midtrans_merchant_id" value="<?= htmlspecialchars($current_api['midtrans_merchant_id']) ?>" placeholder="Contoh: G123456789" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 text-slate-800 text-sm rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary block transition-colors font-medium">
                                </div>

                                <div>
                                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Client Key</label>
                                    <input type="text" name="midtrans_client_key" value="<?= htmlspecialchars($current_api['midtrans_client_key']) ?>" placeholder="SB-Mid-client-..." class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 text-slate-800 text-sm rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary block transition-colors font-medium">
                                    <p class="text-[10px] text-slate-400 mt-1">Sandbox: diawali <code>SB-Mid-client-</code></p>
                                </div>

                                <div>
                                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Server Key</label>
                                    <input type="text" name="midtrans_server_key" value="<?= htmlspecialchars($current_api['midtrans_server_key']) ?>" placeholder="SB-Mid-server-..." class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 text-slate-800 text-sm rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary block transition-colors font-medium">
                                    <p class="text-[10px] text-slate-400 mt-1">Sandbox: diawali <code>SB-Mid-server-</code></p>
                                </div>
                            </div>

                            <!-- Panduan Simulator Sandbox Midtrans -->
                            <div class="mt-4 pt-4 border-t border-slate-100">
                                <details class="group">
                                    <summary class="flex items-center justify-between cursor-pointer font-bold text-xs text-blue-700 hover:text-blue-900 transition-colors">
                                        <span class="flex items-center gap-1.5">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                            Panduan & Kredensial Uji Coba Pembayaran (Sandbox Simulator)
                                        </span>
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 group-open:rotate-180 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                                    </summary>
                                    <div class="mt-3 text-xs text-slate-600 space-y-3 bg-blue-50/50 p-4 rounded-xl border border-blue-100 shadow-sm">
                                        <div>
                                            <p class="font-bold text-slate-800 mb-1">💳 Kartu Kredit / Debit (Test Cards):</p>
                                            <ul class="list-disc list-inside space-y-0.5 text-slate-600">
                                                <li><b>VISA (Accept 3DS):</b> <code>4811 1111 1111 1114</code> | Exp: Bulan/Tahun Bebas | CVV: <code>123</code> | OTP: <code>112233</code></li>
                                                <li><b>Mastercard (Accept 3DS):</b> <code>5211 1111 1111 1117</code> | CVV: <code>123</code> | OTP: <code>112233</code></li>
                                                <li><b>VISA Denied by Bank:</b> <code>4911 1111 1111 1113</code></li>
                                            </ul>
                                        </div>

                                        <div>
                                            <p class="font-bold text-slate-800 mb-1">🏦 Simulator Virtual Account (Bank Transfer):</p>
                                            <div class="flex flex-wrap gap-2 mt-1">
                                                <a href="https://simulator.sandbox.midtrans.com/bca/va/index" target="_blank" class="px-2 py-1 bg-blue-50 text-blue-700 rounded border border-blue-200 hover:bg-blue-100 font-semibold">Simulator BCA VA</a>
                                                <a href="https://simulator.sandbox.midtrans.com/bni/va/index" target="_blank" class="px-2 py-1 bg-orange-50 text-orange-700 rounded border border-orange-200 hover:bg-orange-100 font-semibold">Simulator BNI VA</a>
                                                <a href="https://simulator.sandbox.midtrans.com/openapi/va/index?bank=bri" target="_blank" class="px-2 py-1 bg-blue-50 text-blue-700 rounded border border-blue-200 hover:bg-blue-100 font-semibold">Simulator BRI VA</a>
                                                <a href="https://simulator.sandbox.midtrans.com/openapi/va/index?bank=mandiri" target="_blank" class="px-2 py-1 bg-amber-50 text-amber-700 rounded border border-amber-200 hover:bg-amber-100 font-semibold">Simulator Mandiri Bill</a>
                                                <a href="https://simulator.sandbox.midtrans.com/openapi/va/index?bank=permata" target="_blank" class="px-2 py-1 bg-emerald-50 text-emerald-700 rounded border border-emerald-200 hover:bg-emerald-100 font-semibold">Simulator Permata VA</a>
                                            </div>
                                        </div>

                                        <div>
                                            <p class="font-bold text-slate-800 mb-1">📱 E-Wallet & QRIS:</p>
                                            <ul class="list-disc list-inside space-y-0.5 text-slate-600">
                                                <li><b>QRIS Simulator:</b> <a href="https://simulator.sandbox.midtrans.com/v2/qris/index" target="_blank" class="text-blue-600 underline font-semibold">Link QRIS Simulator</a> (Paste URL Gambar QRIS)</li>
                                                <li><b>GoPay / ShopeePay / DANA:</b> Gunakan PIN <code>123456</code> pada simulator.</li>
                                            </ul>
                                        </div>

                                        <div>
                                            <p class="font-bold text-slate-800 mb-1">🏪 Gerai Ritel (Convenience Store):</p>
                                            <div class="flex flex-wrap gap-2 mt-1">
                                                <a href="https://simulator.sandbox.midtrans.com/indomaret/phoenix/index" target="_blank" class="px-2 py-1 bg-red-50 text-red-700 rounded border border-red-200 hover:bg-red-100 font-semibold">Simulator Indomaret</a>
                                                <a href="https://simulator.sandbox.midtrans.com/alfamart/index" target="_blank" class="px-2 py-1 bg-red-50 text-red-700 rounded border border-red-200 hover:bg-red-100 font-semibold">Simulator Alfamart</a>
                                            </div>
                                        </div>
                                    </div>
                                </details>
                            </div>
                        </div>
                    </div>

                    <!-- Google API Config -->
                    <div class="bg-white rounded-3xl shadow-sm border border-slate-200 overflow-hidden relative group hover:shadow-md transition-all duration-300">
                        <div class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-red-500 to-yellow-500"></div>
                        <div class="px-6 py-4 border-b border-slate-100 bg-slate-50/50 flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-white border border-slate-200 flex items-center justify-center text-slate-700 shadow-sm">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-red-500" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
                                    <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
                                    <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/>
                                    <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
                                </svg>
                            </div>
                            <div>
                                <h3 class="font-extrabold text-slate-900 text-sm">Pengaturan Google OAuth 2.0 (Login Google)</h3>
                                <p class="text-xs text-slate-500 font-medium mt-0.5">Konfigurasi otentikasi menggunakan Google OAuth 2.0</p>
                            </div>
                        </div>
                        <div class="p-6 space-y-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Google Client ID</label>
                                    <input type="text" name="google_client_id" value="<?= htmlspecialchars($current_api['google_client_id']) ?>" placeholder="Contoh: 123456789-abc.apps.googleusercontent.com" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 text-slate-800 text-sm rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary block transition-colors font-medium">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Google Client Secret</label>
                                    <input type="text" name="google_client_secret" value="<?= htmlspecialchars($current_api['google_client_secret']) ?>" placeholder="GOCSPX-..." class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 text-slate-800 text-sm rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary block transition-colors font-medium">
                                </div>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Authorized Redirect URI (Diinput di Google Cloud Console)</label>
                                <input type="text" name="google_redirect_uri" value="<?= htmlspecialchars($redirect_uri_val) ?>" class="w-full px-4 py-2.5 bg-white border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all text-sm font-mono font-bold text-primary outline-none">
                                <p class="text-xs text-slate-500 mt-1.5">
                                    ⚠️ <strong>PENTING:</strong> String URL di atas HARUS dicopy persis sama tanpa beda 1 karakter pun ke <strong>Authorized redirect URIs</strong> di Google Cloud Console untuk menghindari <code>redirect_uri_mismatch</code>.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end pt-4">
                        <button type="submit" class="bg-primary hover:bg-primary/90 text-white font-bold py-2.5 px-6 rounded-xl transition-all duration-300 transform hover:-translate-y-0.5 shadow-lg shadow-primary/30 text-sm flex items-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                            Simpan Konfigurasi API
                        </button>
                    </div>

                </form>
            </div>
        </main>
    </div>
</div>

<!-- Script for Sidebar Toggle -->
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const sidebar = document.getElementById('sidebar');
        const hamburgerBtn = document.getElementById('hamburgerBtn');
        const closeSidebar = document.getElementById('closeSidebar');
        const sidebarOverlay = document.getElementById('sidebarOverlay');

        function toggleSidebar() {
            if(sidebar) {
                if (window.innerWidth < 768) {
                    sidebar.classList.toggle('-translate-x-full');
                    if(sidebarOverlay) sidebarOverlay.classList.toggle('hidden');
                } else {
                    sidebar.classList.toggle('md:hidden');
                    if(sidebar.classList.contains('md:hidden') && sidebarOverlay) {
                        sidebarOverlay.classList.remove('hidden');
                    } else if (sidebarOverlay) {
                        sidebarOverlay.classList.add('hidden');
                    }
                }
            }
        }

        if(hamburgerBtn) hamburgerBtn.addEventListener('click', toggleSidebar);
        
        if(closeSidebar && sidebar) {
            closeSidebar.addEventListener('click', () => {
                sidebar.classList.add('-translate-x-full');
                if(sidebarOverlay) sidebarOverlay.classList.add('hidden');
            });
        }

        if(sidebarOverlay && sidebar) {
            sidebarOverlay.addEventListener('click', () => {
                sidebar.classList.add('-translate-x-full');
                sidebarOverlay.classList.add('hidden');
            });
        }

        window.addEventListener('resize', () => {
            if(sidebar) {
                if (window.innerWidth >= 768) {
                    sidebar.classList.remove('-translate-x-full');
                    if(sidebarOverlay) sidebarOverlay.classList.add('hidden');
                }
            }
        });

        // Auto dismiss alert notifications after 5 seconds
        setTimeout(() => {
            const alerts = document.querySelectorAll('.auto-dismiss-alert');
            alerts.forEach(alert => {
                alert.style.opacity = '0';
                alert.style.transform = 'translateY(-10px)';
                setTimeout(() => alert.remove(), 500);
            });
        }, 5000);
    });

    function testFonnteWA() {
        const token = document.getElementById('fonnte_token_input').value;
        const group = document.getElementById('fonnte_wa_group_input').value;

        if (!token || !group) {
            alert('Harap isi Fonnte API Token dan Target ID Group WA terlebih dahulu.');
            return;
        }

        const formData = new FormData();
        formData.append('test_token', token);
        formData.append('test_group', group);

        fetch('api_settings.php?action=test_fonnte', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            alert(data.message);
        })
        .catch(err => {
            alert('Gagal mengirim pesan tes WA: ' + err.message);
        });
    }

    function runCronJobNow() {
        if (!confirm('Apakah Anda yakin ingin memicu eksekusi Cron Job sekarang?')) {
            return;
        }

        fetch('api_settings.php?action=run_cron')
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                alert('⚡ ' + data.message);
            } else {
                alert('Gagal menjalankan Cron Job: ' + (data.message || 'Terjadi kesalahan'));
            }
        })
        .catch(err => {
            alert('Terjadi kesalahan saat memicu Cron Job: ' + err.message);
        });
    }
</script>
</body>
</html>
