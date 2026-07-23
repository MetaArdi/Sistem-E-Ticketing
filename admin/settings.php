<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../auth/login.php");
    exit;
}
require_once '../config/koneksi.php';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // Proses Upload Logo
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] == 0) {
        $filename = time() . '_' . basename($_FILES['logo']['name']);
        $target = "../assets/images/logo/" . $filename;
        if (move_uploaded_file($_FILES['logo']['tmp_name'], $target)) {
            $stmt = $conn->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'site_logo'");
            $stmt->bind_param("s", $filename);
            $stmt->execute();
        }
    }

    // Proses Upload Favicon
    if (isset($_FILES['favicon']) && $_FILES['favicon']['error'] == 0) {
        $filename = time() . '_' . basename($_FILES['favicon']['name']);
        $target = "../assets/images/favicon/" . $filename;
        if (move_uploaded_file($_FILES['favicon']['tmp_name'], $target)) {
            $stmt = $conn->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'site_favicon'");
            $stmt->bind_param("s", $filename);
            $stmt->execute();
        }
    }

    // Handle Slider Slide Addition
    if (isset($_POST['action']) && $_POST['action'] == 'add_slider') {
        $slide_title = trim($_POST['slide_title'] ?? '');
        $slide_subtitle = trim($_POST['slide_subtitle'] ?? '');
        $slide_link = trim($_POST['slide_link'] ?? '');
        
        if (isset($_FILES['slider_image']) && $_FILES['slider_image']['error'] == 0) {
            $allowed = ['jpg', 'jpeg', 'png', 'webp'];
            $ext = strtolower(pathinfo($_FILES['slider_image']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, $allowed)) {
                $upload_dir = "../assets/images/slider/";
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                $filename = time() . '_' . uniqid() . '.' . $ext;
                $target = $upload_dir . $filename;
                if (move_uploaded_file($_FILES['slider_image']['tmp_name'], $target)) {
                    $sq = $conn->query("SELECT setting_value FROM settings WHERE setting_key = 'landing_hero_slider'");
                    $current_sliders = [];
                    if ($sq && $sq->num_rows > 0) {
                        $row = $sq->fetch_assoc();
                        $current_sliders = json_decode($row['setting_value'], true) ?: [];
                    }
                    
                    $new_slide = [
                        'id' => uniqid('slide_'),
                        'image' => $filename,
                        'title' => $slide_title,
                        'subtitle' => $slide_subtitle,
                        'link' => $slide_link,
                        'created_at' => date('Y-m-d H:i:s')
                    ];
                    
                    $current_sliders[] = $new_slide;
                    $new_json = json_encode(array_values($current_sliders));
                    
                    $stmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('landing_hero_slider', ?) ON DUPLICATE KEY UPDATE setting_value = ?");
                    $stmt->bind_param("ss", $new_json, $new_json);
                    $stmt->execute();
                    $stmt->close();
                    
                    logActivity($conn, $_SESSION['user_id'], 'Add Slider', 'Admin menambahkan slide baru pada slider landing page.');
                    $success_msg = "Slide slider baru berhasil ditambahkan.";
                } else {
                    $error_msg = "Gagal mengunggah gambar slider.";
                }
            } else {
                $error_msg = "Format gambar tidak didukung (Hanya JPG, JPEG, PNG, WEBP).";
            }
        } else {
            $error_msg = "Pilih gambar slider yang ingin diunggah.";
        }
    }

    // Handle Slider Slide Deletion
    if (isset($_POST['action']) && $_POST['action'] == 'delete_slider') {
        $delete_id = $_POST['slider_id'] ?? '';
        if (!empty($delete_id)) {
            $sq = $conn->query("SELECT setting_value FROM settings WHERE setting_key = 'landing_hero_slider'");
            if ($sq && $sq->num_rows > 0) {
                $row = $sq->fetch_assoc();
                $current_sliders = json_decode($row['setting_value'], true) ?: [];
                
                $updated_sliders = [];
                foreach ($current_sliders as $slide) {
                    if ($slide['id'] === $delete_id) {
                        $file_path = "../assets/images/slider/" . $slide['image'];
                        if (file_exists($file_path) && is_file($file_path)) {
                            @unlink($file_path);
                        }
                    } else {
                        $updated_sliders[] = $slide;
                    }
                }
                
                $new_json = json_encode(array_values($updated_sliders));
                $stmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('landing_hero_slider', ?) ON DUPLICATE KEY UPDATE setting_value = ?");
                $stmt->bind_param("ss", $new_json, $new_json);
                $stmt->execute();
                $stmt->close();
                
                logActivity($conn, $_SESSION['user_id'], 'Delete Slider', 'Admin menghapus slide slider landing page.');
                $success_msg = "Slide slider berhasil dihapus.";
            }
        }
    }

    // Proses Text Settings
    $text_settings = ['contact_address', 'contact_cs', 'link_ig', 'link_tiktok', 'admin_markup_type', 'admin_markup_value', 'maintenance_mode', 'midtrans_merchant_id', 'midtrans_server_key', 'midtrans_client_key', 'midtrans_is_production'];
    $stmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
    foreach ($text_settings as $key) {
        if (isset($_POST[$key])) {
            $val = trim($_POST[$key]);
            $stmt->bind_param("sss", $key, $val, $val);
            $stmt->execute();
        }
    }

    logActivity($conn, $_SESSION['user_id'], 'Update Settings', 'Admin mengubah pengaturan sistem (Tampilan, Midtrans & Kontak).');

    if (!isset($success_msg)) {
        $success_msg = "Pengaturan berhasil disimpan.";
    }
}

// Ambil nilai saat ini
$logo_query = $conn->query("SELECT setting_value FROM settings WHERE setting_key = 'site_logo'");
$current_logo = $logo_query->num_rows > 0 ? $logo_query->fetch_assoc()['setting_value'] : '';

$favicon_query = $conn->query("SELECT setting_value FROM settings WHERE setting_key = 'site_favicon'");
$current_favicon = $favicon_query->num_rows > 0 ? $favicon_query->fetch_assoc()['setting_value'] : '';

$slider_query = $conn->query("SELECT setting_value FROM settings WHERE setting_key = 'landing_hero_slider'");
$current_sliders = [];
if ($slider_query && $slider_query->num_rows > 0) {
    $current_sliders = json_decode($slider_query->fetch_assoc()['setting_value'], true) ?: [];
}

$text_settings_keys = ['contact_address', 'contact_cs', 'link_ig', 'link_tiktok', 'admin_markup_type', 'admin_markup_value', 'maintenance_mode', 'midtrans_merchant_id', 'midtrans_server_key', 'midtrans_client_key', 'midtrans_is_production'];
$current_text_settings = [];
foreach($text_settings_keys as $k) {
    $q = $conn->query("SELECT setting_value FROM settings WHERE setting_key = '$k'");
    $current_text_settings[$k] = $q->num_rows > 0 ? $q->fetch_assoc()['setting_value'] : '';
}

$addr_val = $current_text_settings['contact_address'] ?: "Garung Lor,Kec. Kaliwungu, Kabupaten Kudus, Jawa Tengah";
$cs_val = $current_text_settings['contact_cs'] ?: "6281234567890";
$ig_val = $current_text_settings['link_ig'] ?: "https://instagram.com";
$tiktok_val = $current_text_settings['link_tiktok'] ?: "https://tiktok.com";
$markup_type_val = $current_text_settings['admin_markup_type'] ?: "nominal";
$markup_value_val = $current_text_settings['admin_markup_value'] ?: "5000";
$maintenance_mode_val = $current_text_settings['maintenance_mode'] ?? '0';
$midtrans_merchant_id_val = $current_text_settings['midtrans_merchant_id'] ?? '';
$midtrans_server_key_val = $current_text_settings['midtrans_server_key'] ?? '';
$midtrans_client_key_val = $current_text_settings['midtrans_client_key'] ?? '';
$midtrans_is_production_val = $current_text_settings['midtrans_is_production'] ?? '0';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Settings - HaloTiket Admin</title>
    <?php if (isset($global_site_favicon) && $global_site_favicon): ?>
        <link rel="icon" href="<?= $global_site_favicon ?>" type="image/x-icon">
    <?php endif; ?>

    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Outfit', 'sans-serif'],
                    },
                    colors: {
                        primary: '#00c2cb',
                        secondary: '#0f1c3f',
                        dark: '#0a1020',
                    }
                }
            }
        }
    </script>

    <!-- Custom Stylesheet -->
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
</head>
<body class="bg-slate-50 font-sans antialiased text-slate-800 selection:bg-primary selection:text-white overflow-hidden">
    <div class="flex h-screen w-full">
        
        <?php $active_menu = 'settings'; include 'components/sidebar.php'; ?>

        <!-- Main Content Area -->
        <div class="flex-1 flex flex-col h-screen overflow-hidden bg-slate-50 relative z-10 w-full transition-all duration-300">
            
            <!-- Unified Top Header -->
                        <!-- Unified Top Header -->
            <header class="h-20 bg-white border-b border-slate-200 flex items-center justify-between px-4 lg:px-8 shrink-0 shadow-sm z-20">
                <div class="flex items-center gap-4">
                    <button id="hamburgerBtn" class="text-slate-500 hover:text-slate-700 focus:outline-none p-2 rounded-xl hover:bg-slate-100 transition-colors bg-slate-50 border border-slate-200">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>
                    <span class="text-xl font-extrabold text-slate-800 md:hidden">Admin</span>
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
                        <h1 class="text-2xl md:text-3xl font-extrabold text-slate-900 tracking-tight">System Settings</h1>
                        <p class="text-slate-500 mt-1 md:mt-2 font-medium text-sm md:text-base">Kelola logo, identitas visual, dan konfigurasi inti sistem.</p>
                    </div>

                    <?php if(isset($success_msg)): ?>
                        <div class="mb-6 bg-emerald-50 text-emerald-600 px-4 py-4 rounded-xl text-sm font-bold flex items-center border border-emerald-200">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                            <?= $success_msg ?>
                        </div>
                    <?php endif; ?>

                    <div class="bg-white rounded-3xl shadow-sm border border-slate-200 overflow-hidden">
                        <div class="px-6 py-4 border-b border-slate-100 bg-slate-50/50">
                            <h3 class="font-extrabold text-slate-900 flex items-center gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                                Pengaturan Branding Visual
                            </h3>
                        </div>
                        <div class="p-6">
                            <form action="" method="POST" enctype="multipart/form-data" class="space-y-8">
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                                    <!-- Main Logo -->
                                    <div>
                                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Main Logo Sistem</label>
                                        <p class="text-xs text-slate-400 mb-4">Logo ini akan ditampilkan di halaman login dan header. Format: PNG transparan.</p>
                                        
                                        <?php if($current_logo): ?>
                                            <div class="mb-4 bg-slate-100 p-4 rounded-xl inline-block border border-slate-200">
                                                <img src="<?= BASE_URL ?>assets/images/logo/<?= htmlspecialchars($current_logo) ?>" alt="Current Logo" class="h-16 object-contain">
                                            </div>
                                        <?php else: ?>
                                            <div class="mb-4 bg-slate-50 p-4 rounded-xl border border-dashed border-slate-300 text-slate-400 text-sm text-center">
                                                Belum ada logo.
                                            </div>
                                        <?php endif; ?>

                                        <input type="file" id="logoInput" name="logo" accept="image/*" class="w-full text-sm font-medium file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-bold file:bg-primary/10 file:text-primary hover:file:bg-primary/20 transition-all">
                                        <div id="logoPreviewContainer" class="hidden mt-3 p-3 bg-slate-50 border border-slate-200 rounded-2xl flex items-center gap-3">
                                            <img id="logoPreviewImg" class="h-14 object-contain rounded-lg border border-slate-200 bg-white p-1">
                                            <span class="text-xs font-bold text-slate-700">Pratinjau Logo Baru</span>
                                        </div>
                                    </div>

                                    <!-- Favicon -->
                                    <div>
                                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Favicon (Ikon Tab)</label>
                                        <p class="text-xs text-slate-400 mb-4">Ikon kecil yang muncul di tab browser. Rasio 1:1, ukuran rekomendasi 64x64px.</p>
                                        
                                        <?php if($current_favicon): ?>
                                            <div class="mb-4 bg-slate-100 p-4 rounded-xl inline-block border border-slate-200">
                                                <img src="<?= BASE_URL ?>assets/images/favicon/<?= htmlspecialchars($current_favicon) ?>" alt="Current Favicon" class="h-10 w-10 object-contain">
                                            </div>
                                        <?php else: ?>
                                            <div class="mb-4 bg-slate-50 p-4 rounded-xl border border-dashed border-slate-300 text-slate-400 text-sm text-center">
                                                Belum ada favicon.
                                            </div>
                                        <?php endif; ?>

                                        <input type="file" id="faviconInput" name="favicon" accept="image/*" class="w-full text-sm font-medium file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-bold file:bg-primary/10 file:text-primary hover:file:bg-primary/20 transition-all">
                                        <div id="faviconPreviewContainer" class="hidden mt-3 p-3 bg-slate-50 border border-slate-200 rounded-2xl flex items-center gap-3">
                                            <img id="faviconPreviewImg" class="h-10 w-10 object-contain rounded-lg border border-slate-200 bg-white p-1">
                                            <span class="text-xs font-bold text-slate-700">Pratinjau Favicon Baru</span>
                                        </div>
                                    </div>
                                </div>

                                <hr class="border-slate-100">

                                <!-- Image Slider Landing Page -->
                                <div>
                                    <h4 class="text-sm font-bold text-slate-800 mb-2 flex items-center gap-2">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                                        Pengaturan Image Slider Landing Page
                                    </h4>
                                    <p class="text-xs text-slate-400 mb-5">Kelola spanduk promo/event yang tampil pada slider paling atas di halaman utama (Landing Page).</p>

                                    <!-- Slide List -->
                                    <?php if (!empty($current_sliders)): ?>
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                                            <?php foreach ($current_sliders as $idx => $slide): ?>
                                                <div class="relative group bg-slate-50 border border-slate-200 rounded-2xl overflow-hidden shadow-sm p-3 flex flex-col justify-between">
                                                    <div>
                                                        <div class="h-32 w-full rounded-xl overflow-hidden bg-slate-200 relative mb-3">
                                                            <img src="<?= BASE_URL ?>assets/images/slider/<?= htmlspecialchars($slide['image']) ?>" class="w-full h-full object-cover">
                                                            <span class="absolute top-2 left-2 bg-slate-900/80 text-white text-[10px] font-bold px-2 py-0.5 rounded-full">Slide <?= $idx + 1 ?></span>
                                                        </div>
                                                        <h5 class="text-xs font-bold text-slate-800 truncate"><?= !empty($slide['title']) ? htmlspecialchars($slide['title']) : '(Tanpa Judul)' ?></h5>
                                                        <p class="text-[11px] text-slate-500 truncate mb-1"><?= !empty($slide['subtitle']) ? htmlspecialchars($slide['subtitle']) : '(Tanpa Subjudul)' ?></p>
                                                        <?php if (!empty($slide['link'])): ?>
                                                            <a href="<?= htmlspecialchars($slide['link']) ?>" target="_blank" class="text-[10px] font-bold text-primary truncate block hover:underline">Link: <?= htmlspecialchars($slide['link']) ?></a>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="mt-3 pt-3 border-t border-slate-200 flex justify-end">
                                                        <form method="POST" action="" onsubmit="return confirm('Yakin ingin menghapus slide slider ini?');">
                                                            <input type="hidden" name="action" value="delete_slider">
                                                            <input type="hidden" name="slider_id" value="<?= htmlspecialchars($slide['id']) ?>">
                                                            <button type="submit" class="px-3 py-1.5 bg-red-50 hover:bg-red-100 text-red-600 rounded-xl text-xs font-bold transition-colors flex items-center gap-1">
                                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                                                Hapus Slide
                                                            </button>
                                                        </form>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="mb-6 bg-slate-50 border border-dashed border-slate-200 rounded-2xl p-6 text-center text-slate-400 text-xs font-medium">
                                            Belum ada slide custom yang diunggah. Slider landing page akan menampilkan spanduk default sistem.
                                        </div>
                                    <?php endif; ?>

                                    <!-- Form Tambah Slide Baru -->
                                    <div class="bg-slate-50/80 border border-slate-200 rounded-2xl p-5">
                                        <h5 class="text-xs font-bold text-slate-700 uppercase tracking-wider mb-4 flex items-center gap-2">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                                            Tambah Slide Slider Baru
                                        </h5>
                                        <form method="POST" action="" enctype="multipart/form-data" class="space-y-4">
                                            <input type="hidden" name="action" value="add_slider">
                                            
                                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                                <div class="md:col-span-2">
                                                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Gambar Slider <span class="text-red-500">*</span></label>
                                                    <input type="file" id="newSliderInput" name="slider_image" accept="image/*" required class="w-full text-sm font-medium file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-bold file:bg-primary/10 file:text-primary hover:file:bg-primary/20 transition-all cursor-pointer">
                                                    <p class="text-[10px] text-slate-400 mt-1">Rekomendasi rasio 16:9 atau 21:9. Format JPG, PNG, WEBP.</p>

                                                    <!-- Live Preview for Slider Upload -->
                                                    <div id="newSliderPreviewContainer" class="hidden mt-3 p-3 bg-white border border-slate-200 rounded-2xl">
                                                        <p class="text-xs font-bold text-slate-700 mb-2">Pratinjau Gambar Slider Baru</p>
                                                        <img id="newSliderPreviewImg" class="w-full h-36 object-cover rounded-xl border border-slate-200 shadow-sm">
                                                    </div>
                                                </div>

                                                <div>
                                                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Judul Banner <span class="text-slate-400 font-normal">(Opsional)</span></label>
                                                    <input type="text" name="slide_title" placeholder="Contoh: Konser Musik Terbesar 2026" class="w-full px-4 py-2 bg-white border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all text-sm font-medium outline-none">
                                                </div>

                                                <div>
                                                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Subjudul / Deskripsi Singkat <span class="text-slate-400 font-normal">(Opsional)</span></label>
                                                    <input type="text" name="slide_subtitle" placeholder="Contoh: Beli tiketnya sekarang sebelum kehabisan" class="w-full px-4 py-2 bg-white border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all text-sm font-medium outline-none">
                                                </div>

                                                <div class="md:col-span-2">
                                                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Link Tujuan Button / Banner <span class="text-slate-400 font-normal">(Opsional)</span></label>
                                                    <input type="url" name="slide_link" placeholder="https://..." class="w-full px-4 py-2 bg-white border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all text-sm font-medium outline-none">
                                                </div>
                                            </div>

                                            <div class="flex justify-end pt-2">
                                                <button type="submit" class="bg-primary hover:opacity-90 text-white font-bold py-2.5 px-6 rounded-xl text-xs transition-all shadow-sm flex items-center gap-2">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                                                    Tambah Slide
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>

                                <hr class="border-slate-100">

                                <div>
                                    <h4 class="text-sm font-bold text-slate-800 mb-4 flex items-center gap-2">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                        Pengaturan Informasi & Footer
                                    </h4>
                                    
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                        <div class="col-span-1 md:col-span-2">
                                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Alamat Kantor</label>
                                            <textarea name="contact_address" rows="3" class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary focus:bg-white transition-all text-sm font-medium outline-none"><?= htmlspecialchars($addr_val) ?></textarea>
                                        </div>
                                        <div class="col-span-1 md:col-span-2">
                                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Nomor CS (WhatsApp)</label>
                                            <input type="text" name="contact_cs" value="<?= htmlspecialchars($cs_val) ?>" placeholder="6281234567890" class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary focus:bg-white transition-all text-sm font-medium outline-none">
                                            <p class="text-[10px] text-slate-400 mt-1">Nomor tanpa tanda + atau spasi. Contoh: 6281234567890</p>
                                        </div>
                                        <div>
                                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Link Instagram</label>
                                            <input type="url" name="link_ig" value="<?= htmlspecialchars($ig_val) ?>" placeholder="https://instagram.com/..." class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary focus:bg-white transition-all text-sm font-medium outline-none">
                                        </div>
                                        <div>
                                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Link TikTok</label>
                                            <input type="url" name="link_tiktok" value="<?= htmlspecialchars($tiktok_val) ?>" placeholder="https://tiktok.com/@..." class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary focus:bg-white transition-all text-sm font-medium outline-none">
                                        </div>
                                    </div>
                                    </div>
                                </div>

                                <hr class="border-slate-100">

                                <div>
                                    <h4 class="text-sm font-bold text-slate-800 mb-4 flex items-center gap-2">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                        Pengaturan Biaya Layanan Platform
                                    </h4>
                                    
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 bg-emerald-50/50 p-4 rounded-2xl border border-emerald-100">
                                        <div>
                                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Jenis Markup</label>
                                            <select name="admin_markup_type" class="w-full px-4 py-2.5 bg-white border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all text-sm font-medium outline-none">
                                                <option value="nominal" <?= $markup_type_val == 'nominal' ? 'selected' : '' ?>>Nominal (Rp)</option>
                                                <option value="percent" <?= $markup_type_val == 'percent' ? 'selected' : '' ?>>Persentase (%)</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Besaran Biaya Layanan</label>
                                            <input type="number" name="admin_markup_value" value="<?= htmlspecialchars($markup_value_val) ?>" min="0" class="w-full px-4 py-2.5 bg-white border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all text-sm font-medium outline-none">
                                            <p class="text-[10px] text-slate-400 mt-1">Jika memilih persentase, isi dengan angka persen (misal: 10 untuk 10%). Jika nominal, isi dengan harga flat (misal: 5000).</p>
                                        </div>
                                    </div>
                                </div>

                                <hr class="border-slate-100">

                                <div>
                                    <h4 class="text-sm font-bold text-slate-800 mb-4 flex items-center gap-2">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" /></svg>
                                        Pengaturan Midtrans Payment Gateway
                                    </h4>
                                    
                                    <div class="bg-blue-50/50 p-6 rounded-2xl border border-blue-100 space-y-6">
                                        <div>
                                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Environment Pembayaran</label>
                                            <select name="midtrans_is_production" class="w-full px-4 py-2.5 bg-white border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all text-sm font-bold outline-none text-slate-700">
                                                <option value="0" <?= $midtrans_is_production_val == '0' ? 'selected' : '' ?>>Sandbox (Mode Testing / Uji Coba)</option>
                                                <option value="1" <?= $midtrans_is_production_val == '1' ? 'selected' : '' ?>>Production (Mode Live / Transaksi Asli)</option>
                                            </select>
                                            <p class="text-[10px] text-slate-400 mt-1">Pastikan kunci yang dimasukkan di bawah sesuai dengan environment yang dipilih.</p>
                                        </div>

                                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                            <div>
                                                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Merchant ID</label>
                                                <input type="text" name="midtrans_merchant_id" value="<?= htmlspecialchars($midtrans_merchant_id_val) ?>" placeholder="Contoh: G123456789" class="w-full px-4 py-2.5 bg-white border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all text-sm font-medium outline-none">
                                            </div>

                                            <div>
                                                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Client Key</label>
                                                <input type="text" name="midtrans_client_key" value="<?= htmlspecialchars($midtrans_client_key_val) ?>" placeholder="SB-Mid-client-..." class="w-full px-4 py-2.5 bg-white border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all text-sm font-medium outline-none">
                                                <p class="text-[10px] text-slate-400 mt-1">Sandbox: diawali <code>SB-Mid-client-</code></p>
                                            </div>

                                            <div>
                                                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Server Key</label>
                                                <input type="text" name="midtrans_server_key" value="<?= htmlspecialchars($midtrans_server_key_val) ?>" placeholder="SB-Mid-server-..." class="w-full px-4 py-2.5 bg-white border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all text-sm font-medium outline-none">
                                                <p class="text-[10px] text-slate-400 mt-1">Sandbox: diawali <code>SB-Mid-server-</code></p>
                                            </div>
                                        </div>

                                        <!-- Panduan Simulator Sandbox Midtrans -->
                                        <div class="mt-4 pt-4 border-t border-blue-200/60">
                                            <details class="group">
                                                <summary class="flex items-center justify-between cursor-pointer font-bold text-xs text-blue-700 hover:text-blue-900 transition-colors">
                                                    <span class="flex items-center gap-1.5">
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                                        Panduan & Kredensial Uji Coba Pembayaran (Sandbox Simulator)
                                                    </span>
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 group-open:rotate-180 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                                                </summary>
                                                <div class="mt-3 text-xs text-slate-600 space-y-3 bg-white p-4 rounded-xl border border-blue-100 shadow-sm">
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

                                <hr class="border-slate-100">

                                <div>
                                    <h4 class="text-sm font-bold text-slate-800 mb-4 flex items-center gap-2">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                                        Mode Pemeliharaan (Maintenance)
                                    </h4>
                                    
                                    <div class="bg-amber-50/50 p-6 rounded-2xl border border-amber-100 flex items-start gap-4">
                                        <div class="flex-1">
                                            <label class="block text-sm font-bold text-slate-800 mb-1">Aktifkan Maintenance Mode</label>
                                            <p class="text-xs text-slate-500">Jika diaktifkan, halaman publik akan dialihkan ke halaman peringatan maintenance. Halaman Admin tetap bisa diakses.</p>
                                        </div>
                                        <div class="shrink-0 pt-2">
                                            <!-- Checkbox toggle fallback -->
                                            <select name="maintenance_mode" class="px-4 py-2 bg-white border border-slate-200 rounded-xl focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all text-sm font-bold outline-none text-slate-700">
                                                <option value="0" <?= $maintenance_mode_val == '0' ? 'selected' : '' ?>>Nonaktif (Normal)</option>
                                                <option value="1" <?= $maintenance_mode_val == '1' ? 'selected' : '' ?>>Aktif (Maintenance)</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <hr class="border-slate-100">

                                <div class="flex justify-end">
                                    <button type="submit" class="bg-primary hover:bg-blue-700 text-white font-bold py-3 px-8 rounded-xl transition-all shadow-md">
                                        Simpan Pengaturan
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
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
                    } else {
                        sidebar.classList.remove('md:hidden');
                    }
                }
            });

            // Live Image Preview Setup
            function setupLivePreview(inputId, containerId, previewImgId) {
                const input = document.getElementById(inputId);
                const container = document.getElementById(containerId);
                const previewImg = document.getElementById(previewImgId);
                if (!input || !container || !previewImg) return;
                input.addEventListener('change', function(e) {
                    const file = e.target.files[0];
                    if (file) {
                        const reader = new FileReader();
                        reader.onload = function(evt) {
                            previewImg.src = evt.target.result;
                            container.classList.remove('hidden');
                        };
                        reader.readAsDataURL(file);
                    } else {
                        container.classList.add('hidden');
                    }
                });
            }
            setupLivePreview('logoInput', 'logoPreviewContainer', 'logoPreviewImg');
            setupLivePreview('faviconInput', 'faviconPreviewContainer', 'faviconPreviewImg');
            setupLivePreview('newSliderInput', 'newSliderPreviewContainer', 'newSliderPreviewImg');
        });
    </script>

</body>
</html>
