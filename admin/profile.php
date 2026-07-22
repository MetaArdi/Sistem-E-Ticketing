<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../auth/login.php");
    exit;
}
require_once '../config/koneksi.php';

$success_msg = '';
$error_msg = '';
$user_id = $_SESSION['user_id'];

// Get current user info
$stmt = $conn->prepare("SELECT email, nama_lengkap, foto_profil FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_data = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
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
                    $upload_path = '../assets/images/profil/' . $new_filename;
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
                    
                    logActivity($conn, $user_id, 'Update Profile', 'Admin memperbarui data profil akunnya.');
                } else {
                    $error_msg = "Terjadi kesalahan saat menyimpan data.";
                }
                $stmt->close();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil Saya - HaloTiket Admin</title>
    <?php if (isset($global_site_favicon) && $global_site_favicon): ?>
        <link rel="icon" href="<?= $global_site_favicon ?>">
    <?php endif; ?>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.css">
    <style>
        /* circular cropper mask */
        .cropper-view-box,
        .cropper-face {
            border-radius: 50%;
        }
        .cropper-line, .cropper-point {
            display: none !important;
        }
        .cropper-view-box {
            outline: 2px solid #00c2cb;
            outline-color: rgba(0, 194, 203, 0.7);
        }
    </style>
    <script>
        tailwind.config = {
            theme: { extend: { fontFamily: { sans: ['Outfit','sans-serif'] }, colors: { primary: '#00c2cb', secondary: '#0f1c3f', dark: '#0a1020' } } }
        }
    </script>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
</head>
<body class="bg-slate-50 font-sans antialiased text-slate-800 selection:bg-primary selection:text-white overflow-hidden">
<div class="flex h-screen w-full">
    <div id="sidebarOverlay" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-40 hidden md:hidden transition-opacity"></div>
    <?php $active_menu = ''; include 'components/sidebar.php'; ?>

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

        <main class="flex-1 overflow-y-auto p-4 md:p-6 lg:p-10">
            <div class="max-w-3xl mx-auto">
                <div class="mb-8">
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
                            <?php if (isset($user_data['foto_profil']) && !empty($user_data['foto_profil']) && (str_starts_with($user_data['foto_profil'], 'http') || file_exists('../assets/images/profil/'.$user_data['foto_profil']))): ?>
                                <img id="profile-avatar-img" src="<?= str_starts_with($user_data['foto_profil'], 'http') ? htmlspecialchars($user_data['foto_profil']) : '../assets/images/profil/'.htmlspecialchars($user_data['foto_profil']) ?>" class="w-16 h-16 rounded-2xl object-cover shadow-md">
                            <?php else: ?>
                                <div id="profile-avatar-placeholder" class="w-16 h-16 rounded-2xl bg-gradient-to-br from-primary to-secondary text-white flex items-center justify-center font-bold text-2xl shadow-md">
                                    <?= strtoupper(substr($user_data['nama_lengkap'] ?? 'A', 0, 1)) ?>
                                </div>
                            <?php endif; ?>
                            <div>
                                <h3 class="font-extrabold text-slate-900 text-lg"><?= htmlspecialchars($user_data['nama_lengkap']) ?></h3>
                                <p class="text-sm font-bold text-primary uppercase tracking-wider">Administrator</p>
                            </div>
                        </div>
                    </div>
                    <div class="p-6 md:p-8">
                        <form method="POST" action="" enctype="multipart/form-data" class="space-y-5">
                            <input type="hidden" name="cropped_image" id="cropped_image_input">
                            
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
                                <input type="file" id="adminFotoProfilInput" name="foto_profil" accept="image/jpeg, image/png, image/jpg" class="w-full bg-slate-50 border border-slate-200 text-slate-800 text-sm rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary block px-4 py-2.5 transition-colors font-medium file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-bold file:bg-primary/10 file:text-primary hover:file:bg-primary/20 cursor-pointer">
                                <p class="text-xs text-slate-500 font-medium mt-1">Format: JPG, JPEG, PNG. Maksimal ukuran: 2MB.</p>
                                
                                <!-- Live Image Preview Box -->
                                <div id="adminPhotoPreviewBox" class="hidden mt-3 p-3 bg-slate-50 border border-slate-200 rounded-2xl flex items-center gap-3">
                                    <img id="adminPhotoPreviewImg" class="w-16 h-16 rounded-xl object-cover border border-slate-200 shadow-sm">
                                    <div>
                                        <p class="text-xs font-bold text-slate-700">Pratinjau Foto Profil Baru</p>
                                        <p id="adminPhotoFileName" class="text-xs text-slate-500 font-medium truncate max-w-[200px]"></p>
                                    </div>
                                </div>
                            </div>

                            <hr class="border-slate-100 my-6">
                            
                            <div>
                                <h4 class="text-sm font-bold text-slate-800 mb-4">Ubah Kata Sandi <span class="text-slate-400 font-normal">(Kosongkan jika tidak ingin mengubah)</span></h4>
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

                            <div class="pt-6 flex justify-end">
                                <button type="submit" class="bg-primary hover:bg-primary/90 text-white font-bold py-2.5 px-6 rounded-xl transition-all duration-300 transform hover:-translate-y-0.5 shadow-lg shadow-primary/30 text-sm flex items-center gap-2">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                                    Simpan Perubahan
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

            </div>
        </main>
    </div>
</div>

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

<!-- Modal Cropper Foto Profil Admin -->
<div id="cropperModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl shadow-xl border border-slate-200 w-full max-w-lg overflow-hidden flex flex-col transform transition-all duration-300 scale-95 opacity-0" id="cropperModalContent">
        <!-- Header -->
        <div class="px-6 py-4 border-b border-slate-100 bg-slate-50 flex items-center justify-between">
            <h3 class="font-extrabold text-slate-900 text-base">Sesuaikan Foto Profil Admin</h3>
            <button type="button" id="closeCropperBtn" class="text-slate-400 hover:text-slate-650 font-bold text-2xl leading-none">&times;</button>
        </div>
        <!-- Body -->
        <div class="p-6 flex flex-col items-center gap-4 bg-white">
            <div class="w-full max-h-[350px] overflow-hidden rounded-2xl bg-slate-100 flex items-center justify-center border border-slate-200 relative">
                <img id="cropperSourceImage" src="" alt="Source Image" class="max-w-full max-h-[350px]">
            </div>
            
            <!-- Zoom & Rotate Controls -->
            <div class="w-full space-y-3 mt-2">
                <div class="flex items-center gap-3">
                    <span class="text-xs font-bold text-slate-500 uppercase tracking-wider shrink-0">Perbesar/Kecil</span>
                    <input type="range" id="cropperZoomRange" min="0.1" max="3" step="0.01" value="1" class="w-full h-1.5 bg-slate-200 rounded-lg appearance-none cursor-pointer accent-primary">
                </div>
                <div class="flex justify-center gap-4">
                    <button type="button" id="cropperRotateLeft" class="px-3 py-1.5 bg-slate-100 text-slate-700 rounded-lg text-xs font-bold hover:bg-slate-200 transition-colors flex items-center gap-1">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12.066 11.2a1 1 0 000 1.6l5.334 4A1 1 0 0019 16V8a1 1 0 00-1.6-.8l-5.334 4z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 12a8 8 0 018-8m0 0l-3 3m3-3L9 3" /></svg>
                        Putar Kiri
                    </button>
                    <button type="button" id="cropperRotateRight" class="px-3 py-1.5 bg-slate-100 text-slate-700 rounded-lg text-xs font-bold hover:bg-slate-200 transition-colors flex items-center gap-1">
                        Putar Kanan
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.934 12.8a1 1 0 000-1.6l-5.334-4A1 1 0 005 8v8a1 1 0 001.6.8l5.334-4z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12a8 8 0 00-8-8m0 0l3 3m-3-3l3-3" /></svg>
                    </button>
                </div>
            </div>
        </div>
        <!-- Footer -->
        <div class="px-6 py-4 border-t border-slate-100 bg-slate-50 flex items-center justify-end gap-3">
            <button type="button" id="cancelCropperBtn" class="px-5 py-2 text-sm font-bold text-slate-500 hover:text-slate-700 bg-slate-100 hover:bg-slate-200 rounded-xl transition-colors">Batal</button>
            <button type="button" id="saveCropperBtn" class="px-6 py-2 text-sm font-bold text-white bg-primary hover:opacity-90 rounded-xl shadow-md transition-opacity">Simpan</button>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.js"></script>
<script>
    let cropper = null;
    const fileInput = document.getElementById('adminFotoProfilInput');
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

                    if (cropper) {
                        cropper.destroy();
                    }

                    cropper = new Cropper(cropperSourceImage, {
                        aspectRatio: 1,
                        viewMode: 1,
                        dragMode: 'move',
                        autoCropArea: 0.8,
                        restore: false,
                        guides: false,
                        center: false,
                        highlight: false,
                        cropBoxMovable: true,
                        cropBoxResizable: true,
                        toggleDragModeOnDblclick: false,
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
            if (cropper) {
                cropper.zoomTo(parseFloat(this.value));
            }
        });
    }

    const rotateLeft = document.getElementById('cropperRotateLeft');
    if (rotateLeft) {
        rotateLeft.addEventListener('click', function() {
            if (cropper) cropper.rotate(-45);
        });
    }
    const rotateRight = document.getElementById('cropperRotateRight');
    if (rotateRight) {
        rotateRight.addEventListener('click', function() {
            if (cropper) cropper.rotate(45);
        });
    }

    function closeCropperModal() {
        if (cropperModalContent) {
            cropperModalContent.classList.remove('scale-100', 'opacity-100');
            cropperModalContent.classList.add('scale-95', 'opacity-0');
        }
        setTimeout(() => {
            if (cropperModal) cropperModal.classList.add('hidden');
            if (cropper) {
                cropper.destroy();
                cropper = null;
            }
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
                const canvas = cropper.getCroppedCanvas({
                    width: 400,
                    height: 400,
                    imageSmoothingEnabled: true,
                    imageSmoothingQuality: 'high'
                });
                
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
                
                const headerProfileImgs = document.querySelectorAll('header img');
                headerProfileImgs.forEach(img => {
                    img.src = dataUrl;
                });

                if (cropperModalContent) {
                    cropperModalContent.classList.remove('scale-100', 'opacity-100');
                    cropperModalContent.classList.add('scale-95', 'opacity-0');
                }
                setTimeout(() => {
                    if (cropperModal) cropperModal.classList.add('hidden');
                    if (cropper) {
                        cropper.destroy();
                        cropper = null;
                    }
                }, 300);
            }
        });
    }
</script>
</body>
</html>
