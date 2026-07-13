<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'panitia') {
    header("Location: ../auth/login.php");
    exit;
}
require_once '../config/koneksi.php';

$id_panitia = $_SESSION['user_id'];
$sales = $conn->query("
    SELECT t.*, e.judul 
    FROM tickets t 
    JOIN events e ON t.id_event = e.id 
    WHERE e.id_panitia = $id_panitia 
    ORDER BY t.created_at DESC
");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Penjualan - HaloTiket</title>
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

        <?php $active_menu = 'sales'; include 'components/sidebar.php'; ?>

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
                    <span class="text-xl font-extrabold text-slate-800 md:hidden">Laporan Penjualan</span>
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
                <div class="mb-10 flex flex-col md:flex-row md:items-end justify-between gap-4">
                    <div>
                        <h1 class="text-3xl font-extrabold text-slate-900 tracking-tight">Laporan Penjualan</h1>
                        <p class="text-slate-500 mt-2 font-medium">Lacak semua transaksi tiket dari event yang Anda buat.</p>
                    </div>
                </div>
                
                <div class="bg-white rounded-3xl shadow-sm border border-slate-200 overflow-hidden">
                    <div class="px-8 py-6 border-b border-slate-100 bg-slate-50/50 flex justify-between items-center">
                        <h3 class="font-extrabold text-slate-900">Transaksi Terbaru</h3>
                        <span class="bg-indigo-50 text-primary text-xs font-bold px-3 py-1 rounded-full">Total: <?= $sales->num_rows ?> Transaksi</span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-100">
                            <thead class="bg-white">
                                <tr>
                                    <th class="px-8 py-4 text-left text-[11px] font-bold text-slate-400 uppercase tracking-wider">Order Detail</th>
                                    <th class="px-8 py-4 text-left text-[11px] font-bold text-slate-400 uppercase tracking-wider">Event</th>
                                    <th class="px-8 py-4 text-left text-[11px] font-bold text-slate-400 uppercase tracking-wider">Customer</th>
                                    <th class="px-8 py-4 text-left text-[11px] font-bold text-slate-400 uppercase tracking-wider">Status</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-slate-50">
                                <?php while($row = $sales->fetch_assoc()): ?>
                                <tr class="hover:bg-slate-50/80 transition-colors">
                                    <td class="px-8 py-5 whitespace-nowrap">
                                        <div class="text-sm font-extrabold text-slate-900 font-mono"><?= htmlspecialchars($row['order_id']) ?></div>
                                        <div class="text-[11px] font-medium text-slate-400 mt-1"><?= date('d M Y, H:i', strtotime($row['created_at'])) ?></div>
                                    </td>
                                    <td class="px-8 py-5">
                                        <div class="text-sm font-bold text-slate-700 truncate max-w-[200px]" title="<?= htmlspecialchars($row['judul']) ?>"><?= htmlspecialchars($row['judul']) ?></div>
                                    </td>
                                    <td class="px-8 py-5 whitespace-nowrap">
                                        <div class="text-sm font-bold text-slate-900"><?= htmlspecialchars($row['nama_pembeli']) ?></div>
                                        <div class="text-xs text-slate-500 font-medium flex gap-2 items-center mt-1">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>
                                            <?= htmlspecialchars($row['email_pembeli']) ?>
                                        </div>
                                    </td>
                                    <td class="px-8 py-5 whitespace-nowrap">
                                        <?php if($row['status'] == 'lunas'): ?>
                                            <span class="px-3 py-1 inline-flex text-[11px] leading-5 font-bold rounded-full bg-emerald-50 text-emerald-600 border border-emerald-100 uppercase tracking-wider">Lunas</span>
                                        <?php elseif($row['status'] == 'scanned'): ?>
                                            <span class="px-3 py-1 inline-flex text-[11px] leading-5 font-bold rounded-full bg-primary/10 text-primary border border-primary/20 uppercase tracking-wider">Scanned</span>
                                        <?php else: ?>
                                            <span class="px-3 py-1 inline-flex text-[11px] leading-5 font-bold rounded-full bg-amber-50 text-amber-600 border border-amber-100 uppercase tracking-wider">Pending</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                                <?php if($sales->num_rows == 0): ?>
                                    <tr><td colspan="4" class="px-8 py-12 text-center text-slate-500 font-medium">Belum ada penjualan tiket untuk event Anda.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
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
