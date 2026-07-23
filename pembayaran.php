<?php
session_start();
require_once 'config/koneksi.php';

$order_id = isset($_GET['order_id']) ? trim($_GET['order_id']) : (isset($_SESSION['last_order_id']) ? trim($_SESSION['last_order_id']) : '');

if (empty($order_id)) {
    header("Location: index.php");
    exit;
}

// Fetch ticket, event & variant details
$stmt = $conn->prepare("SELECT t.*, e.judul, e.tanggal, e.waktu, e.lokasi, e.banner_image, v.nama_varian, v.harga 
                        FROM tickets t 
                        JOIN events e ON t.id_event = e.id 
                        LEFT JOIN event_ticket_variants v ON t.id_ticket_variant = v.id
                        WHERE t.order_id = ?");
$stmt->bind_param("s", $order_id);
$stmt->execute();
$ticket = $stmt->get_result()->fetch_assoc();

if (!$ticket) {
    $_SESSION['error'] = "Data transaksi tidak ditemukan.";
    header("Location: index.php");
    exit;
}

// Calculate admin fee and total
$markup_type = $global_settings['admin_markup_type'] ?? 'nominal';
$markup_value = (float)($global_settings['admin_markup_value'] ?? 5000);
$harga_tiket = (float)($ticket['harga'] ?? 0);

if ($markup_type == 'percent') {
    $biaya_admin = $harga_tiket * ($markup_value / 100);
} else {
    $biaya_admin = $markup_value;
}
$total_pembayaran = $harga_tiket + $biaya_admin;

// Auto-check Midtrans payment status if pending
if ($ticket['status'] == 'pending' && !empty($ticket['order_id'])) {
    try {
        $serverKey = defined('MIDTRANS_SERVER_KEY') ? trim(MIDTRANS_SERVER_KEY) : '';
        if (!empty($serverKey) && file_exists(__DIR__ . '/vendor/autoload.php')) {
            require_once __DIR__ . '/vendor/autoload.php';
            \Midtrans\Config::$serverKey = $serverKey;
            \Midtrans\Config::$isProduction = defined('MIDTRANS_IS_PRODUCTION') ? MIDTRANS_IS_PRODUCTION : false;

            $statusRes = \Midtrans\Transaction::status($ticket['order_id']);
            if ($statusRes) {
                $tr_status = $statusRes->transaction_status ?? '';
                $fr_status = $statusRes->fraud_status ?? '';

                $isSuccess = false;
                if ($tr_status == 'capture') {
                    if ($fr_status == 'accept') {
                        $isSuccess = true;
                    }
                } elseif ($tr_status == 'settlement') {
                    $isSuccess = true;
                }

                if ($isSuccess) {
                    $ticket_id = (int)$ticket['id'];
                    $conn->query("UPDATE tickets SET status = 'lunas' WHERE id = $ticket_id");
                    $ticket['status'] = 'lunas';
                    if (file_exists('actions/kirim_email_tiket.php')) {
                        @include_once 'actions/kirim_email_tiket.php';
                    }
                }
            }
        }
    } catch (\Throwable $e) {
        // Ignore if order not yet found on Midtrans
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Halaman Pembayaran - HaloTiket</title>
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
                    }
                }
            }
        }
    </script>

    <!-- Midtrans Snap JS Integration -->
    <?php if (!empty(MIDTRANS_CLIENT_KEY)): ?>
        <script type="text/javascript" src="<?= MIDTRANS_SNAP_URL ?>" data-client-key="<?= MIDTRANS_CLIENT_KEY ?>"></script>
    <?php endif; ?>

    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
</head>
<body class="bg-slate-50 text-slate-800 font-sans antialiased flex flex-col min-h-screen selection:bg-primary selection:text-white">
    <!-- Navbar -->
    <nav class="bg-white/80 backdrop-blur-md shadow-sm sticky top-0 z-50 border-b border-slate-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <a href="index.php" class="flex items-center gap-2 group">
                    <?php if (isset($global_site_logo) && $global_site_logo): ?>
                        <img src="<?= $global_site_logo ?>" alt="Logo" class="h-8 object-contain">
                    <?php else: ?>
                        <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-primary to-secondary flex items-center justify-center text-white font-bold text-sm shadow-md">H</div>
                        <span class="text-xl font-extrabold text-slate-900 tracking-tight">HaloTiket</span>
                    <?php endif; ?>
                </a>
                <div class="flex items-center space-x-4">
                    <a href="user/riwayat_pembelian.php?order_id=<?= urlencode($ticket['order_id']) ?>" class="text-slate-600 hover:text-primary font-medium transition-colors text-sm">Riwayat Tiket &rarr;</a>
                </div>
            </div>
        </div>
    </nav>

    <main class="flex-grow max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-10 w-full">
        
        <!-- Header -->
        <div class="text-center mb-8">
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider mb-3
                <?php
                    if($ticket['status'] == 'lunas') echo 'bg-emerald-100 text-emerald-700 border border-emerald-300';
                    elseif($ticket['status'] == 'scanned') echo 'bg-blue-100 text-blue-700 border border-blue-300';
                    else echo 'bg-amber-100 text-amber-800 border border-amber-300';
                ?>">
                <span class="w-2 h-2 rounded-full <?php if($ticket['status'] == 'lunas' || $ticket['status'] == 'scanned') echo 'bg-emerald-500'; else echo 'bg-amber-500 animate-pulse'; ?>"></span>
                <?php
                    if($ticket['status'] == 'lunas') echo 'Pembayaran Lunas';
                    elseif($ticket['status'] == 'scanned') echo 'Tiket Terverifikasi';
                    else echo 'Menunggu Pembayaran';
                ?>
            </div>
            <h1 class="text-3xl font-extrabold text-slate-900 tracking-tight">Halaman Pembayaran</h1>
            <p class="text-slate-500 text-sm font-medium mt-1">Order ID: <strong class="text-slate-800 font-mono"><?= htmlspecialchars($ticket['order_id']) ?></strong></p>
        </div>

        <?php if ($ticket['status'] == 'lunas' || $ticket['status'] == 'scanned'): ?>
            <!-- Success Box -->
            <div class="bg-gradient-to-r from-emerald-600 to-teal-700 text-white rounded-3xl p-8 shadow-xl mb-8 text-center border border-emerald-500/30">
                <div class="w-16 h-16 rounded-full bg-white/20 backdrop-blur-md flex items-center justify-center mx-auto mb-4 border border-white/30">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-9 w-9 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                    </svg>
                </div>
                <h2 class="text-2xl font-extrabold mb-2">Pembayaran Berhasil!</h2>
                <p class="text-emerald-100 text-sm max-w-md mx-auto mb-6">Terima kasih, pembayaran untuk tiket Anda telah kami terima dan diverifikasi. E-Ticket telah siap digunakan.</p>
                <a href="user/riwayat_pembelian.php?order_id=<?= urlencode($ticket['order_id']) ?>&payment_success=1" class="inline-flex items-center gap-2 bg-white text-slate-900 font-bold px-6 py-3.5 rounded-2xl shadow-lg hover:bg-slate-100 transition-all text-sm">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z" /></svg>
                    Lihat E-Ticket Saya
                </a>
            </div>
        <?php else: ?>

            <!-- Pending Payment Card -->
            <div class="bg-white rounded-[2rem] shadow-xl shadow-slate-200/50 border border-slate-200 overflow-hidden mb-8">
                
                <!-- Event Banner / Summary -->
                <div class="bg-slate-900 text-white p-6 sm:p-8 relative overflow-hidden">
                    <div class="absolute top-0 right-0 w-64 h-64 bg-primary rounded-full mix-blend-screen filter blur-[80px] opacity-30 translate-x-1/2 -translate-y-1/2"></div>
                    <div class="relative z-10">
                        <span class="text-xs font-extrabold uppercase tracking-wider text-primary mb-1 block">Ringkasan Event</span>
                        <h2 class="text-2xl font-extrabold text-white mb-1"><?= htmlspecialchars($ticket['judul']) ?></h2>
                        <p class="text-indigo-200 text-sm font-semibold mb-4"><?= htmlspecialchars($ticket['nama_varian']) ?></p>
                        
                        <div class="flex flex-wrap gap-4 text-xs font-medium text-slate-300">
                            <div class="flex items-center gap-1.5">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                                <?= date('d M Y', strtotime($ticket['tanggal'])) ?>
                            </div>
                            <div class="flex items-center gap-1.5">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                <?= htmlspecialchars($ticket['waktu']) ?> WIB
                            </div>
                            <div class="flex items-center gap-1.5">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                                <?= htmlspecialchars($ticket['lokasi']) ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="p-6 sm:p-8 space-y-6">
                    
                    <!-- Buyer Info -->
                    <div>
                        <h3 class="text-xs font-extrabold uppercase tracking-wider text-slate-400 mb-3">Informasi Pemesan</h3>
                        <div class="bg-slate-50 rounded-2xl p-4 border border-slate-100 grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs">
                            <div>
                                <span class="text-slate-400 font-bold block">Nama Lengkap</span>
                                <span class="text-slate-900 font-bold text-sm"><?= htmlspecialchars($ticket['nama_pembeli']) ?></span>
                            </div>
                            <div>
                                <span class="text-slate-400 font-bold block">Alamat Email</span>
                                <span class="text-slate-900 font-bold text-sm truncate block"><?= htmlspecialchars($ticket['email_pembeli']) ?></span>
                            </div>
                            <div>
                                <span class="text-slate-400 font-bold block">Nomor WhatsApp</span>
                                <span class="text-slate-900 font-bold text-sm"><?= htmlspecialchars($ticket['no_hp']) ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Payment Details Breakdown -->
                    <div>
                        <h3 class="text-xs font-extrabold uppercase tracking-wider text-slate-400 mb-3">Rincian Pembayaran</h3>
                        <div class="space-y-2 text-sm text-slate-600 bg-slate-50 p-4 rounded-2xl border border-slate-100">
                            <div class="flex justify-between">
                                <span>Harga Tiket (<?= htmlspecialchars($ticket['nama_varian']) ?>)</span>
                                <span class="font-bold text-slate-900">Rp <?= number_format($harga_tiket, 0, ',', '.') ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span>Biaya Layanan Platform</span>
                                <span class="font-bold text-slate-900">Rp <?= number_format($biaya_admin, 0, ',', '.') ?></span>
                            </div>
                            <div class="pt-3 border-t border-slate-200 flex justify-between items-center">
                                <span class="font-bold text-slate-900 text-base">Total Tagihan</span>
                                <span class="text-2xl font-extrabold text-primary">Rp <?= number_format($total_pembayaran, 0, ',', '.') ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Action Button -->
                    <div class="pt-2 flex flex-col sm:flex-row gap-3">
                        <button type="button" id="btnPayNow" onclick="processPaymentGateway()" class="w-full sm:flex-1 bg-slate-900 hover:bg-primary text-white font-extrabold py-4 px-6 rounded-2xl transition-all shadow-xl hover:shadow-indigo-500/30 text-center text-base sm:text-lg flex items-center justify-center gap-2 group">
                            <span>Bayar Sekarang</span>
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 group-hover:translate-x-1 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" /></svg>
                        </button>
                    </div>

                    <?php if (defined('MIDTRANS_IS_PRODUCTION') && !MIDTRANS_IS_PRODUCTION): ?>
                        <div class="bg-blue-50 border border-blue-200 rounded-2xl p-4 text-center text-xs text-blue-800 space-y-2">
                            <p class="font-bold">💡 Mode Sandbox Midtrans Aktif</p>
                            <p>Gunakan Simulator Midtrans untuk mencoba pembayaran uji coba:</p>
                            <a href="https://simulator.sandbox.midtrans.com/" target="_blank" class="inline-flex items-center gap-1 font-bold text-primary hover:underline">
                                Midtrans Payment Simulator &rarr;
                            </a>
                        </div>
                    <?php endif; ?>

                </div>
            </div>
        <?php endif; ?>

        <div class="text-center text-xs font-medium text-slate-400 flex items-center justify-center gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>
            Sistem Pembayaran Terenkripsi & Otomatis Verifikasi 24/7
        </div>
    </main>

    <script>
        const snapToken = "<?= htmlspecialchars($ticket['snap_token'] ?? '') ?>";
        const redirectUrl = "<?= htmlspecialchars($ticket['snap_redirect_url'] ?? '') ?>";
        const orderId = "<?= htmlspecialchars($ticket['order_id']) ?>";

        function processPaymentGateway() {
            if (redirectUrl) {
                window.location.href = redirectUrl;
            } else if (typeof snap !== 'undefined' && snapToken) {
                snap.pay(snapToken, {
                    onSuccess: function(result) {
                        window.location.href = 'user/riwayat_pembelian.php?order_id=' + encodeURIComponent(orderId) + '&payment_success=1';
                    },
                    onPending: function(result) {
                        window.location.href = 'user/riwayat_pembelian.php?order_id=' + encodeURIComponent(orderId) + '&payment_pending=1';
                    },
                    onError: function(result) {
                        alert('Pembayaran gagal atau dibatalkan.');
                        window.location.reload();
                    },
                    onClose: function() {
                        window.location.href = 'user/riwayat_pembelian.php?order_id=' + encodeURIComponent(orderId);
                    }
                });
            } else {
                alert('Tautan pembayaran tidak tersedia. Silakan hubungi Customer Service.');
            }
        }
    </script>
</body>
</html>
