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
    while($row = $settings_query->fetch_assoc()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
}
$site_logo = (isset($settings['site_logo']) && $settings['site_logo'] != '') ? '../assets/images/logo/' . $settings['site_logo'] : null;
$site_favicon = (isset($settings['site_favicon']) && $settings['site_favicon'] != '') ? '../assets/images/favicon/' . $settings['site_favicon'] : null;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Akun - HaloTiket</title>
    
    <?php if($site_favicon): ?>
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

    <div class="w-full max-w-lg bg-white rounded-3xl shadow-xl shadow-slate-200/50 p-8 sm:p-12 relative z-10 border border-slate-100 my-auto">
        
        <a href="login.php" class="absolute top-6 left-6 text-slate-400 hover:text-primary flex items-center gap-2 text-xs font-bold transition-colors bg-slate-50 px-3 py-1.5 rounded-full border border-slate-200">
            &larr; Kembali Login
        </a>

        <div class="text-center mb-10 mt-6 flex flex-col items-center">
            <?php if($site_logo): ?>
                <img src="<?= $site_logo ?>" alt="Logo" class="w-40 md:w-48 h-auto object-contain mb-6 mx-auto">
            <?php else: ?>
                <div class="w-16 h-16 rounded-2xl bg-gradient-to-br from-primary to-secondary flex items-center justify-center text-white font-extrabold text-3xl shadow-xl shadow-indigo-500/30 mb-4 transform -rotate-6">H</div>
                <h2 class="text-3xl font-extrabold text-slate-900 mb-2 tracking-tight">HaloTiket</h2>
            <?php endif; ?>
            <h2 class="text-2xl font-extrabold text-slate-900 tracking-tight">Buat Akun Baru</h2>
            <p class="text-slate-500 font-medium text-sm mt-1">Lengkapi data diri Anda di bawah ini.</p>
        </div>

        <?php if(isset($_SESSION['error'])): ?>
            <div class="bg-red-50 border border-red-100 text-red-600 px-5 py-4 rounded-2xl mb-6 font-bold text-sm flex items-start gap-3 shadow-sm animate-pulse">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0 mt-0.5 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                <?= $_SESSION['error']; unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>
        
        <?php if(isset($_SESSION['success'])): ?>
            <div class="bg-emerald-50 border border-emerald-100 text-emerald-600 px-5 py-4 rounded-2xl mb-6 font-bold text-sm flex items-start gap-3 shadow-sm">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0 mt-0.5 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                <?= $_SESSION['success']; unset($_SESSION['success']); ?>
            </div>
        <?php endif; ?>

        <form action="proses_register.php" method="POST" class="space-y-5">
            <!-- Nama Lengkap -->
            <div class="space-y-1.5">
                <label class="text-[11px] font-extrabold text-slate-500 uppercase tracking-widest block">Nama Lengkap</label>
                <div class="relative group">
                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none transition-colors group-focus-within:text-primary text-slate-400">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg>
                    </div>
                    <input type="text" name="nama_lengkap" required placeholder="John Doe" class="w-full pl-11 pr-5 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary focus:bg-white transition-all text-sm font-semibold outline-none text-slate-800 placeholder-slate-400">
                </div>
            </div>

            <!-- Email -->
            <div class="space-y-1.5">
                <label class="text-[11px] font-extrabold text-slate-500 uppercase tracking-widest block">Alamat Email</label>
                <div class="relative group">
                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none transition-colors group-focus-within:text-primary text-slate-400">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>
                    </div>
                    <input type="email" name="email" required placeholder="nama@email.com" class="w-full pl-11 pr-5 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary focus:bg-white transition-all text-sm font-semibold outline-none text-slate-800 placeholder-slate-400">
                </div>
            </div>

            <!-- Nomor Telepon/WhatsApp -->
            <div class="space-y-1.5">
                <label class="text-[11px] font-extrabold text-slate-500 uppercase tracking-widest block">No. WhatsApp / Telepon</label>
                <div class="relative group">
                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none transition-colors group-focus-within:text-primary text-slate-400">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" /></svg>
                    </div>
                    <input type="text" name="no_hp" required placeholder="08xxxxxxxxxx" class="w-full pl-11 pr-5 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary focus:bg-white transition-all text-sm font-semibold outline-none text-slate-800 placeholder-slate-400">
                </div>
            </div>

            <!-- Alamat -->
            <div class="space-y-1.5">
                <label class="text-[11px] font-extrabold text-slate-500 uppercase tracking-widest block">Alamat</label>
                <div class="relative group">
                    <div class="absolute inset-y-0 left-0 pl-4 pt-3 pointer-events-none transition-colors group-focus-within:text-primary text-slate-400">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                    </div>
                    <textarea name="alamat" required rows="2" placeholder="Jl. Contoh Alamat No. 123" class="w-full pl-11 pr-5 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary focus:bg-white transition-all text-sm font-semibold outline-none text-slate-800 placeholder-slate-400"></textarea>
                </div>
            </div>

            <!-- Password -->
            <div class="space-y-1.5">
                <label class="text-[11px] font-extrabold text-slate-500 uppercase tracking-widest block">Password</label>
                <div class="relative group">
                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none transition-colors group-focus-within:text-primary text-slate-400">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>
                    </div>
                    <input type="password" id="password_input" name="password" required placeholder="Minimal 6 karakter" minlength="6" class="w-full pl-11 pr-12 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary focus:bg-white transition-all text-sm font-semibold outline-none text-slate-800 placeholder-slate-400">
                    <button type="button" id="toggle_password" class="absolute inset-y-0 right-0 pr-4 flex items-center text-slate-400 hover:text-slate-600 focus:outline-none">
                        <svg id="eye_icon" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                    </button>
                </div>
            </div>

            <div class="pt-4">
                <button type="submit" class="w-full bg-primary hover:bg-slate-900 text-white font-bold py-3.5 px-6 rounded-xl transition-all duration-300 shadow-lg shadow-primary/20 hover:shadow-slate-900/30 hover:-translate-y-0.5 text-sm tracking-wide">
                    Daftar Akun
                </button>
            </div>
        </form>
        
        <div class="mt-6 text-center">
            <p class="text-sm text-slate-500 font-medium">
                Sudah punya akun? <a href="login.php" class="text-primary font-bold hover:text-secondary transition-colors">Masuk di sini</a>
            </p>
        </div>
        
        <div class="mt-8 pt-6 border-t border-slate-100 flex justify-center">
            <p class="text-[10px] font-bold text-slate-400 flex items-center gap-1.5 uppercase tracking-widest">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 text-emerald-500" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M2.166 4.999A11.954 11.954 0 0010 1.944 11.954 11.954 0 0017.834 5c.11.65.166 1.32.166 2.001 0 5.225-3.34 9.67-8 11.317C5.34 16.67 2 12.225 2 7c0-.682.057-1.35.166-2.001zm11.541 3.708a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                &copy; <?= date('Y') ?> HaloTiket
            </p>
        </div>
    </div>

</body>
</html>
<script>
    const toggleBtn = document.getElementById('toggle_password');
    const passInput = document.getElementById('password_input');
    const eyeIcon = document.getElementById('eye_icon');

    toggleBtn.addEventListener('click', function() {
        if (passInput.type === 'password') {
            passInput.type = 'text';
            eyeIcon.innerHTML = `
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
            `;
        } else {
            passInput.type = 'password';
            eyeIcon.innerHTML = `
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
            `;
        }
    });
</script>
