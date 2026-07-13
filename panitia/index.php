<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'panitia') {
    header("Location: ../auth/login.php");
    exit;
}
require_once '../config/koneksi.php';

$id_panitia = $_SESSION['user_id'];

// Statistik Panitia
$tot_events = $conn->query("SELECT COUNT(*) as c FROM events WHERE id_panitia = $id_panitia")->fetch_assoc()['c'];
$tot_tickets = $conn->query("SELECT COUNT(*) as c FROM tickets t JOIN events e ON t.id_event = e.id WHERE e.id_panitia = $id_panitia")->fetch_assoc()['c'];
$tot_lunas = $conn->query("SELECT COUNT(*) as c FROM tickets t JOIN events e ON t.id_event = e.id WHERE e.id_panitia = $id_panitia AND t.status IN ('lunas','scanned')")->fetch_assoc()['c'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panitia Dashboard - HaloTiket</title>
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
        
        <!-- Mobile Overlay -->
        <div id="sidebarOverlay" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-40 hidden md:hidden transition-opacity"></div>

        <?php $active_menu = 'overview'; include 'components/sidebar.php'; ?>

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
                    <a href="profile.php" class="hidden md:flex items-center gap-3 mr-2 px-3 py-1.5 rounded-full border border-slate-100 bg-slate-50 hover:bg-slate-100 hover:border-slate-200 transition-colors group cursor-pointer">
                        <?php if (isset($_SESSION['foto_profil']) && !empty($_SESSION['foto_profil']) && file_exists('../assets/images/profil/'.$_SESSION['foto_profil'])): ?>
                            <img src="../assets/images/profil/<?= htmlspecialchars($_SESSION['foto_profil']) ?>" class="w-8 h-8 rounded-full object-cover shadow-sm">
                        <?php else: ?>
                            <div class="w-8 h-8 rounded-full bg-gradient-to-br from-primary to-secondary text-white flex items-center justify-center font-bold text-sm shadow-sm group-hover:shadow transition-all">
                                <?= strtoupper(substr($_SESSION['nama_lengkap'], 0, 1)) ?>
                            </div>
                        <?php endif; ?>
                        <span class="text-sm font-bold text-slate-700 pr-2 group-hover:text-primary transition-colors"><?= htmlspecialchars($_SESSION['nama_lengkap']) ?></span>
                    </a>
                </div>
            </header>

            <main class="flex-1 overflow-y-auto p-4 md:p-6 lg:p-10">
                <div class="mb-10">
                    <h1 class="text-3xl font-extrabold text-slate-900 tracking-tight">Kinerja Event Anda</h1>
                    <p class="text-slate-500 mt-2 font-medium">Ringkasan singkat dari performa semua event yang Anda kelola.</p>
                </div>
                
                <!-- Stats Grid -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="bg-white rounded-3xl border border-slate-100 shadow-sm p-8 relative overflow-hidden group hover:shadow-md transition-shadow">
                        <div class="absolute -right-6 -top-6 w-24 h-24 bg-primary/10 rounded-full group-hover:scale-150 transition-transform duration-500 ease-out"></div>
                        <div class="w-12 h-12 bg-primary/10 text-primary rounded-xl flex items-center justify-center mb-4 border border-primary/20 relative z-10">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                        </div>
                        <h3 class="text-sm font-bold text-slate-400 uppercase tracking-wider mb-1 relative z-10">Total Event Anda</h3>
                        <p class="text-4xl font-extrabold text-slate-900 relative z-10"><?= $tot_events ?></p>
                    </div>
                    
                    <div class="bg-white rounded-3xl border border-slate-100 shadow-sm p-8 relative overflow-hidden group hover:shadow-md transition-shadow">
                        <div class="absolute -right-6 -top-6 w-24 h-24 bg-secondary/10 rounded-full group-hover:scale-150 transition-transform duration-500 ease-out"></div>
                        <div class="w-12 h-12 bg-secondary/10 text-secondary rounded-xl flex items-center justify-center mb-4 border border-secondary/20 relative z-10">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z" /></svg>
                        </div>
                        <h3 class="text-sm font-bold text-slate-400 uppercase tracking-wider mb-1 relative z-10">Total Tiket Dipesan</h3>
                        <p class="text-4xl font-extrabold text-slate-900 relative z-10"><?= $tot_tickets ?></p>
                    </div>
                    
                    <div class="bg-white rounded-3xl border border-slate-100 shadow-sm p-8 relative overflow-hidden group hover:shadow-md transition-shadow">
                        <div class="absolute -right-6 -top-6 w-24 h-24 bg-emerald-500/10 rounded-full group-hover:scale-150 transition-transform duration-500 ease-out"></div>
                        <div class="w-12 h-12 bg-emerald-50 text-emerald-600 rounded-xl flex items-center justify-center mb-4 border border-emerald-200 relative z-10">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        </div>
                        <h3 class="text-sm font-bold text-slate-400 uppercase tracking-wider mb-1 relative z-10">Pembayaran Sukses</h3>
                        <p class="text-4xl font-extrabold text-slate-900 relative z-10"><?= $tot_lunas ?></p>
                    </div>
                </div>
                
                <!-- Mobile Navigation Links -->
                <div class="mt-8 grid grid-cols-1 gap-4 md:hidden">
                    <a href="manage_events.php" class="bg-white p-5 rounded-2xl shadow-sm border border-slate-100 font-bold text-slate-700 flex justify-between items-center">
                        Kelola Event Saya <span>&rarr;</span>
                    </a>
                    <a href="laporan_sales.php" class="bg-white p-5 rounded-2xl shadow-sm border border-slate-100 font-bold text-slate-700 flex justify-between items-center">
                        Laporan Penjualan <span>&rarr;</span>
                    </a>
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
                    // Mobile Toggle
                    sidebar.classList.toggle('-translate-x-full');
                    if(sidebarOverlay) sidebarOverlay.classList.toggle('hidden');
                } else {
                    // Desktop Toggle
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

            // Handle window resize to reset states if needed
            window.addEventListener('resize', () => {
                if (window.innerWidth >= 768) {
                    sidebar.classList.remove('-translate-x-full');
                    if(sidebarOverlay) sidebarOverlay.classList.add('hidden');
                } else {
                    // On mobile, if desktop was hidden, remove the hidden class 
                    // so it can be animated via translate
                    sidebar.classList.remove('md:hidden');
                }
            });
        });
    </script>
</body>
</html>
