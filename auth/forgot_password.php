<?php
session_start();
if (isset($_SESSION['user_id'])) {
    header("Location: ../" . $_SESSION['role']);
    exit;
}
require_once '../config/koneksi.php';

// Fetch Settings for Logo and Favicon
$settings_query = $conn->query("SELECT * FROM settings");
$settings = [];
if ($settings_query) {
    while ($row = $settings_query->fetch_assoc()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
}
$site_logo = (isset($settings['site_logo']) && $settings['site_logo'] != '') ? '../assets/images/logo/' . $settings['site_logo'] : null;
$site_favicon = (isset($settings['site_favicon']) && $settings['site_favicon'] != '') ? '../assets/images/favicon/' . $settings['site_favicon'] : null;

// Determine step
$step = 1;
if (isset($_SESSION['reset_email'])) {
    $step = 2;
}
if (isset($_SESSION['reset_otp_verified']) && $_SESSION['reset_otp_verified'] === true) {
    $step = 3;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lupa Password - HaloTiket</title>

    <?php if ($site_favicon): ?>
        <link rel="icon" href="<?= $site_favicon ?>" type="image/x-icon">
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
</head>

<body class="bg-slate-50 font-sans antialiased min-h-screen flex items-center justify-center text-slate-800 selection:bg-primary selection:text-white relative py-10 px-4">

    <!-- Decorative Background Elements -->
    <div class="absolute top-0 left-0 w-full h-full overflow-hidden -z-10 pointer-events-none">
        <div class="absolute -top-[20%] -left-[10%] w-[50%] h-[50%] rounded-full bg-primary/10 blur-[100px]"></div>
        <div class="absolute -bottom-[20%] -right-[10%] w-[50%] h-[50%] rounded-full bg-secondary/10 blur-[100px]"></div>
        <div class="absolute top-[20%] right-[20%] w-[30%] h-[30%] rounded-full bg-emerald-400/5 blur-[80px]"></div>
    </div>

    <div class="w-full max-w-md bg-white rounded-3xl shadow-xl shadow-slate-200/50 p-8 sm:p-12 relative z-10 border border-slate-100">

        <?php if($step == 1): ?>
            <a href="login.php" class="absolute top-6 left-6 text-slate-400 hover:text-primary flex items-center gap-2 text-xs font-bold transition-colors bg-slate-50 px-3 py-1.5 rounded-full border border-slate-200">
                &larr; Kembali ke Login
            </a>
        <?php else: ?>
            <form action="proses_forgot_password.php" method="POST" class="inline">
                <input type="hidden" name="action" value="cancel_reset">
                <button type="submit" class="absolute top-6 left-6 text-slate-400 hover:text-red-500 flex items-center gap-2 text-xs font-bold transition-colors bg-slate-50 px-3 py-1.5 rounded-full border border-slate-200">
                    &larr; Batal
                </button>
            </form>
        <?php endif; ?>

        <div class="text-center mb-10 mt-6 flex flex-col items-center">
            <?php if ($site_logo): ?>
                <img src="<?= $site_logo ?>" alt="Logo" class="w-48 md:w-56 h-auto object-contain mb-6 mx-auto">
            <?php else: ?>
                <div class="w-16 h-16 rounded-2xl bg-gradient-to-br from-primary to-secondary flex items-center justify-center text-white font-extrabold text-3xl shadow-xl shadow-indigo-500/30 mb-4 transform -rotate-6">H</div>
            <?php endif; ?>

            <?php if($step == 1): ?>
                <h2 class="text-3xl font-extrabold text-slate-900 mb-2 tracking-tight">Lupa Password</h2>
                <p class="text-slate-500 font-medium text-sm">Masukkan email yang terdaftar untuk menerima OTP.</p>
            <?php elseif($step == 2): ?>
                <h2 class="text-3xl font-extrabold text-slate-900 mb-2 tracking-tight">Verifikasi OTP</h2>
                <p class="text-slate-500 font-medium text-sm">Masukkan 6 digit kode yang dikirim ke <br><span class="font-bold text-slate-700"><?= htmlspecialchars($_SESSION['reset_email']) ?></span></p>
            <?php elseif($step == 3): ?>
                <h2 class="text-3xl font-extrabold text-slate-900 mb-2 tracking-tight">Password Baru</h2>
                <p class="text-slate-500 font-medium text-sm">Silakan buat password baru Anda.</p>
            <?php endif; ?>
        </div>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="bg-red-50 border border-red-100 text-red-600 px-5 py-4 rounded-2xl mb-6 font-bold text-sm flex items-start gap-3 shadow-sm animate-pulse">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0 mt-0.5 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
                <?= $_SESSION['error']; unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="bg-emerald-50 border border-emerald-100 text-emerald-600 px-5 py-4 rounded-2xl mb-6 font-bold text-sm flex items-start gap-3 shadow-sm">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0 mt-0.5 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
                <?= $_SESSION['success']; unset($_SESSION['success']); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['mock_otp'])): ?>
            <!-- HANYA UNTUK KEPERLUAN TESTING -->
            <div class="bg-blue-50 border border-blue-200 text-blue-700 px-5 py-4 rounded-2xl mb-6 font-bold text-sm text-center shadow-sm relative overflow-hidden">
                <div class="absolute top-0 right-0 p-1 text-[10px] bg-blue-200 text-blue-800 rounded-bl-lg font-extrabold uppercase tracking-wider">DEV MODE</div>
                Simulasi OTP Anda:<br>
                <span class="text-3xl tracking-[0.2em] text-blue-900 mt-2 block"><?= $_SESSION['mock_otp']; unset($_SESSION['mock_otp']); ?></span>
            </div>
        <?php endif; ?>

        <!-- STEP 1: Input Email -->
        <?php if($step == 1): ?>
        <form action="proses_forgot_password.php" method="POST" class="space-y-6">
            <input type="hidden" name="action" value="send_otp">
            <div class="space-y-1.5">
                <label class="text-[11px] font-extrabold text-slate-500 uppercase tracking-widest block">Alamat Email</label>
                <div class="relative group">
                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none transition-colors group-focus-within:text-primary text-slate-400">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                        </svg>
                    </div>
                    <input type="email" name="email" required placeholder="Masukan Email Anda" class="w-full pl-11 pr-5 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary focus:bg-white transition-all text-sm font-semibold outline-none text-slate-800 placeholder-slate-400">
                </div>
            </div>
            <button type="submit" class="w-full bg-slate-900 hover:bg-primary text-white font-bold py-3.5 px-4 rounded-xl transition-all duration-300 shadow-lg shadow-slate-900/20 hover:shadow-primary/30 flex items-center justify-center gap-2 group">
                Kirim OTP
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 group-hover:translate-x-1 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" /></svg>
            </button>
        </form>
        <?php endif; ?>

        <!-- STEP 2: Input OTP -->
        <?php if($step == 2): ?>
        <form action="proses_forgot_password.php" method="POST" class="space-y-6">
            <input type="hidden" name="action" value="verify_otp">
            <div class="space-y-1.5">
                <label class="text-[11px] font-extrabold text-slate-500 uppercase tracking-widest block text-center">Kode OTP (6 Digit)</label>
                <div class="relative group mt-2">
                    <input type="text" name="otp" required maxlength="6" pattern="[0-9]{6}" autocomplete="off" placeholder="••••••" class="w-full text-center tracking-[0.5em] py-4 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary focus:bg-white transition-all text-2xl font-black outline-none text-slate-800 placeholder-slate-300">
                </div>
            </div>
            <button type="submit" class="w-full bg-primary hover:bg-slate-900 text-white font-bold py-3.5 px-4 rounded-xl transition-all duration-300 shadow-lg shadow-primary/20 hover:shadow-slate-900/30 flex items-center justify-center gap-2 group">
                Verifikasi
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 group-hover:translate-x-1 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
            </button>
        </form>
        <div class="mt-6 text-center">
            <form action="proses_forgot_password.php" method="POST" class="inline">
                <input type="hidden" name="action" value="resend_otp">
                <button type="submit" class="text-xs font-bold text-slate-500 hover:text-primary transition-colors">Kirim Ulang OTP</button>
            </form>
        </div>
        <?php endif; ?>

        <!-- STEP 3: Input New Password -->
        <?php if($step == 3): ?>
        <form action="proses_forgot_password.php" method="POST" class="space-y-6">
            <input type="hidden" name="action" value="reset_password">
            <div class="space-y-1.5">
                <label class="text-[11px] font-extrabold text-slate-500 uppercase tracking-widest block">Password Baru</label>
                <div class="relative group">
                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none transition-colors group-focus-within:text-primary text-slate-400">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>
                    </div>
                    <input type="password" name="password" id="password" required placeholder="••••••••" class="w-full pl-11 pr-12 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary focus:bg-white transition-all text-sm font-semibold outline-none text-slate-800 placeholder-slate-400">
                    <button type="button" onclick="togglePassword('password', 'eye1', 'eye-off1')" class="absolute inset-y-0 right-0 pr-4 flex items-center text-slate-400 hover:text-primary transition-colors focus:outline-none">
                        <svg id="eye1" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                        <svg id="eye-off1" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" /></svg>
                    </button>
                </div>
            </div>

            <div class="space-y-1.5">
                <label class="text-[11px] font-extrabold text-slate-500 uppercase tracking-widest block">Konfirmasi Password Baru</label>
                <div class="relative group">
                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none transition-colors group-focus-within:text-primary text-slate-400">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>
                    </div>
                    <input type="password" name="password_confirm" id="password_confirm" required placeholder="••••••••" class="w-full pl-11 pr-12 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary focus:bg-white transition-all text-sm font-semibold outline-none text-slate-800 placeholder-slate-400">
                    <button type="button" onclick="togglePassword('password_confirm', 'eye2', 'eye-off2')" class="absolute inset-y-0 right-0 pr-4 flex items-center text-slate-400 hover:text-primary transition-colors focus:outline-none">
                        <svg id="eye2" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                        <svg id="eye-off2" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" /></svg>
                    </button>
                </div>
            </div>
            
            <button type="submit" class="w-full bg-slate-900 hover:bg-primary text-white font-bold py-3.5 px-4 rounded-xl transition-all duration-300 shadow-lg shadow-slate-900/20 hover:shadow-primary/30 flex items-center justify-center gap-2 group mt-2">
                Simpan Password Baru
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 group-hover:scale-110 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
            </button>
        </form>
        
        <script>
            function togglePassword(inputId, eyeId, eyeOffId) {
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
        <?php endif; ?>

    </div>

</body>
</html>
