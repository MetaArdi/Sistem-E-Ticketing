<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'panitia') {
    header("Location: ../auth/login.php");
    exit;
}
require_once '../config/koneksi.php';

$id_panitia = (int)$_SESSION['user_id'];

// Get current validators count (Prepared Statement)
$count_stmt = $conn->prepare("SELECT COUNT(*) as c FROM users WHERE role = 'validator' AND id_panitia = ?");
$count_stmt->bind_param("i", $id_panitia);
$count_stmt->execute();
$current_count = (int)$count_stmt->get_result()->fetch_assoc()['c'];

$can_add = true;

// Handle POST request
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] == 'add_validator') {
        if ($can_add) {
            $email = trim($_POST['email']);
            $nama = trim($_POST['nama_lengkap']);
            $keterangan = trim($_POST['keterangan']);
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $role = 'validator';
            $status_approval = 'pending';
            
            // Cek email unique (Prepared Statement)
            $check_stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $check_stmt->bind_param("s", $email);
            $check_stmt->execute();
            if ($check_stmt->get_result()->num_rows > 0) {
                $_SESSION['error'] = "Email sudah terdaftar. Gunakan email lain.";
            } else {
                $stmt = $conn->prepare("INSERT INTO users (email, password, role, nama_lengkap, id_panitia, status_approval, keterangan) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("ssssiss", $email, $password, $role, $nama, $id_panitia, $status_approval, $keterangan);
                if ($stmt->execute()) {
                    $_SESSION['success'] = "Validator berhasil didaftarkan dan sedang menunggu persetujuan admin.";
                } else {
                    $_SESSION['error'] = "Terjadi kesalahan sistem saat mendaftarkan validator.";
                }
            }
        }
    } elseif ($_POST['action'] == 'edit_validator') {
        $id_edit = (int)$_POST['edit_id'];
        $nama = trim($_POST['nama_lengkap']);
        $email = trim($_POST['email']);
        $keterangan = trim($_POST['keterangan']);
        $new_pass = $_POST['password'] ?? '';

        // Cek email conflict (Prepared Statement)
        $check_stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $check_stmt->bind_param("si", $email, $id_edit);
        $check_stmt->execute();
        if ($check_stmt->get_result()->num_rows > 0) {
            $_SESSION['error'] = "Email sudah digunakan oleh akun lain.";
        } else {
            if (!empty($new_pass)) {
                $hashed = password_hash($new_pass, PASSWORD_DEFAULT);
                $stmt_upd = $conn->prepare("UPDATE users SET nama_lengkap = ?, email = ?, keterangan = ?, password = ? WHERE id = ? AND id_panitia = ? AND role = 'validator'");
                $stmt_upd->bind_param("ssssii", $nama, $email, $keterangan, $hashed, $id_edit, $id_panitia);
            } else {
                $stmt_upd = $conn->prepare("UPDATE users SET nama_lengkap = ?, email = ?, keterangan = ? WHERE id = ? AND id_panitia = ? AND role = 'validator'");
                $stmt_upd->bind_param("sssii", $nama, $email, $keterangan, $id_edit, $id_panitia);
            }
            if ($stmt_upd->execute()) {
                $_SESSION['success'] = "Data validator berhasil diperbarui.";
            } else {
                $_SESSION['error'] = "Gagal memperbarui data validator.";
            }
        }
    } elseif ($_POST['action'] == 'delete_validator') {
        $id_del = (int)$_POST['delete_id'];
        $stmt_del = $conn->prepare("DELETE FROM users WHERE id = ? AND id_panitia = ? AND role = 'validator'");
        $stmt_del->bind_param("ii", $id_del, $id_panitia);
        $stmt_del->execute();
        $_SESSION['success'] = "Validator berhasil dihapus.";
    }
    
    header("Location: manage_validators.php");
    exit;
}

// Get validators list (Prepared Statement)
$stmt_val = $conn->prepare("SELECT * FROM users WHERE role = 'validator' AND id_panitia = ? ORDER BY id DESC");
$stmt_val->bind_param("i", $id_panitia);
$stmt_val->execute();
$validators = $stmt_val->get_result();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Validator - HaloTiket</title>
    <?php if (isset($global_site_favicon) && $global_site_favicon): ?>
        <link rel="icon" href="<?= $global_site_favicon ?>" type="image/x-icon">
    <?php endif; ?>

    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Outfit', 'sans-serif'] },
                    colors: { primary: '#00c2cb', secondary: '#0f1c3f', dark: '#0a1020' }
                }
            }
        }
    </script>
</head>
<body class="bg-slate-50 font-sans antialiased text-slate-800 selection:bg-primary selection:text-white">
    <div class="flex h-screen overflow-hidden">
        <div id="sidebarOverlay" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-40 hidden md:hidden transition-opacity"></div>
        
        <?php $active_menu = 'validators'; include 'components/sidebar.php'; ?>

        <div class="flex-1 flex flex-col h-screen overflow-hidden bg-slate-50 relative z-10 w-full transition-all duration-300">
            <header class="h-20 bg-white border-b border-slate-200 flex items-center justify-between px-4 lg:px-8 shrink-0 shadow-sm z-20">
                <div class="flex items-center gap-4">
                    <button id="hamburgerBtn" class="text-slate-500 hover:text-slate-700 focus:outline-none p-2 rounded-xl hover:bg-slate-100 transition-colors bg-slate-50 border border-slate-200">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" /></svg>
                    </button>
                    <span class="text-xl font-extrabold text-slate-800 md:hidden">Panitia</span>
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
                    <h1 class="text-3xl font-extrabold text-slate-900 tracking-tight">Kelola Validator</h1>
                    <p class="text-slate-500 mt-2 font-medium">Buat akun untuk petugas scan tiket Anda (memerlukan persetujuan admin).</p>
                </div>
                
                <?php if(isset($_SESSION['success'])): ?>
                    <div class="mb-6 bg-emerald-50 text-emerald-700 px-4 py-3 rounded-xl text-sm font-bold flex items-center gap-3 border border-emerald-100">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" /></svg>
                        <?= $_SESSION['success']; unset($_SESSION['success']); ?>
                    </div>
                <?php endif; ?>
                <?php if(isset($_SESSION['error'])): ?>
                    <div class="mb-6 bg-red-50 text-red-700 px-4 py-3 rounded-xl text-sm font-bold flex items-center gap-3 border border-red-100">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" /></svg>
                        <?= $_SESSION['error']; unset($_SESSION['error']); ?>
                    </div>
                <?php endif; ?>

                <div class="grid grid-cols-1 xl:grid-cols-3 gap-8">
                    <!-- Form Tambah Validator -->
                    <div class="xl:col-span-1">
                        <div class="bg-white rounded-3xl shadow-sm border border-slate-200 overflow-hidden mb-6">
                            <div class="p-6 text-center border-b border-slate-100 bg-slate-50">
                                <h3 class="text-sm font-bold text-slate-500 uppercase tracking-widest mb-2">Total Validator Anda</h3>
                                <div class="text-5xl font-extrabold text-slate-900"><?= $current_count ?></div>
                            </div>
                        </div>

                        <div class="bg-white rounded-3xl shadow-sm border border-slate-200 overflow-hidden">
                            <div class="px-6 py-4 border-b border-slate-100 bg-slate-50/50">
                                <h3 class="font-extrabold text-slate-900 flex items-center gap-2 text-sm">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" /></svg>
                                    Tambah Validator
                                </h3>
                            </div>
                            <div class="p-6">
                                <?php if($can_add): ?>
                                <form action="" method="POST" class="space-y-4">
                                    <input type="hidden" name="action" value="add_validator">
                                    <div>
                                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Nama Petugas</label>
                                        <input type="text" name="nama_lengkap" class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all text-sm font-medium" placeholder="Budi Santoso" required>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Email Login</label>
                                        <input type="email" name="email" class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all text-sm font-medium" placeholder="budi@example.com" required>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Password</label>
                                        <input type="password" name="password" class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all text-sm font-medium" placeholder="••••••••" required>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Keterangan / Pos Jaga</label>
                                        <input type="text" name="keterangan" class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all text-sm font-medium" placeholder="Misal: Pintu Masuk VIP" required>
                                    </div>
                                    
                                    <button type="submit" class="w-full bg-slate-900 hover:bg-primary text-white font-bold py-2.5 px-4 rounded-xl transition-all duration-300 mt-4 shadow-md shadow-slate-900/20 text-sm">
                                        Daftarkan Validator
                                    </button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Tabel Validator -->
                    <div class="xl:col-span-2">
                        <div class="bg-white rounded-3xl shadow-sm border border-slate-200 overflow-hidden">
                            <div class="px-6 py-4 border-b border-slate-100 bg-slate-50/50 flex justify-between items-center">
                                <h3 class="font-extrabold text-slate-900">Daftar Validator Anda</h3>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-slate-100">
                                    <thead class="bg-white">
                                        <tr>
                                            <th class="px-6 py-3 text-left text-[11px] font-bold text-slate-400 uppercase tracking-wider">User Info</th>
                                            <th class="px-6 py-3 text-left text-[11px] font-bold text-slate-400 uppercase tracking-wider">Status</th>
                                            <th class="px-6 py-3 text-right text-[11px] font-bold text-slate-400 uppercase tracking-wider">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-slate-50">
                                        <?php while($row = $validators->fetch_assoc()): ?>
                                        <tr class="hover:bg-slate-50/80 transition-colors group">
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="flex items-center">
                                                    <div class="h-10 w-10 flex-shrink-0 bg-slate-100 rounded-full flex items-center justify-center text-slate-500 font-bold border border-slate-200 group-hover:border-primary group-hover:text-primary transition-colors">
                                                        <?= strtoupper(substr($row['nama_lengkap'], 0, 1)) ?>
                                                    </div>
                                                    <div class="ml-4">
                                                        <div class="text-sm font-bold text-slate-900"><?= htmlspecialchars($row['nama_lengkap']) ?></div>
                                                        <div class="text-[10px] text-slate-500 font-medium"><?= htmlspecialchars($row['keterangan']) ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <?php if($row['status_approval'] == 'pending'): ?>
                                                    <span class="px-3 py-1 inline-flex text-[10px] leading-5 font-bold rounded-full bg-amber-50 text-amber-600 border border-amber-100">Menunggu Persetujuan</span>
                                                <?php elseif($row['status_approval'] == 'rejected'): ?>
                                                    <span class="px-3 py-1 inline-flex text-[10px] leading-5 font-bold rounded-full bg-red-50 text-red-600 border border-red-100">Ditolak</span>
                                                    <div class="text-[9px] text-red-500 mt-1 max-w-[200px] truncate" title="<?= htmlspecialchars((string)$row['reject_reason']) ?>">Alasan: <?= htmlspecialchars((string)$row['reject_reason']) ?></div>
                                                <?php else: ?>
                                                    <span class="px-3 py-1 inline-flex text-[10px] leading-5 font-bold rounded-full bg-emerald-50 text-emerald-700 border border-emerald-100">Disetujui Aktif</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium flex justify-end gap-2">
                                                <button type="button" onclick="openEditModal('<?= $row['id'] ?>', '<?= htmlspecialchars(addslashes($row['nama_lengkap'])) ?>', '<?= htmlspecialchars(addslashes($row['email'])) ?>', '<?= htmlspecialchars(addslashes($row['keterangan'])) ?>')" class="text-indigo-600 hover:text-indigo-900 bg-indigo-50 hover:bg-indigo-100 p-2 rounded-lg transition-colors inline-flex border border-transparent hover:border-indigo-200" title="Edit Validator">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                                                </button>
                                                <form action="" method="POST" class="inline">
                                                    <input type="hidden" name="action" value="delete_validator">
                                                    <input type="hidden" name="delete_id" value="<?= $row['id'] ?>">
                                                    <button type="submit" class="text-red-500 hover:text-red-700 bg-red-50 hover:bg-red-100 p-2 rounded-lg transition-colors inline-flex border border-transparent hover:border-red-200" onclick="return confirm('Hapus validator ini?');" title="Hapus">
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                        <?php if($validators->num_rows == 0): ?>
                                            <tr><td colspan="3" class="px-8 py-12 text-center text-slate-500 font-medium">Belum ada akun validator.</td></tr>
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

    <!-- Modal Edit Validator -->
    <div id="editValidatorModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-3xl shadow-xl border border-slate-200 w-full max-w-md overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100 bg-slate-50 flex items-center justify-between">
                <h3 class="font-extrabold text-slate-900 text-sm">Edit Data Validator</h3>
                <button type="button" onclick="closeEditModal()" class="text-slate-400 hover:text-slate-600 font-bold">&times;</button>
            </div>
            <form action="" method="POST" class="p-6 space-y-4">
                <input type="hidden" name="action" value="edit_validator">
                <input type="hidden" name="edit_id" id="edit_id">
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Nama Petugas</label>
                    <input type="text" name="nama_lengkap" id="edit_nama" class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm font-medium outline-none focus:ring-2 focus:ring-primary/20" required>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Email Login</label>
                    <input type="email" name="email" id="edit_email" class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm font-medium outline-none focus:ring-2 focus:ring-primary/20" required>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Password Baru (Opsional)</label>
                    <input type="password" name="password" placeholder="Kosongkan jika tidak diubah" class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm font-medium outline-none focus:ring-2 focus:ring-primary/20">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Keterangan / Pos Jaga</label>
                    <input type="text" name="keterangan" id="edit_keterangan" class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm font-medium outline-none focus:ring-2 focus:ring-primary/20" required>
                </div>
                <div class="flex items-center justify-end gap-3 pt-2">
                    <button type="button" onclick="closeEditModal()" class="px-4 py-2 text-sm font-bold text-slate-500 hover:text-slate-700 bg-slate-100 rounded-xl">Batal</button>
                    <button type="submit" class="px-5 py-2 text-sm font-bold text-white bg-primary hover:opacity-90 rounded-xl shadow-md">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Script for Modal & Sidebar Toggle -->
    <script>
        function openEditModal(id, nama, email, keterangan) {
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_nama').value = nama;
            document.getElementById('edit_email').value = email;
            document.getElementById('edit_keterangan').value = keterangan;
            document.getElementById('editValidatorModal').classList.remove('hidden');
        }
        function closeEditModal() {
            document.getElementById('editValidatorModal').classList.add('hidden');
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
            if(closeSidebar && sidebar) closeSidebar.addEventListener('click', () => { sidebar.classList.add('-translate-x-full'); if(sidebarOverlay) sidebarOverlay.classList.add('hidden'); });
            if(sidebarOverlay && sidebar) sidebarOverlay.addEventListener('click', () => { sidebar.classList.add('-translate-x-full'); sidebarOverlay.classList.add('hidden'); });
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
