<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'panitia') {
    header("Location: ../auth/login.php");
    exit;
}
require_once '../config/koneksi.php';

// Ambil data biaya admin untuk ditampilkan sebagai catatan panitia
$markup_type = $global_settings['admin_markup_type'] ?? 'nominal';
$markup_value = $global_settings['admin_markup_value'] ?? 5000;
$markup_label = ($markup_type == 'percent') ? $markup_value . '%' : 'Rp ' . number_format($markup_value, 0, ',', '.');

$id_panitia = $_SESSION['user_id'];

if (isset($_GET['del'])) {
    $id = (int)$_GET['del'];
    $stmt = $conn->prepare("SELECT banner_image, banner_image2, banner_image3, banner_image4 FROM events WHERE id = ? AND id_panitia = ?");
    $stmt->bind_param("ii", $id, $id_panitia);
    $stmt->execute();
    $evt = $stmt->get_result()->fetch_assoc();
    if ($evt) {
        $imgs = [$evt['banner_image'], $evt['banner_image2'], $evt['banner_image3'], $evt['banner_image4']];
        foreach($imgs as $img) {
            if ($img && file_exists("../assets/images/events/".$img)) {
                unlink("../assets/images/events/".$img);
            }
        }
        $stmt = $conn->prepare("DELETE FROM events WHERE id = ? AND id_panitia = ?");
        $stmt->bind_param("ii", $id, $id_panitia);
        $stmt->execute();
        logActivity($conn, $_SESSION['user_id'], 'Delete Event', "Panitia menghapus event dengan ID: $id");
    }
    header("Location: manage_events.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $judul = $_POST['judul'];
    $deskripsi = $_POST['deskripsi'];
    $tanggal = $_POST['tanggal'];
    $waktu = $_POST['waktu'];
    $waktu_selesai = !empty($_POST['waktu_selesai']) ? $_POST['waktu_selesai'] : null;
    $lokasi = $_POST['lokasi'];
    $link_gmaps = !empty($_POST['link_gmaps']) ? $_POST['link_gmaps'] : null;
    $harga     = min($_POST['harga_varian']);
    $stok      = array_sum($_POST['stok_varian']);
    $kategori = $_POST['kategori'] ?? 'Music';
    $nama_vendor = !empty($_POST['nama_vendor']) ? $_POST['nama_vendor'] : null;
    $banner = null;

    if (isset($_FILES['banner_image']) && $_FILES['banner_image']['error'] == 0) {
        $filename = time() . '_' . basename($_FILES['banner_image']['name']);
        $target = "../assets/images/events/" . $filename;
        if (move_uploaded_file($_FILES['banner_image']['tmp_name'], $target)) {
            $banner = $filename;
        }
    }

    $stmt = $conn->prepare("INSERT INTO events (id_panitia, judul, kategori, deskripsi, tanggal, waktu, waktu_selesai, lokasi, link_gmaps, harga, stok, banner_image, status_approval, nama_vendor) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?)");
    $stmt->bind_param("issssssssdisss", $id_panitia, $judul, $kategori, $deskripsi, $tanggal, $waktu, $waktu_selesai, $lokasi, $link_gmaps, $harga, $stok, $banner, $nama_vendor);
    $stmt->execute();
    $new_event_id = $conn->insert_id;
    
    // Insert variants
    $nama_variants = $_POST['nama_varian'];
    $harga_variants = $_POST['harga_varian'];
    $stok_variants = $_POST['stok_varian'];
    
    $stmt_var = $conn->prepare("INSERT INTO event_ticket_variants (id_event, nama_varian, harga, stok, sisa_stok) VALUES (?, ?, ?, ?, ?)");
    for ($i = 0; $i < count($nama_variants); $i++) {
        $n_var = trim($nama_variants[$i]);
        $h_var = (float)$harga_variants[$i];
        $s_var = (int)$stok_variants[$i];
        if (!empty($n_var) && $s_var > 0) {
            $stmt_var->bind_param("isdii", $new_event_id, $n_var, $h_var, $s_var, $s_var);
            $stmt_var->execute();
        }
    }
    
    logActivity($conn, $_SESSION['user_id'], 'Create Event', "Panitia mengajukan event baru: $judul (ID: $new_event_id)");
    header("Location: manage_events.php");
    exit;
}

$events = $conn->query("SELECT * FROM events WHERE id_panitia = $id_panitia ORDER BY tanggal DESC");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Events - HaloTiket</title>
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
                        dark: '#0a1020',
                    }
                }
            }
        }
    </script>

    <!-- Custom Stylesheet -->
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
</head>
<body class="bg-slate-50 font-sans antialiased text-slate-800 selection:bg-primary selection:text-white overflow-hidden">
    <div class="flex h-screen w-full">
        
        <!-- Mobile Overlay -->
        <div id="sidebarOverlay" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-40 hidden md:hidden transition-opacity"></div>

        <?php $active_menu = 'events'; include 'components/sidebar.php'; ?>

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
                    <span class="text-xl font-extrabold text-slate-800 md:hidden">Event Saya</span>
                </div>
                
                <div class="flex items-center gap-4">
                    <a href="profile.php" class="hidden md:flex items-center gap-3 mr-2 px-3 py-1.5 rounded-full border border-slate-100 bg-slate-50 hover:bg-slate-100 hover:border-slate-200 transition-colors group cursor-pointer">
                        <?php if (isset($_SESSION['foto_profil']) && !empty($_SESSION['foto_profil']) && file_exists('../assets/images/profil/'.$_SESSION['foto_profil'])): ?>
                            <img src="../assets/images/profil/<?= htmlspecialchars($_SESSION['foto_profil']) ?>" class="w-8 h-8 rounded-full object-cover shadow-sm">
                        <?php else: ?>
                            <div class="w-8 h-8 rounded-full bg-gradient-to-br from-primary to-secondary text-white flex items-center justify-center font-bold text-sm shadow-sm group-hover:shadow transition-all">
                                <?= strtoupper(substr($_SESSION['nama_lengkap'], 0, 1)) ?>
                            </div>
                        <?php endif; ?>
                        <span class="text-sm font-bold text-slate-700 pr-2 group-hover:text-primary transition-colors"><?= htmlspecialchars($_SESSION['nama_lengkap']) ?></span>
                    </a>
                </div>
            </header>

            <main class="flex-1 overflow-y-auto p-6 lg:p-10 relative z-10">
                <?php if(isset($success_msg)): ?>
                <div class="mb-6 bg-emerald-50 text-emerald-700 px-5 py-4 rounded-2xl text-sm font-bold flex items-center gap-3 border border-emerald-200">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                    <?= htmlspecialchars($success_msg) ?>
                </div>
                <?php endif; ?>

                <div class="mb-6 bg-blue-50 text-blue-700 px-5 py-4 rounded-2xl text-sm font-medium flex items-center gap-3 border border-blue-200 shadow-sm">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    Catatan: Harga tiket yang tampil ke pembeli akan ditambah biaya layanan platform sebesar <strong class="font-extrabold"><?= $markup_label ?></strong>.
                </div>

                <!-- Form Buat Event Baru -->
                <div class="bg-white rounded-3xl shadow-sm border border-slate-200 overflow-hidden mb-8">
                    <div class="px-6 py-4 border-b border-slate-100 bg-slate-50/50 flex items-center justify-between cursor-pointer" onclick="document.getElementById('form-event').classList.toggle('hidden')">
                        <h3 class="font-extrabold text-slate-900 flex items-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" /></svg>
                            Buat Event Baru
                        </h3>
                        <span class="text-xs text-slate-400 font-medium">Klik untuk buka/tutup</span>
                    </div>
                    <div id="form-event" class="p-6 hidden">
                        <form method="POST" enctype="multipart/form-data" class="grid grid-cols-1 md:grid-cols-2 gap-5">
                            <div class="md:col-span-1">
                                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Judul Event</label>
                                <input type="text" name="judul" required placeholder="Nama event..." class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all text-sm font-medium outline-none">
                            </div>
                            <div class="md:col-span-1">
                                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Nama Vendor</label>
                                <input type="text" name="nama_vendor" required placeholder="Penyelenggara / Vendor..." class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all text-sm font-medium outline-none">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Kategori</label>
                                <select name="kategori" required class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all text-sm font-medium outline-none">
                                    <option value="Music">Music</option>
                                    <option value="Sports">Sports</option>
                                    <option value="Food">Food</option>
                                    <option value="Arts">Arts</option>
                                </select>
                            </div>
                            <div class="md:col-span-1">
                                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Lokasi Venue</label>
                                <input type="text" name="lokasi" required placeholder="Nama venue..." class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all text-sm font-medium outline-none">
                            </div>
                            <div class="md:col-span-1">
                                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Link Google Maps (Opsional)</label>
                                <input type="url" name="link_gmaps" placeholder="https://maps.app.goo.gl/..." class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all text-sm font-medium outline-none">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Tanggal</label>
                                <input type="date" name="tanggal" required class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all text-sm font-medium outline-none">
                            </div>
                            <div class="flex gap-2">
                                <div class="w-1/2">
                                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Waktu Mulai</label>
                                    <input type="time" name="waktu" required class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all text-sm font-medium outline-none">
                                </div>
                                <div class="w-1/2">
                                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Waktu Selesai</label>
                                    <input type="time" name="waktu_selesai" class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all text-sm font-medium outline-none">
                                </div>
                            </div>
                            <div class="md:col-span-2">
                                                                <div class="flex justify-between items-center mb-3">
                                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider">Paket Tiket Lengkap</label>
                                    <button type="button" onclick="addVariant()" class="text-xs font-bold text-primary hover:text-secondary bg-primary/10 px-3 py-1.5 rounded-full transition-colors flex items-center gap-1">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                                        Tambah Paket
                                    </button>
                                </div>
                                <div id="variants_container" class="space-y-4">
                                    <div class="variant-row bg-slate-50 border border-slate-200 p-5 rounded-2xl relative shadow-sm">
                                        <button type="button" onclick="removeVariant(this)" class="absolute top-4 right-4 p-1.5 text-slate-400 hover:text-red-500 hover:bg-red-50 rounded-lg hidden btn-remove-variant transition-colors" title="Hapus Paket">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                        </button>
                                        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4 mb-4">
                                            <div class="md:col-span-2">
                                                <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Periode Penjualan</label>
                                                <input type="text" name="nama_periode[]" required placeholder="Misal: Presale 1, Flash Sale" class="w-full px-4 py-2.5 bg-white border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all text-sm font-medium outline-none">
                                            </div>
                                            <div>
                                                <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Dibuka Tanggal</label>
                                                <input type="datetime-local" name="tgl_mulai_varian[]" required class="w-full px-4 py-2.5 bg-white border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all text-sm font-medium outline-none">
                                            </div>
                                            <div>
                                                <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Ditutup Tanggal</label>
                                                <input type="datetime-local" name="tgl_selesai_varian[]" required class="w-full px-4 py-2.5 bg-white border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all text-sm font-medium outline-none">
                                            </div>
                                        </div>
                                        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
                                            <div>
                                                <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Kategori / Ploting</label>
                                                <input type="text" name="kategori_tempat[]" required placeholder="Misal: VIP, Festival" class="w-full px-4 py-2.5 bg-white border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all text-sm font-medium outline-none">
                                            </div>
                                            <div>
                                                <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Tipe Paket</label>
                                                <select name="tipe_paket[]" required class="w-full px-4 py-2.5 bg-white border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all text-sm font-medium outline-none appearance-none">
                                                    <option value="Sendiri">Sendiri (1 Orang)</option>
                                                    <option value="Couple">Couple (2 Orang)</option>
                                                    <option value="Grup">Grup (Rombongan)</option>
                                                </select>
                                            </div>
                                            <div>
                                                <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Harga (Rp)</label>
                                                <input type="number" name="harga_varian[]" required min="0" placeholder="0" class="w-full px-4 py-2.5 bg-white border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all text-sm font-medium outline-none">
                                            </div>
                                            <div>
                                                <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Stok Tersedia</label>
                                                <input type="number" name="stok_varian[]" required min="1" placeholder="10" class="w-full px-4 py-2.5 bg-white border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all text-sm font-medium outline-none">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Deskripsi</label>
                                <textarea name="deskripsi" rows="3" placeholder="Deskripsi event..." class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all text-sm font-medium outline-none"></textarea>
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Foto Event (JPG, PNG, JPEG)</label>
                                <input type="file" name="banner_image" accept=".jpg,.jpeg,.png" class="w-full text-sm font-medium file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-bold file:bg-primary/10 file:text-primary hover:file:bg-primary/20 transition-all">
                            </div>
                            <div class="md:col-span-2 flex justify-end">
                                <button type="submit" class="bg-primary hover:opacity-90 text-white font-bold py-3 px-8 rounded-xl transition-all shadow-md">Buat Event</button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="mb-6 flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div>
                        <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight">Event Saya</h1>
                        <p class="text-slate-500 mt-1 font-medium text-sm">Monitoring event yang Anda selenggarakan.</p>
                    </div>
                </div>
                
                <div class="bg-white rounded-3xl shadow-sm border border-slate-200 overflow-hidden">
                    <div class="px-6 py-4 border-b border-slate-100 bg-slate-50/50 flex justify-between items-center">
                        <h3 class="font-extrabold text-slate-900">Database Event</h3>
                        <span class="bg-indigo-50 text-primary text-xs font-bold px-3 py-1 rounded-full">Total: <?= $events->num_rows ?> Event</span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-100">
                            <thead class="bg-white">
                                <tr>
                                    <th class="px-6 py-3 text-left text-[11px] font-bold text-slate-400 uppercase tracking-wider">Event & Penyelenggara</th>
                                    <th class="px-6 py-3 text-left text-[11px] font-bold text-slate-400 uppercase tracking-wider">Kategori</th>
                                    <th class="px-6 py-3 text-left text-[11px] font-bold text-slate-400 uppercase tracking-wider">Jadwal</th>
                                    <th class="px-6 py-3 text-left text-[11px] font-bold text-slate-400 uppercase tracking-wider">Tiket</th>
                                    <th class="px-6 py-3 text-left text-[11px] font-bold text-slate-400 uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-3 text-right text-[11px] font-bold text-slate-400 uppercase tracking-wider">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-slate-50">
                                <?php while($row = $events->fetch_assoc()): ?>
                                <tr class="hover:bg-slate-50/80 transition-colors group">
                                    <td class="px-6 py-4">
                                        <div class="flex items-center">
                                            <div class="h-12 w-16 flex-shrink-0 bg-slate-100 rounded-lg overflow-hidden border border-slate-200 flex items-center justify-center relative group/img">
                                                <?php if(!empty($row['banner_image']) && file_exists('../assets/images/events/'.$row['banner_image'])): ?>
                                                    <img src="../assets/images/events/<?= htmlspecialchars($row['banner_image']) ?>" class="h-full w-full object-cover object-center cursor-pointer hover:scale-110 transition-transform duration-300">
                                                <?php else: ?>
                                                    <div class="h-full w-full flex items-center justify-center text-slate-300">
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="ml-4">
                                                <div class="text-sm font-bold text-slate-900 group-hover:text-primary transition-colors"><?= htmlspecialchars($row['judul']) ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="bg-primary/10 text-primary text-xs font-bold px-3 py-1 rounded-full"><?= htmlspecialchars($row['kategori'] ?? '-') ?></span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-bold text-slate-700"><?= date('d M Y', strtotime($row['tanggal'])) ?></div>
                                        <div class="text-xs text-slate-500 font-medium"><?= $row['waktu'] ?> WIB</div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-extrabold text-slate-900">Rp <?= number_format($row['harga'], 0, ',', '.') ?></div>
                                        <div class="text-xs text-slate-500 font-medium mt-1">Sisa stok: <span class="<?= $row['stok'] > 0 ? 'text-emerald-600' : 'text-red-500' ?> font-bold"><?= $row['stok'] ?></span></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php if($row['status_approval'] == 'pending'): ?>
                                            <span class="px-3 py-1 inline-flex text-xs leading-5 font-bold rounded-full bg-amber-50 text-amber-600 border border-amber-200 shadow-sm"><span class="w-1.5 h-1.5 bg-amber-500 rounded-full mr-1.5 mt-1"></span> Menunggu</span>
                                        <?php elseif($row['status_approval'] == 'approved'): ?>
                                            <span class="px-3 py-1 inline-flex text-xs leading-5 font-bold rounded-full bg-emerald-50 text-emerald-600 border border-emerald-200 shadow-sm"><span class="w-1.5 h-1.5 bg-emerald-500 rounded-full mr-1.5 mt-1"></span> Disetujui</span>
                                        <?php else: ?>
                                            <span class="px-3 py-1 inline-flex text-xs leading-5 font-bold rounded-full bg-red-50 text-red-600 border border-red-200 shadow-sm"><span class="w-1.5 h-1.5 bg-red-500 rounded-full mr-1.5 mt-1"></span> Ditolak</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <a href="edit_event.php?id=<?= $row['id'] ?>" class="text-blue-500 hover:text-blue-700 bg-blue-50 hover:bg-blue-100 p-2 rounded-lg transition-colors inline-flex border border-transparent hover:border-blue-200" title="Edit Event">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                                        </a>
                                        <a href="manage_events.php?del=<?= $row['id'] ?>" class="text-red-500 hover:text-red-700 bg-red-50 hover:bg-red-100 p-2 rounded-lg transition-colors inline-flex border border-transparent hover:border-red-200" onclick="return confirm('Yakin ingin menghapus event ini?');" title="Hapus">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                        </a>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                                <?php if($events->num_rows == 0): ?>
                                    <tr><td colspan="6" class="px-8 py-12 text-center text-slate-500 font-medium">Anda belum membuat event apapun.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </main>
        </div>
    </div>

        <!-- Script for Sidebar Toggle -->
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const sidebar = document.getElementById('sidebar');
            const hamburgerBtn = document.getElementById('hamburgerBtn');
            const closeSidebar = document.getElementById('closeSidebar');
            const sidebarOverlay = document.getElementById('sidebarOverlay');

            function toggleSidebar() {
                if (window.innerWidth < 768) {
                    // Mobile Toggle
                    sidebar.classList.toggle('-translate-x-full');
                    if(sidebarOverlay) sidebarOverlay.classList.toggle('hidden');
                } else {
                    // Desktop Toggle
                    sidebar.classList.toggle('md:hidden');
                }
            }

            if(hamburgerBtn) hamburgerBtn.addEventListener('click', toggleSidebar);
            
            if(closeSidebar) {
                closeSidebar.addEventListener('click', () => {
                    sidebar.classList.add('-translate-x-full');
                    if(sidebarOverlay) sidebarOverlay.classList.add('hidden');
                });
            }

            if(sidebarOverlay) {
                sidebarOverlay.addEventListener('click', () => {
                    sidebar.classList.add('-translate-x-full');
                    sidebarOverlay.classList.add('hidden');
                });
            }

            // Handle window resize to reset states if needed
            window.addEventListener('resize', () => {
                if (window.innerWidth >= 768) {
                    sidebar.classList.remove('-translate-x-full');
                    if(sidebarOverlay) sidebarOverlay.classList.add('hidden');
                } else {
                    // On mobile, if desktop was hidden, remove the hidden class 
                    // so it can be animated via translate
                    sidebar.classList.remove('md:hidden');
                }
            });
        });

        // Script for Ticket Variants
        function addVariant() {
            const container = document.getElementById('variants_container');
            const rows = container.getElementsByClassName('variant-row');
            const newRow = rows[0].cloneNode(true);
            const inputs = newRow.querySelectorAll('input');
            inputs.forEach(input => input.value = '');
            newRow.querySelector('.btn-remove-variant').classList.remove('hidden');
            container.appendChild(newRow);
            updateRemoveButtons();
        }
        
        function removeVariant(btn) {
            const row = btn.closest('.variant-row');
            row.remove();
            updateRemoveButtons();
        }
        
        function updateRemoveButtons() {
            const container = document.getElementById('variants_container');
            const rows = container.getElementsByClassName('variant-row');
            if (rows.length > 1) {
                Array.from(rows).forEach(row => row.querySelector('.btn-remove-variant').classList.remove('hidden'));
            } else {
                rows[0].querySelector('.btn-remove-variant').classList.add('hidden');
            }
        }
    </script>
</body>
</html>
