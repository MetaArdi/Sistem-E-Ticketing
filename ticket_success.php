<?php
session_start();
require_once 'config/koneksi.php';
require_once 'vendor/autoload.php';

// Konfigurasi Midtrans dari koneksi.php
\Midtrans\Config::$serverKey = MIDTRANS_SERVER_KEY;
\Midtrans\Config::$isProduction = MIDTRANS_IS_PRODUCTION;
\Midtrans\Config::$isSanitized = true;
\Midtrans\Config::$is3ds = true;

$order_id = $_GET['order_id'] ?? null;

if (!$order_id) {
    die("Akses ditolak. Order ID tidak ditemukan.");
}

// Ambil data tiket
$stmt = $conn->prepare("SELECT t.*, e.judul, e.tanggal, e.waktu, v.nama_varian 
                        FROM tickets t 
                        JOIN events e ON t.id_event = e.id 
                        JOIN event_ticket_variants v ON t.id_ticket_variant = v.id 
                        WHERE t.order_id = ?");
$stmt->bind_param("s", $order_id);
$stmt->execute();
$tiket = $stmt->get_result()->fetch_assoc();

if (!$tiket) {
    die("Tiket tidak ditemukan.");
}

$transaction_status = $tiket['status'];
$midtrans_status = 'pending'; // Default fallback

try {
    $status = \Midtrans\Transaction::status($order_id);
    $midtrans_status = $status->transaction_status;
    
    // Update status di lokal jika berbeda
    if (($midtrans_status == 'settlement' || $midtrans_status == 'capture') && $tiket['status'] == 'pending') {
        $transaction_status = 'lunas';
        $conn->query("UPDATE tickets SET status = 'lunas' WHERE order_id = '$order_id'");
    } elseif ($midtrans_status == 'expire' || $midtrans_status == 'cancel' || $midtrans_status == 'deny') {
        $transaction_status = 'batal';
        $conn->query("UPDATE tickets SET status = 'batal' WHERE order_id = '$order_id'");
        // Revert Stok
        $conn->query("UPDATE event_ticket_variants SET sisa_stok = sisa_stok + 1 WHERE id = {$tiket['id_ticket_variant']}");
        $conn->query("UPDATE events SET stok = stok + 1 WHERE id = {$tiket['id_event']}");
    }
} catch (Exception $e) {
    // Jika API Midtrans gagal diakses (misal timeout), gunakan status lokal saja.
}

$is_success = ($transaction_status == 'lunas');

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Status Pembayaran - HaloTiket</title>
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
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="bg-slate-50 text-slate-800 font-sans antialiased min-h-screen flex flex-col justify-center items-center p-4">
    <div class="max-w-md w-full bg-white rounded-3xl shadow-xl border border-slate-100 overflow-hidden text-center p-8">
        
        <?php if ($is_success): ?>
            <!-- Success Status -->
            <div class="w-24 h-24 bg-emerald-100 text-emerald-500 rounded-full flex items-center justify-center mx-auto mb-6 shadow-sm">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
            </div>
            <h1 class="text-3xl font-extrabold text-slate-900 mb-2">Pembayaran Berhasil!</h1>
            <p class="text-slate-500 font-medium mb-6">Terima kasih, <b><?= htmlspecialchars($tiket['nama_pembeli']) ?></b>. Pembayaran tiket Anda telah kami terima.</p>
            
            <div class="bg-slate-50 border border-slate-200 rounded-xl p-4 text-left mb-6 space-y-2">
                <div class="flex justify-between text-sm">
                    <span class="text-slate-500">Order ID</span>
                    <span class="font-bold text-slate-800"><?= $tiket['order_id'] ?></span>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="text-slate-500">Event</span>
                    <span class="font-bold text-slate-800"><?= htmlspecialchars($tiket['judul']) ?></span>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="text-slate-500">Varian</span>
                    <span class="font-bold text-slate-800"><?= htmlspecialchars($tiket['nama_varian']) ?></span>
                </div>
            </div>

            <div class="space-y-3">
                <!-- Tombol Download PDF -->
                <a href="user/download_tiket.php?token=<?= $tiket['token_qr'] ?>" target="_blank" class="w-full flex items-center justify-center gap-2 bg-slate-900 hover:bg-slate-800 text-white font-bold py-3.5 px-6 rounded-xl transition-all shadow-md">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                    Download PDF Tiket
                </a>
                
                <!-- Tombol Kirim Email -->
                <button onclick="kirimEmailTiket('<?= $tiket['token_qr'] ?>')" id="btnEmail" class="w-full flex items-center justify-center gap-2 bg-white hover:bg-slate-50 border border-slate-200 text-slate-700 font-bold py-3.5 px-6 rounded-xl transition-all shadow-sm">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>
                    Kirim Ulang ke Email
                </button>
                
                <a href="index.php" class="block w-full text-center text-sm font-bold text-slate-500 hover:text-primary transition-colors pt-2">Kembali ke Beranda</a>
            </div>

        <?php elseif ($transaction_status == 'pending'): ?>
            <!-- Pending Status -->
            <div class="w-24 h-24 bg-amber-100 text-amber-500 rounded-full flex items-center justify-center mx-auto mb-6 shadow-sm">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
            </div>
            <h1 class="text-3xl font-extrabold text-slate-900 mb-2">Menunggu Pembayaran</h1>
            <p class="text-slate-500 font-medium mb-6">Silakan selesaikan pembayaran Anda atau tunggu beberapa saat jika Anda sudah membayar.</p>
            <a href="" class="inline-block bg-primary text-white font-bold px-6 py-3 rounded-xl hover:bg-blue-600 transition-colors">Refresh Status</a>
            <div class="mt-4">
                <a href="index.php" class="text-sm font-bold text-slate-500 hover:text-primary transition-colors">Kembali ke Beranda</a>
            </div>

        <?php else: ?>
            <!-- Failed/Canceled Status -->
            <div class="w-24 h-24 bg-red-100 text-red-500 rounded-full flex items-center justify-center mx-auto mb-6 shadow-sm">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
            </div>
            <h1 class="text-3xl font-extrabold text-slate-900 mb-2">Pembayaran Batal</h1>
            <p class="text-slate-500 font-medium mb-6">Waktu pembayaran telah habis atau dibatalkan oleh pengguna.</p>
            <a href="index.php" class="inline-block bg-slate-900 text-white font-bold px-6 py-3 rounded-xl hover:bg-slate-800 transition-colors">Pesan Tiket Baru</a>
        <?php endif; ?>
        
    </div>

    <script>
        function kirimEmailTiket(token) {
            const btn = document.getElementById('btnEmail');
            const originalContent = btn.innerHTML;
            btn.innerHTML = '<svg class="animate-spin h-5 w-5 text-slate-500 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Mengirim...';
            btn.disabled = true;

            fetch('actions/kirim_email_tiket.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'token=' + encodeURIComponent(token)
            })
            .then(response => response.json())
            .then(data => {
                btn.innerHTML = originalContent;
                btn.disabled = false;
                if (data.status === 'success') {
                    Swal.fire({
                        icon: 'success',
                        title: 'Terkirim!',
                        text: 'Tiket berhasil dikirim ke email Anda.',
                        confirmButtonColor: '#00c2cb'
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Oops...',
                        text: data.message || 'Gagal mengirim email.',
                        confirmButtonColor: '#ef4444'
                    });
                }
            })
            .catch(error => {
                btn.innerHTML = originalContent;
                btn.disabled = false;
                Swal.fire({
                    icon: 'error',
                    title: 'Kesalahan Sistem',
                    text: 'Terjadi kesalahan saat memproses permintaan.',
                    confirmButtonColor: '#ef4444'
                });
            });
        }
    </script>
</body>
</html>
