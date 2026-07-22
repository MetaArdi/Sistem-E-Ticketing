<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../auth/login.php");
    exit;
}
require_once '../config/koneksi.php';

// Pagination setup
$limit = 20;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Fetch logs
$query = "SELECT l.*, u.nama_lengkap, u.role, u.email 
          FROM system_logs l 
          LEFT JOIN users u ON l.user_id = u.id 
          ORDER BY l.created_at DESC 
          LIMIT $limit OFFSET $offset";
$logs = $conn->query($query);

// Get total for pagination
$total_query = $conn->query("SELECT COUNT(*) as count FROM system_logs");
$total_records = $total_query->fetch_assoc()['count'];
$total_pages = ceil($total_records / $limit);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logs Sistem - HaloTiket Admin</title>
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
    <?php $active_menu = 'logs'; include 'components/sidebar.php'; ?>

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
                <span class="text-xl font-extrabold text-slate-800 md:hidden">System Logs</span>
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
            <div class="max-w-7xl mx-auto">
                <div class="mb-8 flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div>
                        <h1 class="text-2xl md:text-3xl font-extrabold text-slate-900 tracking-tight">Logs Sistem</h1>
                        <p class="text-slate-500 mt-1 font-medium text-sm">Pantau aktivitas seluruh pengguna dalam platform HaloTiket.</p>
                    </div>
                </div>

                <div class="bg-white rounded-3xl shadow-sm border border-slate-200 overflow-hidden">
                    <div class="px-6 py-4 border-b border-slate-100 bg-slate-50/50 flex justify-between items-center">
                        <h3 class="font-extrabold text-slate-900 text-sm">Riwayat Aktivitas</h3>
                        <span class="bg-primary/10 text-primary text-xs font-bold px-3 py-1 rounded-full">Total: <?= $total_records ?></span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-100">
                            <thead class="bg-white">
                                <tr>
                                    <th class="px-6 py-3 text-left text-[11px] font-bold text-slate-400 uppercase tracking-wider">Waktu</th>
                                    <th class="px-6 py-3 text-left text-[11px] font-bold text-slate-400 uppercase tracking-wider">User</th>
                                    <th class="px-6 py-3 text-left text-[11px] font-bold text-slate-400 uppercase tracking-wider">Aktivitas</th>
                                    <th class="px-6 py-3 text-left text-[11px] font-bold text-slate-400 uppercase tracking-wider">Deskripsi</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-slate-50">
                                <?php if($logs->num_rows > 0): ?>
                                    <?php while($row = $logs->fetch_assoc()): ?>
                                    <tr class="hover:bg-slate-50/80 transition-colors group">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-xs font-bold text-slate-700"><?= date('d M Y', strtotime($row['created_at'])) ?></div>
                                            <div class="text-[11px] font-medium text-slate-400"><?= date('H:i:s', strtotime($row['created_at'])) ?></div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <?php if($row['nama_lengkap']): ?>
                                                <div class="flex items-center">
                                                    <div class="h-8 w-8 flex-shrink-0 bg-slate-100 rounded-full flex items-center justify-center text-slate-500 font-bold border border-slate-200 text-xs">
                                                        <?= strtoupper(substr($row['nama_lengkap'], 0, 1)) ?>
                                                    </div>
                                                    <div class="ml-3">
                                                        <div class="text-sm font-bold text-slate-900"><?= htmlspecialchars($row['nama_lengkap']) ?></div>
                                                        <div class="text-[10px] font-bold uppercase tracking-wider text-primary"><?= htmlspecialchars($row['role']) ?></div>
                                                    </div>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-sm italic text-slate-400">System/Unknown</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="bg-slate-100 text-slate-700 text-[11px] font-bold px-2.5 py-1 rounded-md border border-slate-200">
                                                <?= htmlspecialchars($row['action']) ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="text-sm text-slate-600 font-medium">
                                                <?= htmlspecialchars($row['description']) ?>
                                            </div>
                                            <?php if($row['ip_address']): ?>
                                            <div class="text-[10px] text-slate-400 font-mono mt-1">IP: <?= htmlspecialchars($row['ip_address']) ?></div>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="px-6 py-12 text-center text-slate-500 font-medium">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 mx-auto text-slate-300 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                            Belum ada riwayat aktivitas.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <?php if($total_pages > 1): ?>
                    <div class="px-6 py-4 border-t border-slate-100 bg-slate-50/50 flex justify-center">
                        <div class="flex gap-1">
                            <?php for($i = 1; $i <= $total_pages; $i++): ?>
                                <a href="?page=<?= $i ?>" class="w-8 h-8 flex items-center justify-center rounded-lg text-sm font-bold transition-colors <?= $i == $page ? 'bg-primary text-white shadow-md' : 'bg-white text-slate-500 border border-slate-200 hover:bg-slate-50 hover:text-primary' ?>">
                                    <?= $i ?>
                                </a>
                            <?php endfor; ?>
                        </div>
                    </div>
                    <?php endif; ?>
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
