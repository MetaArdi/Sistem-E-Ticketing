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

// Fetch all events for filter dropdown & CRUD modals
$all_events = [];
$events_res = $conn->query("SELECT id, judul FROM events ORDER BY judul ASC");
if ($events_res) {
    while($ev = $events_res->fetch_assoc()) {
        $var_res = $conn->query("SELECT id, nama_varian, harga, sisa_stok FROM event_ticket_variants WHERE id_event = " . (int)$ev['id']);
        $variants = [];
        if ($var_res) {
            while($vr = $var_res->fetch_assoc()) {
                $variants[] = $vr;
            }
        }
        $ev['variants'] = $variants;
        $all_events[] = $ev;
    }
}
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
                    
                    <!-- Filter Dropdown & Tambah Transaksi -->
                    <div class="flex flex-col sm:flex-row items-center gap-3 w-full md:w-auto">
                        <button type="button" onclick="openCreateModal()" class="w-full sm:w-auto px-4 py-2.5 bg-primary hover:bg-teal-600 text-white font-extrabold text-xs rounded-xl shadow-md transition-all flex items-center justify-center gap-2 active:scale-95">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" /></svg>
                            <span>+ Tambah Transaksi Manual</span>
                        </button>
                        
                        <form method="GET" action="manage_transactions.php" class="w-full sm:w-auto">
                            <select name="event_id" class="px-4 py-2 bg-white border border-slate-200 rounded-xl text-sm font-medium focus:ring-2 focus:ring-primary/20 focus:border-primary w-full sm:w-64" onchange="this.form.submit()">
                                <option value="0">Semua Event</option>
                                <?php while($ev = $events_query->fetch_assoc()): ?>
                                    <option value="<?= $ev['id'] ?>" <?= $filter_event == $ev['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($ev['judul']) ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </form>
                    </div>
                </div>

                <?php if (isset($_SESSION['success'])): ?>
                <div class="mb-6 bg-emerald-50 text-emerald-700 px-5 py-4 rounded-xl text-sm font-bold flex items-center gap-3 border border-emerald-200">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                    <?= htmlspecialchars($_SESSION['success']) ?>
                </div>
                <?php unset($_SESSION['success']); endif; ?>

                <?php if (isset($_SESSION['error'])): ?>
                <div class="mb-6 bg-red-50 text-red-700 px-5 py-4 rounded-xl text-sm font-bold flex items-center gap-3 border border-red-200">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                    <?= htmlspecialchars($_SESSION['error']) ?>
                </div>
                <?php unset($_SESSION['error']); endif; ?>

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
                                    <th class="px-6 py-3 text-left text-[11px] font-bold text-slate-400 uppercase tracking-wider">Metode</th>
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
                                            <?php if(($row['payment_method'] ?? 'midtrans') == 'manual'): ?>
                                                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-blue-100 text-blue-800 border border-blue-200">
                                                    Manual Transfer
                                                </span>
                                            <?php else: ?>
                                                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-200">
                                                    Midtrans Gateway
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <?php 
                                            $status_class = 'bg-slate-100 text-slate-600';
                                            if($row['status'] == 'lunas') $status_class = 'bg-emerald-100 text-emerald-700';
                                            if($row['status'] == 'pending') $status_class = 'bg-yellow-100 text-yellow-700';
                                            if($row['status'] == 'scanned') $status_class = 'bg-blue-100 text-blue-700';
                                            if($row['status'] == 'batal') $status_class = 'bg-red-100 text-red-700';
                                            ?>
                                            <span class="px-2.5 py-1 rounded-md text-[11px] font-bold uppercase tracking-wider <?= $status_class ?>">
                                                <?= htmlspecialchars($row['status']) ?>
                                            </span>
                                            <?php if(!empty($row['bukti_pembayaran']) && $row['status'] == 'pending'): ?>
                                                <span class="block text-[9px] font-bold text-amber-600 mt-1">
                                                    📷 Perlu Verifikasi
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-xs font-medium space-x-1">
                                            <!-- Detail Button -->
                                            <button onclick="showDetail(<?= htmlspecialchars(json_encode($row)) ?>)" title="Lihat Detail Transaksi" class="text-slate-700 bg-slate-100 hover:bg-slate-200 px-2.5 py-1.5 rounded-lg transition-colors font-bold text-xs border border-slate-200">
                                                🔍 Detail
                                            </button>
                                            
                                            <!-- Cetak PDF Button (Jika status Lunas / Scanned) -->
                                            <?php if (in_array($row['status'], ['lunas', 'scanned']) && !empty($row['token_qr'])): ?>
                                                <a href="<?= BASE_URL ?>user/download_tiket.php?token=<?= urlencode($row['token_qr']) ?>" target="_blank" title="Cetak PDF Tiket Pembeli" class="text-teal-700 bg-teal-50 hover:bg-teal-100 px-2.5 py-1.5 rounded-lg transition-colors font-bold text-xs border border-teal-200 inline-flex items-center gap-1">
                                                    📄 Cetak PDF
                                                </a>
                                            <?php endif; ?>

                                            <!-- Edit Button -->
                                            <button onclick="openEditModal(<?= htmlspecialchars(json_encode($row)) ?>)" title="Edit Data Transaksi" class="text-blue-700 bg-blue-50 hover:bg-blue-100 px-2.5 py-1.5 rounded-lg transition-colors font-bold text-xs border border-blue-200">
                                                ✏️ Edit
                                            </button>

                                            <!-- Delete Button -->
                                            <form action="actions/crud_transaksi.php" method="POST" class="inline-block" onsubmit="return confirm('Apakah Anda yakin ingin menghapus transaksi Order ID #<?= htmlspecialchars($row['order_id']) ?>? Stok tiket akan dikembalikan.')">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="ticket_id" value="<?= $row['id'] ?>">
                                                <button type="submit" title="Hapus Transaksi" class="text-red-700 bg-red-50 hover:bg-red-100 px-2 py-1.5 rounded-lg transition-colors font-bold text-xs border border-red-200">
                                                    🗑️
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="px-6 py-12 text-center text-slate-500 font-medium">
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
    <div class="bg-white rounded-3xl shadow-2xl w-full max-w-lg overflow-hidden transform scale-95 opacity-0 transition-all duration-300 flex flex-col max-h-[92vh]" id="detailModalContent">
        
        <!-- Header Modal (Fixed Top) -->
        <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50/80 shrink-0">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl bg-primary/10 text-primary flex items-center justify-center font-bold text-base">
                    🎫
                </div>
                <div>
                    <h3 class="font-extrabold text-slate-900 text-sm">Detail Pesanan Tiket</h3>
                    <div class="text-[11px] font-mono font-bold text-primary" id="modalOrderId"></div>
                </div>
            </div>
            <button onclick="closeDetail()" class="text-slate-400 hover:text-slate-600 transition-colors p-2 bg-white rounded-full border border-slate-200 hover:bg-slate-100">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>
            </button>
        </div>

        <!-- Body Modal (Scrollable Content) -->
        <div class="p-6 overflow-y-auto space-y-4 text-xs flex-1">
            <!-- Informasi Pembeli & Event -->
            <div class="bg-slate-50 p-4 rounded-2xl border border-slate-200 grid grid-cols-2 gap-3">
                <div>
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Nama Pembeli</span>
                    <span class="font-extrabold text-slate-900 block text-sm" id="modalNama"></span>
                </div>
                <div>
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">No. WhatsApp</span>
                    <span class="font-bold text-slate-800 block" id="modalHp"></span>
                </div>
                <div class="col-span-2">
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Email Pembeli</span>
                    <span class="font-medium text-slate-700 block truncate" id="modalEmail"></span>
                </div>
            </div>

            <div class="bg-slate-50 p-4 rounded-2xl border border-slate-200 grid grid-cols-2 gap-3">
                <div class="col-span-2">
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Event & Jadwal</span>
                    <span class="font-extrabold text-slate-900 block text-sm mt-0.5" id="modalEvent"></span>
                    <span class="text-[11px] font-medium text-slate-500 block mt-0.5" id="modalJadwal"></span>
                </div>
                <div>
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Status Transaksi</span>
                    <div id="modalStatus" class="mt-1"></div>
                </div>
                <div>
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Metode Pembayaran</span>
                    <div id="modalMetode" class="mt-1"></div>
                </div>
                <div class="col-span-2 pt-2 border-t border-slate-200/60 flex items-center justify-between">
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Token QR Code</span>
                    <span class="font-mono text-[11px] font-bold text-slate-700 bg-white border border-slate-200 px-2 py-0.5 rounded-md" id="modalToken"></span>
                </div>
            </div>

            <!-- Section Bukti Transfer Preview -->
            <div id="modalBuktiArea" class="hidden space-y-2">
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Bukti Pembayaran Manual Uploaded</span>
                <div id="modalBuktiContent"></div>
            </div>
        </div>

        <!-- Footer Action Modal (Fixed Bottom - Always Visible) -->
        <div id="modalVerifyAction" class="p-4 border-t border-slate-100 bg-slate-50/90 shrink-0 hidden flex flex-col gap-2">
            <div id="modalPrintPdfBtnArea" class="w-full hidden">
                <a id="modalPrintPdfBtnLink" href="#" target="_blank" class="w-full bg-teal-600 hover:bg-teal-700 text-white font-bold py-2.5 px-3 rounded-xl text-xs transition-all shadow-md active:scale-95 flex items-center justify-center gap-1.5">
                    <span>📄 Cetak / Download E-Ticket (PDF) &rarr;</span>
                </a>
            </div>
            <div id="modalVerifyButtonsRow" class="flex gap-3 w-full">
                <form action="actions/verifikasi_pembayaran_manual.php" method="POST" class="w-1/2">
                    <input type="hidden" name="ticket_id" id="verifyTicketIdApprove">
                    <input type="hidden" name="action" value="approve">
                    <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-2.5 px-3 rounded-xl text-xs transition-all shadow-md active:scale-95 flex items-center justify-center gap-1.5" onclick="return confirm('Apakah Anda yakin ingin menyetujui pembayaran ini?')">
                        <span>✓ Setujui (Lunas)</span>
                    </button>
                </form>
                <form action="actions/verifikasi_pembayaran_manual.php" method="POST" class="w-1/2">
                    <input type="hidden" name="ticket_id" id="verifyTicketIdReject">
                    <input type="hidden" name="action" value="reject">
                    <button type="submit" class="w-full bg-red-600 hover:bg-red-700 text-white font-bold py-2.5 px-3 rounded-xl text-xs transition-all shadow-md active:scale-95 flex items-center justify-center gap-1.5" onclick="return confirm('Apakah Anda yakin ingin menolak transaksi ini?')">
                        <span>✕ Tolak Pesanan</span>
                    </button>
                </form>
            </div>
        </div>

    </div>
</div>

<!-- Modal Tambah Transaksi Manual (Create) -->
<div id="createModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl shadow-2xl w-full max-w-lg overflow-hidden transform scale-95 opacity-0 transition-all duration-300 flex flex-col max-h-[92vh]" id="createModalContent">
        <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50/80 shrink-0">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl bg-primary/10 text-primary flex items-center justify-center font-bold text-base">
                    ➕
                </div>
                <div>
                    <h3 class="font-extrabold text-slate-900 text-sm">Tambah Transaksi Manual</h3>
                    <p class="text-[11px] text-slate-500 font-medium">Input data transaksi pembeli baru langsung dari admin.</p>
                </div>
            </div>
            <button onclick="closeCreateModal()" class="text-slate-400 hover:text-slate-600 transition-colors p-2 bg-white rounded-full border border-slate-200 hover:bg-slate-100">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>
            </button>
        </div>

        <form action="actions/crud_transaksi.php" method="POST" class="flex flex-col flex-1 overflow-hidden">
            <input type="hidden" name="action" value="create">
            <div class="p-6 overflow-y-auto space-y-4 text-xs flex-1">
                <div>
                    <label class="block font-bold text-slate-700 uppercase tracking-wider text-[10px] mb-1">Pilih Event <span class="text-red-500">*</span></label>
                    <select name="id_event" id="createEventSelect" onchange="onCreateEventChange(this.value)" required class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 text-xs font-bold focus:ring-2 focus:ring-primary/20 focus:border-primary">
                        <option value="">-- Pilih Event --</option>
                        <?php foreach($all_events as $ev): ?>
                            <option value="<?= $ev['id'] ?>"><?= htmlspecialchars($ev['judul']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block font-bold text-slate-700 uppercase tracking-wider text-[10px] mb-1">Pilih Varian Tiket <span class="text-red-500">*</span></label>
                    <select name="id_ticket_variant" id="createVariantSelect" required class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 text-xs font-bold focus:ring-2 focus:ring-primary/20 focus:border-primary">
                        <option value="">-- Pilih Event Terlebih Dahulu --</option>
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block font-bold text-slate-700 uppercase tracking-wider text-[10px] mb-1">Nama Pembeli <span class="text-red-500">*</span></label>
                        <input type="text" name="nama_pembeli" placeholder="Nama Lengkap Pembeli" required class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 text-xs font-bold focus:ring-2 focus:ring-primary/20 focus:border-primary">
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 uppercase tracking-wider text-[10px] mb-1">No. WhatsApp <span class="text-red-500">*</span></label>
                        <input type="text" name="no_hp" placeholder="Contoh: 081234567890" required class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 text-xs font-bold focus:ring-2 focus:ring-primary/20 focus:border-primary">
                    </div>
                </div>

                <div>
                    <label class="block font-bold text-slate-700 uppercase tracking-wider text-[10px] mb-1">Email Pembeli <span class="text-red-500">*</span></label>
                    <input type="email" name="email_pembeli" placeholder="email@domain.com" required class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 text-xs font-bold focus:ring-2 focus:ring-primary/20 focus:border-primary">
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block font-bold text-slate-700 uppercase tracking-wider text-[10px] mb-1">Status Pembayaran</label>
                        <select name="status" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 text-xs font-bold focus:ring-2 focus:ring-primary/20 focus:border-primary">
                            <option value="lunas" selected>LUNAS (Aktifkan Tiket)</option>
                            <option value="pending">PENDING (Menunggu Pembayaran)</option>
                            <option value="batal">BATAL</option>
                        </select>
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 uppercase tracking-wider text-[10px] mb-1">Metode Pembayaran</label>
                        <select name="payment_method" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 text-xs font-bold focus:ring-2 focus:ring-primary/20 focus:border-primary">
                            <option value="manual" selected>Transfer / QRIS Manual</option>
                            <option value="midtrans">Midtrans Gateway</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="p-4 border-t border-slate-100 bg-slate-50/90 shrink-0 flex justify-end gap-3">
                <button type="button" onclick="closeCreateModal()" class="px-4 py-2.5 bg-white border border-slate-200 hover:bg-slate-100 text-slate-700 font-bold text-xs rounded-xl transition-colors">
                    Batal
                </button>
                <button type="submit" class="px-6 py-2.5 bg-primary hover:bg-teal-600 text-white font-extrabold text-xs rounded-xl shadow-md transition-all active:scale-95">
                    ✓ Simpan Transaksi Manual
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Edit Transaksi (Update) -->
<div id="editModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl shadow-2xl w-full max-w-lg overflow-hidden transform scale-95 opacity-0 transition-all duration-300 flex flex-col max-h-[92vh]" id="editModalContent">
        <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50/80 shrink-0">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl bg-blue-100 text-blue-700 flex items-center justify-center font-bold text-base">
                    ✏️
                </div>
                <div>
                    <h3 class="font-extrabold text-slate-900 text-sm">Edit Data Transaksi</h3>
                    <div class="text-[11px] font-mono font-bold text-blue-600" id="editModalOrderId"></div>
                </div>
            </div>
            <button onclick="closeEditModal()" class="text-slate-400 hover:text-slate-600 transition-colors p-2 bg-white rounded-full border border-slate-200 hover:bg-slate-100">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>
            </button>
        </div>

        <form action="actions/crud_transaksi.php" method="POST" class="flex flex-col flex-1 overflow-hidden">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="ticket_id" id="editTicketId">
            <div class="p-6 overflow-y-auto space-y-4 text-xs flex-1">
                <div>
                    <label class="block font-bold text-slate-700 uppercase tracking-wider text-[10px] mb-1">Event (Read Only)</label>
                    <input type="text" id="editEventJudul" readonly class="w-full px-4 py-2.5 bg-slate-100 border border-slate-200 rounded-xl text-slate-600 text-xs font-bold cursor-not-allowed">
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block font-bold text-slate-700 uppercase tracking-wider text-[10px] mb-1">Nama Pembeli <span class="text-red-500">*</span></label>
                        <input type="text" name="nama_pembeli" id="editNama" required class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 text-xs font-bold focus:ring-2 focus:ring-primary/20 focus:border-primary">
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 uppercase tracking-wider text-[10px] mb-1">No. WhatsApp <span class="text-red-500">*</span></label>
                        <input type="text" name="no_hp" id="editHp" required class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 text-xs font-bold focus:ring-2 focus:ring-primary/20 focus:border-primary">
                    </div>
                </div>

                <div>
                    <label class="block font-bold text-slate-700 uppercase tracking-wider text-[10px] mb-1">Email Pembeli <span class="text-red-500">*</span></label>
                    <input type="email" name="email_pembeli" id="editEmail" required class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 text-xs font-bold focus:ring-2 focus:ring-primary/20 focus:border-primary">
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block font-bold text-slate-700 uppercase tracking-wider text-[10px] mb-1">Status Pembayaran</label>
                        <select name="status" id="editStatus" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 text-xs font-bold focus:ring-2 focus:ring-primary/20 focus:border-primary">
                            <option value="lunas">LUNAS</option>
                            <option value="pending">PENDING</option>
                            <option value="scanned">SCANNED</option>
                            <option value="batal">BATAL</option>
                        </select>
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 uppercase tracking-wider text-[10px] mb-1">Metode Pembayaran</label>
                        <select name="payment_method" id="editMetode" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 text-xs font-bold focus:ring-2 focus:ring-primary/20 focus:border-primary">
                            <option value="manual">Transfer / QRIS Manual</option>
                            <option value="midtrans">Midtrans Gateway</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="p-4 border-t border-slate-100 bg-slate-50/90 shrink-0 flex justify-end gap-3">
                <button type="button" onclick="closeEditModal()" class="px-4 py-2.5 bg-white border border-slate-200 hover:bg-slate-100 text-slate-700 font-bold text-xs rounded-xl transition-colors">
                    Batal
                </button>
                <button type="submit" class="px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-extrabold text-xs rounded-xl shadow-md transition-all active:scale-95">
                    ✓ Perbarui Transaksi
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    const allEventsData = <?= json_encode($all_events) ?>;

    function showDetail(data) {
        const modal = document.getElementById('detailModal');
        const content = document.getElementById('detailModalContent');
        
        document.getElementById('modalOrderId').textContent = '#' + data.order_id;
        document.getElementById('modalNama').textContent = data.nama_pembeli;
        document.getElementById('modalHp').textContent = data.no_hp;
        document.getElementById('modalEmail').textContent = data.email_pembeli;
        document.getElementById('modalEvent').textContent = data.judul;
        
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
        if(data.status === 'lunas') statusHtml = '<span class="bg-emerald-100 text-emerald-700 border border-emerald-200 px-2.5 py-0.5 rounded-md text-[10px] font-bold uppercase tracking-wider">LUNAS</span>';
        else if(data.status === 'pending') statusHtml = '<span class="bg-yellow-100 text-yellow-700 border border-yellow-200 px-2.5 py-0.5 rounded-md text-[10px] font-bold uppercase tracking-wider">PENDING</span>';
        else if(data.status === 'scanned') statusHtml = '<span class="bg-blue-100 text-blue-700 border border-blue-200 px-2.5 py-0.5 rounded-md text-[10px] font-bold uppercase tracking-wider">SCANNED</span>';
        else if(data.status === 'batal') statusHtml = '<span class="bg-red-100 text-red-700 border border-red-200 px-2.5 py-0.5 rounded-md text-[10px] font-bold uppercase tracking-wider">BATAL</span>';
        
        document.getElementById('modalStatus').innerHTML = statusHtml;

        const isManual = (data.payment_method || 'midtrans') === 'manual';
        document.getElementById('modalMetode').innerHTML = isManual ? 
            '<span class="text-blue-700 font-bold text-xs">Transfer / QRIS Manual</span>' : 
            '<span class="text-emerald-700 font-bold text-xs">Midtrans Gateway</span>';

        const buktiArea = document.getElementById('modalBuktiArea');
        const buktiContent = document.getElementById('modalBuktiContent');
        const verifyAction = document.getElementById('modalVerifyAction');
        const printPdfBtnArea = document.getElementById('modalPrintPdfBtnArea');
        const printPdfBtnLink = document.getElementById('modalPrintPdfBtnLink');
        const verifyButtonsRow = document.getElementById('modalVerifyButtonsRow');

        if (data.bukti_pembayaran) {
            const fileUrl = '<?= BASE_URL ?>uploads/bukti_pembayaran/' + data.bukti_pembayaran;
            const ext = data.bukti_pembayaran.split('.').pop().toLowerCase();
            
            if (['jpg', 'jpeg', 'png', 'webp'].includes(ext)) {
                buktiContent.innerHTML = '<div class="bg-slate-50 border border-slate-200 p-2.5 rounded-2xl flex items-center justify-between gap-3"><div class="flex items-center gap-3"><img src="' + fileUrl + '" class="w-14 h-14 object-cover rounded-xl border border-slate-200 shrink-0"><div class="text-xs font-bold text-slate-800">Bukti Transfer Gambar<span class="block text-[10px] text-slate-400 font-normal">Klik tombol di kanan untuk memperbesar</span></div></div><a href="' + fileUrl + '" target="_blank" class="px-3 py-2 bg-primary/10 hover:bg-primary/20 text-primary font-bold text-xs rounded-xl transition-colors shrink-0">🔍 Perbesar</a></div>';
            } else {
                buktiContent.innerHTML = '<a href="' + fileUrl + '" target="_blank" class="inline-flex items-center gap-2 bg-blue-50 text-blue-700 border border-blue-200 font-bold px-3 py-2 rounded-xl text-xs hover:bg-blue-100 transition-colors">📄 Lihat Dokumen Bukti Transfer (PDF) &rarr;</a>';
            }
            buktiArea.classList.remove('hidden');
        } else {
            buktiArea.classList.add('hidden');
        }

        let showFooter = false;

        // Cetak PDF Link
        if (['lunas', 'scanned'].includes(data.status) && data.token_qr) {
            printPdfBtnLink.href = '<?= BASE_URL ?>user/download_tiket.php?token=' + encodeURIComponent(data.token_qr);
            printPdfBtnArea.classList.remove('hidden');
            showFooter = true;
        } else {
            printPdfBtnArea.classList.add('hidden');
        }

        // Verifikasi Approve/Reject Buttons
        if (data.status === 'pending') {
            document.getElementById('verifyTicketIdApprove').value = data.id;
            document.getElementById('verifyTicketIdReject').value = data.id;
            verifyButtonsRow.classList.remove('hidden');
            showFooter = true;
        } else {
            verifyButtonsRow.classList.add('hidden');
        }
        
        if (showFooter) {
            verifyAction.classList.remove('hidden');
        } else {
            verifyAction.classList.add('hidden');
        }

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

    // Modal Create Transaksi Manual
    function openCreateModal() {
        const modal = document.getElementById('createModal');
        const content = document.getElementById('createModalContent');
        modal.classList.remove('hidden');
        setTimeout(() => {
            content.classList.remove('scale-95', 'opacity-0');
            content.classList.add('scale-100', 'opacity-100');
        }, 10);
    }

    function closeCreateModal() {
        const modal = document.getElementById('createModal');
        const content = document.getElementById('createModalContent');
        content.classList.remove('scale-100', 'opacity-100');
        content.classList.add('scale-95', 'opacity-0');
        setTimeout(() => {
            modal.classList.add('hidden');
        }, 300);
    }

    function onCreateEventChange(eventId) {
        const variantSelect = document.getElementById('createVariantSelect');
        variantSelect.innerHTML = '<option value="">-- Pilih Varian Tiket --</option>';

        if (!eventId) return;

        const foundEv = allEventsData.find(ev => ev.id == eventId);
        if (foundEv && foundEv.variants && foundEv.variants.length > 0) {
            foundEv.variants.forEach(vr => {
                const opt = document.createElement('option');
                opt.value = vr.id;
                opt.textContent = vr.nama_varian + ' (Rp ' + Number(vr.harga).toLocaleString('id-ID') + ' | Sisa: ' + vr.sisa_stok + ')';
                variantSelect.appendChild(opt);
            });
        } else {
            variantSelect.innerHTML = '<option value="">-- Tidak Ada Varian Tiket --</option>';
        }
    }

    // Modal Edit Transaksi
    function openEditModal(data) {
        const modal = document.getElementById('editModal');
        const content = document.getElementById('editModalContent');

        document.getElementById('editTicketId').value = data.id;
        document.getElementById('editModalOrderId').textContent = '#' + data.order_id;
        document.getElementById('editEventJudul').value = data.judul;
        document.getElementById('editNama').value = data.nama_pembeli;
        document.getElementById('editHp').value = data.no_hp;
        document.getElementById('editEmail').value = data.email_pembeli;
        document.getElementById('editStatus').value = data.status;
        document.getElementById('editMetode').value = data.payment_method || 'manual';

        modal.classList.remove('hidden');
        setTimeout(() => {
            content.classList.remove('scale-95', 'opacity-0');
            content.classList.add('scale-100', 'opacity-100');
        }, 10);
    }

    function closeEditModal() {
        const modal = document.getElementById('editModal');
        const content = document.getElementById('editModalContent');
        content.classList.remove('scale-100', 'opacity-100');
        content.classList.add('scale-95', 'opacity-0');
        setTimeout(() => {
            modal.classList.add('hidden');
        }, 300);
    }
    
    // Close modals on outside click
    ['detailModal', 'createModal', 'editModal'].forEach(id => {
        const modalEl = document.getElementById(id);
        if (modalEl) {
            modalEl.addEventListener('click', function(e) {
                if(e.target === this) {
                    if (id === 'detailModal') closeDetail();
                    if (id === 'createModal') closeCreateModal();
                    if (id === 'editModal') closeEditModal();
                }
            });
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
