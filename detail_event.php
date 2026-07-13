<?php
session_start();
require_once 'config/koneksi.php';

if (!isset($_GET['id'])) {
    header("Location: index.php");
    exit;
}

$id = (int)$_GET['id'];
$stmt = $conn->prepare("SELECT e.*, u.nama_lengkap as panitia FROM events e JOIN users u ON e.id_panitia = u.id WHERE e.id = ? AND e.status_approval = 'approved'");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    header("Location: index.php");
    exit;
}
$event = $result->fetch_assoc();

// Cek varian tiket yang aktif
$stmt_var = $conn->prepare("SELECT * FROM event_ticket_variants WHERE id_event = ? AND tgl_mulai <= NOW() AND tgl_selesai >= NOW() AND sisa_stok > 0 ORDER BY harga ASC");
$stmt_var->bind_param("i", $id);
$stmt_var->execute();
$variants = $stmt_var->get_result();
$variants_data = [];
while($v = $variants->fetch_assoc()){
    $variants_data[] = $v;
}

// Cek apakah ada flash message
$error = isset($_SESSION['error']) ? $_SESSION['error'] : null;
unset($_SESSION['error']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($event['judul']) ?> - HaloTiket</title>
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
                    <a href="index.php" class="text-slate-600 hover:text-primary font-medium transition-colors text-sm">&larr; Kembali</a>
                </div>
            </div>
        </div>
    </nav>

    <main class="flex-grow max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 w-full">
        <?php if($error): ?>
            <div class="bg-red-50 border border-red-100 text-red-600 px-6 py-4 rounded-2xl mb-8 font-medium shadow-sm flex items-center gap-3 animate-pulse">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                <?= $error ?>
            </div>
        <?php endif; ?>

        <div class="bg-white rounded-[2rem] shadow-xl shadow-slate-200/40 overflow-hidden border border-slate-100">
            <div class="grid grid-cols-1 lg:grid-cols-2">
                <!-- Image Section -->
                <div class="relative h-64 sm:h-96 lg:h-auto overflow-hidden bg-slate-100">
                    <?php if($event['banner_image']): ?>
                        <img src="assets/images/events/<?= $event['banner_image'] ?>" alt="<?= htmlspecialchars($event['judul']) ?>" class="absolute inset-0 w-full h-full object-cover">
                    <?php else: ?>
                        <div class="absolute inset-0 flex flex-col items-center justify-center text-slate-400">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 mb-2 opacity-50" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                            <span class="font-medium">No Image Available</span>
                        </div>
                    <?php endif; ?>
                    <div class="absolute inset-0 bg-gradient-to-t from-slate-900/60 to-transparent lg:hidden"></div>
                </div>

                <!-- Content Section -->
                <div class="p-8 sm:p-12 lg:p-16 flex flex-col relative">
                    <div class="mb-8">
                        <div class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider mb-4 <?= $event['stok'] > 0 ? 'bg-indigo-50 text-primary' : 'bg-red-50 text-red-500' ?>">
                            <?= $event['stok'] > 0 ? 'Tiket Tersedia' : 'Sold Out' ?>
                        </div>
                        <h1 class="text-3xl sm:text-4xl lg:text-5xl font-extrabold text-slate-900 tracking-tight leading-tight mb-4"><?= htmlspecialchars($event['judul']) ?></h1>
                        <p class="text-slate-500 text-lg leading-relaxed"><?= nl2br(htmlspecialchars($event['deskripsi'])) ?></p>
                    </div>

                    <div class="grid grid-cols-2 gap-6 mb-10">
                        <div class="bg-slate-50 p-4 rounded-2xl border border-slate-100">
                            <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Jadwal</p>
                            <p class="font-bold text-slate-900"><?= date('d F Y', strtotime($event['tanggal'])) ?></p>
                            <p class="text-sm font-medium text-slate-500">
                                <?= substr($event['waktu'], 0, 5) ?><?= $event['waktu_selesai'] ? ' - ' . substr($event['waktu_selesai'], 0, 5) : '' ?> WIB
                            </p>
                        </div>
                        <div class="bg-slate-50 p-4 rounded-2xl border border-slate-100">
                            <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Penyelenggara</p>
                            <p class="font-bold text-slate-900"><?= htmlspecialchars($event['nama_vendor'] ?: $event['panitia']) ?></p>
                        </div>
                        <div class="col-span-2 bg-slate-50 p-4 rounded-2xl border border-slate-100 flex items-start gap-3">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-slate-400 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                            <div>
                                <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Lokasi</p>
                                <?php if($event['link_gmaps']): ?>
                                    <a href="<?= htmlspecialchars($event['link_gmaps']) ?>" target="_blank" class="font-bold text-primary hover:text-indigo-700 leading-tight flex items-center gap-1 transition-colors hover:underline">
                                        <?= htmlspecialchars($event['lokasi']) ?>
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" /></svg>
                                    </a>
                                <?php else: ?>
                                    <p class="font-bold text-slate-900 leading-tight"><?= htmlspecialchars($event['lokasi']) ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="mt-auto">
                        <div class="flex items-end justify-between mb-6">
                            <div>
                                <p class="text-sm font-medium text-slate-500 mb-1">Mulai Dari</p>
                                <div class="text-4xl font-extrabold text-slate-900 tracking-tight">Rp <?= number_format($event['harga'], 0, ',', '.') ?></div>
                            </div>
                            <div class="text-right">
                                <p class="text-sm font-medium text-slate-500 mb-1">Sisa Kuota</p>
                                <div class="text-xl font-bold <?= $event['stok'] > 10 ? 'text-emerald-500' : 'text-orange-500' ?>"><?= $event['stok'] ?> <span class="text-sm font-normal text-slate-400">tiket</span></div>
                            </div>
                        </div>

                        <form action="checkout.php" method="POST" id="ticketForm">
                            <input type="hidden" name="id" value="<?= $event['id'] ?>">
                            <div class="mb-6">
                                <p class="text-sm font-bold text-slate-700 mb-3">Pilih Jenis Tiket (Aktif)</p>
                                <?php if(count($variants_data) > 0): ?>
                                    <div class="space-y-3">
                                        <?php foreach($variants_data as $index => $v): ?>
                                        <label class="relative flex cursor-pointer rounded-xl border p-4 shadow-sm transition-all hover:bg-slate-50 ticket-variant-label <?= $index === 0 ? 'bg-indigo-50/50 border-primary ring-1 ring-primary' : 'bg-white border-slate-200' ?>" onclick="selectVariant(this)">
                                            <input type="radio" name="id_ticket_variant" value="<?= $v['id'] ?>" class="hidden" <?= $index === 0 ? 'checked' : '' ?> required>
                                            <div class="flex w-full items-center justify-between">
                                                <div>
                                                    <p class="text-sm font-bold text-slate-900"><?= htmlspecialchars($v['nama_varian']) ?></p>
                                                    <div class="flex items-center gap-2 mt-1">
                                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-indigo-100 text-indigo-700">Kapasitas: <?= htmlspecialchars($v['tipe_paket']) ?></span>
                                                        <span class="text-xs text-slate-500">Sisa: <?= $v['sisa_stok'] ?> tiket</span>
                                                    </div>
                                                </div>
                                                <div class="text-right pl-3">
                                                    <p class="text-sm font-bold text-primary">Rp <?= number_format($v['harga'], 0, ',', '.') ?></p>
                                                </div>
                                            </div>
                                        </label>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="bg-slate-100 border border-slate-200 text-slate-500 p-4 rounded-xl text-sm font-medium flex items-center gap-2">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                        Saat ini tidak ada tiket yang dibuka atau tiket telah habis.
                                    </div>
                                <?php endif; ?>
                            </div>

                            <?php if(count($variants_data) > 0 && strtotime($event['tanggal']) >= strtotime(date('Y-m-d'))): ?>
                                <button type="submit" class="block w-full bg-slate-900 hover:bg-primary text-white text-center font-bold py-4 px-6 rounded-2xl transition-all duration-300 shadow-xl shadow-slate-900/20 hover:shadow-indigo-500/30 hover:-translate-y-1 text-lg">
                                    Pesan Tiket Sekarang
                                </button>
                            <?php else: ?>
                                <button disabled type="button" class="block w-full bg-slate-100 text-slate-400 text-center font-bold py-4 px-6 rounded-2xl cursor-not-allowed border border-slate-200 text-lg">
                                    Tiket Tidak Tersedia
                                </button>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </main>
    <script>
        function selectVariant(element) {
            const labels = document.querySelectorAll('.ticket-variant-label');
            labels.forEach(l => {
                l.classList.remove('bg-indigo-50/50', 'border-primary', 'ring-1', 'ring-primary');
                l.classList.add('bg-white', 'border-slate-200');
            });
            element.classList.remove('bg-white', 'border-slate-200');
            element.classList.add('bg-indigo-50/50', 'border-primary', 'ring-1', 'ring-primary');
            element.querySelector('input[type="radio"]').checked = true;
        }
    </script>
</body>
</html>
