<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../auth/login.php");
    exit;
}
require_once '../config/koneksi.php';

$success = '';
$error = '';

if (isset($_GET['approve'])) {
    $id = (int)$_GET['approve'];
    $stmt = $conn->prepare("UPDATE events SET status_approval = 'approved' WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    logActivity($conn, $_SESSION['user_id'], 'Approve Event', "Menyetujui event dengan ID: $id");
    header("Location: manage_events.php?msg=approved");
    exit;
}

if (isset($_GET['reject'])) {
    $id = (int)$_GET['reject'];
    $stmt = $conn->prepare("UPDATE events SET status_approval = 'rejected' WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    logActivity($conn, $_SESSION['user_id'], 'Reject Event', "Menolak event dengan ID: $id");
    header("Location: manage_events.php?msg=rejected");
    exit;
}

if (isset($_GET['del'])) {
    $id = (int)$_GET['del'];
    $stmt = $conn->prepare("SELECT banner_image, banner_image2, banner_image3, banner_image4 FROM events WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $evt = $stmt->get_result()->fetch_assoc();
    if ($evt) {
        $imgs = [$evt['banner_image'], $evt['banner_image2'], $evt['banner_image3'], $evt['banner_image4']];
        foreach($imgs as $img) {
            if ($img && file_exists("../assets/images/events/".$img)) {
                unlink("../assets/images/events/".$img);
            }
        }
    }
    $stmt = $conn->prepare("DELETE FROM events WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    logActivity($conn, $_SESSION['user_id'], 'Delete Event', "Menghapus event dengan ID: $id");
    header("Location: manage_events.php?msg=deleted");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && ($_POST['action'] ?? '') == 'create') {
    $judul     = $conn->real_escape_string(trim($_POST['judul']));
    $kategori  = $conn->real_escape_string(trim($_POST['kategori']));
    $deskripsi = $conn->real_escape_string(trim($_POST['deskripsi'] ?? ''));
    $tanggal   = $conn->real_escape_string($_POST['tanggal']);
    $waktu     = $conn->real_escape_string($_POST['waktu']);
    $waktu_selesai = !empty($_POST['waktu_selesai']) ? "'" . $conn->real_escape_string($_POST['waktu_selesai']) . "'" : "NULL";
    $lokasi    = $conn->real_escape_string(trim($_POST['lokasi']));
    $link_gmaps = !empty($_POST['link_gmaps']) ? "'" . $conn->real_escape_string(trim($_POST['link_gmaps'])) . "'" : "NULL";
    $harga     = (isset($_POST['harga_varian']) && is_array($_POST['harga_varian']) && count($_POST['harga_varian']) > 0) ? min($_POST['harga_varian']) : 0;
    $stok      = (isset($_POST['stok_varian']) && is_array($_POST['stok_varian'])) ? array_sum($_POST['stok_varian']) : 0;
    $nama_vendor = !empty($_POST['nama_vendor']) ? "'" . $conn->real_escape_string(trim($_POST['nama_vendor'])) . "'" : "NULL";
    $is_war_ticket = isset($_POST['is_war_ticket']) ? 1 : 0;
    $war_start_time = (!empty($_POST['war_start_time']) && $is_war_ticket) ? "'" . $conn->real_escape_string($_POST['war_start_time']) . "'" : "NULL";
    $id_panitia = (int)$_SESSION['user_id'];
    $images = ["NULL", "NULL", "NULL", "NULL"];
    $allowed_ext = ['jpg', 'jpeg', 'png'];
    $upload_count = 0;
    
    if (isset($_FILES['banners']) && is_array($_FILES['banners']['name'])) {
        $total_files = count($_FILES['banners']['name']);
        if ($total_files > 4) {
            $error = "Gagal: Maksimal 4 foto yang diizinkan (format: JPG, PNG, JPEG).";
        } else {
            for ($i = 0; $i < $total_files; $i++) {
                if ($_FILES['banners']['error'][$i] == 0) {
                    $ext = strtolower(pathinfo($_FILES['banners']['name'][$i], PATHINFO_EXTENSION));
                    if (in_array($ext, $allowed_ext)) {
                        $fname = time() . '_' . $i . '_evt.' . $ext;
                        if (move_uploaded_file($_FILES['banners']['tmp_name'][$i], "../assets/images/events/" . $fname)) {
                            $images[$upload_count] = "'$fname'";
                            $upload_count++;
                        }
                    }
                }
            }
        }
    }

    $tiket_header_val = "NULL";
    if (isset($_FILES['tiket_header']) && $_FILES['tiket_header']['error'] == 0) {
        $ext_header = strtolower(pathinfo($_FILES['tiket_header']['name'], PATHINFO_EXTENSION));
        if (in_array($ext_header, $allowed_ext)) {
            $fname_header = time() . '_header.' . $ext_header;
            if (move_uploaded_file($_FILES['tiket_header']['tmp_name'], "../assets/images/events/" . $fname_header)) {
                $tiket_header_val = "'$fname_header'";
            }
        }
    }

    if (empty($error)) {
        $status_approval = ($_SESSION['role'] == 'admin') ? 'approved' : 'pending';
        $sql = "INSERT INTO events (id_panitia,judul,kategori,deskripsi,tanggal,waktu,waktu_selesai,lokasi,link_gmaps,harga,stok,banner_image,banner_image2,banner_image3,banner_image4,status_approval,nama_vendor,tiket_header,is_war_ticket,war_start_time)
                VALUES ($id_panitia,'$judul','$kategori','$deskripsi','$tanggal','$waktu',$waktu_selesai,'$lokasi',$link_gmaps,$harga,$stok,{$images[0]},{$images[1]},{$images[2]},{$images[3]},'$status_approval',$nama_vendor,$tiket_header_val,$is_war_ticket,$war_start_time)";
        if ($conn->query($sql)) {
            $new_event_id = $conn->insert_id;
            
            // Insert variants
            $nama_periode = $_POST['nama_periode'] ?? [];
            $tgl_mulai_varian = $_POST['tgl_mulai_varian'] ?? [];
            $tgl_selesai_varian = $_POST['tgl_selesai_varian'] ?? [];
            $kategori_tempat = $_POST['kategori_tempat'] ?? [];
            $tipe_paket = $_POST['tipe_paket'] ?? [];
            $harga_variants = $_POST['harga_varian'] ?? [];
            $stok_variants = $_POST['stok_varian'] ?? [];
            
            $stmt_var = $conn->prepare("INSERT INTO event_ticket_variants (id_event, nama_varian, harga, stok, sisa_stok, tgl_mulai, tgl_selesai, kategori_tempat, tipe_paket) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            for ($i = 0; $i < count($nama_periode); $i++) {
                $periode = trim($nama_periode[$i]);
                $tgl_mulai = $tgl_mulai_varian[$i];
                $tgl_selesai = $tgl_selesai_varian[$i];
                $kat = trim($kategori_tempat[$i]);
                $tipe = trim($tipe_paket[$i]);
                $h_var = (float)$harga_variants[$i];
                $s_var = (int)$stok_variants[$i];
                
                $n_var = $periode . ' - ' . $kat . ' (' . $tipe . ')';
                
                if (!empty($periode) && $s_var > 0) {
                    $stmt_var->bind_param("isdiissss", $new_event_id, $n_var, $h_var, $s_var, $s_var, $tgl_mulai, $tgl_selesai, $kat, $tipe);
                    $stmt_var->execute();
                }
            }
            
            logActivity($conn, $_SESSION['user_id'], 'Create Event', "Membuat event baru: $judul (ID: $new_event_id)");
            $success = "Event \"$judul\" berhasil dibuat!";
        } else {
            $error = "Gagal: " . $conn->error;
        }
    }
}

if (isset($_GET['msg'])) {
    if($_GET['msg'] == 'deleted') $success = "Event berhasil dihapus.";
    if($_GET['msg'] == 'approved') $success = "Event berhasil disetujui.";
    if($_GET['msg'] == 'rejected') $success = "Event telah ditolak.";
}

$filter = $_GET['filter'] ?? 'all';
$where = "";
if ($filter === 'pending') $where = "WHERE e.status_approval = 'pending'";
elseif ($filter === 'approved') $where = "WHERE e.status_approval = 'approved'";
elseif ($filter === 'rejected') $where = "WHERE e.status_approval = 'rejected'";

$events = $conn->query("SELECT e.*, u.nama_lengkap as panitia FROM events e JOIN users u ON e.id_panitia = u.id $where ORDER BY e.tanggal DESC");
$cat_q  = $conn->query("SELECT nama FROM event_categories ORDER BY nama ASC");
$cat_list = [];
while ($c = $cat_q->fetch_assoc()) { $cat_list[] = $c['nama']; }
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Events - HaloTiket</title>
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
        <div id="sidebarOverlay" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-40 hidden md:hidden transition-opacity"></div>
        
        <?php $active_menu = 'events'; include 'components/sidebar.php'; ?>

        <!-- Main Content Area -->
        <div class="flex-1 flex flex-col h-screen overflow-hidden bg-slate-50 relative z-10 w-full transition-all duration-300">
            <!-- Top Header (Mobile) -->
                        <!-- Unified Top Header -->
            <header class="h-20 bg-white border-b border-slate-200 flex items-center justify-between px-4 lg:px-8 shrink-0 shadow-sm z-20">
                <div class="flex items-center gap-4">
                    <button id="hamburgerBtn" class="text-slate-500 hover:text-slate-700 focus:outline-none p-2 rounded-xl hover:bg-slate-100 transition-colors bg-slate-50 border border-slate-200">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>
                    <span class="text-xl font-extrabold text-slate-800 md:hidden">Admin</span>
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

            <main class="flex-1 overflow-y-auto p-6 lg:p-10 relative z-10">
                <?php if($success): ?>
                <div class="mb-6 bg-emerald-50 text-emerald-700 px-5 py-4 rounded-2xl text-sm font-bold flex items-center gap-3 border border-emerald-200">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                    <?= htmlspecialchars($success) ?>
                </div>
                <?php endif; ?>
                <?php if($error): ?>
                <div class="mb-6 bg-red-50 text-red-600 px-5 py-4 rounded-2xl text-sm font-bold flex items-center gap-3 border border-red-200">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01" /></svg>
                    <?= htmlspecialchars($error) ?>
                </div>
                <?php endif; ?>

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
                            <input type="hidden" name="action" value="create">
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
                                    <?php foreach($cat_list as $c): ?>
                                    <option value="<?= htmlspecialchars($c) ?>"><?= htmlspecialchars($c) ?></option>
                                    <?php endforeach; ?>
                                    <?php if(empty($cat_list)): ?><option value="Umum">Umum</option><?php endif; ?>
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

                            <!-- Feature War Ticket Toggle & Waktu -->
                            <div class="md:col-span-2 bg-gradient-to-r from-amber-50 to-orange-50 p-4 rounded-2xl border border-amber-200/80 mb-2">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <label for="is_war_ticket" class="text-sm font-extrabold text-amber-900 flex items-center gap-2 cursor-pointer">
                                            ⚡ Aktifkan Fitur War Ticket
                                        </label>
                                        <p class="text-xs text-amber-700 mt-0.5">Tahan pembelian tiket di landing page hingga waktu hitung mundur (countdown) dimulai.</p>
                                    </div>
                                    <input type="checkbox" id="is_war_ticket" name="is_war_ticket" value="1" onchange="document.getElementById('war_time_container').classList.toggle('hidden', !this.checked)" class="w-5 h-5 text-amber-600 rounded border-amber-300 focus:ring-amber-500 cursor-pointer">
                                </div>
                                <div id="war_time_container" class="mt-4 hidden">
                                    <label class="block text-xs font-bold text-amber-800 uppercase tracking-wider mb-2">Waktu Mulai War Ticket</label>
                                    <input type="datetime-local" name="war_start_time" class="w-full md:w-1/2 px-4 py-2 bg-white border border-amber-200 rounded-xl focus:ring-2 focus:ring-amber-400 focus:border-amber-400 text-sm font-medium outline-none">
                                </div>
                            </div>

                            <div class="md:col-span-2">
                                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Deskripsi</label>
                                <textarea name="deskripsi" rows="3" placeholder="Deskripsi event..." class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all text-sm font-medium outline-none"></textarea>
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Foto Event (Maks. 4 - JPG, PNG, JPEG)</label>
                                <input type="file" id="adminBannersInput" name="banners[]" multiple accept=".jpg,.jpeg,.png" class="w-full text-sm font-medium file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-bold file:bg-primary/10 file:text-primary hover:file:bg-primary/20 transition-all">
                                <p class="text-[10px] text-slate-400 mt-1">*Anda bisa memilih hingga 4 foto sekaligus (tekan Ctrl/Cmd saat memilih).</p>
                                
                                <!-- Live Image Preview Grid for Event Banners -->
                                <div id="adminBannersPreviewGrid" class="hidden mt-3 grid grid-cols-2 sm:grid-cols-4 gap-3"></div>
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Desain Header Tiket PDF (Opsional)</label>
                                <input type="file" id="adminHeaderInput" name="tiket_header" accept=".jpg,.jpeg,.png" class="w-full text-sm font-medium file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-bold file:bg-indigo-50 file:text-indigo-600 hover:file:bg-indigo-100 transition-all">
                                <p class="text-[10px] text-slate-400 mt-1">*Gambar ini akan dipasang di bagian paling atas PDF Tiket (seperti desain tiket fisik).</p>
                                
                                <!-- Live Image Preview Box for Header Tiket -->
                                <div id="adminHeaderPreviewContainer" class="hidden mt-3 p-3 bg-indigo-50/50 border border-indigo-100 rounded-2xl">
                                    <p class="text-xs font-bold text-indigo-900 mb-2">Pratinjau Desain Header Tiket PDF Baru</p>
                                    <img id="adminHeaderPreviewImg" class="w-full h-28 object-cover rounded-xl border border-indigo-200 shadow-sm">
                                </div>
                            </div>
                            <div class="md:col-span-2 flex justify-end">
                                <button type="submit" class="bg-primary hover:opacity-90 text-white font-bold py-3 px-8 rounded-xl transition-all shadow-md">Buat Event</button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="mb-6 flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div>
                        <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight">Semua Event</h1>
                        <p class="text-slate-500 mt-1 font-medium text-sm">Monitoring event dari seluruh panitia di sistem.</p>
                    </div>
                    <div class="flex items-center gap-2 bg-white p-1.5 rounded-xl border border-slate-200 shadow-sm overflow-x-auto w-full md:w-auto">
                        <a href="?filter=all" class="px-4 py-2 text-sm font-bold rounded-lg whitespace-nowrap transition-colors <?= $filter == 'all' ? 'bg-primary text-white' : 'text-slate-500 hover:bg-slate-50 hover:text-slate-700' ?>">Semua</a>
                        <a href="?filter=pending" class="px-4 py-2 text-sm font-bold rounded-lg whitespace-nowrap transition-colors <?= $filter == 'pending' ? 'bg-amber-500 text-white' : 'text-slate-500 hover:bg-slate-50 hover:text-slate-700' ?>">Pending</a>
                        <a href="?filter=approved" class="px-4 py-2 text-sm font-bold rounded-lg whitespace-nowrap transition-colors <?= $filter == 'approved' ? 'bg-emerald-500 text-white' : 'text-slate-500 hover:bg-slate-50 hover:text-slate-700' ?>">Disetujui</a>
                        <a href="?filter=rejected" class="px-4 py-2 text-sm font-bold rounded-lg whitespace-nowrap transition-colors <?= $filter == 'rejected' ? 'bg-red-500 text-white' : 'text-slate-500 hover:bg-slate-50 hover:text-slate-700' ?>">Ditolak</a>
                    </div>
                </div>
                
                <div class="bg-white rounded-3xl shadow-sm border border-slate-200 overflow-hidden">
                    <div class="px-6 py-4 border-b border-slate-100 bg-slate-50/50 flex justify-between items-center">
                        <h3 class="font-extrabold text-slate-900">Database Event Global</h3>
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
                                    <th class="px-6 py-3 text-right text-[11px] font-bold text-slate-400 uppercase tracking-wider">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-slate-50">
                                <?php while($row = $events->fetch_assoc()): ?>
                                <tr class="hover:bg-slate-50/80 transition-colors group">
                                    <td class="px-6 py-4">
                                        <div class="flex items-center">
                                            <?php 
                                            $imgs = [];
                                            if(!empty($row['banner_image'])) $imgs[] = '../assets/images/events/'.$row['banner_image'];
                                            if(!empty($row['banner_image2'])) $imgs[] = '../assets/images/events/'.$row['banner_image2'];
                                            if(!empty($row['banner_image3'])) $imgs[] = '../assets/images/events/'.$row['banner_image3'];
                                            if(!empty($row['banner_image4'])) $imgs[] = '../assets/images/events/'.$row['banner_image4'];
                                            $imgs_json = htmlspecialchars(json_encode($imgs), ENT_QUOTES, 'UTF-8');
                                            ?>
                                            <div class="h-12 w-16 flex-shrink-0 bg-slate-100 rounded-lg overflow-hidden border border-slate-200 flex items-center justify-center relative group/img">
                                                <?php if(count($imgs) > 0): ?>
                                                    <img src="<?= $imgs[0] ?>" alt="" onclick="openLightbox(<?= $imgs_json ?>)" class="h-full w-full object-cover object-center cursor-pointer hover:scale-110 transition-transform duration-300">
                                                    <?php if(count($imgs) > 1): ?>
                                                        <div class="absolute bottom-0 right-0 bg-black/60 text-white text-[9px] font-bold px-1.5 py-0.5 rounded-tl-lg pointer-events-none">+<?= count($imgs)-1 ?></div>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <div class="h-full w-full flex items-center justify-center text-slate-300">
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="ml-4">
                                                <div class="text-sm font-bold text-slate-900 group-hover:text-primary transition-colors"><?= htmlspecialchars($row['judul']) ?></div>
                                                <div class="text-xs font-medium text-slate-500 mt-1 flex items-center gap-1">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>
                                                    Penyelenggara: <?= htmlspecialchars($row['panitia']) ?>
                                                </div>
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
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <div class="flex items-center justify-end gap-2">
                                            <?php if($row['status_approval'] == 'pending'): ?>
                                                <a href="manage_events.php?approve=<?= $row['id'] ?>" class="text-emerald-500 hover:text-emerald-700 bg-emerald-50 hover:bg-emerald-100 p-2 rounded-lg transition-colors inline-flex border border-transparent hover:border-emerald-200" onclick="return confirm('Setujui event ini?');" title="Setujui Event">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                                                </a>
                                                <a href="manage_events.php?reject=<?= $row['id'] ?>" class="text-amber-500 hover:text-amber-700 bg-amber-50 hover:bg-amber-100 p-2 rounded-lg transition-colors inline-flex border border-transparent hover:border-amber-200" onclick="return confirm('Tolak event ini?');" title="Tolak Event">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                                </a>
                                            <?php endif; ?>
                                            <a href="edit_event.php?id=<?= $row['id'] ?>" class="text-blue-500 hover:text-blue-700 bg-blue-50 hover:bg-blue-100 p-2 rounded-lg transition-colors inline-flex border border-transparent hover:border-blue-200" title="Edit Event">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                                            </a>
                                            <a href="manage_events.php?del=<?= $row['id'] ?>" class="text-red-500 hover:text-red-700 bg-red-50 hover:bg-red-100 p-2 rounded-lg transition-colors inline-flex border border-transparent hover:border-red-200" onclick="return confirm('Yakin hapus event ini secara paksa? Semua tiket terkait akan hilang!');" title="Hapus Event">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                                <?php if($events->num_rows == 0): ?>
                                    <tr><td colspan="5" class="px-8 py-12 text-center text-slate-500 font-medium">Belum ada event terdaftar di sistem.</td></tr>
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
                    sidebar.classList.toggle('-translate-x-full');
                    if(sidebarOverlay) sidebarOverlay.classList.toggle('hidden');
                } else {
                    sidebar.classList.toggle('md:hidden');
                }
            }

            if(hamburgerBtn) hamburgerBtn.addEventListener('click', toggleSidebar);
            if(closeSidebar) closeSidebar.addEventListener('click', toggleSidebar);
            if(sidebarOverlay) sidebarOverlay.addEventListener('click', toggleSidebar);
            window.addEventListener('resize', () => {
                if (window.innerWidth >= 768) {
                    if(sidebar) sidebar.classList.remove('-translate-x-full');
                    if(sidebarOverlay) sidebarOverlay.classList.add('hidden');
                } else {
                    if(sidebar) sidebar.classList.remove('md:hidden');
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

    <!-- Lightbox Modal -->
    <div id="lightbox" class="fixed inset-0 z-[100] bg-black/90 hidden flex items-center justify-center opacity-0 transition-opacity duration-300">
        <button onclick="closeLightbox()" class="absolute top-6 right-6 text-white hover:text-red-500 transition-colors bg-white/10 hover:bg-white/20 p-2 rounded-full backdrop-blur-sm z-50">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
        </button>
        
        <button id="lightboxPrev" onclick="prevImage()" class="absolute left-6 text-white hover:text-primary transition-colors bg-white/10 hover:bg-white/20 p-3 rounded-full backdrop-blur-sm z-50 hidden">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
        </button>
        
        <img id="lightboxImg" src="" alt="Event Banner" class="max-w-[90vw] max-h-[90vh] object-contain rounded-xl shadow-2xl scale-95 transition-transform duration-300">
        
        <button id="lightboxNext" onclick="nextImage()" class="absolute right-6 text-white hover:text-primary transition-colors bg-white/10 hover:bg-white/20 p-3 rounded-full backdrop-blur-sm z-50 hidden">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
        </button>
        
        <div id="lightboxCounter" class="absolute bottom-6 text-white font-bold bg-black/50 px-4 py-1.5 rounded-full backdrop-blur-sm text-sm">1 / 1</div>
    </div>

    <!-- Lightbox Script -->
    <script>
        let currentImages = [];
        let currentIndex = 0;
        const lightbox = document.getElementById('lightbox');
        const lightboxImg = document.getElementById('lightboxImg');
        const btnPrev = document.getElementById('lightboxPrev');
        const btnNext = document.getElementById('lightboxNext');
        const counter = document.getElementById('lightboxCounter');

        function openLightbox(images) {
            if(!images || images.length === 0) return;
            currentImages = images;
            currentIndex = 0;
            updateLightbox();
            
            lightbox.classList.remove('hidden');
            // Trigger reflow for animation
            void lightbox.offsetWidth;
            lightbox.classList.remove('opacity-0');
            lightboxImg.classList.remove('scale-95');
            lightboxImg.classList.add('scale-100');
        }

        function closeLightbox() {
            lightbox.classList.add('opacity-0');
            lightboxImg.classList.remove('scale-100');
            lightboxImg.classList.add('scale-95');
            setTimeout(() => {
                lightbox.classList.add('hidden');
                lightboxImg.src = '';
            }, 300);
        }

        function updateLightbox() {
            lightboxImg.src = currentImages[currentIndex];
            counter.innerText = `${currentIndex + 1} / ${currentImages.length}`;
            
            if (currentImages.length > 1) {
                btnPrev.classList.remove('hidden');
                btnNext.classList.remove('hidden');
            } else {
                btnPrev.classList.add('hidden');
                btnNext.classList.add('hidden');
            }
        }

        function nextImage() {
            currentIndex = (currentIndex + 1) % currentImages.length;
            updateLightbox();
        }

        function prevImage() {
            currentIndex = (currentIndex - 1 + currentImages.length) % currentImages.length;
            updateLightbox();
        }

        // Close on click outside
        lightbox.addEventListener('click', (e) => {
            if (e.target === lightbox) closeLightbox();
        });
        
        // Keyboard navigation
        document.addEventListener('keydown', (e) => {
            if (!lightbox.classList.contains('hidden')) {
                if (e.key === 'Escape') closeLightbox();
                if (e.key === 'ArrowRight') nextImage();
                if (e.key === 'ArrowLeft') prevImage();
            }
        });

        // Banner Multi-image & Header Live Preview Handlers
        const bannersInput = document.getElementById('adminBannersInput');
        const bannersPreviewGrid = document.getElementById('adminBannersPreviewGrid');

        if (bannersInput && bannersPreviewGrid) {
            bannersInput.addEventListener('change', function(e) {
                bannersPreviewGrid.innerHTML = '';
                const files = Array.from(e.target.files).slice(0, 4);
                if (files.length > 0) {
                    bannersPreviewGrid.classList.remove('hidden');
                    files.forEach((file, index) => {
                        const reader = new FileReader();
                        reader.onload = function(evt) {
                            const item = document.createElement('div');
                            item.className = 'relative group rounded-xl overflow-hidden border border-slate-200 shadow-sm bg-slate-100 h-24';
                            item.innerHTML = `
                                <img src="${evt.target.result}" class="w-full h-full object-cover">
                                <span class="absolute top-1 left-1 bg-slate-900/70 text-white text-[9px] font-bold px-1.5 py-0.5 rounded">Foto ${index + 1}</span>
                            `;
                            bannersPreviewGrid.appendChild(item);
                        };
                        reader.readAsDataURL(file);
                    });
                } else {
                    bannersPreviewGrid.classList.add('hidden');
                }
            });
        }

        const headerInput = document.getElementById('adminHeaderInput');
        const headerContainer = document.getElementById('adminHeaderPreviewContainer');
        const headerPreviewImg = document.getElementById('adminHeaderPreviewImg');

        if (headerInput && headerContainer && headerPreviewImg) {
            headerInput.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(evt) {
                        headerPreviewImg.src = evt.target.result;
                        headerContainer.classList.remove('hidden');
                    };
                    reader.readAsDataURL(file);
                } else {
                    headerContainer.classList.add('hidden');
                }
            });
        }
    </script>
</body>
</html>
