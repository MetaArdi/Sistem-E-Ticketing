<?php
// Turn off display of warnings
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
ini_set('display_errors', 0);

require_once __DIR__ . '/../config/koneksi.php';

$autoloadPath = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoloadPath)) {
    require_once $autoloadPath;
}

if (!isset($_GET['token']) || empty($_GET['token'])) {
    die("Token tidak valid.");
}

$token = trim($_GET['token']);

// 1. Cari tiket berdasarkan token & join variant
$stmt = $conn->prepare("SELECT t.*, e.judul, e.tanggal, e.waktu, e.lokasi, e.banner_image, e.tiket_header, v.nama_varian, v.harga, u.nama_lengkap as penyelenggara 
                        FROM tickets t 
                        JOIN events e ON t.id_event = e.id 
                        LEFT JOIN event_ticket_variants v ON t.id_ticket_variant = v.id
                        JOIN users u ON e.id_panitia = u.id
                        WHERE t.token_qr = ?");
$stmt->bind_param("s", $token);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    die("Tiket tidak ditemukan.");
}

$tiket = $result->fetch_assoc();

// 2. Auto-sync Midtrans status jika masih pending
if ($tiket['status'] == 'pending' && !empty($tiket['order_id'])) {
    try {
        $serverKey = defined('MIDTRANS_SERVER_KEY') ? trim(MIDTRANS_SERVER_KEY) : '';
        if (!empty($serverKey)) {
            \Midtrans\Config::$serverKey = $serverKey;
            \Midtrans\Config::$isProduction = defined('MIDTRANS_IS_PRODUCTION') ? MIDTRANS_IS_PRODUCTION : false;

            $statusRes = \Midtrans\Transaction::status($tiket['order_id']);
            if ($statusRes) {
                $tr_status = $statusRes->transaction_status ?? '';
                $fr_status = $statusRes->fraud_status ?? '';
                
                $isPaid = false;
                if ($tr_status == 'capture') {
                    if ($fr_status == 'accept') $isPaid = true;
                } elseif ($tr_status == 'settlement') {
                    $isPaid = true;
                }

                if ($isPaid) {
                    $t_id = (int)$tiket['id'];
                    $conn->query("UPDATE tickets SET status = 'lunas' WHERE id = $t_id");
                    $tiket['status'] = 'lunas';
                }
            }
        }
    } catch (\Throwable $ex) {
        // Suppress
    }
}

if ($tiket['status'] != 'lunas' && $tiket['status'] != 'scanned') {
    die("Tiket belum lunas. Silakan selesaikan pembayaran terlebih dahulu.");
}

// Format Tanggal Bahasa Indonesia
function formatTanggalIndo($dateStr) {
    $days = ['Sun' => 'Minggu', 'Mon' => 'Senin', 'Tue' => 'Selasa', 'Wed' => 'Rabu', 'Thu' => 'Kamis', 'Fri' => 'Jumat', 'Sat' => 'Sabtu'];
    $months = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    $timestamp = strtotime($dateStr);
    if (!$timestamp) return $dateStr;
    $dayName = $days[date('D', $timestamp)] ?? '';
    $dayNum = date('d', $timestamp);
    $monthName = $months[(int)date('n', $timestamp)] ?? '';
    $year = date('Y', $timestamp);
    return "$dayName, $dayNum $monthName $year";
}

// Tentukan Banner Image Event
$eventBannerUrl = '';
if (!empty($tiket['banner_image']) && file_exists(__DIR__ . '/../assets/images/events/' . $tiket['banner_image'])) {
    $eventBannerUrl = BASE_URL . 'assets/images/events/' . $tiket['banner_image'];
} elseif (!empty($tiket['tiket_header']) && file_exists(__DIR__ . '/../assets/images/events/' . $tiket['tiket_header'])) {
    $eventBannerUrl = BASE_URL . 'assets/images/events/' . $tiket['tiket_header'];
} else {
    $eventBannerUrl = 'https://images.unsplash.com/photo-1470225620780-dba8ba36b745?auto=format&fit=crop&w=1200&q=80';
}

$namaVarian = !empty($tiket['nama_varian']) ? strtoupper($tiket['nama_varian']) : 'TIKET REGULER';
$qrCodeUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=400x400&data=' . urlencode($token);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>E-Ticket - <?= htmlspecialchars($tiket['judul']) ?></title>
    <?php if (isset($global_site_favicon) && $global_site_favicon): ?>
        <link rel="icon" href="<?= $global_site_favicon ?>">
    <?php endif; ?>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Outfit', sans-serif;
            background-color: #f1f5f9;
        }
        @media print {
            .no-print {
                display: none !important;
            }
            body {
                background-color: #ffffff !important;
                padding: 0 !important;
            }
            .ticket-card {
                box-shadow: none !important;
                border: 1px solid #cbd5e1 !important;
                margin: 0 auto !important;
                max-width: 100% !important;
            }
        }
    </style>
</head>
<body class="p-4 sm:p-8 min-h-screen flex flex-col items-center justify-center">

    <!-- Top Action Bar (Hidden when printing) -->
    <div class="no-print w-full max-w-4xl flex items-center justify-between mb-6 bg-white p-4 rounded-2xl shadow-sm border border-slate-200">
        <a href="riwayat_pembelian.php?order_id=<?= urlencode($tiket['order_id']) ?>" class="inline-flex items-center gap-2 text-sm font-bold text-slate-700 hover:text-primary transition-colors">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
            Kembali ke Tiket Saya
        </a>
        <button onclick="window.print()" class="bg-[#00c2cb] hover:bg-teal-600 text-white font-extrabold px-6 py-2.5 rounded-xl shadow-md hover:shadow-lg transition-all flex items-center gap-2 text-sm">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" /></svg>
            Cetak / Simpan PDF
        </button>
    </div>

    <!-- MAIN E-TICKET CARD (Sesuai Referensi Gambar) -->
    <div class="ticket-card w-full max-w-4xl bg-white rounded-2xl shadow-2xl border border-slate-200 overflow-hidden relative">
        
        <!-- Header Dark Teal Bar -->
        <div class="bg-[#003846] px-6 sm:px-8 py-5 flex items-center justify-between text-white">
            <div class="flex items-center gap-3">
                <?php if (isset($global_site_logo) && $global_site_logo): ?>
                    <img src="<?= $global_site_logo ?>" alt="Logo" class="h-9 w-auto object-contain">
                <?php else: ?>
                    <div class="w-8 h-8 rounded-lg bg-[#00c2cb] flex items-center justify-center text-white font-black text-base shadow-sm">H</div>
                    <span class="text-xl font-extrabold tracking-tight text-white">HaloTiket</span>
                <?php endif; ?>
            </div>
            <div class="bg-[#00c2cb] text-white px-5 py-1.5 rounded-full text-xs font-black uppercase tracking-widest shadow-sm">
                E-TIKET
            </div>
        </div>

        <!-- Banner Event Image (Diambil dari gambar event yang dipilih) -->
        <div class="w-full h-64 sm:h-80 bg-slate-900 relative overflow-hidden">
            <img src="<?= $eventBannerUrl ?>" alt="Event Banner" class="w-full h-full object-cover">
        </div>

        <!-- Ticket Body Content -->
        <div class="p-6 sm:p-10">
            <!-- Title & Variant Badge -->
            <div class="mb-6">
                <h1 class="text-2xl sm:text-4xl font-extrabold text-slate-900 tracking-tight mb-3">
                    <?= htmlspecialchars($tiket['judul']) ?>
                </h1>
                <div class="inline-block px-4 py-1.5 rounded-xl border-2 border-[#00c2cb] text-[#00c2cb] font-extrabold text-xs sm:text-sm tracking-wider uppercase">
                    <?= htmlspecialchars($namaVarian) ?>
                </div>
            </div>

            <!-- Dotted Line Divider -->
            <div class="border-b-2 border-dashed border-slate-200 w-full my-6"></div>

            <!-- Grid QR Code & Ticket Info -->
            <div class="grid grid-cols-1 md:grid-cols-12 gap-8 items-center">
                
                <!-- Left: QR Code Section -->
                <div class="md:col-span-5 flex flex-col items-center justify-center">
                    <div class="bg-white border-2 border-slate-200 rounded-3xl p-5 shadow-sm flex flex-col items-center justify-center">
                        <img src="<?= $qrCodeUrl ?>" alt="QR Code Tiket" class="w-52 h-52 sm:w-60 sm:h-60 object-contain rounded-xl">
                        <p class="text-xs font-bold text-slate-400 mt-4 text-center">
                            Pindai kode QR ini di pintu masuk venue
                        </p>
                    </div>
                </div>

                <!-- Right: Information Details -->
                <div class="md:col-span-7 space-y-6">
                    <div>
                        <span class="block text-xs font-black text-[#00a3ad] uppercase tracking-wider mb-1">PEMEGANG TIKET</span>
                        <span class="text-lg sm:text-xl font-extrabold text-slate-900"><?= htmlspecialchars($tiket['nama_pembeli']) ?></span>
                    </div>

                    <div>
                        <span class="block text-xs font-black text-[#00a3ad] uppercase tracking-wider mb-1">EMAIL</span>
                        <span class="text-sm font-bold text-slate-700"><?= htmlspecialchars($tiket['email_pembeli']) ?></span>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <span class="block text-xs font-black text-[#00a3ad] uppercase tracking-wider mb-1">TANGGAL</span>
                            <span class="text-sm sm:text-base font-bold text-slate-900"><?= formatTanggalIndo($tiket['tanggal']) ?></span>
                        </div>
                        <div>
                            <span class="block text-xs font-black text-[#00a3ad] uppercase tracking-wider mb-1">WAKTU</span>
                            <span class="text-sm sm:text-base font-bold text-slate-900"><?= date('H:i', strtotime($tiket['waktu'])) ?></span>
                        </div>
                    </div>

                    <div class="pt-4 border-t border-slate-100">
                        <span class="text-xs text-slate-400 font-mono tracking-wider">ID Tiket: <?= htmlspecialchars($token) ?></span>
                    </div>
                </div>

            </div>

        </div>

    </div>

</body>
</html>
