<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'panitia') {
    header("Location: ../auth/login.php");
    exit;
}
require_once '../config/koneksi.php';

$id_panitia = $_SESSION['user_id'];

if (isset($_GET['del'])) {
    $id = (int)$_GET['del'];
    $evt = $conn->query("SELECT banner_image, banner_image2, banner_image3, banner_image4 FROM events WHERE id = $id AND id_panitia = $id_panitia")->fetch_assoc();
    if ($evt) {
        $imgs = [$evt['banner_image'], $evt['banner_image2'], $evt['banner_image3'], $evt['banner_image4']];
        foreach($imgs as $img) {
            if ($img && file_exists("../assets/images/events/".$img)) {
                unlink("../assets/images/events/".$img);
            }
        }
        $conn->query("DELETE FROM events WHERE id = $id AND id_panitia = $id_panitia");
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
    $harga = $_POST['harga'];
    $stok = $_POST['stok'];
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
<body class="bg-slate-50 font-sans antialiased text-slate-800 selection:bg-primary selection:text-white">
    <div class="flex h-screen overflow-hidden">
        
        <!-- Premium Sidebar -->
        <aside class="w-72 bg-dark flex flex-col hidden md:flex relative z-20">
            <div class="h-20 flex items-center px-8 border-b border-slate-800">
                <a href="../index.php" class="flex items-center gap-3 group">
                    <?php if (isset($global_site_logo) && $global_site_logo): ?>
                    <img src="<?= $global_site_logo ?>" alt="Logo" class="w-40 md:w-48 h-auto object-contain">
                <?php else: ?>
                    <div class="w-8 h-10 bg-white px-3 py-1.5 rounded-xl shadow-sm rounded-lg bg-gradient-to-br from-primary to-secondary flex items-center justify-center text-white font-bold text-sm shadow-md">H</div>
                <span class="text-xl font-extrabold text-white tracking-tight">Panitia Panel</span>
                <?php endif; ?>
                    
                </a>
            </div>
            
            <nav class="flex-1 overflow-y-auto py-6 px-4">
                <p class="px-4 text-xs font-bold text-slate-500 uppercase tracking-widest mb-3">Main Menu</p>
                <ul class="space-y-2">
                    <li>
                        <a href="index.php" class="flex items-center px-4 py-3 text-slate-400 hover:text-white hover:bg-white/5 rounded-xl font-medium transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" /></svg>
                            Dashboard
                        </a>
                    </li>
                    <li>
                        <a href="manage_events.php" class="flex items-center px-4 py-3 text-white bg-white/10 rounded-xl font-medium transition-colors border border-white/5">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-3 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                            Event Saya
                        </a>
                    </li>
                    <li>
                        <a href="laporan_sales.php" class="flex items-center px-4 py-3 text-slate-400 hover:text-white hover:bg-white/5 rounded-xl font-medium transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" /></svg>
                            Laporan Penjualan
                        </a>
                    </li>
                </ul>
            </nav>
            <div class="p-6 border-t border-slate-800">
                <a href="../auth/logout.php" class="flex items-center justify-center w-full px-4 py-3 text-sm font-bold text-slate-300 bg-slate-800 hover:bg-slate-700 hover:text-white rounded-xl transition-colors border border-slate-700">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" /></svg>
                    Logout
                </a>
            </div>
        </aside>

        <!-- Main Content -->
        <div class="flex-1 flex flex-col h-screen overflow-hidden bg-slate-50 relative">
            <!-- Top Header (Mobile) -->
            <header class="h-16 bg-dark border-b border-slate-800 flex items-center justify-between px-4 md:hidden text-white">
                <span class="text-xl font-bold">Panitia</span>
                <div class="flex space-x-3 items-center">
                    <a href="index.php" class="text-xs font-bold text-slate-400 bg-slate-800 px-3 py-1.5 rounded-lg">Overview</a>
                    <a href="../auth/logout.php" class="text-xs font-bold text-slate-300">Logout</a>
                </div>
            </header>

            <main class="flex-1 overflow-y-auto p-6 lg:p-10 relative z-10">
                <div class="mb-10">
                    <h1 class="text-3xl font-extrabold text-slate-900 tracking-tight">Kelola Event</h1>
                    <p class="text-slate-500 mt-2 font-medium">Buat dan atur acara yang akan Anda selenggarakan.</p>
                </div>
                
                <div class="grid grid-cols-1 xl:grid-cols-3 gap-8">
                    <!-- Form Tambah Event -->
                    <div class="xl:col-span-1">
                        <div class="bg-white rounded-3xl shadow-sm border border-slate-200 overflow-hidden">
                            <div class="px-8 py-6 border-b border-slate-100 bg-slate-50/50">
                                <h3 class="font-extrabold text-slate-900 flex items-center gap-2">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                                    Buat Event Baru
                                </h3>
                            </div>
                            <div class="p-8">
                                <form action="" method="POST" enctype="multipart/form-data" class="space-y-5">
                                    <div>
                                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Judul Event</label>
                                        <input type="text" name="judul" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all text-sm font-medium" required>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Nama Vendor</label>
                                        <input type="text" name="nama_vendor" required placeholder="Penyelenggara / Vendor..." class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all text-sm font-medium">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Kategori Event</label>
                                        <select name="kategori" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all text-sm font-medium" required>
                                            <option value="Music">Music</option>
                                            <option value="Sports">Sports</option>
                                            <option value="Food">Food</option>
                                            <option value="Arts">Arts</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Deskripsi</label>
                                        <textarea name="deskripsi" rows="3" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all text-sm font-medium" required></textarea>
                                    </div>
                                    <div class="grid grid-cols-2 gap-4">
                                        <div class="col-span-2">
                                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Tanggal</label>
                                            <input type="date" name="tanggal" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all text-sm font-medium" required>
                                        </div>
                                        <div>
                                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Waktu Mulai</label>
                                            <input type="time" name="waktu" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all text-sm font-medium" required>
                                        </div>
                                        <div>
                                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Waktu Selesai</label>
                                            <input type="time" name="waktu_selesai" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all text-sm font-medium">
                                        </div>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Lokasi Lengkap</label>
                                        <input type="text" name="lokasi" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all text-sm font-medium" required>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Link Google Maps (Opsional)</label>
                                        <input type="url" name="link_gmaps" placeholder="https://maps.app.goo.gl/..." class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all text-sm font-medium">
                                    </div>
                                    <div class="grid grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Harga (Rp)</label>
                                            <input type="number" name="harga" min="0" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all text-sm font-medium" required>
                                        </div>
                                        <div>
                                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Kapasitas / Stok</label>
                                            <input type="number" name="stok" min="1" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all text-sm font-medium" required>
                                        </div>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Banner / Poster (Opsional)</label>
                                        <div class="relative">
                                            <input type="file" name="banner_image" accept="image/*" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all text-sm font-medium file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-bold file:bg-primary/10 file:text-primary hover:file:bg-primary/20">
                                        </div>
                                    </div>
                                    <button type="submit" class="w-full bg-slate-900 hover:bg-primary text-white font-bold py-3 px-4 rounded-xl transition-all duration-300 mt-6 shadow-md shadow-slate-900/20">
                                        Publikasikan Event
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Tabel Event -->
                    <div class="xl:col-span-2">
                        <div class="bg-white rounded-3xl shadow-sm border border-slate-200 overflow-hidden">
                            <div class="px-8 py-6 border-b border-slate-100 bg-slate-50/50 flex justify-between items-center">
                                <h3 class="font-extrabold text-slate-900">Daftar Event</h3>
                                <span class="bg-indigo-50 text-primary text-xs font-bold px-3 py-1 rounded-full">Total: <?= $events->num_rows ?></span>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-slate-100">
                                    <thead class="bg-white">
                                        <tr>
                                            <th class="px-8 py-4 text-left text-[11px] font-bold text-slate-400 uppercase tracking-wider">Info Event</th>
                                            <th class="px-8 py-4 text-left text-[11px] font-bold text-slate-400 uppercase tracking-wider">Waktu & Tempat</th>
                                            <th class="px-8 py-4 text-left text-[11px] font-bold text-slate-400 uppercase tracking-wider">Tiket</th>
                                            <th class="px-8 py-4 text-right text-[11px] font-bold text-slate-400 uppercase tracking-wider">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-slate-50">
                                        <?php while($row = $events->fetch_assoc()): ?>
                                        <tr class="hover:bg-slate-50/80 transition-colors group">
                                            <td class="px-8 py-5">
                                                <div class="flex items-center">
                                                    <div class="h-12 w-16 flex-shrink-0 bg-slate-100 rounded-lg overflow-hidden border border-slate-200">
                                                        <?php if($row['banner_image']): ?>
                                                            <img src="../assets/images/events/<?= $row['banner_image'] ?>" alt="" class="h-full w-full object-cover">
                                                        <?php else: ?>
                                                            <div class="h-full w-full flex items-center justify-center text-slate-300">
                                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="ml-4">
                                                        <div class="text-sm font-bold text-slate-900 group-hover:text-primary transition-colors"><?= htmlspecialchars($row['judul']) ?></div>
                                                        <div class="mt-1">
                                                            <?php if($row['status_approval'] == 'pending'): ?>
                                                                <span class="bg-amber-100 text-amber-700 text-[10px] font-bold px-2 py-0.5 rounded-full">Pending (Menunggu Review)</span>
                                                            <?php elseif($row['status_approval'] == 'approved'): ?>
                                                                <span class="bg-emerald-100 text-emerald-700 text-[10px] font-bold px-2 py-0.5 rounded-full">Disetujui</span>
                                                            <?php else: ?>
                                                                <span class="bg-red-100 text-red-700 text-[10px] font-bold px-2 py-0.5 rounded-full">Ditolak</span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-8 py-5">
                                                <div class="text-sm font-bold text-slate-700"><?= date('d M Y', strtotime($row['tanggal'])) ?></div>
                                                <div class="text-xs text-slate-500 font-medium truncate max-w-[150px]"><?= $row['waktu'] ?> &bull; <?= htmlspecialchars($row['lokasi']) ?></div>
                                            </td>
                                            <td class="px-8 py-5 whitespace-nowrap">
                                                <div class="text-sm font-extrabold text-slate-900">Rp <?= number_format($row['harga'], 0, ',', '.') ?></div>
                                                <div class="text-xs text-slate-500 font-medium mt-1">Stok: <span class="<?= $row['stok'] > 0 ? 'text-emerald-600' : 'text-red-500' ?> font-bold"><?= $row['stok'] ?></span></div>
                                            </td>
                                            <td class="px-8 py-5 whitespace-nowrap text-right text-sm font-medium">
                                                <a href="manage_events.php?del=<?= $row['id'] ?>" class="text-red-500 hover:text-red-700 bg-red-50 hover:bg-red-100 p-2 rounded-lg transition-colors inline-flex border border-transparent hover:border-red-200" onclick="return confirm('Yakin hapus event ini? Semua data tiket akan hilang!');" title="Hapus Event">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                                </a>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                        <?php if($events->num_rows == 0): ?>
                                            <tr><td colspan="4" class="px-8 py-12 text-center text-slate-500 font-medium">Anda belum membuat event apapun.</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
</body>
</html>
