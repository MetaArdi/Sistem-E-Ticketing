<?php
session_start();
require_once 'config/koneksi.php';

if (!isset($_GET['id'])) {
    header("Location: index.php");
    exit;
}

$id_event = (int)$_GET['id'];
$stmt = $conn->prepare("SELECT * FROM events WHERE id = ? AND status_approval = 'approved'");
$stmt->bind_param("i", $id_event);
$stmt->execute();
$event = $stmt->get_result()->fetch_assoc();

if (!$event || $event['stok'] < 1 || strtotime($event['tanggal']) < strtotime(date('Y-m-d'))) {
    $_SESSION['error'] = "Tiket tidak tersedia atau event sudah berlalu.";
    header("Location: detail_event.php?id=" . $id_event);
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout Tiket - HaloTiket</title>
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

    <!-- Custom Stylesheet -->
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
                    <a href="detail_event.php?id=<?= $id_event ?>" class="text-slate-600 hover:text-primary font-medium transition-colors text-sm">&larr; Batal</a>
                </div>
            </div>
        </div>
    </nav>

    <main class="flex-grow max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-12 w-full">
        
        <div class="text-center mb-10">
            <h1 class="text-3xl font-extrabold text-slate-900 tracking-tight mb-2">Selesaikan Pesanan Anda</h1>
            <p class="text-slate-500 font-medium">Pastikan data yang Anda masukkan benar untuk pengiriman E-Ticket.</p>
        </div>

        <div class="bg-white rounded-[2rem] shadow-xl shadow-slate-200/40 border border-slate-100 overflow-hidden">
            <!-- Event Summary Banner -->
            <div class="bg-slate-900 p-8 text-white relative overflow-hidden">
                <div class="absolute inset-0 bg-[url('https://www.transparenttextures.com/patterns/cubes.png')] opacity-10 mix-blend-overlay"></div>
                <div class="absolute top-0 right-0 w-64 h-64 bg-primary rounded-full mix-blend-screen filter blur-[80px] opacity-40 translate-x-1/2 -translate-y-1/2"></div>
                
                <div class="relative z-10">
                    <p class="text-indigo-300 text-xs font-bold uppercase tracking-wider mb-2">Event yang dipilih</p>
                    <h2 class="text-2xl font-bold mb-4"><?= htmlspecialchars($event['judul']) ?></h2>
                    
                    <div class="flex flex-wrap gap-4 text-sm font-medium text-slate-300">
                        <div class="flex items-center gap-1.5">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                            <?= date('d M Y', strtotime($event['tanggal'])) ?>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                            <?= $event['waktu'] ?> WIB
                        </div>
                    </div>
                </div>
            </div>

            <!-- Form Section -->
            <div class="p-8 sm:p-12">
                <form action="proses_checkout.php" method="POST" class="space-y-6">
                    <input type="hidden" name="id_event" value="<?= $event['id'] ?>">
                    
                    <div class="space-y-1">
                        <label class="text-sm font-bold text-slate-700">Nama Lengkap</label>
                        <input type="text" name="nama" required placeholder="Sesuai KTP" class="w-full px-5 py-3.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary focus:bg-white transition-all text-sm">
                    </div>
                    
                    <div class="space-y-1">
                        <label class="text-sm font-bold text-slate-700">Alamat Email</label>
                        <input type="email" name="email" required placeholder="E-ticket akan dikirim ke email ini" class="w-full px-5 py-3.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary focus:bg-white transition-all text-sm">
                    </div>
                    
                    <div class="space-y-1">
                        <label class="text-sm font-bold text-slate-700">Nomor WhatsApp</label>
                        <input type="text" name="no_hp" required placeholder="081234567890" class="w-full px-5 py-3.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary focus:bg-white transition-all text-sm">
                    </div>
                    
                    <div class="pt-6 mt-8 border-t border-slate-100 flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-slate-500 mb-1">Total Pembayaran</p>
                            <p class="text-3xl font-extrabold text-slate-900">Rp <?= number_format($event['harga'], 0, ',', '.') ?></p>
                        </div>
                        <button type="submit" class="bg-slate-900 hover:bg-primary text-white font-bold py-4 px-8 rounded-2xl transition-all duration-300 shadow-xl shadow-slate-900/20 hover:shadow-indigo-500/30 hover:-translate-y-1 text-lg flex items-center gap-2">
                            Bayar 
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" /></svg>
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <div class="mt-8 text-center flex items-center justify-center gap-2 text-sm font-medium text-slate-400">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>
            Pembayaran Aman dan Terenkripsi
        </div>
    </main>
</body>
</html>
