<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'panitia') {
    header("Location: ../auth/login.php");
    exit;
}

require_once '../config/koneksi.php';

$user_id = (int)($_SESSION['user_id'] ?? 0);
if ($user_id <= 0) {
    header("Location: ../auth/login.php");
    exit;
}

// Get current user info safely (termasuk rekening bank)
$user_data = [
    'nama_lengkap' => $_SESSION['nama_lengkap'] ?? 'Panitia Event',
    'email' => $_SESSION['email'] ?? '',
    'foto_profil' => $_SESSION['foto_profil'] ?? '',
    'nama_bank' => '',
    'no_rekening' => '',
    'nama_rekening' => ''
];

$stmt = $conn->prepare("SELECT email, nama_lengkap, foto_profil, nama_bank, no_rekening, nama_rekening FROM users WHERE id = ?");
if ($stmt) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $db_user = $res->fetch_assoc()) {
        $user_data = array_merge($user_data, array_filter($db_user, function($v) { return $v !== null; }));
    }
    $stmt->close();
}

// Flash messages for withdraw
$success_withdraw = $_SESSION['success_withdraw'] ?? '';
$error_withdraw = $_SESSION['error_withdraw'] ?? '';
unset($_SESSION['success_withdraw'], $_SESSION['error_withdraw']);

// Calculation for Vendor Saldo & Withdrawals safely
$total_earnings = 0;
try {
    $stmt_rev = $conn->prepare("
        SELECT SUM(e.harga) as total_earnings
        FROM tickets t
        JOIN events e ON t.id_event = e.id
        WHERE e.id_panitia = ? AND t.status IN ('lunas', 'scanned')
    ");
    if ($stmt_rev) {
        $stmt_rev->bind_param("i", $user_id);
        $stmt_rev->execute();
        $res = $stmt_rev->get_result();
        if ($res && $row = $res->fetch_assoc()) {
            $total_earnings = (float)($row['total_earnings'] ?? 0);
        }
        $stmt_rev->close();
    }
} catch (\Throwable $e) {
    $total_earnings = 0;
}

$total_approved_withdraw = 0;
try {
    $stmt_wd_approved = $conn->prepare("SELECT SUM(amount) as total_approved FROM withdrawals WHERE user_id = ? AND status = 'approved'");
    if ($stmt_wd_approved) {
        $stmt_wd_approved->bind_param("i", $user_id);
        $stmt_wd_approved->execute();
        $res = $stmt_wd_approved->get_result();
        if ($res && $row = $res->fetch_assoc()) {
            $total_approved_withdraw = (float)($row['total_approved'] ?? 0);
        }
        $stmt_wd_approved->close();
    }
} catch (\Throwable $e) {
    $total_approved_withdraw = 0;
}

$total_pending_withdraw = 0;
try {
    $stmt_wd_pending = $conn->prepare("SELECT SUM(amount) as total_pending FROM withdrawals WHERE user_id = ? AND status = 'pending'");
    if ($stmt_wd_pending) {
        $stmt_wd_pending->bind_param("i", $user_id);
        $stmt_wd_pending->execute();
        $res = $stmt_wd_pending->get_result();
        if ($res && $row = $res->fetch_assoc()) {
            $total_pending_withdraw = (float)($row['total_pending'] ?? 0);
        }
        $stmt_wd_pending->close();
    }
} catch (\Throwable $e) {
    $total_pending_withdraw = 0;
}

$available_balance = max(0, $total_earnings - ($total_approved_withdraw + $total_pending_withdraw));

// Fetch withdrawal history safely
$withdraw_history_data = [];
try {
    $stmt_history = $conn->prepare("SELECT * FROM withdrawals WHERE user_id = ? ORDER BY created_at DESC");
    if ($stmt_history) {
        $stmt_history->bind_param("i", $user_id);
        $stmt_history->execute();
        $res = $stmt_history->get_result();
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $withdraw_history_data[] = $row;
            }
        }
        $stmt_history->close();
    }
} catch (\Throwable $e) {
    $withdraw_history_data = [];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tarik Tunai & Rekening Bank - HaloTiket Panitia</title>
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
    <?php $active_menu = 'withdrawal'; include 'components/sidebar.php'; ?>

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
                <span class="text-xl font-extrabold text-slate-800 md:hidden">Tarik Tunai & Bank</span>
            </div>
            
            <div class="flex items-center gap-4">
                <a href="profile.php" class="hidden md:flex items-center gap-3 mr-2 px-3 py-1.5 rounded-full border border-slate-100 bg-slate-50 hover:bg-slate-100 hover:border-slate-200 transition-colors group cursor-pointer">
                    <?php if (isset($_SESSION['foto_profil']) && !empty($_SESSION['foto_profil']) && file_exists('../assets/images/profil/'.$_SESSION['foto_profil'])): ?>
                        <img src="../assets/images/profil/<?= htmlspecialchars($_SESSION['foto_profil']) ?>" class="w-8 h-8 rounded-full object-cover shadow-sm">
                    <?php else: ?>
                        <div class="w-8 h-8 rounded-full bg-gradient-to-br from-primary to-secondary text-white flex items-center justify-center font-bold text-sm shadow-sm group-hover:shadow transition-all">
                            <?= strtoupper(substr($_SESSION['nama_lengkap'] ?? 'P', 0, 1)) ?>
                        </div>
                    <?php endif; ?>
                    <span class="text-sm font-bold text-slate-700 pr-2 group-hover:text-primary transition-colors"><?= htmlspecialchars($_SESSION['nama_lengkap'] ?? 'Panitia') ?></span>
                </a>
            </div>
        </header>

        <main class="flex-1 overflow-y-auto p-4 md:p-6 lg:p-10">
            <div class="max-w-4xl mx-auto space-y-8">
                
                <!-- Page Title -->
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div>
                        <h1 class="text-2xl md:text-3xl font-extrabold text-slate-900 tracking-tight flex items-center gap-3">
                            <span class="w-10 h-10 rounded-2xl bg-primary/10 text-primary flex items-center justify-center text-xl">💸</span>
                            Tarik Tunai & Rekening Bank
                        </h1>
                        <p class="text-slate-500 mt-1 font-medium text-sm">Kelola informasi rekening bank penampung dan ajukan pencairan saldo pendapatan tiket Anda.</p>
                    </div>
                </div>

                <?php if($success_withdraw): ?>
                <div class="bg-emerald-50 text-emerald-700 px-5 py-4 rounded-xl text-sm font-bold flex items-center gap-3 border border-emerald-200 shadow-sm">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                    <?= htmlspecialchars($success_withdraw) ?>
                </div>
                <?php endif; ?>

                <?php if($error_withdraw): ?>
                <div class="bg-red-50 text-red-600 px-5 py-4 rounded-xl text-sm font-bold flex items-center gap-3 border border-red-200 shadow-sm">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                    <?= htmlspecialchars($error_withdraw) ?>
                </div>
                <?php endif; ?>

                <!-- Balance Summary Cards -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="bg-gradient-to-br from-slate-900 to-indigo-950 rounded-3xl p-6 text-white shadow-xl relative overflow-hidden flex flex-col justify-between">
                        <div class="absolute -right-6 -bottom-6 w-32 h-32 bg-primary/20 rounded-full blur-2xl"></div>
                        <div>
                            <p class="text-xs font-bold uppercase tracking-wider text-indigo-300 mb-1">Saldo Tersedia (Siap Ditarik)</p>
                            <h3 class="text-3xl font-extrabold font-mono text-white">Rp <?= number_format($available_balance, 0, ',', '.') ?></h3>
                            <p class="text-xs text-slate-300 mt-2 font-medium">Bisa diajukan penarikan ke rekening bank Anda</p>
                        </div>
                        <div class="mt-6 pt-4 border-t border-indigo-900/50">
                            <button onclick="openWithdrawModal()" class="w-full bg-primary hover:bg-primary/90 text-white font-bold py-2.5 px-4 rounded-xl shadow-lg transition-all text-sm flex items-center justify-center gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" /></svg>
                                Ajukan Tarik Tunai
                            </button>
                        </div>
                    </div>

                    <div class="bg-gradient-to-br from-emerald-500 to-teal-600 rounded-3xl p-6 text-white shadow-lg shadow-emerald-500/20 flex flex-col justify-between">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-wider text-emerald-100 mb-1">Total Pendapatan Tiket</p>
                            <h3 class="text-3xl font-extrabold font-mono">Rp <?= number_format($total_earnings, 0, ',', '.') ?></h3>
                            <p class="text-xs text-emerald-100 mt-2 font-medium">Akumulasi bersih dari tiket lunas & scanned</p>
                        </div>
                        <div class="mt-6 text-xs text-emerald-100/80 font-medium">
                            Otomatis terakumulasi per transaksi lunas
                        </div>
                    </div>

                    <div class="bg-gradient-to-br from-slate-800 to-slate-900 rounded-3xl p-6 text-white shadow-lg shadow-slate-900/20 flex flex-col justify-between">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-wider text-slate-300 mb-1">Total Pencairan & Pending</p>
                            <h3 class="text-3xl font-extrabold font-mono">Rp <?= number_format($total_approved_withdraw + $total_pending_withdraw, 0, ',', '.') ?></h3>
                            <div class="mt-2 text-xs text-slate-300 space-y-0.5">
                                <p>• Disetujui: <span class="font-bold text-emerald-400">Rp <?= number_format($total_approved_withdraw, 0, ',', '.') ?></span></p>
                                <p>• Pending: <span class="font-bold text-amber-400">Rp <?= number_format($total_pending_withdraw, 0, ',', '.') ?></span></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Bank Account Settings Card -->
                <div class="bg-white rounded-3xl shadow-sm border border-slate-200 overflow-hidden">
                    <div class="px-6 py-4 border-b border-slate-100 bg-slate-50/50 flex items-center justify-between">
                        <h3 class="font-extrabold text-slate-900 text-sm flex items-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" /></svg>
                            Informasi Rekening Bank Penampung
                        </h3>
                        <span class="text-xs font-bold text-slate-400">Diperlukan untuk Pencairan</span>
                    </div>
                    <div class="p-6 md:p-8">
                        <form method="POST" action="actions/proses_withdraw.php" class="space-y-5">
                            <input type="hidden" name="bank_form" value="1">
                            <input type="hidden" name="action" value="update_bank">
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                                <div class="space-y-2">
                                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider">Nama Bank</label>
                                    <select name="nama_bank" required class="w-full bg-slate-50 border border-slate-200 text-slate-800 text-sm rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary block px-4 py-2.5 transition-colors font-medium">
                                        <option value="">-- Pilih Bank --</option>
                                        <?php 
                                        $banks = ['Bank BCA', 'Bank Mandiri', 'Bank BNI', 'Bank BRI', 'Bank Syariah Indonesia (BSI)', 'Bank CIMB Niaga', 'Bank Permata', 'Bank Danamon', 'Bank Tabungan Negara (BTN)', 'Lainnya'];
                                        foreach($banks as $b): ?>
                                            <option value="<?= $b ?>" <?= ($user_data['nama_bank'] ?? '') == $b ? 'selected' : '' ?>><?= $b ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="space-y-2">
                                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider">Nomor Rekening</label>
                                    <input type="text" name="no_rekening" value="<?= htmlspecialchars($user_data['no_rekening'] ?? '') ?>" required placeholder="Contoh: 1234567890" class="w-full bg-slate-50 border border-slate-200 text-slate-800 text-sm rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary block px-4 py-2.5 transition-colors font-medium">
                                </div>

                                <div class="space-y-2">
                                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider">Atas Nama Rekening</label>
                                    <input type="text" name="nama_rekening" value="<?= htmlspecialchars($user_data['nama_rekening'] ?? '') ?>" required placeholder="Sesuai buku tabungan" class="w-full bg-slate-50 border border-slate-200 text-slate-800 text-sm rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary block px-4 py-2.5 transition-colors font-medium">
                                </div>
                            </div>

                            <div class="pt-2 flex justify-end">
                                <button type="submit" class="bg-slate-800 hover:bg-slate-900 text-white font-bold py-2.5 px-6 rounded-xl transition-all text-sm flex items-center gap-2">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                                    Simpan Rekening Bank
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Withdrawal History Table -->
                <div class="bg-white rounded-3xl shadow-sm border border-slate-200 overflow-hidden">
                    <div class="px-6 py-4 border-b border-slate-100 bg-slate-50/50 flex justify-between items-center">
                        <h3 class="font-extrabold text-slate-900 text-sm">Riwayat Penarikan Dana</h3>
                        <span class="bg-primary/10 text-primary text-xs font-bold px-3 py-1 rounded-full">Total: <?= count($withdraw_history_data) ?> Transaksi</span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-100">
                            <thead class="bg-white">
                                <tr>
                                    <th class="px-6 py-3 text-left text-[11px] font-bold text-slate-400 uppercase tracking-wider">ID & Waktu</th>
                                    <th class="px-6 py-3 text-left text-[11px] font-bold text-slate-400 uppercase tracking-wider">Rekening Tujuan</th>
                                    <th class="px-6 py-3 text-left text-[11px] font-bold text-slate-400 uppercase tracking-wider">Nominal Penarikan</th>
                                    <th class="px-6 py-3 text-left text-[11px] font-bold text-slate-400 uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-3 text-left text-[11px] font-bold text-slate-400 uppercase tracking-wider">Catatan Admin</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-slate-50">
                                <?php if(count($withdraw_history_data) > 0): ?>
                                    <?php foreach($withdraw_history_data as $wh): ?>
                                    <tr class="hover:bg-slate-50/80 transition-colors">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-xs font-bold text-slate-900">#WD-<?= $wh['id'] ?></div>
                                            <div class="text-[11px] font-medium text-slate-400 mt-1"><?= date('d M Y, H:i', strtotime($wh['created_at'])) ?></div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm font-bold text-slate-800"><?= htmlspecialchars($wh['bank_name'] ?? '') ?></div>
                                            <div class="text-xs font-mono text-slate-600"><?= htmlspecialchars($wh['account_number'] ?? '') ?></div>
                                            <div class="text-[11px] text-slate-400">a.n <?= htmlspecialchars($wh['account_name'] ?? '') ?></div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm font-extrabold font-mono text-slate-900">Rp <?= number_format($wh['amount'] ?? 0, 0, ',', '.') ?></div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <?php if(($wh['status'] ?? '') == 'approved'): ?>
                                                <span class="px-2.5 py-1 rounded-md text-[11px] font-bold uppercase tracking-wider bg-emerald-100 text-emerald-700">Disetujui</span>
                                            <?php elseif(($wh['status'] ?? '') == 'rejected'): ?>
                                                <span class="px-2.5 py-1 rounded-md text-[11px] font-bold uppercase tracking-wider bg-red-100 text-red-700">Ditolak</span>
                                            <?php else: ?>
                                                <span class="px-2.5 py-1 rounded-md text-[11px] font-bold uppercase tracking-wider bg-amber-100 text-amber-700 animate-pulse">Menunggu Verifikasi</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 text-xs font-medium text-slate-500">
                                            <?= !empty($wh['admin_note']) ? htmlspecialchars($wh['admin_note']) : '-' ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="px-6 py-8 text-center text-slate-400 font-medium">
                                            Belum ada riwayat penarikan dana.
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

<!-- Modal Request Withdrawal -->
<div id="withdrawModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl shadow-xl w-full max-w-md overflow-hidden transform scale-95 opacity-0 transition-all duration-300" id="withdrawModalContent">
        <form method="POST" action="actions/proses_withdraw.php">
            <input type="hidden" name="withdraw_form" value="1">
            <input type="hidden" name="action" value="request_withdraw">
            <div class="px-6 py-4 border-b border-slate-100 bg-slate-900 text-white flex justify-between items-center">
                <h3 class="font-extrabold text-base">Pengajuan Tarik Tunai</h3>
                <button type="button" onclick="closeWithdrawModal()" class="text-slate-400 hover:text-white text-2xl font-bold">&times;</button>
            </div>
            <div class="p-6 space-y-4">
                <div class="bg-indigo-50 border border-indigo-100 p-4 rounded-2xl text-xs space-y-1 text-indigo-900">
                    <p>Saldo Maksimal yang Bisa Ditarik:</p>
                    <p class="text-xl font-extrabold font-mono text-primary">Rp <?= number_format($available_balance, 0, ',', '.') ?></p>
                </div>

                <?php if(empty($user_data['nama_bank']) || empty($user_data['no_rekening'])): ?>
                    <div class="bg-amber-50 border border-amber-200 text-amber-800 text-xs p-3 rounded-xl">
                        ⚠️ <strong>Perhatian:</strong> Informasi rekening bank Anda belum lengkap. Mohon isi data rekening terlebih dahulu.
                    </div>
                <?php else: ?>
                    <div class="bg-slate-50 p-3 rounded-xl border border-slate-200 text-xs text-slate-700">
                        <p class="font-bold text-slate-900">Rekening Tujuan Transfer:</p>
                        <p><?= htmlspecialchars($user_data['nama_bank']) ?> - <?= htmlspecialchars($user_data['no_rekening']) ?></p>
                        <p class="text-slate-500">a.n <?= htmlspecialchars($user_data['nama_rekening']) ?></p>
                    </div>
                <?php endif; ?>

                <div class="space-y-2">
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider">Jumlah Penarikan (Rp) <span class="text-red-500">*</span></label>
                    <input type="number" name="amount" min="10000" max="<?= (int)$available_balance ?>" required placeholder="Contoh: 100000" class="w-full bg-slate-50 border border-slate-200 text-slate-800 text-sm rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary p-3 transition-colors font-mono font-bold">
                    <p class="text-[11px] text-slate-400 font-medium">Minimal penarikan Rp 10.000.</p>
                </div>
            </div>
            <div class="px-6 py-4 border-t border-slate-100 bg-slate-50 flex justify-end gap-3">
                <button type="button" onclick="closeWithdrawModal()" class="px-4 py-2 text-sm font-bold text-slate-500 hover:text-slate-700 bg-slate-200 rounded-xl">Batal</button>
                <button type="submit" <?= ($available_balance < 10000 || empty($user_data['nama_bank'])) ? 'disabled' : '' ?> class="px-6 py-2 text-sm font-bold text-white bg-primary hover:bg-primary/90 disabled:opacity-50 disabled:cursor-not-allowed rounded-xl shadow-md transition-all">Kirim Pengajuan</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openWithdrawModal() {
        const modal = document.getElementById('withdrawModal');
        const content = document.getElementById('withdrawModalContent');
        modal.classList.remove('hidden');
        setTimeout(() => {
            content.classList.remove('scale-95', 'opacity-0');
            content.classList.add('scale-100', 'opacity-100');
        }, 10);
    }

    function closeWithdrawModal() {
        const modal = document.getElementById('withdrawModal');
        const content = document.getElementById('withdrawModalContent');
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
