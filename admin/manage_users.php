<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../auth/login.php");
    exit;
}
require_once '../config/koneksi.php';

if (isset($_GET['del'])) {
    $id = (int)$_GET['del'];
    $conn->query("DELETE FROM users WHERE id = $id AND id != {$_SESSION['user_id']}");
    logActivity($conn, $_SESSION['user_id'], 'Delete User', "Menghapus user dengan ID: $id");
    header("Location: manage_users.php");
    exit;
}

if (isset($_GET['approve'])) {
    $id = (int)$_GET['approve'];
    $conn->query("UPDATE users SET status_approval = 'approved' WHERE id = $id AND role = 'validator'");
    logActivity($conn, $_SESSION['user_id'], 'Approve Validator', "Menyetujui validator ID: $id");
    header("Location: manage_users.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'reject_validator') {
    $id = (int)$_POST['reject_id'];
    $reason = $conn->real_escape_string($_POST['reject_reason']);
    $conn->query("UPDATE users SET status_approval = 'rejected', reject_reason = '$reason' WHERE id = $id AND role = 'validator'");
    logActivity($conn, $_SESSION['user_id'], 'Reject Validator', "Menolak validator ID: $id. Alasan: $reason");
    header("Location: manage_users.php");
    exit;
}

$edit_user = null;
if (isset($_GET['edit_id'])) {
    $edit_id = (int)$_GET['edit_id'];
    $stmt_edit = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt_edit->bind_param("i", $edit_id);
    $stmt_edit->execute();
    $edit_user = $stmt_edit->get_result()->fetch_assoc();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);
    $role = $_POST['role'];
    $nama = $_POST['nama_lengkap'];
    $alamat = $_POST['alamat'] ?? '';
    $no_hp = $_POST['no_hp'] ?? '';
    
    // Khusus validator/panitia
    $validator_quota = isset($_POST['validator_quota']) ? (int)$_POST['validator_quota'] : 0;
    $keterangan = $_POST['keterangan'] ?? null;
    $status_approval = ($role == 'validator') ? 'approved' : null;
    
    if (isset($_POST['user_id']) && !empty($_POST['user_id'])) {
        $id = (int)$_POST['user_id'];
        if (!empty($_POST['password'])) {
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET email=?, password=?, role=?, nama_lengkap=?, alamat=?, no_hp=?, validator_quota=?, keterangan=? WHERE id=?");
            $stmt->bind_param("ssssssisi", $email, $password, $role, $nama, $alamat, $no_hp, $validator_quota, $keterangan, $id);
        } else {
            $stmt = $conn->prepare("UPDATE users SET email=?, role=?, nama_lengkap=?, alamat=?, no_hp=?, validator_quota=?, keterangan=? WHERE id=?");
            $stmt->bind_param("sssssisi", $email, $role, $nama, $alamat, $no_hp, $validator_quota, $keterangan, $id);
        }
        $stmt->execute();
        logActivity($conn, $_SESSION['user_id'], 'Edit User', "Mengubah data user ID: $id");
    } else {
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO users (email, password, role, nama_lengkap, alamat, no_hp, validator_quota, keterangan, status_approval) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssssiss", $email, $password, $role, $nama, $alamat, $no_hp, $validator_quota, $keterangan, $status_approval);
        $stmt->execute();
        logActivity($conn, $_SESSION['user_id'], 'Add User', "Menambahkan user baru: $email ($role)");
    }
    header("Location: manage_users.php");
    exit;
}

$filter_role = isset($_GET['role']) && in_array($_GET['role'], ['admin','panitia','validator']) ? $_GET['role'] : '';

$where_clause = "WHERE u.id != {$_SESSION['user_id']}";
if ($filter_role) {
    $where_clause .= " AND u.role = '$filter_role'";
}

$users = $conn->query("
    SELECT u.*, p.nama_lengkap as pembuat_nama 
    FROM users u 
    LEFT JOIN users p ON u.id_panitia = p.id 
    $where_clause 
    ORDER BY u.id DESC
");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - HaloTiket</title>
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
        
        <?php $active_menu = 'users'; include 'components/sidebar.php'; ?>

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
                <div class="mb-8">
                    <h1 class="text-3xl font-extrabold text-slate-900 tracking-tight">Manage Users</h1>
                    <p class="text-slate-500 mt-2 font-medium">Tambahkan admin, panitia, atau validator baru ke sistem.</p>
                </div>
                
                <!-- Form Tambah/Edit User -->
                <div class="bg-white rounded-3xl shadow-sm border border-slate-200 overflow-hidden mb-8">
                    <div class="px-6 py-4 border-b border-slate-100 bg-slate-50/50 flex items-center justify-between cursor-pointer" onclick="document.getElementById('form-user').classList.toggle('hidden')">
                        <h3 class="font-extrabold text-slate-900 flex items-center gap-2 text-sm">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" /></svg>
                            <?= $edit_user ? 'Edit Pengguna' : 'Tambah Pengguna' ?>
                        </h3>
                        <span class="text-xs text-slate-400 font-medium">Klik untuk buka/tutup</span>
                    </div>
                    <div id="form-user" class="p-6 <?= $edit_user ? '' : 'hidden' ?>">
                        <form action="" method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-5">
                            <?php if($edit_user): ?>
                                        <input type="hidden" name="user_id" value="<?= $edit_user['id'] ?>">
                                    <?php endif; ?>
    <div>
        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Nama Lengkap</label>
        <div class="relative">
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg>
            </div>
            <input type="text" name="nama_lengkap" value="<?= $edit_user ? htmlspecialchars($edit_user['nama_lengkap']) : '' ?>" class="w-full pl-9 pr-4 py-2 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all text-sm font-medium" placeholder="John Doe" required>
        </div>
    </div>
    
    <div>
        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Email Login</label>
        <div class="relative">
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>
            </div>
            <input type="email" name="email" value="<?= $edit_user ? htmlspecialchars($edit_user['email']) : '' ?>" class="w-full pl-9 pr-4 py-2 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all text-sm font-medium" placeholder="admin@example.com" required>
        </div>
    </div>
    
    <div>
        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Alamat Lengkap</label>
        <div class="relative">
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
            </div>
            <input type="text" name="alamat" value="<?= $edit_user ? htmlspecialchars($edit_user['alamat']) : '' ?>" class="w-full pl-9 pr-4 py-2 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all text-sm font-medium" placeholder="Jl. Contoh No. 123">
        </div>
    </div>
    
    <div>
        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Nomor Telepon / WA</label>
        <div class="relative">
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" /></svg>
            </div>
            <input type="text" name="no_hp" value="<?= $edit_user ? htmlspecialchars($edit_user['no_hp']) : '' ?>" class="w-full pl-9 pr-4 py-2 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all text-sm font-medium" placeholder="08123456789">
        </div>
    </div>

    <div>
        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Password</label>
        <div class="relative">
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>
            </div>
            <input type="password" name="password" id="password" class="w-full pl-9 pr-10 py-2 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all text-sm font-medium" placeholder="<?= $edit_user ? 'Kosongkan jika tidak ingin diubah' : '••••••••' ?>" <?= $edit_user ? '' : 'required' ?>>
            <button type="button" id="togglePassword" class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-primary transition-colors focus:outline-none">
                <!-- Eye icon -->
                <svg id="eyeIcon" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                <!-- Eye off icon (hidden by default) -->
                <svg id="eyeOffIcon" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" /></svg>
            </button>
        </div>
    </div>
    
    <div>
        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Role</label>
        <div class="relative">
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>
            </div>
            <select name="role" id="role_select" class="w-full pl-9 pr-4 py-2 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all text-sm font-medium appearance-none" required>
                <option value="admin" <?= ($edit_user && $edit_user['role'] == 'admin') ? 'selected' : '' ?>>Admin</option>
                <option value="panitia" <?= ($edit_user && $edit_user['role'] == 'panitia') ? 'selected' : '' ?>>Panitia</option>
                <option value="validator" <?= ($edit_user && $edit_user['role'] == 'validator') ? 'selected' : '' ?>>Validator</option>
            </select>
            <div class="absolute inset-y-0 right-0 flex items-center px-3 pointer-events-none text-slate-400">
                <svg class="w-4 h-4 fill-current" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"/></svg>
            </div>
        </div>
    </div>
    <div id="quota_container" class="<?= ($edit_user && $edit_user['role'] == 'panitia') ? '' : 'hidden' ?>">
        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Set Kuota Validator</label>
        <div class="relative">
            <input type="number" name="validator_quota" id="validator_quota" min="0" value="<?= $edit_user ? (int)$edit_user['validator_quota'] : 0 ?>" class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all text-sm font-medium" placeholder="0">
        </div>
        <p class="text-[10px] text-slate-400 mt-1">*Maksimal akun validator yang bisa dibuat oleh panitia ini.</p>
    </div>

    <div id="keterangan_container" class="<?= ($edit_user && $edit_user['role'] == 'validator') ? '' : 'hidden' ?>">
        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Keterangan / Lokasi Jaga</label>
        <div class="relative">
            <input type="text" name="keterangan" id="keterangan" value="<?= $edit_user ? htmlspecialchars((string)$edit_user['keterangan']) : '' ?>" class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all text-sm font-medium" placeholder="Pintu Masuk VIP">
        </div>
    </div>
    
    <script>
        document.getElementById('role_select').addEventListener('change', function() {
            var role = this.value;
            var quotaContainer = document.getElementById('quota_container');
            var ketContainer = document.getElementById('keterangan_container');
            
            if (role === 'panitia') {
                quotaContainer.classList.remove('hidden');
            } else {
                quotaContainer.classList.add('hidden');
            }
            
            if (role === 'validator') {
                ketContainer.classList.remove('hidden');
            } else {
                ketContainer.classList.add('hidden');
            }
        });
    </script>
    
    <div class="md:col-span-2">
        <button type="submit" class="w-full bg-slate-900 hover:bg-primary text-white font-bold py-3 px-4 rounded-xl transition-all duration-300 mt-2 shadow-md shadow-slate-900/20 flex items-center justify-center gap-2 group text-sm">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 group-hover:scale-110 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
            <?= $edit_user ? 'Update Pengguna' : 'Simpan Pengguna' ?>
        </button>
    </div>
</form>
                    </div>
                </div>

                <!-- Tabel User -->
                <div class="bg-white rounded-3xl shadow-sm border border-slate-200 overflow-hidden">
                            <div class="px-6 py-4 border-b border-slate-100 bg-slate-50/50 flex justify-between items-center flex-wrap gap-4">
                                <div class="flex items-center gap-4">
                                    <h3 class="font-extrabold text-slate-900">Daftar Pengguna</h3>
                                    <span class="bg-indigo-50 text-primary text-xs font-bold px-3 py-1 rounded-full">Total: <?= $users->num_rows ?></span>
                                </div>
                                <form method="GET" action="" class="flex items-center gap-2 text-sm">
                                    <label for="filter_role" class="font-bold text-slate-500">Filter Role:</label>
                                    <select name="role" id="filter_role" onchange="this.form.submit()" class="bg-white border border-slate-200 rounded-lg px-3 py-1 focus:ring-2 focus:ring-primary/20 focus:border-primary font-medium text-slate-700">
                                        <option value="">Semua Role</option>
                                        <option value="admin" <?= $filter_role == 'admin' ? 'selected' : '' ?>>Admin</option>
                                        <option value="panitia" <?= $filter_role == 'panitia' ? 'selected' : '' ?>>Panitia</option>
                                        <option value="validator" <?= $filter_role == 'validator' ? 'selected' : '' ?>>Validator</option>
                                    </select>
                                </form>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-slate-100">
                                    <thead class="bg-white">
                                        <tr>
                                            <th class="px-6 py-3 text-left text-[11px] font-bold text-slate-400 uppercase tracking-wider">User Info</th>
                                            <th class="px-6 py-3 text-left text-[11px] font-bold text-slate-400 uppercase tracking-wider">Role</th>
                                            <th class="px-6 py-3 text-right text-[11px] font-bold text-slate-400 uppercase tracking-wider">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-slate-50">
                                        <?php while($row = $users->fetch_assoc()): ?>
                                        <tr class="hover:bg-slate-50/80 transition-colors group">
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="flex items-center">
                                                    <?php if(!empty($row['foto_profil']) && file_exists('../assets/images/profil/'.$row['foto_profil'])): ?>
                                                        <img src="../assets/images/profil/<?= htmlspecialchars($row['foto_profil']) ?>" class="h-10 w-10 flex-shrink-0 rounded-full object-cover border border-slate-200 group-hover:border-primary transition-colors">
                                                    <?php else: ?>
                                                        <div class="h-10 w-10 flex-shrink-0 bg-slate-100 rounded-full flex items-center justify-center text-slate-500 font-bold border border-slate-200 group-hover:border-primary group-hover:text-primary transition-colors">
                                                            <?= strtoupper(substr($row['nama_lengkap'], 0, 1)) ?>
                                                        </div>
                                                    <?php endif; ?>
                                                    <div class="ml-4">
                                                        <div class="text-sm font-bold text-slate-900"><?= htmlspecialchars($row['nama_lengkap']) ?></div>
                                                        <div class="text-xs font-medium text-slate-500"><?= htmlspecialchars($row['email']) ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <?php if($row['role'] == 'panitia'): ?>
                                                    <span class="px-3 py-1 inline-flex text-xs leading-5 font-bold rounded-full bg-violet-50 text-violet-700 border border-violet-100 mb-1">Panitia</span>
                                                    <div class="text-[10px] text-slate-500 font-medium">Kuota Validator: <?= $row['validator_quota'] ?></div>
                                                <?php else: ?>
                                                    <span class="px-3 py-1 inline-flex text-xs leading-5 font-bold rounded-full bg-emerald-50 text-emerald-700 border border-emerald-100 mb-1">Validator</span>
                                                    <div class="text-[10px] text-slate-500 font-medium">Dibuat oleh: <?= $row['pembuat_nama'] ? htmlspecialchars($row['pembuat_nama']) : 'Admin' ?></div>
                                                    <?php if($row['status_approval'] == 'pending'): ?>
                                                        <span class="px-2 py-0.5 inline-flex text-[10px] leading-5 font-bold rounded-full bg-amber-50 text-amber-600 border border-amber-100">Pending</span>
                                                    <?php elseif($row['status_approval'] == 'rejected'): ?>
                                                        <span class="px-2 py-0.5 inline-flex text-[10px] leading-5 font-bold rounded-full bg-red-50 text-red-600 border border-red-100" title="<?= htmlspecialchars((string)$row['reject_reason']) ?>">Ditolak</span>
                                                    <?php else: ?>
                                                        <span class="px-2 py-0.5 inline-flex text-[10px] leading-5 font-bold rounded-full bg-blue-50 text-blue-600 border border-blue-100">Approved</span>
                                                    <?php endif; ?>
                                                    <?php if(!empty($row['keterangan'])): ?>
                                                        <div class="text-[10px] text-slate-500 mt-1 max-w-[150px] truncate" title="<?= htmlspecialchars($row['keterangan']) ?>"><?= htmlspecialchars($row['keterangan']) ?></div>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                                <?php if($row['role'] == 'validator' && $row['status_approval'] == 'pending'): ?>
                                                    <a href="manage_users.php?approve=<?= $row['id'] ?>" class="text-emerald-500 hover:text-emerald-700 bg-emerald-50 hover:bg-emerald-100 p-2 rounded-lg transition-colors inline-flex border border-transparent hover:border-emerald-200 mr-1" title="Approve" onclick="return confirm('Setujui validator ini?');">
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                                                    </a>
                                                    <button type="button" onclick="rejectValidator(<?= $row['id'] ?>)" class="text-amber-500 hover:text-amber-700 bg-amber-50 hover:bg-amber-100 p-2 rounded-lg transition-colors inline-flex border border-transparent hover:border-amber-200 mr-1" title="Tolak">
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                                    </button>
                                                <?php endif; ?>
                                                <a href="manage_users.php?edit_id=<?= $row['id'] ?>" class="text-blue-500 hover:text-blue-700 bg-blue-50 hover:bg-blue-100 p-2 rounded-lg transition-colors inline-flex border border-transparent hover:border-blue-200 mr-1" title="Edit">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                                                </a>
                                                <a href="manage_users.php?del=<?= $row['id'] ?>" class="text-red-500 hover:text-red-700 bg-red-50 hover:bg-red-100 p-2 rounded-lg transition-colors inline-flex border border-transparent hover:border-red-200" onclick="return confirm('Hapus pengguna ini secara permanen?');" title="Hapus">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                                </a>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                        <?php if($users->num_rows == 0): ?>
                                            <tr><td colspan="3" class="px-8 py-12 text-center text-slate-500 font-medium">Belum ada user yang ditambahkan.</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
            </main>
        </div>
    </div>

    <script>
        const togglePassword = document.getElementById('togglePassword');
        const password = document.getElementById('password');
        const eyeIcon = document.getElementById('eyeIcon');
        const eyeOffIcon = document.getElementById('eyeOffIcon');

        togglePassword.addEventListener('click', function (e) {
            const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
            password.setAttribute('type', type);
            
            if (type === 'text') {
                eyeIcon.classList.add('hidden');
                eyeOffIcon.classList.remove('hidden');
            } else {
                eyeIcon.classList.remove('hidden');
                eyeOffIcon.classList.add('hidden');
            }
        });
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
        
        function rejectValidator(id) {
            let reason = prompt("Masukkan alasan penolakan untuk validator ini:");
            if (reason != null && reason.trim() !== "") {
                document.getElementById('reject_id').value = id;
                document.getElementById('reject_reason').value = reason;
                document.getElementById('rejectForm').submit();
            } else if (reason != null) {
                alert("Alasan penolakan tidak boleh kosong.");
            }
        }
    </script>

    <!-- Hidden form for rejection -->
    <form id="rejectForm" action="" method="POST" class="hidden">
        <input type="hidden" name="action" value="reject_validator">
        <input type="hidden" name="reject_id" id="reject_id" value="">
        <input type="hidden" name="reject_reason" id="reject_reason" value="">
    </form>
</body>
</html>
