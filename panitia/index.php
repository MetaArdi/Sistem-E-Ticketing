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
<body class="bg-slate-50 font-sans antialiased text-slate-800 selection:bg-primary selection:text-white">
    <div class="flex h-screen overflow-hidden">
        
        <!-- Premium Sidebar -->
        <aside class="w-72 bg-dark flex flex-col hidden md:flex relative z-20">
            <div class="h-20 flex items-center px-8 border-b border-slate-800">
                <a href="../index.php" class="flex items-center gap-3 group">
                    <?php if (isset($global_site_logo) && $global_site_logo): ?>
                    <img src="<?= $global_site_logo ?>" alt="Logo" class="w-40 md:w-48 h-auto object-contain">
                <?php else: ?>
                    <div class="w-8 h-10 bg-white px-3 py-1.5 rounded-xl shadow-sm rounded-lg bg-gradient-to-br from-primary to-secondary flex items-center justify-center text-white font-bold text-sm shadow-md">H</div>
                <span class="text-xl font-extrabold text-white tracking-tight">Panitia Panel</span>
                <?php endif; ?>
                    
                </a>
            </div>
            
            <nav class="flex-1 overflow-y-auto py-6 px-4">
                <p class="px-4 text-xs font-bold text-slate-500 uppercase tracking-widest mb-3">Main Menu</p>
                <ul class="space-y-2">
                    <li>
                        <a href="index.php" class="flex items-center px-4 py-3 text-white bg-white/10 rounded-xl font-medium transition-colors border border-white/5">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-3 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" /></svg>
                            Dashboard
                        </a>
                    </li>
                    <li>
                        <a href="manage_events.php" class="flex items-center px-4 py-3 text-slate-400 hover:text-white hover:bg-white/5 rounded-xl font-medium transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                            Event Saya
                        </a>
                    </li>
                    <li>
                        <a href="laporan_sales.php" class="flex items-center px-4 py-3 text-slate-400 hover:text-white hover:bg-white/5 rounded-xl font-medium transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" /></svg>
                            Laporan Penjualan
                        </a>
                    </li>
                </ul>
            </nav>
            <div class="p-6 border-t border-slate-800">
                <a href="../auth/logout.php" class="flex items-center justify-center w-full px-4 py-3 text-sm font-bold text-slate-300 bg-slate-800 hover:bg-slate-700 hover:text-white rounded-xl transition-colors border border-slate-700">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" /></svg>
                    Logout
                </a>
            </div>
        </aside>

        <!-- Main Content -->
        <div class="flex-1 flex flex-col h-screen overflow-hidden bg-slate-50 relative">
            <!-- Top Header (Mobile) -->
            <header class="h-16 bg-dark border-b border-slate-800 flex items-center justify-between px-4 md:hidden text-white">
                <span class="text-xl font-bold">Panitia</span>
                <a href="../auth/logout.php" class="text-sm font-medium text-slate-300">Logout</a>
            </header>

            <main class="flex-1 overflow-y-auto p-6 lg:p-10 relative z-10">
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
</body>
</html>
