<?php
session_start();
require_once 'config/koneksi.php';

if (!isset($_GET['id'])) {
    header("Location: index.php");
    exit;
}

$id = (int) $_GET['id'];
$stmt = $conn->prepare("SELECT e.*, u.nama_lengkap as panitia FROM events e JOIN users u ON e.id_panitia = u.id WHERE e.id = ? AND e.status_approval = 'approved'");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    header("Location: index.php");
    exit;
}
$event = $result->fetch_assoc();

// War Ticket Status Check
$is_war_active = false;
$war_start_timestamp = 0;
if (!empty($event['is_war_ticket']) && $event['is_war_ticket'] == 1 && !empty($event['war_start_time'])) {
    $war_start_timestamp = strtotime($event['war_start_time']);
    if ($war_start_timestamp > time()) {
        $is_war_active = true;
    }
}

// Cek varian tiket yang aktif
$stmt_var = $conn->prepare("SELECT * FROM event_ticket_variants WHERE id_event = ? AND tgl_mulai <= NOW() AND tgl_selesai >= NOW() AND sisa_stok > 0 ORDER BY harga ASC");
$stmt_var->bind_param("i", $id);
$stmt_var->execute();
$variants = $stmt_var->get_result();
$variants_data = [];
while ($v = $variants->fetch_assoc()) {
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
    
    <!-- PWA Meta Tags & Manifest -->
    <link rel="manifest" href="<?= BASE_URL ?>manifest.json.php">
    <meta name="theme-color" content="#0f1c3f">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="HaloTiket">
    <!-- Icons & PWA Logo -->
    <?php if (isset($global_site_favicon) && $global_site_favicon): ?>
        <link rel="shortcut icon" href="<?= $global_site_favicon ?>">
    <?php endif; ?>
    <link rel="icon" type="image/png" sizes="192x192" href="<?= BASE_URL ?>assets/images/pwa/icon-192.png">
    <link rel="icon" type="image/png" sizes="512x512" href="<?= BASE_URL ?>assets/images/pwa/icon-512.png">
    <link rel="apple-touch-icon" sizes="192x192" href="<?= BASE_URL ?>assets/images/pwa/icon-192.png">

    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function() {
                navigator.serviceWorker.register('<?= BASE_URL ?>sw.js');
            });
        }
    </script>

    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">
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

<body
    class="bg-slate-50 text-slate-800 font-sans antialiased flex flex-col min-h-screen selection:bg-primary selection:text-white">
    <!-- Navbar -->
    <nav class="bg-white/80 backdrop-blur-md shadow-sm sticky top-0 z-50 border-b border-slate-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <a href="index.php" class="flex items-center gap-2 group">
                    <?php if (isset($global_site_logo) && $global_site_logo): ?>
                        <img src="<?= $global_site_logo ?>" alt="Logo" class="h-8 object-contain">
                    <?php else: ?>
                        <div
                            class="w-8 h-8 rounded-lg bg-gradient-to-br from-primary to-secondary flex items-center justify-center text-white font-bold text-sm shadow-md">
                            H</div>
                        <span class="text-xl font-extrabold text-slate-900 tracking-tight">HaloTiket</span>
                    <?php endif; ?>
                </a>
                <div class="flex items-center space-x-4">
                    <a href="index.php" class="inline-flex items-center gap-2 text-xs sm:text-sm font-bold text-slate-600 hover:text-primary transition-all bg-slate-100/90 hover:bg-slate-200/80 px-4 py-2 rounded-full border border-slate-200 group shadow-sm active:scale-95">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-slate-400 group-hover:text-primary group-hover:-translate-x-0.5 transition-all" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                        </svg>
                        <span>Kembali</span>
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <main class="flex-grow max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 w-full">
        <?php if ($error): ?>
            <div
                class="bg-red-50 border border-red-100 text-red-600 px-6 py-4 rounded-2xl mb-8 font-medium shadow-sm flex items-center gap-3 animate-pulse">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 shrink-0" fill="none" viewBox="0 0 24 24"
                    stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
                <?= $error ?>
            </div>
        <?php endif; ?>

        <div class="bg-white rounded-[2rem] shadow-xl shadow-slate-200/40 overflow-hidden border border-slate-100">
            <div class="grid grid-cols-1 lg:grid-cols-2">
                <!-- Image Section -->
                <div class="relative h-64 sm:h-96 lg:h-auto overflow-hidden bg-slate-100">
                    <?php if ($event['banner_image']): ?>
                        <img src="<?= BASE_URL ?>assets/images/events/<?= $event['banner_image'] ?>"
                            alt="<?= htmlspecialchars($event['judul']) ?>"
                            class="absolute inset-0 w-full h-full object-cover">
                    <?php else: ?>
                        <div class="absolute inset-0 flex flex-col items-center justify-center text-slate-400">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 mb-2 opacity-50" fill="none"
                                viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1"
                                    d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                            <span class="font-medium">No Image Available</span>
                        </div>
                    <?php endif; ?>
                    <div class="absolute inset-0 bg-gradient-to-t from-slate-900/60 to-transparent lg:hidden"></div>
                </div>

                <!-- Content Section -->
                <div class="p-8 sm:p-12 lg:p-16 flex flex-col relative">
                    <div class="mb-8">
                        <div class="flex flex-wrap items-center gap-2 mb-4">
                            <div class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider <?= $event['stok'] > 0 ? 'bg-indigo-50 text-primary' : 'bg-red-50 text-red-500' ?>">
                                <?= $event['stok'] > 0 ? 'Tiket Tersedia' : 'Sold Out' ?>
                            </div>
                            <?php if (!empty($event['kategori'])): ?>
                                <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-slate-100 text-slate-700 border border-slate-200/60">
                                    <svg class="w-3.5 h-3.5 text-primary shrink-0" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round">
                                        <?= getCategoryIconSvg('', $event['kategori']) ?>
                                    </svg>
                                    <?= htmlspecialchars($event['kategori']) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <h1
                            class="text-3xl sm:text-4xl lg:text-5xl font-extrabold text-slate-900 tracking-tight leading-tight mb-4">
                            <?= htmlspecialchars($event['judul']) ?></h1>
                        <p class="text-slate-500 text-lg leading-relaxed">
                            <?= nl2br(htmlspecialchars($event['deskripsi'])) ?></p>
                    </div>

                    <div class="grid grid-cols-2 gap-6 mb-10">
                        <div class="bg-slate-50 p-4 rounded-2xl border border-slate-100">
                            <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Jadwal</p>
                            <p class="font-bold text-slate-900"><?= date('d F Y', strtotime($event['tanggal'])) ?></p>
                            <p class="text-sm font-medium text-slate-500">
                                <?= substr($event['waktu'], 0, 5) ?><?= $event['waktu_selesai'] ? ' - ' . substr($event['waktu_selesai'], 0, 5) : '' ?>
                                WIB
                            </p>
                        </div>
                        <div class="bg-slate-50 p-4 rounded-2xl border border-slate-100">
                            <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Penyelenggara</p>
                            <p class="font-bold text-slate-900">
                                <?= htmlspecialchars($event['nama_vendor'] ?: $event['panitia']) ?></p>
                        </div>
                        <div
                            class="col-span-2 bg-slate-50 p-4 rounded-2xl border border-slate-100 flex items-start gap-3">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-slate-400 shrink-0 mt-0.5"
                                fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            <div>
                                <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Lokasi</p>
                                <?php if ($event['link_gmaps']): ?>
                                    <a href="<?= htmlspecialchars($event['link_gmaps']) ?>" target="_blank"
                                        class="font-bold text-primary hover:text-indigo-700 leading-tight flex items-center gap-1 transition-colors hover:underline">
                                        <?= htmlspecialchars($event['lokasi']) ?>
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none"
                                            viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                        </svg>
                                    </a>
                                <?php else: ?>
                                    <p class="font-bold text-slate-900 leading-tight">
                                        <?= htmlspecialchars($event['lokasi']) ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="mt-auto">
                        <div class="flex items-end justify-between mb-6">
                            <div>
                                <p class="text-sm font-medium text-slate-500 mb-1">Mulai Dari</p>
                                <div class="text-4xl font-extrabold text-slate-900 tracking-tight">Rp
                                    <?= number_format($event['harga'], 0, ',', '.') ?></div>
                            </div>
                            <div class="text-right">
                                <p class="text-sm font-medium text-slate-500 mb-1">Sisa Kuota</p>
                                <div
                                    class="text-xl font-bold <?= $event['stok'] > 10 ? 'text-emerald-500' : 'text-orange-500' ?>">
                                    <?= $event['stok'] ?> <span class="text-sm font-normal text-slate-400">tiket</span>
                                </div>
                            </div>
                        </div>

                        <?php if ($is_war_active): ?>
                            <div class="mb-6 p-5 bg-gradient-to-r from-amber-500 to-orange-600 rounded-2xl text-white shadow-lg shadow-orange-500/20 text-center relative overflow-hidden">
                                <div class="relative z-10">
                                    <span class="inline-block px-3 py-1 bg-white/20 backdrop-blur-md text-white text-[11px] font-extrabold uppercase tracking-widest rounded-full mb-2">⚡ WAR TIKET ONGOING</span>
                                    <h4 class="text-base font-extrabold mb-1">Penjualan Tiket Dibuka Dalam:</h4>
                                    <div id="warCountdown" class="text-3xl font-black tracking-tight font-mono my-2 text-yellow-300">00 : 00 : 00</div>
                                    <p class="text-xs text-amber-100 font-medium">Harap bersiap sebelum hitung mundur selesai.</p>
                                </div>
                            </div>
                        <?php endif; ?>

                        <form action="checkout.php" method="POST" id="ticketForm">
                            <input type="hidden" name="id" value="<?= $event['id'] ?>">
                            <div class="mb-6">
                                <p class="text-sm font-bold text-slate-700 mb-3">Pilih Jenis Tiket (Aktif)</p>
                                <?php if (count($variants_data) > 0): ?>
                                    <div class="space-y-3">
                                        <?php foreach ($variants_data as $index => $v): ?>
                                            <label
                                                class="relative flex cursor-pointer rounded-xl border p-4 shadow-sm transition-all hover:bg-slate-50 ticket-variant-label <?= $index === 0 ? 'bg-indigo-50/50 border-primary ring-1 ring-primary' : 'bg-white border-slate-200' ?>"
                                                onclick="selectVariant(this)">
                                                <input type="radio" name="id_ticket_variant" value="<?= $v['id'] ?>"
                                                    class="hidden" <?= $index === 0 ? 'checked' : '' ?> required>
                                                <div class="flex w-full items-center justify-between">
                                                    <div>
                                                        <p class="text-sm font-bold text-slate-900">
                                                            <?= htmlspecialchars($v['nama_varian']) ?></p>
                                                        <div class="flex items-center gap-2 mt-1">
                                                            <span
                                                                class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-indigo-100 text-indigo-700">Kapasitas:
                                                                <?= htmlspecialchars($v['tipe_paket']) ?></span>
                                                            <span class="text-xs text-slate-500">Sisa: <?= $v['sisa_stok'] ?>
                                                                tiket</span>
                                                        </div>
                                                    </div>
                                                    <div class="text-right pl-3">
                                                        <p class="text-sm font-bold text-primary">Rp
                                                            <?= number_format($v['harga'], 0, ',', '.') ?></p>
                                                    </div>
                                                </div>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div
                                        class="bg-slate-100 border border-slate-200 text-slate-500 p-4 rounded-xl text-sm font-medium flex items-center gap-2">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none"
                                            viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        Saat ini tidak ada tiket yang dibuka atau tiket telah habis.
                                    </div>
                                <?php endif; ?>
                            </div>

                            <?php if ($is_war_active): ?>
                                <button disabled id="warSubmitBtn" type="button"
                                    class="block w-full bg-slate-300 text-slate-600 text-center font-extrabold py-4 px-6 rounded-2xl cursor-not-allowed text-lg transition-all shadow-inner">
                                    ⚡ War Ticket Belum Dimulai
                                </button>
                            <?php elseif (count($variants_data) > 0 && strtotime($event['tanggal']) >= strtotime(date('Y-m-d'))): ?>
                                <button type="submit"
                                    class="block w-full bg-slate-900 hover:bg-primary text-white text-center font-bold py-4 px-6 rounded-2xl transition-all duration-300 shadow-xl shadow-slate-900/20 hover:shadow-indigo-500/30 hover:-translate-y-1 text-lg">
                                    Pesan Tiket Sekarang
                                </button>
                            <?php else: ?>
                                <button disabled type="button"
                                    class="block w-full bg-slate-100 text-slate-400 text-center font-bold py-4 px-6 rounded-2xl cursor-not-allowed border border-slate-200 text-lg">
                                    Tiket Tidak Tersedia
                                </button>
                            <?php endif; ?>
                        </form>


                        <!-- Customer Service Section -->
                        <div class="mt-8 pt-6 border-t border-slate-100">
                            <p class="text-sm font-bold text-slate-700 mb-4">Butuh Bantuan? Hubungi CS</p>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <?php if(isset($global_contact_cs) && !empty($global_contact_cs)): ?>
                                    <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $global_contact_cs) ?>?text=Halo%20Admin%20HaloTiket,%20saya%20mengalami%20kendala%20sistem/pembayaran%20pada%20event%20<?= urlencode($event['judul']) ?>" target="_blank" class="flex items-center gap-3 p-3 rounded-xl border border-slate-200 bg-white hover:border-emerald-500 hover:shadow-sm hover:shadow-emerald-500/10 transition-all group">
                                        <div class="w-10 h-10 rounded-lg bg-emerald-50 text-emerald-500 flex items-center justify-center shrink-0 group-hover:bg-emerald-500 group-hover:text-white transition-colors">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path></svg>
                                        </div>
                                        <div>
                                            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wide">Kendala Sistem / Bayar</p>
                                            <p class="text-sm font-bold text-slate-800">Hubungi Admin</p>
                                        </div>
                                    </a>
                                <?php endif; ?>

                                <?php if(isset($event['cs_panitia']) && !empty($event['cs_panitia'])): ?>
                                    <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $event['cs_panitia']) ?>?text=Halo%20Panitia%20<?= urlencode($event['judul']) ?>,%20saya%20ingin%20bertanya%20seputar%20event..." target="_blank" class="flex items-center gap-3 p-3 rounded-xl border border-slate-200 bg-white hover:border-blue-500 hover:shadow-sm hover:shadow-blue-500/10 transition-all group">
                                        <div class="w-10 h-10 rounded-lg bg-blue-50 text-blue-500 flex items-center justify-center shrink-0 group-hover:bg-blue-500 group-hover:text-white transition-colors">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path></svg>
                                        </div>
                                        <div>
                                            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wide">Info Seputar Event</p>
                                            <p class="text-sm font-bold text-slate-800 line-clamp-1">Hubungi Panitia</p>
                                        </div>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
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

        <?php if ($is_war_active): ?>
        document.addEventListener('DOMContentLoaded', () => {
            const warTargetTime = <?= $war_start_timestamp ?> * 1000;
            const countdownEl = document.getElementById('warCountdown');

            function updateWarCountdown() {
                const now = new Date().getTime();
                const distance = warTargetTime - now;

                if (distance <= 0) {
                    if (countdownEl) countdownEl.innerText = "00 : 00 : 00";
                    window.location.reload();
                    return;
                }

                const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
                const seconds = Math.floor((distance % (1000 * 60)) / 1000);

                const hStr = String(hours).padStart(2, '0');
                const mStr = String(minutes).padStart(2, '0');
                const sStr = String(seconds).padStart(2, '0');

                if (countdownEl) {
                    countdownEl.innerText = `${hStr} : ${mStr} : ${sStr}`;
                }
            }

            updateWarCountdown();
            setInterval(updateWarCountdown, 1000);
        });
        <?php endif; ?>
    </script>
</body>

</html>