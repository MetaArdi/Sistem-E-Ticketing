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

    // Proses Text Settings
    $text_settings = ['contact_address', 'contact_cs', 'link_ig', 'link_tiktok'];
    $stmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
    foreach ($text_settings as $key) {
        if (isset($_POST[$key])) {
            $val = $_POST[$key];
            $stmt->bind_param("sss", $key, $val, $val);
            $stmt->execute();
        }
    }

    logActivity($conn, $_SESSION['user_id'], 'Update Settings', 'Admin mengubah pengaturan sistem (Tampilan & Kontak).');

    $success_msg = "Pengaturan berhasil disimpan.";
}

// Ambil nilai saat ini
$logo_query = $conn->query("SELECT setting_value FROM settings WHERE setting_key = 'site_logo'");
$current_logo = $logo_query->num_rows > 0 ? $logo_query->fetch_assoc()['setting_value'] : '';

$favicon_query = $conn->query("SELECT setting_value FROM settings WHERE setting_key = 'site_favicon'");
$current_favicon = $favicon_query->num_rows > 0 ? $favicon_query->fetch_assoc()['setting_value'] : '';

$text_settings_keys = ['contact_address', 'contact_cs', 'link_ig', 'link_tiktok'];
$current_text_settings = [];
foreach($text_settings_keys as $k) {
    $q = $conn->query("SELECT setting_value FROM settings WHERE setting_key = '$k'");
    $current_text_settings[$k] = $q->num_rows > 0 ? $q->fetch_assoc()['setting_value'] : '';
}

$addr_val = $current_text_settings['contact_address'] ?: "HaloTiket Tower Lt. 5\nJl. Jend. Sudirman No. 123, Senayan\nJakarta Selatan, DKI Jakarta 12190";
$cs_val = $current_text_settings['contact_cs'] ?: "6281234567890";
$ig_val = $current_text_settings['link_ig'] ?: "https://instagram.com";
$tiktok_val = $current_text_settings['link_tiktok'] ?: "https://tiktok.com";
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
                        <div class="w-8 h-8 rounded-full bg-gradient-to-br from-primary to-secondary text-white flex items-center justify-center font-bold text-sm shadow-sm group-hover:shadow transition-all">
                            <?= strtoupper(substr($_SESSION['nama_lengkap'], 0, 1)) ?>
                        </div>
                        <span class="text-sm font-bold text-slate-700 pr-2 group-hover:text-primary transition-colors"><?= htmlspecialchars($_SESSION['nama_lengkap']) ?></span>
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
                                                <img src="../assets/images/logo/<?= htmlspecialchars($current_logo) ?>" alt="Current Logo" class="h-16 object-contain">
                                            </div>
                                        <?php else: ?>
                                            <div class="mb-4 bg-slate-50 p-4 rounded-xl border border-dashed border-slate-300 text-slate-400 text-sm text-center">
                                                Belum ada logo.
                                            </div>
                                        <?php endif; ?>

                                        <input type="file" name="logo" accept="image/*" class="w-full text-sm font-medium file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-bold file:bg-primary/10 file:text-primary hover:file:bg-primary/20 transition-all">
                                    </div>

                                    <!-- Favicon -->
                                    <div>
                                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Favicon (Ikon Tab)</label>
                                        <p class="text-xs text-slate-400 mb-4">Ikon kecil yang muncul di tab browser. Rasio 1:1, ukuran rekomendasi 64x64px.</p>
                                        
                                        <?php if($current_favicon): ?>
                                            <div class="mb-4 bg-slate-100 p-4 rounded-xl inline-block border border-slate-200">
                                                <img src="../assets/images/favicon/<?= htmlspecialchars($current_favicon) ?>" alt="Current Favicon" class="h-10 w-10 object-contain">
                                            </div>
                                        <?php else: ?>
                                            <div class="mb-4 bg-slate-50 p-4 rounded-xl border border-dashed border-slate-300 text-slate-400 text-sm text-center">
                                                Belum ada favicon.
                                            </div>
                                        <?php endif; ?>

                                        <input type="file" name="favicon" accept="image/*" class="w-full text-sm font-medium file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-bold file:bg-primary/10 file:text-primary hover:file:bg-primary/20 transition-all">
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
        });
    </script>

</body>
</html>
