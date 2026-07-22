<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'validator') {
    header("Location: ../auth/login.php");
    exit;
}
require_once '../config/koneksi.php';

$active_menu = 'overview';
$id_user = $_SESSION['user_id'];

// Get validator info to know their panitia
$stmt = $conn->prepare("SELECT id_panitia FROM users WHERE id = ?");
$stmt->bind_param("i", $id_user);
$stmt->execute();
$validator_info = $stmt->get_result()->fetch_assoc();
$id_panitia = $validator_info['id_panitia'];

// Stats: Total scanned tickets for this panitia's events
$tot_scanned = 0;
$tot_gagal = 0;
$tot_all = 0;

if ($id_panitia) {
    $q_scanned = $conn->query("SELECT COUNT(*) as c FROM tickets t JOIN events e ON t.id_event = e.id WHERE e.id_panitia = $id_panitia AND t.status = 'scanned'");
    if($q_scanned) $tot_scanned = $q_scanned->fetch_assoc()['c'];
    
    // Asumsi gagal scan (saat ini belum ada tabel log error scan spesifik)
    $tot_gagal = 0; 
    
    $q_all = $conn->query("SELECT COUNT(*) as c FROM tickets t JOIN events e ON t.id_event = e.id WHERE e.id_panitia = $id_panitia AND t.status IN ('lunas', 'scanned')");
    if($q_all) $tot_all = $q_all->fetch_assoc()['c'];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Validator Dashboard - HaloTiket</title>
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
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
</head>
<body class="bg-slate-50 font-sans antialiased text-slate-800 selection:bg-primary selection:text-white overflow-hidden">
    <div class="flex h-screen w-full">
        
        <!-- Mobile Overlay -->
        <div id="sidebarOverlay" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-40 hidden md:hidden transition-opacity"></div>

        <?php include 'components/sidebar.php'; ?>

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
                    <span class="text-xl font-extrabold text-slate-800 md:hidden">Dashboard</span>
                </div>
                
                <div class="flex items-center gap-4">
                    <div class="hidden md:flex items-center gap-3 mr-2 px-3 py-1.5 rounded-full border border-slate-100 bg-slate-50 hover:bg-slate-100 hover:border-slate-200 transition-colors group cursor-pointer">
                        <?php if (isset($_SESSION['foto_profil']) && !empty($_SESSION['foto_profil']) && file_exists('../assets/images/profil/'.$_SESSION['foto_profil'])): ?>
                            <img src="../assets/images/profil/<?= htmlspecialchars($_SESSION['foto_profil']) ?>" class="w-8 h-8 rounded-full object-cover shadow-sm">
                        <?php else: ?>
                            <div class="w-8 h-8 rounded-full bg-gradient-to-br from-primary to-secondary text-white flex items-center justify-center font-bold text-sm shadow-sm group-hover:shadow transition-all">
                                <?= strtoupper(substr($_SESSION['nama_lengkap'], 0, 1)) ?>
                            </div>
                        <?php endif; ?>
                        <span class="text-sm font-bold text-slate-700 pr-2 group-hover:text-primary transition-colors"><?= htmlspecialchars($_SESSION['nama_lengkap']) ?></span>
                    </div>
                </div>
            </header>

            <main class="flex-1 overflow-y-auto p-4 md:p-6 lg:p-10">
                <div class="mb-10">
                    <h1 class="text-3xl font-extrabold text-slate-900 tracking-tight">Selamat Datang, <?= htmlspecialchars(explode(' ', $_SESSION['nama_lengkap'])[0]) ?>!</h1>
                    <p class="text-slate-500 mt-2 font-medium">Ini adalah dashboard khusus validator untuk melakukan pemindaian tiket event.</p>
                </div>
                
                <!-- Scanner Section -->
                <div class="bg-indigo-50 border border-indigo-100 rounded-3xl p-8 flex flex-col items-center text-center gap-6 shadow-sm mb-10">
                    <div>
                        <h3 class="text-xl font-extrabold text-indigo-900 mb-2">Mulai Scan Tiket Pengunjung</h3>
                        <p class="text-indigo-700 font-medium">Buka kamera Anda dan pindai QR Code yang terdapat pada e-ticket pengunjung untuk memvalidasi akses masuk mereka ke dalam event.</p>
                    </div>
                    <a href="scan.php" class="shrink-0 bg-primary hover:bg-teal-600 text-white font-bold py-4 px-8 rounded-2xl shadow-lg shadow-primary/30 transition-all hover:-translate-y-1 flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm14 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" /></svg>
                        Buka Scanner
                    </a>
                </div>

                <!-- Stats Grid -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-10">
                    <!-- Berhasil Scan -->
                    <div class="bg-white rounded-3xl border border-slate-100 shadow-sm p-8 relative overflow-hidden group hover:shadow-md transition-shadow">
                        <div class="absolute -right-6 -top-6 w-24 h-24 bg-emerald-500/10 rounded-full group-hover:scale-150 transition-transform duration-500 ease-out"></div>
                        <div class="w-12 h-12 bg-emerald-50 text-emerald-600 rounded-xl flex items-center justify-center mb-4 border border-emerald-200 relative z-10">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        </div>
                        <h3 class="text-sm font-bold text-slate-400 uppercase tracking-wider mb-1 relative z-10">Berhasil Di Scan</h3>
                        <p class="text-4xl font-extrabold text-slate-900 relative z-10"><?= $tot_scanned ?></p>
                        <p class="text-xs text-slate-400 mt-2 relative z-10">Total tiket yang sukses diverifikasi</p>
                    </div>
                    
                    <!-- Gagal Scan -->
                    <div class="bg-white rounded-3xl border border-slate-100 shadow-sm p-8 relative overflow-hidden group hover:shadow-md transition-shadow">
                        <div class="absolute -right-6 -top-6 w-24 h-24 bg-red-500/10 rounded-full group-hover:scale-150 transition-transform duration-500 ease-out"></div>
                        <div class="w-12 h-12 bg-red-50 text-red-600 rounded-xl flex items-center justify-center mb-4 border border-red-200 relative z-10">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        </div>
                        <h3 class="text-sm font-bold text-slate-400 uppercase tracking-wider mb-1 relative z-10">Gagal Di Scan</h3>
                        <p class="text-4xl font-extrabold text-slate-900 relative z-10"><?= $tot_gagal ?></p>
                        <p class="text-xs text-slate-400 mt-2 relative z-10">Tiket tidak valid atau expired</p>
                    </div>

                    <!-- Keseluruhan Tiket -->
                    <div class="bg-white rounded-3xl border border-slate-100 shadow-sm p-8 relative overflow-hidden group hover:shadow-md transition-shadow">
                        <div class="absolute -right-6 -top-6 w-24 h-24 bg-secondary/10 rounded-full group-hover:scale-150 transition-transform duration-500 ease-out"></div>
                        <div class="w-12 h-12 bg-secondary/10 text-secondary rounded-xl flex items-center justify-center mb-4 border border-secondary/20 relative z-10">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z" /></svg>
                        </div>
                        <h3 class="text-sm font-bold text-slate-400 uppercase tracking-wider mb-1 relative z-10">Keseluruhan Tiket</h3>
                        <p class="text-4xl font-extrabold text-slate-900 relative z-10"><?= $tot_all ?></p>
                        <p class="text-xs text-slate-400 mt-2 relative z-10">Total tiket aktif untuk event panitia ini</p>
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
                if (window.innerWidth < 768) {
                    sidebar.classList.toggle('-translate-x-full');
                    if(sidebarOverlay) sidebarOverlay.classList.toggle('hidden');
                } else {
                    sidebar.classList.toggle('md:hidden');
                }
            }

            if(hamburgerBtn) hamburgerBtn.addEventListener('click', toggleSidebar);
            
            if(closeSidebar) {
                closeSidebar.addEventListener('click', () => {
                    sidebar.classList.add('-translate-x-full');
                    if(sidebarOverlay) sidebarOverlay.classList.add('hidden');
                });
            }

            if(sidebarOverlay) {
                sidebarOverlay.addEventListener('click', () => {
                    sidebar.classList.add('-translate-x-full');
                    sidebarOverlay.classList.add('hidden');
                });
            }

            window.addEventListener('resize', () => {
                if (window.innerWidth >= 768) {
                    sidebar.classList.remove('-translate-x-full');
                    if(sidebarOverlay) sidebarOverlay.classList.add('hidden');
                } else {
                    sidebar.classList.remove('md:hidden');
                }
            });
        });
    </script>
</body>
</html>
