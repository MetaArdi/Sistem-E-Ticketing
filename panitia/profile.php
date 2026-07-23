<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'panitia') {
    header("Location: ../auth/login.php");
    exit;
}

require_once '../config/koneksi.php';

// Cek jika user membatalkan / menutup modal verifikasi OTP
if (isset($_GET['cancel_otp'])) {
    unset($_SESSION['pending_change_email'], $_SESSION['change_email_otp'], $_SESSION['change_email_otp_expires']);
    header("Location: profile.php");
    exit;
}

$success_msg = '';
$error_msg = '';
$show_otp_modal = false;
$show_request_email_modal = false;
$user_id = (int)($_SESSION['user_id'] ?? 0);

if ($user_id <= 0) {
    header("Location: ../auth/login.php");
    exit;
}

// Get current user info safely
$user_data = [
    'nama_lengkap' => $_SESSION['nama_lengkap'] ?? 'Panitia Event',
    'email' => $_SESSION['email'] ?? '',
    'foto_profil' => $_SESSION['foto_profil'] ?? ''
];

$stmt = $conn->prepare("SELECT email, nama_lengkap, foto_profil FROM users WHERE id = ?");
if ($stmt) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $db_user = $res->fetch_assoc()) {
        $user_data = array_merge($user_data, array_filter($db_user, function($v) { return $v !== null; }));
    }
    $stmt->close();
}

// 1. PROSES PENGAJUAN UBAH EMAIL (KIRIM KODE OTP KE EMAIL BARU)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['request_email_change'])) {
    $email_baru = trim($_POST['email_baru'] ?? '');

    if (empty($email_baru) || !filter_var($email_baru, FILTER_VALIDATE_EMAIL)) {
        $error_msg = "Silakan masukkan alamat email baru yang valid.";
        $show_request_email_modal = true;
    } elseif (strtolower($user_data['email']) === strtolower($email_baru)) {
        $error_msg = "Alamat email baru tidak boleh sama dengan alamat email saat ini.";
        $show_request_email_modal = true;
    } else {
        // Cek apakah email baru sudah dipakai user lain
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        if ($stmt) {
            $stmt->bind_param("si", $email_baru, $user_id);
            $stmt->execute();
            $res_check = $stmt->get_result();
            if ($res_check && $res_check->num_rows > 0) {
                $error_msg = "Email baru tersebut sudah terdaftar pada akun lain.";
                $show_request_email_modal = true;
            } else {
                // GENERATE & KIRIM KODE OTP KE EMAIL BARU
                $otp = sprintf("%06d", mt_rand(100000, 999999));
                $_SESSION['pending_change_email'] = $email_baru;
                $_SESSION['change_email_otp'] = $otp;
                $_SESSION['change_email_otp_expires'] = time() + 300; // 5 menit

                $to = $email_baru;
                $subject = "[$otp] Kode OTP Verifikasi Ubah Email Login Panitia - HaloTiket";
                $message = "
                <!DOCTYPE html>
                <html lang='id'>
                <head><meta charset='UTF-8'></head>
                <body style='font-family: Arial, sans-serif; background-color: #f8fafc; padding: 30px; margin: 0;'>
                    <div style='max-width: 520px; margin: 0 auto; background: #ffffff; border-radius: 20px; border: 1px solid #e2e8f0; overflow: hidden;'>
                        <div style='background: #003846; color: #ffffff; padding: 25px; text-align: center;'>
                            <h1 style='margin: 0; font-size: 22px;'>HaloTiket Panitia</h1>
                            <p style='margin: 5px 0 0 0; font-size: 12px; color: #94a3b8;'>Verifikasi Perubahan Email Akun Login</p>
                        </div>
                        <div style='padding: 30px; text-align: center;'>
                            <h3 style='margin-top: 0; color: #0f172a;'>Kode Verifikasi OTP Anda</h3>
                            <p style='font-size: 14px; color: #64748b;'>Anda mengajukan perubahan email login Panitia ke: <strong>$email_baru</strong>.</p>
                            <div style='background: #f1f5f9; border: 2px dashed #00c2cb; padding: 18px; text-align: center; border-radius: 14px; font-size: 32px; font-weight: 800; letter-spacing: 8px; color: #008b94; margin: 20px auto; max-width: 280px;'>
                                $otp
                            </div>
                            <p style='font-size: 12px; color: #94a3b8;'>Kode ini dikirimkan ke <strong>$email_baru</strong> dan hanya berlaku selama <strong>5 menit</strong>.</p>
                        </div>
                    </div>
                </body>
                </html>";

                $headers = "MIME-Version: 1.0\r\n";
                $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
                $headers .= "From: HaloTiket <no-reply@halotiket.com>\r\n";
                $headers .= "Reply-To: no-reply@halotiket.com\r\n";
                $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";

                $mail_sent = @mail($to, $subject, $message, $headers, "-f no-reply@halotiket.com");
                if (!$mail_sent) {
                    @mail($to, $subject, $message, $headers);
                }

                $show_otp_modal = true;
                $success_msg = "Kode OTP telah dikirimkan ke email baru Anda: " . htmlspecialchars($email_baru) . ". Silakan masukkan kode OTP 6-digit untuk memverifikasi.";
            }
            $stmt->close();
        }
    }
}

// 2. PROSES VERIFIKASI KODE OTP UBAH EMAIL
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['verify_email_otp'])) {
    $user_otp = trim($_POST['otp_code'] ?? '');
    $pending_email = $_SESSION['pending_change_email'] ?? '';
    $sess_otp = $_SESSION['change_email_otp'] ?? '';
    $sess_expires = $_SESSION['change_email_otp_expires'] ?? 0;

    if (empty($user_otp) || empty($pending_email) || empty($sess_otp)) {
        $error_msg = "Sesi verifikasi OTP email telah kadaluarsa. Silakan ajukan ulang perubahan email.";
    } elseif (time() > $sess_expires) {
        $error_msg = "Kode OTP telah kadaluarsa. Silakan ajukan perubahan email kembali.";
    } elseif ($user_otp !== $sess_otp) {
        $error_msg = "Kode OTP yang Anda masukkan salah. Silakan periksa inbox email baru Anda.";
        $show_otp_modal = true;
    } else {
        // OTP Valid! Simpan email baru ke tabel users & tickets
        $old_email = $user_data['email'] ?? '';
        $stmt_u = $conn->prepare("UPDATE users SET email = ? WHERE id = ?");
        if ($stmt_u) {
            $stmt_u->bind_param("si", $pending_email, $user_id);
            $stmt_u->execute();
            $stmt_u->close();
        }

        if (!empty($old_email)) {
            $stmt_t = $conn->prepare("UPDATE tickets SET email_pembeli = ? WHERE email_pembeli = ?");
            if ($stmt_t) {
                $stmt_t->bind_param("ss", $pending_email, $old_email);
                $stmt_t->execute();
                $stmt_t->close();
            }
        }

        $_SESSION['email'] = $pending_email;
        $user_data['email'] = $pending_email;
        unset($_SESSION['pending_change_email'], $_SESSION['change_email_otp'], $_SESSION['change_email_otp_expires']);
        
        $success_msg = "✓ Email login panitia Anda berhasil diubah menjadi: " . htmlspecialchars($pending_email);
    }
}

// 3. UPDATE PROFIL UTAMA (NAMA, FOTO & PASSWORD)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && !isset($_POST['verify_email_otp']) && !isset($_POST['request_email_change'])) {
    $nama_lengkap = trim($_POST['nama_lengkap'] ?? '');
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';

    if (empty($nama_lengkap)) {
        $error_msg = "Nama Lengkap tidak boleh kosong.";
    } else {
        $foto_profil = $user_data['foto_profil'] ?? '';
        
        // Handle foto profil upload
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
                            @mkdir($upload_dir, 0755, true);
                        }
                        $upload_path = $upload_dir . $new_filename;
                        if (@file_put_contents($upload_path, $img_data)) {
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
                    @mkdir($upload_dir, 0755, true);
                }
                $upload_path = $upload_dir . $new_filename;
                if (@move_uploaded_file($_FILES['foto_profil']['tmp_name'], $upload_path)) {
                    $foto_profil = $new_filename;
                } else {
                    $error_msg = "Gagal mengunggah foto profil.";
                }
            }
        }

        if (empty($error_msg)) {
            $stmt_update = null;
            if (!empty($password)) {
                if ($password !== $password_confirm) {
                    $error_msg = "Konfirmasi password tidak cocok.";
                } else {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $stmt_update = $conn->prepare("UPDATE users SET nama_lengkap = ?, password = ?, foto_profil = ? WHERE id = ?");
                    if ($stmt_update) $stmt_update->bind_param("sssi", $nama_lengkap, $hashed_password, $foto_profil, $user_id);
                }
            } else {
                $stmt_update = $conn->prepare("UPDATE users SET nama_lengkap = ?, foto_profil = ? WHERE id = ?");
                if ($stmt_update) $stmt_update->bind_param("ssi", $nama_lengkap, $foto_profil, $user_id);
            }

            if (empty($error_msg) && $stmt_update) {
                if ($stmt_update->execute()) {
                    $_SESSION['nama_lengkap'] = $nama_lengkap;
                    $_SESSION['foto_profil'] = $foto_profil;
                    $user_data['nama_lengkap'] = $nama_lengkap;
                    $user_data['foto_profil'] = $foto_profil;
                    $success_msg = "Profil berhasil diperbarui.";
                } else {
                    $error_msg = "Terjadi kesalahan saat menyimpan data.";
                }
                $stmt_update->close();
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
    <title>Profil Panitia - HaloTiket</title>
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
    <?php $active_menu = 'profile'; include 'components/sidebar.php'; ?>

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
                        <h1 class="text-2xl md:text-3xl font-extrabold text-slate-900 tracking-tight">Profil Panitia</h1>
                        <p class="text-slate-500 mt-1 font-medium text-sm">Kelola informasi data diri, foto profil, dan kata sandi akun Panitia Anda.</p>
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
                                    <div class="space-y-2">
                                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider">Nama Lengkap Panitia</label>
                                        <input type="text" name="nama_lengkap" value="<?= htmlspecialchars($user_data['nama_lengkap'] ?? '') ?>" required class="w-full bg-slate-50 border border-slate-200 text-slate-800 text-sm rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary block px-4 py-2.5 transition-colors font-medium">
                                    </div>

                                    <div class="space-y-2">
                                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider">
                                            Alamat Email Login Panitia
                                        </label>
                                        <div class="flex items-center gap-2">
                                            <div class="relative w-full">
                                                <input type="email" value="<?= htmlspecialchars($user_data['email'] ?? '') ?>" readonly class="w-full bg-slate-100 border border-slate-200 text-slate-500 text-sm rounded-xl block px-4 py-2.5 font-medium cursor-not-allowed select-none pr-10">
                                                <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none text-slate-400">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>
                                                </div>
                                            </div>
                                            <button type="button" onclick="openRequestEmailModal()" class="shrink-0 bg-primary/10 hover:bg-primary/20 text-primary border border-primary/30 font-bold px-4 py-2.5 rounded-xl transition-all text-xs flex items-center gap-1.5 shadow-sm">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" /></svg>
                                                Ubah Email
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="space-y-2 mt-5">
                                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider">Foto Profil <span class="text-slate-400 font-normal">(Opsional)</span></label>
                                    <input type="hidden" name="cropped_image" id="cropped_image_input">
                                    <input type="file" id="foto_profil_input" name="foto_profil" accept="image/jpeg, image/png, image/jpg" class="w-full bg-slate-50 border border-slate-200 text-slate-800 text-sm rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary block px-4 py-2.5 transition-colors font-medium file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-bold file:bg-primary/10 file:text-primary hover:file:bg-primary/20 cursor-pointer">
                                    <p class="text-xs text-slate-500 font-medium mt-1">Format: JPG, JPEG, PNG. Maksimal ukuran: 2MB.</p>
                                </div>

                                <hr class="border-slate-100 my-6">
                                
                                <div>
                                    <h4 class="text-sm font-bold text-slate-800 mb-4">Ubah Kata Sandi <span class="text-slate-400 font-normal">(Kosongkan jika tidak ingin mengubah)</span></h4>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                                        <div class="space-y-2">
                                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider">Password Baru</label>
                                            <div class="relative">
                                                <input type="password" id="password" name="password" placeholder="••••••••" class="w-full bg-slate-50 border border-slate-200 text-slate-800 text-sm rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary block px-4 py-2.5 transition-colors font-medium pr-10">
                                                <button type="button" onclick="togglePasswordVisibility('password', 'eyeIcon1', 'eyeOffIcon1')" class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-slate-600 focus:outline-none">
                                                    <svg id="eyeIcon1" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                                                    <svg id="eyeOffIcon1" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" /></svg>
                                                </button>
                                            </div>
                                        </div>
                                        <div class="space-y-2">
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

            </div>
        </main>
    </div>
</div>

<!-- Modal Form Pengajuan Email Baru -->
<div id="requestEmailModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 <?= $show_request_email_modal ? '' : 'hidden' ?> flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl shadow-2xl border border-slate-200 w-full max-w-md overflow-hidden transform transition-all duration-300">
        <form method="POST" action="">
            <input type="hidden" name="request_email_change" value="1">
            <div class="px-6 py-4 border-b border-slate-100 bg-slate-900 text-white flex justify-between items-center">
                <h3 class="font-extrabold text-base flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" /></svg>
                    Ubah Alamat Email Login Panitia
                </h3>
                <button type="button" onclick="closeRequestEmailModal()" class="text-slate-400 hover:text-white text-2xl font-bold">&times;</button>
            </div>
            <div class="p-6 space-y-4">
                <div class="bg-slate-50 border border-slate-200 p-3.5 rounded-xl text-xs text-slate-600 space-y-1">
                    <p class="font-bold text-slate-800">Email Login Saat Ini:</p>
                    <p class="font-mono text-primary font-bold text-sm"><?= htmlspecialchars($user_data['email'] ?? '') ?></p>
                </div>

                <div class="space-y-2">
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider">Masukkan Alamat Email Baru <span class="text-red-500">*</span></label>
                    <input type="email" name="email_baru" required placeholder="contoh: emailbaru@gmail.com" class="w-full bg-slate-50 border border-slate-200 text-slate-800 text-sm rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary p-3 transition-colors font-medium">
                    <p class="text-[11px] text-slate-400 font-medium">Kode verifikasi OTP 6-digit akan dikirimkan ke email baru ini.</p>
                </div>
            </div>
            <div class="px-6 py-4 border-t border-slate-100 bg-slate-50 flex justify-end gap-3">
                <button type="button" onclick="closeRequestEmailModal()" class="px-4 py-2 text-sm font-bold text-slate-500 hover:text-slate-700 bg-slate-200 rounded-xl">Batal</button>
                <button type="submit" class="px-6 py-2 text-sm font-bold text-white bg-primary hover:bg-primary/90 rounded-xl shadow-md transition-all">Kirim Kode OTP Verifikasi</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal OTP Verifikasi Ubah Email Panitia -->
<div id="emailOtpModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 <?= $show_otp_modal ? '' : 'hidden' ?> flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl shadow-2xl border border-slate-200 w-full max-w-md overflow-hidden transform transition-all duration-300">
        <form method="POST" action="">
            <input type="hidden" name="verify_email_otp" value="1">
            <div class="px-6 py-4 border-b border-slate-100 bg-slate-900 text-white flex justify-between items-center">
                <h3 class="font-extrabold text-base flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>
                    Verifikasi OTP Email Baru
                </h3>
                <a href="profile.php?cancel_otp=1" class="text-slate-400 hover:text-white text-2xl font-bold">&times;</a>
            </div>
            <div class="p-6 text-center space-y-4">
                <p class="text-xs text-slate-500 font-medium leading-relaxed">
                    Kode verifikasi OTP 6-digit telah dikirimkan ke email baru Anda: <br>
                    <strong class="text-slate-900 font-mono text-sm bg-blue-50 border border-blue-200 px-3 py-1 rounded-lg inline-block mt-2"><?= htmlspecialchars($_SESSION['pending_change_email'] ?? '') ?></strong>
                </p>

                <div class="space-y-2">
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider">Masukkan 6-Digit Kode OTP</label>
                    <input type="text" name="otp_code" maxlength="6" required placeholder="123456" class="w-full text-center tracking-[0.5em] text-2xl font-extrabold font-mono bg-slate-50 border-2 border-slate-200 rounded-2xl focus:border-primary focus:bg-white p-3 outline-none transition-all">
                </div>
            </div>
            <div class="px-6 py-4 border-t border-slate-100 bg-slate-50 flex justify-between gap-3">
                <a href="profile.php?cancel_otp=1" class="px-4 py-2 text-sm font-bold text-slate-500 hover:text-slate-700 bg-slate-200 rounded-xl inline-block">Batal</a>
                <button type="submit" class="px-6 py-2 text-sm font-bold text-white bg-primary hover:bg-primary/90 rounded-xl shadow-md transition-all">Verifikasi & Simpan Email</button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.js"></script>
<script>
    function openRequestEmailModal() {
        const modal = document.getElementById('requestEmailModal');
        if (modal) modal.classList.remove('hidden');
    }

    function closeRequestEmailModal() {
        const modal = document.getElementById('requestEmailModal');
        if (modal) modal.classList.add('hidden');
    }

    function closeEmailOtpModal() {
        window.location.href = 'profile.php?cancel_otp=1';
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
</body>
</html>
