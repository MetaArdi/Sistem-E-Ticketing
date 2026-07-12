<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../auth/login.php");
    exit;
}
require_once '../config/koneksi.php';

$success = '';
$error = '';

// Handle Tambah
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] == 'add') {
        $nama = trim($conn->real_escape_string($_POST['nama']));
        $ikon = trim($conn->real_escape_string($_POST['ikon'] ?? 'star'));
        if (!empty($nama)) {
            $stmt = $conn->prepare("INSERT IGNORE INTO event_categories (nama, ikon) VALUES (?, ?)");
            $stmt->bind_param("ss", $nama, $ikon);
            $stmt->execute();
            if ($stmt && $conn->affected_rows > 0) {
                logActivity($conn, $_SESSION['user_id'], 'Add Category', "Menambahkan kategori baru: $nama");
                $success = "Kategori \"$nama\" berhasil ditambahkan.";
            } else {
                $error = "Kategori \"$nama\" sudah ada atau gagal ditambahkan.";
            }
        }
    } elseif ($_POST['action'] == 'delete') {
        $id = (int)$_POST['id'];
        $stmt = $conn->prepare("DELETE FROM event_categories WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        logActivity($conn, $_SESSION['user_id'], 'Delete Category', "Menghapus kategori dengan ID: $id");
        $success = "Kategori berhasil dihapus.";
    } elseif ($_POST['action'] == 'edit') {
        $id   = (int)$_POST['id'];
        $nama = trim($conn->real_escape_string($_POST['nama']));
        $ikon = trim($conn->real_escape_string($_POST['ikon'] ?? 'star'));
        $stmt = $conn->prepare("UPDATE event_categories SET nama=?, ikon=? WHERE id=?");
        $stmt->bind_param("ssi", $nama, $ikon, $id);
        $stmt->execute();
        logActivity($conn, $_SESSION['user_id'], 'Edit Category', "Mengubah kategori dengan ID: $id menjadi: $nama");
        $success = "Kategori berhasil diperbarui.";
    }
}

$cats = $conn->query("SELECT * FROM event_categories ORDER BY nama ASC");

$icon_options = [
    'music'   => 'Musik 🎵',
    'sports'  => 'Olahraga ⚽',
    'food'    => 'Makanan 🍔',
    'arts'    => 'Seni 🎨',
    'seminar' => 'Seminar 📚',
    'festival'=> 'Festival 🎉',
    'comedy'  => 'Komedi 😂',
    'tech'    => 'Teknologi 💻',
    'star'    => 'Lainnya ⭐',
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Kategori - HaloTiket Admin</title>
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

    <!-- Sidebar -->
    <?php $active_menu = 'categories'; include 'components/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="flex-1 flex flex-col h-screen overflow-hidden bg-slate-50 relative z-10 w-full transition-all duration-300">
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
                        <div class="w-8 h-8 rounded-full bg-gradient-to-br from-primary to-secondary text-white flex items-center justify-center font-bold text-sm shadow-sm group-hover:shadow transition-all">
                            <?= strtoupper(substr($_SESSION['nama_lengkap'], 0, 1)) ?>
                        </div>
                        <span class="text-sm font-bold text-slate-700 pr-2 group-hover:text-primary transition-colors"><?= htmlspecialchars($_SESSION['nama_lengkap']) ?></span>
                    </a>
                </div>
            </header>

        <main class="flex-1 overflow-y-auto p-4 md:p-6 lg:p-10">
            <div class="max-w-5xl mx-auto">
                <div class="mb-8">
                    <h1 class="text-2xl md:text-3xl font-extrabold text-slate-900 tracking-tight">Kelola Kategori Event</h1>
                    <p class="text-slate-500 mt-1 font-medium text-sm">Tambah, ubah, atau hapus kategori yang tampil di halaman utama.</p>
                </div>

                <?php if($success): ?>
                <div class="mb-6 bg-emerald-50 text-emerald-700 px-5 py-4 rounded-xl text-sm font-bold flex items-center gap-3 border border-emerald-200">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                    <?= htmlspecialchars($success) ?>
                </div>
                <?php endif; ?>
                <?php if($error): ?>
                <div class="mb-6 bg-red-50 text-red-600 px-5 py-4 rounded-xl text-sm font-bold flex items-center gap-3 border border-red-200">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                    <?= htmlspecialchars($error) ?>
                </div>
                <?php endif; ?>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <!-- Form Tambah -->
                    <div class="lg:col-span-1">
                        <div class="bg-white rounded-3xl shadow-sm border border-slate-200 p-6">
                            <h3 class="font-extrabold text-slate-900 mb-5 flex items-center gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" /></svg>
                                Tambah Kategori Baru
                            </h3>
                            <form method="POST" class="space-y-4">
                                <input type="hidden" name="action" value="add">
                                <div>
                                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Nama Kategori</label>
                                    <input type="text" name="nama" required placeholder="contoh: Festival" class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary focus:bg-white transition-all text-sm font-medium outline-none">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Ikon</label>
                                    <select name="ikon" class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary focus:bg-white transition-all text-sm font-medium outline-none">
                                        <?php foreach($icon_options as $val => $label): ?>
                                        <option value="<?= $val ?>"><?= $label ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <button type="submit" class="w-full bg-primary hover:opacity-90 text-white font-bold py-2 px-6 rounded-xl transition-all shadow-md">
                                    Tambah Kategori
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Daftar Kategori -->
                    <div class="lg:col-span-2">
                        <div class="bg-white rounded-3xl shadow-sm border border-slate-200 overflow-hidden">
                            <div class="px-6 py-4 border-b border-slate-100 bg-slate-50/50 flex justify-between items-center">
                                <h3 class="font-extrabold text-slate-900">Daftar Kategori</h3>
                                <span class="bg-primary/10 text-primary text-xs font-bold px-3 py-1 rounded-full"><?= $cats->num_rows ?> Kategori</span>
                            </div>
                            <div class="divide-y divide-slate-50">
                                <?php if($cats->num_rows == 0): ?>
                                <div class="px-8 py-12 text-center text-slate-400 font-medium">Belum ada kategori. Tambahkan sekarang!</div>
                                <?php endif; ?>
                                <?php while($cat = $cats->fetch_assoc()): ?>
                                <div class="px-6 py-4 flex items-center justify-between gap-4 hover:bg-slate-50/80 transition-colors group" id="cat-row-<?= $cat['id'] ?>">
                                    <div class="flex items-center gap-4 flex-1">
                                        <div class="w-10 h-10 rounded-xl bg-primary/10 flex items-center justify-center text-primary font-bold text-lg shrink-0">
                                            <?= mb_substr($cat['nama'], 0, 1) ?>
                                        </div>
                                        <!-- View Mode -->
                                        <div class="view-mode-<?= $cat['id'] ?>">
                                            <p class="font-bold text-slate-800 text-sm"><?= htmlspecialchars($cat['nama']) ?></p>
                                            <p class="text-xs text-slate-400">Ikon: <?= htmlspecialchars($cat['ikon'] ?? 'star') ?></p>
                                        </div>
                                        <!-- Edit Mode (hidden) -->
                                        <form method="POST" class="edit-mode-<?= $cat['id'] ?> hidden flex-1 flex gap-2" onsubmit="">
                                            <input type="hidden" name="action" value="edit">
                                            <input type="hidden" name="id" value="<?= $cat['id'] ?>">
                                            <input type="text" name="nama" value="<?= htmlspecialchars($cat['nama']) ?>" class="flex-1 px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm font-medium outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary">
                                            <select name="ikon" class="px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary">
                                                <?php foreach($icon_options as $val => $label): ?>
                                                <option value="<?= $val ?>" <?= ($cat['ikon'] ?? 'star') == $val ? 'selected' : '' ?>><?= $label ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <button type="submit" class="bg-emerald-500 text-white px-4 py-2 rounded-lg text-sm font-bold hover:bg-emerald-600 transition-colors whitespace-nowrap">Simpan</button>
                                        </form>
                                    </div>
                                    <div class="flex items-center gap-2 shrink-0">
                                        <!-- Edit Toggle -->
                                        <button onclick="toggleEdit(<?= $cat['id'] ?>)" class="p-2 rounded-lg bg-slate-100 hover:bg-primary/10 text-slate-500 hover:text-primary transition-colors" title="Edit">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                                        </button>
                                        <!-- Delete -->
                                        <form method="POST" onsubmit="return confirm('Hapus kategori ini?')">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= $cat['id'] ?>">
                                            <button type="submit" class="p-2 rounded-lg bg-slate-100 hover:bg-red-50 text-slate-500 hover:text-red-500 transition-colors" title="Hapus">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                                <?php endwhile; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<script>
function toggleEdit(id) {
    const view = document.querySelector('.view-mode-' + id);
    const edit = document.querySelector('.edit-mode-' + id);
    view.classList.toggle('hidden');
    edit.classList.toggle('hidden');
}
</script>

    <!-- Script for Sidebar Toggle -->
    <script>
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
                        if(sidebar.classList.contains('md:hidden') && sidebarOverlay) {
                            sidebarOverlay.classList.remove('hidden');
                        } else if (sidebarOverlay) {
                            sidebarOverlay.classList.add('hidden');
                        }
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

            window.addEventListener('resize', () => {
                if(sidebar) {
                    if (window.innerWidth >= 768) {
                        sidebar.classList.remove('-translate-x-full');
                        if(sidebarOverlay) sidebarOverlay.classList.add('hidden');
                    } else {
                        sidebar.classList.remove('md:hidden');
                    }
                }
            });
        });
    </script>

</body>
</html>
