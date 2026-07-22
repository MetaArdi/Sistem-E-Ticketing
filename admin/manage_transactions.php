<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../auth/login.php");
    exit;
}
require_once '../config/koneksi.php';

// Pagination setup
$limit = 50;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Filter Event
$filter_event = isset($_GET['event_id']) ? (int)$_GET['event_id'] : 0;
$where = "";
if ($filter_event > 0) {
    $where = "WHERE t.id_event = $filter_event";
}

// Fetch transactions
$query = "SELECT t.*, e.judul, e.tanggal, e.waktu 
          FROM tickets t 
          JOIN events e ON t.id_event = e.id 
          $where 
          ORDER BY t.created_at DESC 
          LIMIT $limit OFFSET $offset";
$transactions = $conn->query($query);

// Get total for pagination
$total_query = $conn->query("SELECT COUNT(*) as count FROM tickets t $where");
$total_records = $total_query->fetch_assoc()['count'];
$total_pages = ceil($total_records / $limit);

// Fetch all events for filter dropdown
$events_query = $conn->query("SELECT id, judul FROM events ORDER BY judul ASC");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transaksi - HaloTiket Admin</title>
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
    <?php $active_menu = 'transactions'; include 'components/sidebar.php'; ?>

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
                <span class="text-xl font-extrabold text-slate-800 md:hidden">Transaksi</span>
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
                <div class="mb-8 flex flex-col md:flex-row md:items-end justify-between gap-4">
                    <div>
                        <h1 class="text-2xl md:text-3xl font-extrabold text-slate-900 tracking-tight">Daftar Transaksi</h1>
                        <p class="text-slate-500 mt-1 font-medium text-sm">Lihat data pembeli tiket berdasarkan event.</p>
                    </div>
                    
                    <!-- Filter Dropdown -->
                    <form method="GET" action="manage_transactions.php" class="flex gap-2 w-full md:w-auto">
                        <select name="event_id" class="px-4 py-2 bg-white border border-slate-200 rounded-xl text-sm font-medium focus:ring-2 focus:ring-primary/20 focus:border-primary w-full md:w-64" onchange="this.form.submit()">
                            <option value="0">Semua Event</option>
                            <?php while($ev = $events_query->fetch_assoc()): ?>
                                <option value="<?= $ev['id'] ?>" <?= $filter_event == $ev['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($ev['judul']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </form>
                </div>

                <div class="bg-white rounded-3xl shadow-sm border border-slate-200 overflow-hidden">
                    <div class="px-6 py-4 border-b border-slate-100 bg-slate-50/50 flex justify-between items-center">
                        <h3 class="font-extrabold text-slate-900 text-sm">Riwayat Pembelian</h3>
                        <span class="bg-primary/10 text-primary text-xs font-bold px-3 py-1 rounded-full">Total: <?= $total_records ?> Data</span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-100">
                            <thead class="bg-white">
                                <tr>
                                    <th class="px-6 py-3 text-left text-[11px] font-bold text-slate-400 uppercase tracking-wider">Order ID & Waktu</th>
                                    <th class="px-6 py-3 text-left text-[11px] font-bold text-slate-400 uppercase tracking-wider">Pembeli</th>
                                    <th class="px-6 py-3 text-left text-[11px] font-bold text-slate-400 uppercase tracking-wider">Event</th>
                                    <th class="px-6 py-3 text-left text-[11px] font-bold text-slate-400 uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-3 text-right text-[11px] font-bold text-slate-400 uppercase tracking-wider">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-slate-50">
                                <?php if($transactions->num_rows > 0): ?>
                                    <?php while($row = $transactions->fetch_assoc()): ?>
                                    <tr class="hover:bg-slate-50/80 transition-colors group">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-xs font-bold text-slate-900">#<?= htmlspecialchars($row['order_id']) ?></div>
                                            <div class="text-[11px] font-medium text-slate-500 mt-1"><?= date('d M Y, H:i', strtotime($row['created_at'])) ?></div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm font-bold text-slate-900"><?= htmlspecialchars($row['nama_pembeli']) ?></div>
                                            <div class="text-[11px] font-medium text-slate-500"><?= htmlspecialchars($row['email_pembeli']) ?></div>
                                            <div class="text-[11px] text-slate-400"><?= htmlspecialchars($row['no_hp']) ?></div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm font-bold text-slate-700 truncate max-w-[200px]" title="<?= htmlspecialchars($row['judul']) ?>"><?= htmlspecialchars($row['judul']) ?></div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <?php 
                                            $status_class = 'bg-slate-100 text-slate-600';
                                            if($row['status'] == 'lunas') $status_class = 'bg-emerald-100 text-emerald-700';
                                            if($row['status'] == 'pending') $status_class = 'bg-yellow-100 text-yellow-700';
                                            if($row['status'] == 'scanned') $status_class = 'bg-blue-100 text-blue-700';
                                            ?>
                                            <span class="px-2.5 py-1 rounded-md text-[11px] font-bold uppercase tracking-wider <?= $status_class ?>">
                                                <?= htmlspecialchars($row['status']) ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <button onclick="showDetail(<?= htmlspecialchars(json_encode($row)) ?>)" class="text-primary hover:text-primary/80 bg-primary/10 hover:bg-primary/20 px-3 py-1.5 rounded-lg transition-colors font-bold text-xs border border-primary/20">
                                                Detail
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="px-6 py-12 text-center text-slate-500 font-medium">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 mx-auto text-slate-300 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                                            Belum ada data transaksi untuk event ini.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <?php if($total_pages > 1): ?>
                    <div class="px-6 py-4 border-t border-slate-100 bg-slate-50/50 flex justify-center">
                        <div class="flex flex-wrap gap-1">
                            <?php for($i = 1; $i <= $total_pages; $i++): ?>
                                <a href="?event_id=<?= $filter_event ?>&page=<?= $i ?>" class="w-8 h-8 flex items-center justify-center rounded-lg text-sm font-bold transition-colors <?= $i == $page ? 'bg-primary text-white shadow-md' : 'bg-white text-slate-500 border border-slate-200 hover:bg-slate-50 hover:text-primary' ?>">
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

<!-- Modal Detail Transaksi -->
<div id="detailModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl shadow-xl w-full max-w-md overflow-hidden transform scale-95 opacity-0 transition-all duration-300" id="detailModalContent">
        <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50">
            <h3 class="font-extrabold text-slate-900">Detail Transaksi</h3>
            <button onclick="closeDetail()" class="text-slate-400 hover:text-slate-600 transition-colors p-2 bg-white rounded-full border border-slate-200 hover:bg-slate-100">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>
            </button>
        </div>
        <div class="p-6 space-y-4">
            <div class="text-center pb-4 border-b border-slate-100">
                <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Order ID</div>
                <div class="text-xl font-extrabold text-primary" id="modalOrderId"></div>
            </div>
            
            <div class="grid grid-cols-2 gap-4 text-sm">
                <div>
                    <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Nama Pembeli</div>
                    <div class="font-bold text-slate-900" id="modalNama"></div>
                </div>
                <div>
                    <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">No. HP</div>
                    <div class="font-medium text-slate-700" id="modalHp"></div>
                </div>
                <div class="col-span-2">
                    <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Email</div>
                    <div class="font-medium text-slate-700" id="modalEmail"></div>
                </div>
                <div class="col-span-2 pt-2 border-t border-slate-100">
                    <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Event</div>
                    <div class="font-bold text-slate-900" id="modalEvent"></div>
                    <div class="text-xs text-slate-500 mt-1" id="modalJadwal"></div>
                </div>
                <div class="pt-2 border-t border-slate-100">
                    <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Status Pembayaran</div>
                    <div id="modalStatus" class="inline-block mt-1"></div>
                </div>
                <div class="pt-2 border-t border-slate-100">
                    <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">QR Token</div>
                    <div class="font-mono text-xs font-bold text-slate-700 bg-slate-100 border border-slate-200 px-2 py-1 rounded inline-block" id="modalToken"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function showDetail(data) {
        const modal = document.getElementById('detailModal');
        const content = document.getElementById('detailModalContent');
        
        document.getElementById('modalOrderId').textContent = '#' + data.order_id;
        document.getElementById('modalNama').textContent = data.nama_pembeli;
        document.getElementById('modalHp').textContent = data.no_hp;
        document.getElementById('modalEmail').textContent = data.email_pembeli;
        document.getElementById('modalEvent').textContent = data.judul;
        
        // Format date and time (fallback if browser doesn't support toLocaleDateString well)
        let formattedDate = data.tanggal;
        try {
            const dateObj = new Date(data.tanggal);
            if (!isNaN(dateObj)) {
                formattedDate = dateObj.toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' });
            }
        } catch(e) {}
        
        document.getElementById('modalJadwal').textContent = formattedDate + ' • ' + (data.waktu ? data.waktu.substring(0,5) : '');
        
        document.getElementById('modalToken').textContent = data.token_qr;
        
        let statusHtml = '';
        if(data.status === 'lunas') statusHtml = '<span class="bg-emerald-100 text-emerald-700 border border-emerald-200 px-2.5 py-1 rounded-md text-[11px] font-bold uppercase tracking-wider">LUNAS</span>';
        else if(data.status === 'pending') statusHtml = '<span class="bg-yellow-100 text-yellow-700 border border-yellow-200 px-2.5 py-1 rounded-md text-[11px] font-bold uppercase tracking-wider">PENDING</span>';
        else if(data.status === 'scanned') statusHtml = '<span class="bg-blue-100 text-blue-700 border border-blue-200 px-2.5 py-1 rounded-md text-[11px] font-bold uppercase tracking-wider">SCANNED (Telah Digunakan)</span>';
        
        document.getElementById('modalStatus').innerHTML = statusHtml;
        
        modal.classList.remove('hidden');
        setTimeout(() => {
            content.classList.remove('scale-95', 'opacity-0');
            content.classList.add('scale-100', 'opacity-100');
        }, 10);
    }
    
    function closeDetail() {
        const modal = document.getElementById('detailModal');
        const content = document.getElementById('detailModalContent');
        
        content.classList.remove('scale-100', 'opacity-100');
        content.classList.add('scale-95', 'opacity-0');
        
        setTimeout(() => {
            modal.classList.add('hidden');
        }, 300);
    }
    
    // Close modal on outside click
    document.getElementById('detailModal').addEventListener('click', function(e) {
        if(e.target === this) {
            closeDetail();
        }
    });
</script>

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
