<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../auth/login.php");
    exit;
}
require_once '../config/koneksi.php';

$success_msg = '';
$error_msg = '';

// Process Approve / Reject Withdrawal
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $withdrawal_id = (int)($_POST['withdrawal_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    $admin_note = trim($_POST['admin_note'] ?? '');

    $stmt_check = $conn->prepare("SELECT w.*, u.nama_lengkap, u.email FROM withdrawals w JOIN users u ON w.user_id = u.id WHERE w.id = ?");
    $stmt_check->bind_param("i", $withdrawal_id);
    $stmt_check->execute();
    $w_data = $stmt_check->get_result()->fetch_assoc();
    $stmt_check->close();

    if ($w_data && $w_data['status'] === 'pending') {
        if ($action === 'approve') {
            $stmt = $conn->prepare("UPDATE withdrawals SET status = 'approved', admin_note = ? WHERE id = ?");
            $stmt->bind_param("si", $admin_note, $withdrawal_id);
            if ($stmt->execute()) {
                logActivity($conn, $_SESSION['user_id'], 'Approve Withdrawal', "Admin menyetujui penarikan dana #{$withdrawal_id} sebesar Rp " . number_format($w_data['amount'], 0, ',', '.') . " untuk {$w_data['nama_lengkap']}.");
                $success_msg = "Penarikan dana #{$withdrawal_id} berhasil disetujui.";
            } else {
                $error_msg = "Gagal memperbarui status penarikan dana.";
            }
            $stmt->close();
        } elseif ($action === 'reject') {
            if (empty($admin_note)) {
                $error_msg = "Catatan alasan penolakan wajib diisi.";
            } else {
                $stmt = $conn->prepare("UPDATE withdrawals SET status = 'rejected', admin_note = ? WHERE id = ?");
                $stmt->bind_param("si", $admin_note, $withdrawal_id);
                if ($stmt->execute()) {
                    logActivity($conn, $_SESSION['user_id'], 'Reject Withdrawal', "Admin menolak penarikan dana #{$withdrawal_id} untuk {$w_data['nama_lengkap']}. Alasan: {$admin_note}");
                    $success_msg = "Penarikan dana #{$withdrawal_id} berhasil ditolak.";
                } else {
                    $error_msg = "Gagal memperbarui status penarikan dana.";
                }
                $stmt->close();
            }
        }
    } else {
        $error_msg = "Data penarikan dana tidak ditemukan atau sudah diproses.";
    }
}

// Filter Status
$filter_status = $_GET['status'] ?? 'all';
$where_clause = "";
if (in_array($filter_status, ['pending', 'approved', 'rejected'])) {
    $where_clause = "WHERE w.status = '$filter_status'";
}

// Fetch Withdrawals
$query = "
    SELECT w.*, u.nama_lengkap, u.email 
    FROM withdrawals w 
    JOIN users u ON w.user_id = u.id 
    $where_clause 
    ORDER BY w.created_at DESC
";
$withdrawals_res = $conn->query($query);

// Summary Stats
$stats_q = $conn->query("
    SELECT 
        SUM(CASE WHEN status = 'approved' THEN amount ELSE 0 END) as total_approved_amount,
        COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_count,
        COUNT(CASE WHEN status = 'approved' THEN 1 END) as approved_count,
        COUNT(CASE WHEN status = 'rejected' THEN 1 END) as rejected_count
    FROM withdrawals
");
$stats = $stats_q ? $stats_q->fetch_assoc() : [];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Penarikan Dana - HaloTiket Admin</title>
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
    <?php $active_menu = 'withdrawals'; include 'components/sidebar.php'; ?>

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
                <span class="text-xl font-extrabold text-slate-800 md:hidden">Penarikan Dana</span>
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
                        <h1 class="text-2xl md:text-3xl font-extrabold text-slate-900 tracking-tight">Manajemen Penarikan Dana Vendor</h1>
                        <p class="text-slate-500 mt-1 font-medium text-sm">Verifikasi dan proses pengajuan klaim/tarik tunai hasil penjualan tiket vendor.</p>
                    </div>
                </div>

                <?php if($success_msg): ?>
                <div class="mb-6 bg-emerald-50 text-emerald-700 px-5 py-4 rounded-xl text-sm font-bold flex items-center gap-3 border border-emerald-200 shadow-sm">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                    <?= htmlspecialchars($success_msg) ?>
                </div>
                <?php endif; ?>

                <?php if($error_msg): ?>
                <div class="mb-6 bg-red-50 text-red-600 px-5 py-4 rounded-xl text-sm font-bold flex items-center gap-3 border border-red-200 shadow-sm">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                    <?= htmlspecialchars($error_msg) ?>
                </div>
                <?php endif; ?>

                <!-- Summary Cards -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                    <div class="bg-gradient-to-br from-emerald-500 to-teal-600 rounded-3xl p-6 text-white shadow-lg shadow-emerald-500/20">
                        <p class="text-xs font-bold uppercase tracking-wider text-emerald-100 mb-1">Total Payout Disetujui</p>
                        <h3 class="text-3xl font-extrabold font-mono">Rp <?= number_format($stats['total_approved_amount'] ?? 0, 0, ',', '.') ?></h3>
                        <p class="text-xs text-emerald-100 mt-2 font-medium">Dari <?= $stats['approved_count'] ?? 0 ?> transaksi pencairan</p>
                    </div>

                    <div class="bg-gradient-to-br from-amber-500 to-orange-600 rounded-3xl p-6 text-white shadow-lg shadow-amber-500/20">
                        <p class="text-xs font-bold uppercase tracking-wider text-amber-100 mb-1">Pengajuan Menunggu Approval</p>
                        <h3 class="text-3xl font-extrabold font-mono"><?= $stats['pending_count'] ?? 0 ?> <span class="text-lg font-normal">Permintaan</span></h3>
                        <p class="text-xs text-amber-100 mt-2 font-medium">Perlu tindakan verifikasi admin</p>
                    </div>

                    <div class="bg-gradient-to-br from-slate-800 to-slate-900 rounded-3xl p-6 text-white shadow-lg shadow-slate-900/20">
                        <p class="text-xs font-bold uppercase tracking-wider text-slate-300 mb-1">Total Penolakan (Rejected)</p>
                        <h3 class="text-3xl font-extrabold font-mono"><?= $stats['rejected_count'] ?? 0 ?> <span class="text-lg font-normal">Pengajuan</span></h3>
                        <p class="text-xs text-slate-300 mt-2 font-medium">Saldo dikembalikan ke vendor</p>
                    </div>
                </div>

                <!-- Filter Navigation Tabs -->
                <div class="flex items-center gap-2 mb-6 border-b border-slate-200 pb-4 overflow-x-auto">
                    <a href="?status=all" class="px-4 py-2 rounded-xl text-xs font-bold transition-all shrink-0 <?= $filter_status == 'all' ? 'bg-primary text-white shadow-md' : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-100' ?>">
                        Semua Pengajuan
                    </a>
                    <a href="?status=pending" class="px-4 py-2 rounded-xl text-xs font-bold transition-all shrink-0 <?= $filter_status == 'pending' ? 'bg-amber-500 text-white shadow-md' : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-100' ?>">
                        Menunggu (Pending) <?= ($stats['pending_count'] ?? 0) > 0 ? "({$stats['pending_count']})" : '' ?>
                    </a>
                    <a href="?status=approved" class="px-4 py-2 rounded-xl text-xs font-bold transition-all shrink-0 <?= $filter_status == 'approved' ? 'bg-emerald-500 text-white shadow-md' : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-100' ?>">
                        Disetujui (Approved)
                    </a>
                    <a href="?status=rejected" class="px-4 py-2 rounded-xl text-xs font-bold transition-all shrink-0 <?= $filter_status == 'rejected' ? 'bg-red-500 text-white shadow-md' : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-100' ?>">
                        Ditolak (Rejected)
                    </a>
                </div>

                <!-- Table Content -->
                <div class="bg-white rounded-3xl shadow-sm border border-slate-200 overflow-hidden">
                    <div class="px-6 py-4 border-b border-slate-100 bg-slate-50/50 flex justify-between items-center">
                        <h3 class="font-extrabold text-slate-900 text-sm">Daftar Pengajuan Penarikan Dana</h3>
                        <span class="bg-primary/10 text-primary text-xs font-bold px-3 py-1 rounded-full">Total: <?= $withdrawals_res->num_rows ?> Data</span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-100">
                            <thead class="bg-white">
                                <tr>
                                    <th class="px-6 py-3 text-left text-[11px] font-bold text-slate-400 uppercase tracking-wider">ID & Waktu</th>
                                    <th class="px-6 py-3 text-left text-[11px] font-bold text-slate-400 uppercase tracking-wider">Vendor / Panitia</th>
                                    <th class="px-6 py-3 text-left text-[11px] font-bold text-slate-400 uppercase tracking-wider">Rekening Tujuan</th>
                                    <th class="px-6 py-3 text-left text-[11px] font-bold text-slate-400 uppercase tracking-wider">Nominal Penarikan</th>
                                    <th class="px-6 py-3 text-left text-[11px] font-bold text-slate-400 uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-3 text-right text-[11px] font-bold text-slate-400 uppercase tracking-wider">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-slate-50">
                                <?php if($withdrawals_res->num_rows > 0): ?>
                                    <?php while($row = $withdrawals_res->fetch_assoc()): ?>
                                    <tr class="hover:bg-slate-50/80 transition-colors">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-xs font-bold text-slate-900">#WD-<?= $row['id'] ?></div>
                                            <div class="text-[11px] font-medium text-slate-500 mt-1"><?= date('d M Y, H:i', strtotime($row['created_at'])) ?></div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm font-bold text-slate-900"><?= htmlspecialchars($row['nama_lengkap']) ?></div>
                                            <div class="text-[11px] font-medium text-slate-500"><?= htmlspecialchars($row['email']) ?></div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm font-bold text-slate-800"><?= htmlspecialchars($row['bank_name']) ?></div>
                                            <div class="text-xs font-mono font-semibold text-slate-600"><?= htmlspecialchars($row['account_number']) ?></div>
                                            <div class="text-[11px] text-slate-400">a.n <?= htmlspecialchars($row['account_name']) ?></div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm font-extrabold font-mono text-slate-900">Rp <?= number_format($row['amount'], 0, ',', '.') ?></div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <?php if($row['status'] == 'approved'): ?>
                                                <span class="px-2.5 py-1 rounded-md text-[11px] font-bold uppercase tracking-wider bg-emerald-100 text-emerald-700">Disetujui</span>
                                            <?php elseif($row['status'] == 'rejected'): ?>
                                                <span class="px-2.5 py-1 rounded-md text-[11px] font-bold uppercase tracking-wider bg-red-100 text-red-700">Ditolak</span>
                                            <?php else: ?>
                                                <span class="px-2.5 py-1 rounded-md text-[11px] font-bold uppercase tracking-wider bg-amber-100 text-amber-700 animate-pulse">Menunggu</span>
                                            <?php endif; ?>

                                            <?php if(!empty($row['admin_note'])): ?>
                                                <div class="text-[11px] text-slate-400 mt-1 italic max-w-[180px] truncate" title="<?= htmlspecialchars($row['admin_note']) ?>">
                                                    Note: <?= htmlspecialchars($row['admin_note']) ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <?php if($row['status'] == 'pending'): ?>
                                                <div class="flex justify-end gap-2">
                                                    <button onclick="openApproveModal(<?= htmlspecialchars(json_encode($row)) ?>)" class="bg-emerald-50 hover:bg-emerald-100 text-emerald-600 font-bold px-3 py-1.5 rounded-lg text-xs border border-emerald-200 transition-colors">
                                                        Setujui
                                                    </button>
                                                    <button onclick="openRejectModal(<?= htmlspecialchars(json_encode($row)) ?>)" class="bg-red-50 hover:bg-red-100 text-red-600 font-bold px-3 py-1.5 rounded-lg text-xs border border-red-200 transition-colors">
                                                        Tolak
                                                    </button>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-xs text-slate-400 font-medium">Selesai</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="px-6 py-12 text-center text-slate-500 font-medium">
                                            Belum ada data pengajuan penarikan dana.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Modal Approve -->
<div id="approveModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl shadow-xl w-full max-w-md overflow-hidden transform scale-95 opacity-0 transition-all duration-300" id="approveModalContent">
        <form method="POST" action="">
            <input type="hidden" name="action" value="approve">
            <input type="hidden" name="withdrawal_id" id="approve_withdrawal_id">
            <div class="px-6 py-4 border-b border-slate-100 bg-emerald-50 flex justify-between items-center">
                <h3 class="font-extrabold text-emerald-900 text-base">Setujui Penarikan Dana</h3>
                <button type="button" onclick="closeModal('approveModal')" class="text-slate-400 hover:text-slate-600 text-2xl font-bold">&times;</button>
            </div>
            <div class="p-6 space-y-4">
                <div class="bg-slate-50 p-4 rounded-2xl border border-slate-100 text-sm space-y-1">
                    <p class="text-slate-500 font-medium">Vendor: <strong class="text-slate-800" id="approve_vendor_name"></strong></p>
                    <p class="text-slate-500 font-medium">Nominal: <strong class="text-emerald-600 font-mono text-base" id="approve_amount"></strong></p>
                    <p class="text-slate-500 font-medium">Bank Tujuan: <strong class="text-slate-800" id="approve_bank"></strong></p>
                </div>
                <div class="space-y-2">
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider">Nomor Referensi / Catatan Transfer <span class="text-slate-400 font-normal">(Opsional)</span></label>
                    <textarea name="admin_note" rows="3" placeholder="Contoh: Transfer via M-Banking BCA Ref #8912301" class="w-full bg-slate-50 border border-slate-200 text-slate-800 text-sm rounded-xl focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 p-3 transition-colors font-medium"></textarea>
                </div>
            </div>
            <div class="px-6 py-4 border-t border-slate-100 bg-slate-50 flex justify-end gap-3">
                <button type="button" onclick="closeModal('approveModal')" class="px-4 py-2 text-sm font-bold text-slate-500 hover:text-slate-700 bg-slate-200 rounded-xl">Batal</button>
                <button type="submit" class="px-6 py-2 text-sm font-bold text-white bg-emerald-600 hover:bg-emerald-700 rounded-xl shadow-md transition-colors">Ya, Setujui & Transfer</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Reject -->
<div id="rejectModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl shadow-xl w-full max-w-md overflow-hidden transform scale-95 opacity-0 transition-all duration-300" id="rejectModalContent">
        <form method="POST" action="">
            <input type="hidden" name="action" value="reject">
            <input type="hidden" name="withdrawal_id" id="reject_withdrawal_id">
            <div class="px-6 py-4 border-b border-slate-100 bg-red-50 flex justify-between items-center">
                <h3 class="font-extrabold text-red-900 text-base">Tolak Penarikan Dana</h3>
                <button type="button" onclick="closeModal('rejectModal')" class="text-slate-400 hover:text-slate-600 text-2xl font-bold">&times;</button>
            </div>
            <div class="p-6 space-y-4">
                <div class="bg-slate-50 p-4 rounded-2xl border border-slate-100 text-sm space-y-1">
                    <p class="text-slate-500 font-medium">Vendor: <strong class="text-slate-800" id="reject_vendor_name"></strong></p>
                    <p class="text-slate-500 font-medium">Nominal: <strong class="text-red-600 font-mono text-base" id="reject_amount"></strong></p>
                </div>
                <div class="space-y-2">
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider">Alasan Penolakan <span class="text-red-500">*</span></label>
                    <textarea name="admin_note" required rows="3" placeholder="Contoh: Rekening tidak valid / tidak cocok dengan nama akun" class="w-full bg-slate-50 border border-slate-200 text-slate-800 text-sm rounded-xl focus:ring-2 focus:ring-red-500/20 focus:border-red-500 p-3 transition-colors font-medium"></textarea>
                </div>
            </div>
            <div class="px-6 py-4 border-t border-slate-100 bg-slate-50 flex justify-end gap-3">
                <button type="button" onclick="closeModal('rejectModal')" class="px-4 py-2 text-sm font-bold text-slate-500 hover:text-slate-700 bg-slate-200 rounded-xl">Batal</button>
                <button type="submit" class="px-6 py-2 text-sm font-bold text-white bg-red-600 hover:bg-red-700 rounded-xl shadow-md transition-colors">Tolak Penarikan</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openApproveModal(data) {
        document.getElementById('approve_withdrawal_id').value = data.id;
        document.getElementById('approve_vendor_name').textContent = data.nama_lengkap;
        document.getElementById('approve_amount').textContent = 'Rp ' + new Intl.NumberFormat('id-ID').format(data.amount);
        document.getElementById('approve_bank').textContent = data.bank_name + ' - ' + data.account_number + ' (a.n ' + data.account_name + ')';

        const modal = document.getElementById('approveModal');
        const content = document.getElementById('approveModalContent');
        modal.classList.remove('hidden');
        setTimeout(() => {
            content.classList.remove('scale-95', 'opacity-0');
            content.classList.add('scale-100', 'opacity-100');
        }, 10);
    }

    function openRejectModal(data) {
        document.getElementById('reject_withdrawal_id').value = data.id;
        document.getElementById('reject_vendor_name').textContent = data.nama_lengkap;
        document.getElementById('reject_amount').textContent = 'Rp ' + new Intl.NumberFormat('id-ID').format(data.amount);

        const modal = document.getElementById('rejectModal');
        const content = document.getElementById('rejectModalContent');
        modal.classList.remove('hidden');
        setTimeout(() => {
            content.classList.remove('scale-95', 'opacity-0');
            content.classList.add('scale-100', 'opacity-100');
        }, 10);
    }

    function closeModal(modalId) {
        const modal = document.getElementById(modalId);
        const content = modal.querySelector('div');
        content.classList.remove('scale-100', 'opacity-100');
        content.classList.add('scale-95', 'opacity-0');
        setTimeout(() => {
            modal.classList.add('hidden');
        }, 300);
    }

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
    });
</script>
</body>
</html>
