<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'panitia') {
    header("Location: ../auth/login.php");
    exit;
}
require_once '../config/koneksi.php';

$success_msg = '';
$error_msg = '';
$user_id = (int)($_SESSION['user_id'] ?? 0);

if ($user_id <= 0) {
    header("Location: ../auth/login.php");
    exit;
}

// Get current user info (including bank details)
$stmt = $conn->prepare("SELECT email, nama_lengkap, foto_profil, nama_bank, no_rekening, nama_rekening FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_data = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user_data) {
    $user_data = [
        'nama_lengkap' => $_SESSION['nama_lengkap'] ?? 'Panitia Event',
        'email' => $_SESSION['email'] ?? '',
        'foto_profil' => $_SESSION['foto_profil'] ?? '',
        'nama_bank' => '',
        'no_rekening' => '',
        'nama_rekening' => ''
    ];
}

// Update Profile Logic
if ($_SERVER['REQUEST_METHOD'] == 'POST' && !isset($_POST['withdraw_form']) && !isset($_POST['bank_form'])) {
    $nama_lengkap = trim($_POST['nama_lengkap']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $password_confirm = $_POST['password_confirm'];

    if (empty($nama_lengkap) || empty($email)) {
        $error_msg = "Nama Lengkap dan Email tidak boleh kosong.";
    } else {
        // Cek apakah email sudah dipakai user lain
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->bind_param("si", $email, $user_id);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $error_msg = "Email tersebut sudah terdaftar pada akun lain.";
        }
        $stmt->close();

        if (empty($error_msg)) {
            // Handle foto profil upload
            $foto_profil = $user_data['foto_profil'];
            
            // Check if there is a cropped image from the modal (Base64)
            if (isset($_POST['cropped_image']) && !empty($_POST['cropped_image'])) {
                $img_data = $_POST['cropped_image'];
                if (preg_match('/^data:image\/(\w+);base64,/', $img_data, $type)) {
                    $img_data = substr($img_data, strpos($img_data, ',') + 1);
                    $ext = strtolower($type[1]);
                    if (in_array($ext, ['jpg', 'jpeg', 'png'])) {
                        $img_data = base64_decode($img_data);
                        if ($img_data !== false) {
                            $new_filename = uniqid('profil_') . '.' . $ext;
                            $upload_dir = '../assets/images/profil/';
                            if (!is_dir($upload_dir)) {
                                mkdir($upload_dir, 0755, true);
                            }
                            $upload_path = $upload_dir . $new_filename;
                            if (file_put_contents($upload_path, $img_data)) {
                                $foto_profil = $new_filename;
                            } else {
                                $error_msg = "Gagal mengunggah foto profil.";
                            }
                        } else {
                            $error_msg = "Gagal memproses foto profil.";
                        }
                    } else {
                        $error_msg = "Format foto tidak didukung. Hanya JPG, JPEG, dan PNG.";
                    }
                }
            } elseif (isset($_FILES['foto_profil']) && $_FILES['foto_profil']['error'] == 0) {
                $allowed = ['jpg', 'jpeg', 'png'];
                $filename = $_FILES['foto_profil']['name'];
                $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                $filesize = $_FILES['foto_profil']['size'];

                if (!in_array($ext, $allowed)) {
                    $error_msg = "Format foto tidak didukung. Hanya JPG, JPEG, dan PNG.";
                } elseif ($filesize > 2097152) {
                    $error_msg = "Ukuran foto maksimal 2MB.";
                } else {
                    $new_filename = uniqid('profil_') . '.' . $ext;
                    $upload_dir = '../assets/images/profil/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }
                    $upload_path = $upload_dir . $new_filename;
                    if (move_uploaded_file($_FILES['foto_profil']['tmp_name'], $upload_path)) {
                        $foto_profil = $new_filename;
                    } else {
                        $error_msg = "Gagal mengunggah foto profil.";
                    }
                }
            }

            if (empty($error_msg)) {
                if (!empty($password)) {
                    if ($password !== $password_confirm) {
                        $error_msg = "Konfirmasi password tidak cocok.";
                    } else {
                        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = $conn->prepare("UPDATE users SET nama_lengkap = ?, email = ?, password = ?, foto_profil = ? WHERE id = ?");
                        $stmt->bind_param("ssssi", $nama_lengkap, $email, $hashed_password, $foto_profil, $user_id);
                    }
                } else {
                    $stmt = $conn->prepare("UPDATE users SET nama_lengkap = ?, email = ?, foto_profil = ? WHERE id = ?");
                    $stmt->bind_param("sssi", $nama_lengkap, $email, $foto_profil, $user_id);
                }
            }

            if (empty($error_msg) && isset($stmt)) {
                $old_photo = $user_data['foto_profil'];
                if ($stmt->execute()) {
                    if (!empty($old_photo) && $old_photo !== $foto_profil && !str_starts_with($old_photo, 'http')) {
                        $old_path = '../assets/images/profil/' . $old_photo;
                        if (file_exists($old_path) && is_file($old_path)) {
                            @unlink($old_path);
                        }
                    }
                    $_SESSION['nama_lengkap'] = $nama_lengkap;
                    $_SESSION['foto_profil'] = $foto_profil;
                    $success_msg = "Profil berhasil diperbarui.";
                    $user_data['nama_lengkap'] = $nama_lengkap;
                    $user_data['email'] = $email;
                    $user_data['foto_profil'] = $foto_profil;
                    
                    logActivity($conn, $user_id, 'Update Profile', 'Panitia memperbarui data profil akunnya.');
                } else {
                    $error_msg = "Terjadi kesalahan saat menyimpan data.";
                }
                $stmt->close();
            }
        }
    }
}

// Flash messages for withdraw
$success_withdraw = $_SESSION['success_withdraw'] ?? '';
$error_withdraw = $_SESSION['error_withdraw'] ?? '';
unset($_SESSION['success_withdraw'], $_SESSION['error_withdraw']);

// Calculation for Vendor Saldo & Withdrawals
$stmt_rev = $conn->prepare("
    SELECT SUM(COALESCE(v.harga, e.harga)) as total_earnings
    FROM tickets t
    JOIN events e ON t.id_event = e.id
    LEFT JOIN event_ticket_variants v ON t.id_ticket_variant = v.id
    WHERE e.id_panitia = ? AND t.status IN ('lunas', 'scanned')
");
$stmt_rev->bind_param("i", $user_id);
$stmt_rev->execute();
$total_earnings = (float)($stmt_rev->get_result()->fetch_assoc()['total_earnings'] ?? 0);
$stmt_rev->close();

$stmt_wd_approved = $conn->prepare("SELECT SUM(amount) as total_approved FROM withdrawals WHERE user_id = ? AND status = 'approved'");
$stmt_wd_approved->bind_param("i", $user_id);
$stmt_wd_approved->execute();
$total_approved_withdraw = (float)($stmt_wd_approved->get_result()->fetch_assoc()['total_approved'] ?? 0);
$stmt_wd_approved->close();

$stmt_wd_pending = $conn->prepare("SELECT SUM(amount) as total_pending FROM withdrawals WHERE user_id = ? AND status = 'pending'");
$stmt_wd_pending->bind_param("i", $user_id);
$stmt_wd_pending->execute();
$total_pending_withdraw = (float)($stmt_wd_pending->get_result()->fetch_assoc()['total_pending'] ?? 0);
$stmt_wd_pending->close();

$available_balance = $total_earnings - ($total_approved_withdraw + $total_pending_withdraw);

// Fetch withdrawal history
$stmt_history = $conn->prepare("SELECT * FROM withdrawals WHERE user_id = ? ORDER BY created_at DESC");
$stmt_history->bind_param("i", $user_id);
$stmt_history->execute();
$withdraw_history = $stmt_history->get_result();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil Saya & Tarik Tunai - HaloTiket Panitia</title>
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.css">
    <style>
        .cropper-view-box, .cropper-face { border-radius: 50%; }
        .cropper-line, .cropper-point { display: none !important; }
        .cropper-view-box { outline: 2px solid #00c2cb; outline-color: rgba(0, 194, 203, 0.7); }
    </style>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
</head>
<body class="bg-slate-50 font-sans antialiased text-slate-800 selection:bg-primary selection:text-white overflow-hidden">
<div class="flex h-screen w-full">
    <div id="sidebarOverlay" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-40 hidden md:hidden transition-opacity"></div>
    <?php $active_menu = 'withdrawal'; include 'components/sidebar.php'; ?>

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
                <span class="text-xl font-extrabold text-slate-800 md:hidden">Profil Saya</span>
            </div>
            
            <div class="flex items-center gap-4">
                <a href="profile.php" class="hidden md:flex items-center gap-3 mr-2 px-3 py-1.5 rounded-full border border-slate-100 bg-slate-50 hover:bg-slate-100 hover:border-slate-200 transition-colors group cursor-pointer">
                    <?php if (isset($_SESSION['foto_profil']) && !empty($_SESSION['foto_profil']) && file_exists('../assets/images/profil/'.$_SESSION['foto_profil'])): ?>
                        <img src="../assets/images/profil/<?= htmlspecialchars($_SESSION['foto_profil']) ?>" class="w-8 h-8 rounded-full object-cover shadow-sm">
                    <?php else: ?>
                        <div class="w-8 h-8 rounded-full bg-gradient-to-br from-primary to-secondary text-white flex items-center justify-center font-bold text-sm shadow-sm group-hover:shadow transition-all">
                            <?= strtoupper(substr($_SESSION['nama_lengkap'] ?? 'P', 0, 1)) ?>
                        </div>
                    <?php endif; ?>
                    <span class="text-sm font-bold text-slate-700 pr-2 group-hover:text-primary transition-colors"><?= htmlspecialchars($_SESSION['nama_lengkap'] ?? 'Panitia') ?></span>
                </a>
            </div>
        </header>

        <main class="flex-1 overflow-y-auto p-4 md:p-6 lg:p-10">
            <div class="max-w-4xl mx-auto space-y-10">
                
                <!-- Section 1: Profil Panitia -->
                <div>
                    <div class="mb-6">
                        <h1 class="text-2xl md:text-3xl font-extrabold text-slate-900 tracking-tight">Profil Saya</h1>
                        <p class="text-slate-500 mt-1 font-medium text-sm">Kelola informasi data diri dan kata sandi akun Anda.</p>
                    </div>

                    <?php if($success_msg): ?>
                    <div class="mb-6 bg-emerald-50 text-emerald-700 px-5 py-4 rounded-xl text-sm font-bold flex items-center gap-3 border border-emerald-200">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                        <?= htmlspecialchars($success_msg) ?>
                    </div>
                    <?php endif; ?>

                    <?php if($error_msg): ?>
                    <div class="mb-6 bg-red-50 text-red-600 px-5 py-4 rounded-xl text-sm font-bold flex items-center gap-3 border border-red-200">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                        <?= htmlspecialchars($error_msg) ?>
                    </div>
                    <?php endif; ?>

                    <div class="bg-white rounded-3xl shadow-sm border border-slate-200 overflow-hidden">
                        <div class="px-6 py-6 border-b border-slate-100 bg-slate-50/50 flex justify-between items-center">
                            <div class="flex items-center gap-4">
                                <?php if (isset($user_data['foto_profil']) && !empty($user_data['foto_profil']) && file_exists('../assets/images/profil/'.$user_data['foto_profil'])): ?>
                                    <img id="profile-avatar-img" src="../assets/images/profil/<?= htmlspecialchars($user_data['foto_profil']) ?>" class="w-16 h-16 rounded-2xl object-cover shadow-md">
                                <?php else: ?>
                                    <div id="profile-avatar-placeholder" class="w-16 h-16 rounded-2xl bg-gradient-to-br from-primary to-secondary text-white flex items-center justify-center font-bold text-2xl shadow-md">
                                        <?= strtoupper(substr($user_data['nama_lengkap'] ?? 'P', 0, 1)) ?>
                                    </div>
                                <?php endif; ?>
                                <div>
                                    <h3 class="font-extrabold text-slate-900 text-lg"><?= htmlspecialchars($user_data['nama_lengkap'] ?? 'Panitia Event') ?></h3>
                                    <p class="text-sm font-bold text-primary uppercase tracking-wider">Panitia Event</p>
                                </div>
                            </div>
                        </div>
                        <div class="p-6 md:p-8">
                            <form method="POST" action="" enctype="multipart/form-data" class="space-y-5">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                                    <div class="space-y-4">
                                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider">Nama Lengkap</label>
                                        <input type="text" name="nama_lengkap" value="<?= htmlspecialchars($user_data['nama_lengkap']) ?>" required class="w-full bg-slate-50 border border-slate-200 text-slate-800 text-sm rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary block px-4 py-2.5 transition-colors font-medium">
                                    </div>
                                    <div class="space-y-4">
                                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider">Alamat Email</label>
                                        <input type="email" name="email" value="<?= htmlspecialchars($user_data['email']) ?>" required class="w-full bg-slate-50 border border-slate-200 text-slate-800 text-sm rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary block px-4 py-2.5 transition-colors font-medium">
                                    </div>
                                </div>
                                
                                <div class="space-y-4 mt-5">
                                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider">Foto Profil <span class="text-slate-400 font-normal">(Opsional)</span></label>
                                    <input type="hidden" name="cropped_image" id="cropped_image_input">
                                    <input type="file" id="foto_profil_input" name="foto_profil" accept="image/jpeg, image/png, image/jpg" class="w-full bg-slate-50 border border-slate-200 text-slate-800 text-sm rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary block px-4 py-2.5 transition-colors font-medium file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-bold file:bg-primary/10 file:text-primary hover:file:bg-primary/20 cursor-pointer">
                                    <p class="text-xs text-slate-500 font-medium mt-1">Format: JPG, JPEG, PNG. Maksimal ukuran: 2MB.</p>
                                </div>

                                <hr class="border-slate-100 my-6">
                                
                                <div>
                                    <h4 class="text-sm font-bold text-slate-800 mb-4">Ubah Kata Sandi <span class="text-slate-400 font-normal">(Kosongkan jika tidak ingin mengubah)</span></h4>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                                        <div class="space-y-4">
                                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider">Password Baru</label>
                                            <div class="relative">
                                                <input type="password" id="password" name="password" placeholder="••••••••" class="w-full bg-slate-50 border border-slate-200 text-slate-800 text-sm rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary block px-4 py-2.5 transition-colors font-medium pr-10">
                                                <button type="button" onclick="togglePasswordVisibility('password', 'eyeIcon1', 'eyeOffIcon1')" class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-slate-600 focus:outline-none">
                                                    <svg id="eyeIcon1" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                                                    <svg id="eyeOffIcon1" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" /></svg>
                                                </button>
                                            </div>
                                        </div>
                                        <div class="space-y-4">
                                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider">Konfirmasi Password Baru</label>
                                            <div class="relative">
                                                <input type="password" id="password_confirm" name="password_confirm" placeholder="••••••••" class="w-full bg-slate-50 border border-slate-200 text-slate-800 text-sm rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary block px-4 py-2.5 transition-colors font-medium pr-10">
                                                <button type="button" onclick="togglePasswordVisibility('password_confirm', 'eyeIcon2', 'eyeOffIcon2')" class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-slate-600 focus:outline-none">
                                                    <svg id="eyeIcon2" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                                                    <svg id="eyeOffIcon2" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" /></svg>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="pt-6 flex justify-end">
                                    <button type="submit" class="bg-primary hover:bg-primary/90 text-white font-bold py-2.5 px-6 rounded-xl transition-all duration-300 transform hover:-translate-y-0.5 shadow-lg shadow-primary/30 text-sm flex items-center gap-2">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                                        Simpan Perubahan Profil
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Section 2: Saldo Vendor & Fitur Tarik Tunai -->
                <div id="withdrawal" class="pt-6 border-t border-slate-200">
                    <div class="mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                        <div>
                            <h2 class="text-2xl font-extrabold text-slate-900 tracking-tight flex items-center gap-3">
                                <span class="w-8 h-8 rounded-xl bg-primary/10 text-primary flex items-center justify-center text-lg">💸</span>
                                Saldo Vendor & Tarik Tunai
                            </h2>
                            <p class="text-slate-500 mt-1 font-medium text-sm">Kelola rekening penampung dan ajukan pencairan saldo pendapatan tiket Anda.</p>
                        </div>
                    </div>

                    <?php if($success_withdraw): ?>
                    <div class="mb-6 bg-emerald-50 text-emerald-700 px-5 py-4 rounded-xl text-sm font-bold flex items-center gap-3 border border-emerald-200 shadow-sm">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                        <?= htmlspecialchars($success_withdraw) ?>
                    </div>
                    <?php endif; ?>

                    <?php if($error_withdraw): ?>
                    <div class="mb-6 bg-red-50 text-red-600 px-5 py-4 rounded-xl text-sm font-bold flex items-center gap-3 border border-red-200 shadow-sm">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                        <?= htmlspecialchars($error_withdraw) ?>
                    </div>
                    <?php endif; ?>

                    <!-- Balance Summary Cards -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                        <div class="bg-gradient-to-br from-slate-900 to-indigo-950 rounded-3xl p-6 text-white shadow-xl relative overflow-hidden flex flex-col justify-between">
                            <div class="absolute -right-6 -bottom-6 w-32 h-32 bg-primary/20 rounded-full blur-2xl"></div>
                            <div>
                                <p class="text-xs font-bold uppercase tracking-wider text-indigo-300 mb-1">Saldo Tersedia (Siap Ditarik)</p>
                                <h3 class="text-3xl font-extrabold font-mono text-white">Rp <?= number_format($available_balance, 0, ',', '.') ?></h3>
                                <p class="text-xs text-slate-300 mt-2 font-medium">Bisa diajukan penarikan ke rekening bank Anda</p>
                            </div>
                            <div class="mt-6 pt-4 border-t border-indigo-900/50">
                                <button onclick="openWithdrawModal()" class="w-full bg-primary hover:bg-primary/90 text-white font-bold py-2.5 px-4 rounded-xl shadow-lg transition-all text-sm flex items-center justify-center gap-2">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" /></svg>
                                    Ajukan Tarik Tunai
                                </button>
                            </div>
                        </div>

                        <div class="bg-gradient-to-br from-emerald-500 to-teal-600 rounded-3xl p-6 text-white shadow-lg shadow-emerald-500/20 flex flex-col justify-between">
                            <div>
                                <p class="text-xs font-bold uppercase tracking-wider text-emerald-100 mb-1">Total Pendapatan Tiket</p>
                                <h3 class="text-3xl font-extrabold font-mono">Rp <?= number_format($total_earnings, 0, ',', '.') ?></h3>
                                <p class="text-xs text-emerald-100 mt-2 font-medium">Akumulasi bersih dari tiket lunas & scanned</p>
                            </div>
                            <div class="mt-6 text-xs text-emerald-100/80 font-medium">
                                Otomatis terakumulasi per transaksi lunas
                            </div>
                        </div>

                        <div class="bg-gradient-to-br from-slate-800 to-slate-900 rounded-3xl p-6 text-white shadow-lg shadow-slate-900/20 flex flex-col justify-between">
                            <div>
                                <p class="text-xs font-bold uppercase tracking-wider text-slate-300 mb-1">Total Pencairan & Pending</p>
                                <h3 class="text-3xl font-extrabold font-mono">Rp <?= number_format($total_approved_withdraw + $total_pending_withdraw, 0, ',', '.') ?></h3>
                                <div class="mt-2 text-xs text-slate-300 space-y-0.5">
                                    <p>• Disetujui: <span class="font-bold text-emerald-400">Rp <?= number_format($total_approved_withdraw, 0, ',', '.') ?></span></p>
                                    <p>• Pending: <span class="font-bold text-amber-400">Rp <?= number_format($total_pending_withdraw, 0, ',', '.') ?></span></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Bank Account Settings Card -->
                    <div class="bg-white rounded-3xl shadow-sm border border-slate-200 overflow-hidden mb-8">
                        <div class="px-6 py-4 border-b border-slate-100 bg-slate-50/50 flex items-center justify-between">
                            <h3 class="font-extrabold text-slate-900 text-sm flex items-center gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" /></svg>
                                Informasi Rekening Bank Penampung
                            </h3>
                            <span class="text-xs font-bold text-slate-400">Diperlukan untuk Pencairan</span>
                        </div>
                        <div class="p-6 md:p-8">
                            <form method="POST" action="actions/proses_withdraw.php" class="space-y-5">
                                <input type="hidden" name="bank_form" value="1">
                                <input type="hidden" name="action" value="update_bank">
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                                    <div class="space-y-2">
                                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider">Nama Bank</label>
                                        <select name="nama_bank" required class="w-full bg-slate-50 border border-slate-200 text-slate-800 text-sm rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary block px-4 py-2.5 transition-colors font-medium">
                                            <option value="">-- Pilih Bank --</option>
                                            <?php 
                                            $banks = ['Bank BCA', 'Bank Mandiri', 'Bank BNI', 'Bank BRI', 'Bank Syariah Indonesia (BSI)', 'Bank CIMB Niaga', 'Bank Permata', 'Bank Danamon', 'Bank Tabungan Negara (BTN)', 'Lainnya'];
                                            foreach($banks as $b): ?>
                                                <option value="<?= $b ?>" <?= ($user_data['nama_bank'] ?? '') == $b ? 'selected' : '' ?>><?= $b ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="space-y-2">
                                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider">Nomor Rekening</label>
                                        <input type="text" name="no_rekening" value="<?= htmlspecialchars($user_data['no_rekening'] ?? '') ?>" required placeholder="Contoh: 1234567890" class="w-full bg-slate-50 border border-slate-200 text-slate-800 text-sm rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary block px-4 py-2.5 transition-colors font-medium">
                                    </div>

                                    <div class="space-y-2">
                                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider">Atas Nama Rekening</label>
                                        <input type="text" name="nama_rekening" value="<?= htmlspecialchars($user_data['nama_rekening'] ?? '') ?>" required placeholder="Sesuai buku tabungan" class="w-full bg-slate-50 border border-slate-200 text-slate-800 text-sm rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary block px-4 py-2.5 transition-colors font-medium">
                                    </div>
                                </div>

                                <div class="pt-2 flex justify-end">
                                    <button type="submit" class="bg-slate-800 hover:bg-slate-900 text-white font-bold py-2.5 px-6 rounded-xl transition-all text-sm flex items-center gap-2">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                                        Simpan Rekening Bank
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Withdrawal History Table -->
                    <div class="bg-white rounded-3xl shadow-sm border border-slate-200 overflow-hidden">
                        <div class="px-6 py-4 border-b border-slate-100 bg-slate-50/50 flex justify-between items-center">
                            <h3 class="font-extrabold text-slate-900 text-sm">Riwayat Penarikan Dana</h3>
                            <span class="bg-primary/10 text-primary text-xs font-bold px-3 py-1 rounded-full">Total: <?= $withdraw_history->num_rows ?> Transaksi</span>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-slate-100">
                                <thead class="bg-white">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-[11px] font-bold text-slate-400 uppercase tracking-wider">ID & Waktu</th>
                                        <th class="px-6 py-3 text-left text-[11px] font-bold text-slate-400 uppercase tracking-wider">Rekening Tujuan</th>
                                        <th class="px-6 py-3 text-left text-[11px] font-bold text-slate-400 uppercase tracking-wider">Nominal Penarikan</th>
                                        <th class="px-6 py-3 text-left text-[11px] font-bold text-slate-400 uppercase tracking-wider">Status</th>
                                        <th class="px-6 py-3 text-left text-[11px] font-bold text-slate-400 uppercase tracking-wider">Catatan Admin</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-slate-50">
                                    <?php if($withdraw_history->num_rows > 0): ?>
                                        <?php while($wh = $withdraw_history->fetch_assoc()): ?>
                                        <tr class="hover:bg-slate-50/80 transition-colors">
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-xs font-bold text-slate-900">#WD-<?= $wh['id'] ?></div>
                                                <div class="text-[11px] font-medium text-slate-400 mt-1"><?= date('d M Y, H:i', strtotime($wh['created_at'])) ?></div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm font-bold text-slate-800"><?= htmlspecialchars($wh['bank_name']) ?></div>
                                                <div class="text-xs font-mono text-slate-600"><?= htmlspecialchars($wh['account_number']) ?></div>
                                                <div class="text-[11px] text-slate-400">a.n <?= htmlspecialchars($wh['account_name']) ?></div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm font-extrabold font-mono text-slate-900">Rp <?= number_format($wh['amount'], 0, ',', '.') ?></div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <?php if($wh['status'] == 'approved'): ?>
                                                    <span class="px-2.5 py-1 rounded-md text-[11px] font-bold uppercase tracking-wider bg-emerald-100 text-emerald-700">Disetujui</span>
                                                <?php elseif($wh['status'] == 'rejected'): ?>
                                                    <span class="px-2.5 py-1 rounded-md text-[11px] font-bold uppercase tracking-wider bg-red-100 text-red-700">Ditolak</span>
                                                <?php else: ?>
                                                    <span class="px-2.5 py-1 rounded-md text-[11px] font-bold uppercase tracking-wider bg-amber-100 text-amber-700 animate-pulse">Menunggu Verification</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-6 py-4 text-xs font-medium text-slate-500">
                                                <?= !empty($wh['admin_note']) ? htmlspecialchars($wh['admin_note']) : '-' ?>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="5" class="px-6 py-8 text-center text-slate-400 font-medium">
                                                Belum ada riwayat penarikan dana.
                                            </td>
                                        </tr>
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

<!-- Modal Request Withdrawal -->
<div id="withdrawModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl shadow-xl w-full max-w-md overflow-hidden transform scale-95 opacity-0 transition-all duration-300" id="withdrawModalContent">
        <form method="POST" action="actions/proses_withdraw.php">
            <input type="hidden" name="withdraw_form" value="1">
            <input type="hidden" name="action" value="request_withdraw">
            <div class="px-6 py-4 border-b border-slate-100 bg-slate-900 text-white flex justify-between items-center">
                <h3 class="font-extrabold text-base">Pengajuan Tarik Tunai</h3>
                <button type="button" onclick="closeWithdrawModal()" class="text-slate-400 hover:text-white text-2xl font-bold">&times;</button>
            </div>
            <div class="p-6 space-y-4">
                <div class="bg-indigo-50 border border-indigo-100 p-4 rounded-2xl text-xs space-y-1 text-indigo-900">
                    <p>Saldo Maksimal yang Bisa Ditarik:</p>
                    <p class="text-xl font-extrabold font-mono text-primary">Rp <?= number_format($available_balance, 0, ',', '.') ?></p>
                </div>

                <?php if(empty($user_data['nama_bank']) || empty($user_data['no_rekening'])): ?>
                    <div class="bg-amber-50 border border-amber-200 text-amber-800 text-xs p-3 rounded-xl">
                        ⚠️ <strong>Perhatian:</strong> Informasi rekening bank Anda belum lengkap. Mohon isi data rekening terlebih dahulu.
                    </div>
                <?php else: ?>
                    <div class="bg-slate-50 p-3 rounded-xl border border-slate-200 text-xs text-slate-700">
                        <p class="font-bold text-slate-900">Rekening Tujuan Transfer:</p>
                        <p><?= htmlspecialchars($user_data['nama_bank']) ?> - <?= htmlspecialchars($user_data['no_rekening']) ?></p>
                        <p class="text-slate-500">a.n <?= htmlspecialchars($user_data['nama_rekening']) ?></p>
                    </div>
                <?php endif; ?>

                <div class="space-y-2">
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider">Jumlah Penarikan (Rp) <span class="text-red-500">*</span></label>
                    <input type="number" name="amount" min="10000" max="<?= (int)$available_balance ?>" required placeholder="Contoh: 100000" class="w-full bg-slate-50 border border-slate-200 text-slate-800 text-sm rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary p-3 transition-colors font-mono font-bold">
                    <p class="text-[11px] text-slate-400 font-medium">Minimal penarikan Rp 10.000.</p>
                </div>
            </div>
            <div class="px-6 py-4 border-t border-slate-100 bg-slate-50 flex justify-end gap-3">
                <button type="button" onclick="closeWithdrawModal()" class="px-4 py-2 text-sm font-bold text-slate-500 hover:text-slate-700 bg-slate-200 rounded-xl">Batal</button>
                <button type="submit" <?= ($available_balance < 10000 || empty($user_data['nama_bank'])) ? 'disabled' : '' ?> class="px-6 py-2 text-sm font-bold text-white bg-primary hover:bg-primary/90 disabled:opacity-50 disabled:cursor-not-allowed rounded-xl shadow-md transition-all">Kirim Pengajuan</button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.js"></script>
<script>
    function openWithdrawModal() {
        const modal = document.getElementById('withdrawModal');
        const content = document.getElementById('withdrawModalContent');
        modal.classList.remove('hidden');
        setTimeout(() => {
            content.classList.remove('scale-95', 'opacity-0');
            content.classList.add('scale-100', 'opacity-100');
        }, 10);
    }

    function closeWithdrawModal() {
        const modal = document.getElementById('withdrawModal');
        const content = document.getElementById('withdrawModalContent');
        content.classList.remove('scale-100', 'opacity-100');
        content.classList.add('scale-95', 'opacity-0');
        setTimeout(() => {
            modal.classList.add('hidden');
        }, 300);
    }

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
    });

    function togglePasswordVisibility(inputId, eyeId, eyeOffId) {
        const input = document.getElementById(inputId);
        const eye = document.getElementById(eyeId);
        const eyeOff = document.getElementById(eyeOffId);
        if (input.type === 'password') {
            input.type = 'text';
            eye.classList.add('hidden');
            eyeOff.classList.remove('hidden');
        } else {
            input.type = 'password';
            eye.classList.remove('hidden');
            eyeOff.classList.add('hidden');
        }
    }
</script>

<!-- Modal Cropper Foto Profil -->
<div id="cropperModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl shadow-xl border border-slate-200 w-full max-w-lg overflow-hidden flex flex-col transform transition-all duration-300 scale-95 opacity-0" id="cropperModalContent">
        <div class="px-6 py-4 border-b border-slate-100 bg-slate-50 flex items-center justify-between">
            <h3 class="font-extrabold text-slate-900 text-base">Sesuaikan Foto Profil</h3>
            <button type="button" id="closeCropperBtn" class="text-slate-400 hover:text-slate-650 font-bold text-2xl leading-none">&times;</button>
        </div>
        <div class="p-6 flex flex-col items-center gap-4 bg-white">
            <div class="w-full max-h-[350px] overflow-hidden rounded-2xl bg-slate-100 flex items-center justify-center border border-slate-200 relative">
                <img id="cropperSourceImage" src="" alt="Source Image" class="max-w-full max-h-[350px]">
            </div>
            <div class="w-full space-y-3 mt-2">
                <div class="flex items-center gap-3">
                    <span class="text-xs font-bold text-slate-500 uppercase tracking-wider shrink-0">Perbesar/Kecil</span>
                    <input type="range" id="cropperZoomRange" min="0.1" max="3" step="0.01" value="1" class="w-full h-1.5 bg-slate-200 rounded-lg appearance-none cursor-pointer accent-primary">
                </div>
                <div class="flex justify-center gap-4">
                    <button type="button" id="cropperRotateLeft" class="px-3 py-1.5 bg-slate-100 text-slate-700 rounded-lg text-xs font-bold hover:bg-slate-200 transition-colors flex items-center gap-1">
                        Putar Kiri
                    </button>
                    <button type="button" id="cropperRotateRight" class="px-3 py-1.5 bg-slate-100 text-slate-700 rounded-lg text-xs font-bold hover:bg-slate-200 transition-colors flex items-center gap-1">
                        Putar Kanan
                    </button>
                </div>
            </div>
        </div>
        <div class="px-6 py-4 border-t border-slate-100 bg-slate-50 flex items-center justify-end gap-3">
            <button type="button" id="cancelCropperBtn" class="px-5 py-2 text-sm font-bold text-slate-500 hover:text-slate-700 bg-slate-100 hover:bg-slate-200 rounded-xl transition-colors">Batal</button>
            <button type="button" id="saveCropperBtn" class="px-6 py-2 text-sm font-bold text-white bg-primary hover:opacity-90 rounded-xl shadow-md transition-opacity">Simpan</button>
        </div>
    </div>
</div>

<script>
    let cropper = null;
    const fileInput = document.getElementById('foto_profil_input');
    const cropperModal = document.getElementById('cropperModal');
    const cropperModalContent = document.getElementById('cropperModalContent');
    const cropperSourceImage = document.getElementById('cropperSourceImage');
    const cropperZoomRange = document.getElementById('cropperZoomRange');

    if (fileInput) {
        fileInput.addEventListener('change', function(e) {
            const files = e.target.files;
            if (files && files.length > 0) {
                const file = files[0];
                const allowedTypes = ['image/jpeg', 'image/png', 'image/jpg'];
                if (!allowedTypes.includes(file.type)) {
                    alert('Format file tidak didukung. Hanya JPG, JPEG, dan PNG.');
                    fileInput.value = '';
                    return;
                }
                if (file.size > 2 * 1024 * 1024) {
                    alert('Ukuran file maksimal 2MB.');
                    fileInput.value = '';
                    return;
                }

                const reader = new FileReader();
                reader.onload = function(event) {
                    cropperSourceImage.src = event.target.result;
                    cropperModal.classList.remove('hidden');
                    setTimeout(() => {
                        cropperModalContent.classList.remove('scale-95', 'opacity-0');
                        cropperModalContent.classList.add('scale-100', 'opacity-100');
                    }, 50);

                    if (cropper) { cropper.destroy(); }
                    cropper = new Cropper(cropperSourceImage, {
                        aspectRatio: 1, viewMode: 1, dragMode: 'move', autoCropArea: 0.8,
                        restore: false, guides: false, center: false, highlight: false,
                        cropBoxMovable: true, cropBoxResizable: true, toggleDragModeOnDblclick: false,
                        ready: function() {
                            const imageData = cropper.getImageData();
                            cropperZoomRange.min = imageData.width / imageData.naturalWidth * 0.5;
                            cropperZoomRange.max = cropperZoomRange.min * 10;
                            cropperZoomRange.value = cropper.getData().scaleX || 1;
                        }
                    });
                };
                reader.readAsDataURL(file);
            }
        });
    }

    if (cropperZoomRange) {
        cropperZoomRange.addEventListener('input', function() {
            if (cropper) { cropper.zoomTo(parseFloat(this.value)); }
        });
    }

    const rotateLeft = document.getElementById('cropperRotateLeft');
    if (rotateLeft) { rotateLeft.addEventListener('click', function() { if (cropper) cropper.rotate(-45); }); }
    const rotateRight = document.getElementById('cropperRotateRight');
    if (rotateRight) { rotateRight.addEventListener('click', function() { if (cropper) cropper.rotate(45); }); }

    function closeCropperModal() {
        if (cropperModalContent) {
            cropperModalContent.classList.remove('scale-100', 'opacity-100');
            cropperModalContent.classList.add('scale-95', 'opacity-0');
        }
        setTimeout(() => {
            if (cropperModal) cropperModal.classList.add('hidden');
            if (cropper) { cropper.destroy(); cropper = null; }
            if (fileInput) fileInput.value = '';
        }, 300);
    }

    const cancelCropper = document.getElementById('cancelCropperBtn');
    if (cancelCropper) cancelCropper.addEventListener('click', closeCropperModal);
    const closeCropper = document.getElementById('closeCropperBtn');
    if (closeCropper) closeCropper.addEventListener('click', closeCropperModal);

    const saveCropper = document.getElementById('saveCropperBtn');
    if (saveCropper) {
        saveCropper.addEventListener('click', function() {
            if (cropper) {
                const canvas = cropper.getCroppedCanvas({ width: 400, height: 400, imageSmoothingEnabled: true, imageSmoothingQuality: 'high' });
                const dataUrl = canvas.toDataURL('image/jpeg', 0.9);
                const croppedInput = document.getElementById('cropped_image_input');
                if (croppedInput) croppedInput.value = dataUrl;
                
                const profileAvatarImg = document.getElementById('profile-avatar-img');
                const profileAvatarPlaceholder = document.getElementById('profile-avatar-placeholder');
                if (profileAvatarImg) {
                    profileAvatarImg.src = dataUrl;
                } else if (profileAvatarPlaceholder) {
                    const newImg = document.createElement('img');
                    newImg.id = 'profile-avatar-img';
                    newImg.src = dataUrl;
                    newImg.className = 'w-16 h-16 rounded-2xl object-cover shadow-md';
                    profileAvatarPlaceholder.replaceWith(newImg);
                }

                if (cropperModalContent) {
                    cropperModalContent.classList.remove('scale-100', 'opacity-100');
                    cropperModalContent.classList.add('scale-95', 'opacity-0');
                }
                setTimeout(() => {
                    if (cropperModal) cropperModal.classList.add('hidden');
                    if (cropper) { cropper.destroy(); cropper = null; }
                }, 300);
            }
        });
    }
</script>
</body>
</html>
