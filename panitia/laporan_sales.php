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
                        <a href="index.php" class="flex items-center px-4 py-3 text-slate-400 hover:text-white hover:bg-white/5 rounded-xl font-medium transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" /></svg>
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
                        <a href="laporan_sales.php" class="flex items-center px-4 py-3 text-white bg-white/10 rounded-xl font-medium transition-colors border border-white/5">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-3 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" /></svg>
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
                <div class="flex space-x-3 items-center">
                    <a href="index.php" class="text-xs font-bold text-slate-400 bg-slate-800 px-3 py-1.5 rounded-lg">Overview</a>
                    <a href="../auth/logout.php" class="text-xs font-bold text-slate-300">Logout</a>
                </div>
            </header>

            <main class="flex-1 overflow-y-auto p-6 lg:p-10 relative z-10">
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
</body>
</html>
