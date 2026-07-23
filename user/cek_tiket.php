<?php
session_start();
require_once __DIR__ . '/../config/koneksi.php';

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cek Tiket & OTP - HaloTiket</title>
    <?php if (isset($global_site_favicon) && $global_site_favicon): ?>
        <link rel="icon" href="<?= $global_site_favicon ?>">
        <link rel="shortcut icon" href="<?= $global_site_favicon ?>">
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
                    }
                }
            }
        }
    </script>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
</head>
<body class="bg-slate-50 text-slate-800 font-sans antialiased flex flex-col min-h-screen selection:bg-primary selection:text-white relative">
    
    <!-- Decorative Background -->
    <div class="absolute inset-0 pointer-events-none overflow-hidden">
        <div class="absolute -top-1/4 -right-1/4 w-1/2 h-1/2 bg-primary/5 rounded-full blur-3xl"></div>
        <div class="absolute -bottom-1/4 -left-1/4 w-1/2 h-1/2 bg-secondary/5 rounded-full blur-3xl"></div>
    </div>

    <!-- DESKTOP NAVBAR -->
    <nav class="hidden md:block bg-white shadow-sm sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-20 relative">
                <div class="flex items-center shrink-0">
                    <button id="desktop-menu-btn" class="text-slate-900 focus:outline-none hover:bg-slate-50 p-2 rounded-lg transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>
                </div>

                <div class="absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2">
                    <a href="<?= BASE_URL ?>index.php" class="flex items-center gap-2 group">
                        <?php if (isset($global_site_logo) && $global_site_logo): ?>
                            <img src="<?= $global_site_logo ?>" alt="Logo" class="w-40 md:w-48 lg:w-56 h-auto object-contain group-hover:scale-105 transition-transform duration-300">
                        <?php else: ?>
                            <div class="w-10 h-10 rounded-xl bg-primary flex items-center justify-center text-white font-bold text-xl shadow-md group-hover:scale-105 transition-transform duration-300">H</div>
                            <span class="text-2xl font-extrabold text-slate-900 tracking-tight">HaloTiket</span>
                        <?php endif; ?>
                    </a>
                </div>

                <div class="flex items-center shrink-0">
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <a href="<?= BASE_URL ?><?= $_SESSION['role'] ?>/index.php" class="bg-primary text-white hover:bg-blue-700 px-6 py-2.5 rounded-full text-sm font-semibold transition-all shadow-md hover:shadow-lg">Dashboard</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <!-- MOBILE HEADER -->
    <header class="md:hidden px-5 py-4 flex items-center justify-between bg-white sticky top-0 z-40 shadow-sm">
        <button id="mobile-menu-btn" class="text-slate-900 focus:outline-none">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
            </svg>
        </button>
        <div class="flex items-center justify-center">
            <?php if (isset($global_site_logo) && $global_site_logo): ?>
                <img src="<?= $global_site_logo ?>" alt="Logo" class="w-28 h-auto object-contain">
            <?php else: ?>
                <h1 class="text-xl font-bold text-primary tracking-tight">HaloTiket</h1>
            <?php endif; ?>
        </div>
        <div class="w-6"></div>
    </header>

    <main class="flex-grow flex items-center justify-center p-4 sm:p-6 lg:p-12 w-full relative z-10">
        <div class="bg-white/90 backdrop-blur-xl rounded-[2.5rem] shadow-2xl shadow-slate-200/60 border border-slate-100 p-8 sm:p-12 w-full max-w-xl text-center relative overflow-hidden transition-all duration-300">
            <!-- Decorative Accent Bar -->
            <div class="absolute top-0 left-0 right-0 h-2 bg-gradient-to-r from-primary via-blue-500 to-indigo-600"></div>

            <!-- STEP 1: INPUT EMAIL / ORDER ID -->
            <div id="step1-card">
                <div class="w-24 h-24 bg-gradient-to-br from-cyan-50 to-blue-100/60 rounded-3xl flex items-center justify-center mx-auto mb-6 shadow-inner border border-blue-100 transform hover:scale-105 transition-transform duration-300">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z" />
                    </svg>
                </div>
                
                <h1 class="text-3xl sm:text-4xl font-extrabold text-slate-900 tracking-tight mb-3">Cari & Akses E-Ticket</h1>
                <p class="text-slate-500 font-medium text-sm sm:text-base mb-8 leading-relaxed">
                    Masukkan <span class="text-slate-800 font-bold">Email Pembelian</span> atau <span class="text-slate-800 font-bold">Kode Order ID</span> (contoh: <code>HTK-17482...</code>). Kami akan mengirimkan kode verifikasi OTP 6-digit ke email Anda.
                </p>
                
                <div id="alertMessageStep1" class="hidden mb-6 p-4 rounded-2xl text-sm font-semibold"></div>

                <form id="formKirimOtp" onsubmit="handleSendOtp(event)" class="space-y-4">
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-4 sm:pl-5 flex items-center pointer-events-none">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                            </svg>
                        </div>
                        <input type="text" id="input_search" name="input_search" required placeholder="Email Pembelian / Kode Order ID" class="w-full pl-12 sm:pl-14 pr-5 py-4 bg-slate-50 border border-slate-200 rounded-2xl focus:ring-4 focus:ring-primary/20 focus:border-primary focus:bg-white transition-all text-sm sm:text-base font-semibold text-slate-800 placeholder-slate-400 shadow-sm">
                    </div>

                    <button type="submit" id="btnSendOtp" class="w-full bg-slate-900 hover:bg-primary text-white font-bold py-4 px-6 rounded-2xl transition-all duration-300 shadow-xl shadow-slate-900/20 hover:shadow-indigo-500/30 hover:-translate-y-0.5 text-base sm:text-lg flex items-center justify-center gap-2">
                        <span>Kirim Kode OTP ke Email</span>
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3" /></svg>
                    </button>
                </form>
            </div>

            <!-- STEP 2: INPUT OTP VERIFICATION (Initially Hidden) -->
            <div id="step2-card" class="hidden">
                <div class="w-20 h-20 bg-emerald-50 rounded-3xl flex items-center justify-center mx-auto mb-6 border border-emerald-100 shadow-inner">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                    </svg>
                </div>
                
                <h2 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight mb-2">Cek Inbox Email Anda</h2>
                <p class="text-slate-500 font-medium text-xs sm:text-sm mb-6 leading-relaxed">
                    Kode verifikasi OTP 6-digit telah dikirimkan ke email: <br>
                    <strong id="targetEmailLabel" class="text-slate-900 font-mono text-sm bg-blue-50 border border-blue-200 px-3 py-1 rounded-lg inline-block mt-2">user@example.com</strong>
                </p>

                <div id="alertMessageStep2" class="hidden mb-6 p-4 rounded-2xl text-sm font-semibold"></div>

                <form id="formVerifyOtp" onsubmit="handleVerifyOtp(event)" class="space-y-6">
                    <!-- 6 Digit Input Box -->
                    <div class="flex justify-center gap-2 sm:gap-3" id="otpInputsContainer">
                        <input type="text" maxlength="1" class="otp-box w-11 h-14 sm:w-12 sm:h-16 text-center text-xl sm:text-2xl font-black bg-slate-50 border-2 border-slate-200 rounded-xl focus:border-primary focus:bg-white focus:ring-4 focus:ring-primary/20 outline-none transition-all" autofocus>
                        <input type="text" maxlength="1" class="otp-box w-11 h-14 sm:w-12 sm:h-16 text-center text-xl sm:text-2xl font-black bg-slate-50 border-2 border-slate-200 rounded-xl focus:border-primary focus:bg-white focus:ring-4 focus:ring-primary/20 outline-none transition-all">
                        <input type="text" maxlength="1" class="otp-box w-11 h-14 sm:w-12 sm:h-16 text-center text-xl sm:text-2xl font-black bg-slate-50 border-2 border-slate-200 rounded-xl focus:border-primary focus:bg-white focus:ring-4 focus:ring-primary/20 outline-none transition-all">
                        <input type="text" maxlength="1" class="otp-box w-11 h-14 sm:w-12 sm:h-16 text-center text-xl sm:text-2xl font-black bg-slate-50 border-2 border-slate-200 rounded-xl focus:border-primary focus:bg-white focus:ring-4 focus:ring-primary/20 outline-none transition-all">
                        <input type="text" maxlength="1" class="otp-box w-11 h-14 sm:w-12 sm:h-16 text-center text-xl sm:text-2xl font-black bg-slate-50 border-2 border-slate-200 rounded-xl focus:border-primary focus:bg-white focus:ring-4 focus:ring-primary/20 outline-none transition-all">
                        <input type="text" maxlength="1" class="otp-box w-11 h-14 sm:w-12 sm:h-16 text-center text-xl sm:text-2xl font-black bg-slate-50 border-2 border-slate-200 rounded-xl focus:border-primary focus:bg-white focus:ring-4 focus:ring-primary/20 outline-none transition-all">
                    </div>

                    <button type="submit" id="btnVerifyOtp" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-4 px-6 rounded-2xl transition-all duration-300 shadow-xl shadow-emerald-600/20 hover:-translate-y-0.5 text-base sm:text-lg flex items-center justify-center gap-2">
                        <span>Verifikasi OTP & Buka Tiket</span>
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                    </button>
                </form>

                <div class="mt-6 flex items-center justify-between text-xs font-bold text-slate-500 pt-4 border-t border-slate-100">
                    <button type="button" onclick="backToStep1()" class="hover:text-slate-800 transition-colors flex items-center gap-1">
                        ← Ubah Email / Order ID
                    </button>
                    <button type="button" onclick="resendOtp()" id="btnResend" class="text-primary hover:underline">
                        Kirim Ulang Kode OTP
                    </button>
                </div>
            </div>

            <div class="mt-8 pt-6 border-t border-slate-100 flex items-center justify-center gap-2 text-xs font-semibold text-slate-400">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                </svg>
                Kode OTP Dikirimkan Resmi Ke Email Anda
            </div>

        </div>
    </main>

    <!-- FOOTER -->
    <footer class="bg-gray-200 text-gray-600 py-10 mt-auto border-t border-gray-300">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-10 md:gap-8 items-start">
                <div class="flex flex-col items-start gap-4">
                    <?php if (isset($global_site_logo) && $global_site_logo): ?>
                        <a href="<?= BASE_URL ?>index.php"><img src="<?= $global_site_logo ?>" alt="Logo" class="w-36 md:w-44 lg:w-52 h-auto object-contain"></a>
                    <?php else: ?>
                        <a href="<?= BASE_URL ?>index.php" class="flex items-center gap-2">
                            <div class="w-8 h-8 rounded-lg bg-primary flex items-center justify-center text-white font-bold text-sm shadow-sm">H</div>
                            <span class="text-xl font-extrabold text-gray-900 tracking-tight">HaloTiket</span>
                        </a>
                    <?php endif; ?>
                    <p class="text-sm text-gray-500 mt-2">&copy; <?= date('Y') ?> HaloTiket. All rights reserved.</p>
                </div>
            </div>
        </div>
    </footer>

    <script>
        let currentSearchInput = '';

        function showAlert(step, type, msg) {
            const el = document.getElementById(step === 1 ? 'alertMessageStep1' : 'alertMessageStep2');
            el.classList.remove('hidden', 'bg-red-50', 'text-red-700', 'border-red-200', 'bg-emerald-50', 'text-emerald-700', 'border-emerald-200');
            if (type === 'error') {
                el.classList.add('bg-red-50', 'text-red-700', 'border', 'border-red-200');
            } else {
                el.classList.add('bg-emerald-50', 'text-emerald-700', 'border', 'border-emerald-200');
            }
            el.innerHTML = msg;
        }

        function handleSendOtp(e) {
            e.preventDefault();
            const inputVal = document.getElementById('input_search').value.trim();
            if (!inputVal) return;

            currentSearchInput = inputVal;
            const btn = document.getElementById('btnSendOtp');
            btn.disabled = true;
            btn.innerHTML = '<span>Mengirim Kode OTP...</span>';

            const formData = new FormData();
            formData.append('input_search', inputVal);

            fetch('<?= BASE_URL ?>actions/kirim_otp_tiket.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                btn.disabled = false;
                btn.innerHTML = '<span>Kirim Kode OTP ke Email</span>';

                if (data.status === 'success') {
                    document.getElementById('step1-card').classList.add('hidden');
                    document.getElementById('step2-card').classList.remove('hidden');
                    document.getElementById('targetEmailLabel').textContent = data.email;

                    const boxes = document.querySelectorAll('.otp-box');
                    boxes.forEach(b => b.value = '');
                    if (boxes[0]) boxes[0].focus();
                } else {
                    showAlert(1, 'error', data.message);
                }
            })
            .catch(err => {
                btn.disabled = false;
                btn.innerHTML = '<span>Kirim Kode OTP ke Email</span>';
                showAlert(1, 'error', 'Terjadi kesalahan jaringan.');
            });
        }

        function resendOtp() {
            if (!currentSearchInput) return;
            const btn = document.getElementById('btnResend');
            btn.textContent = 'Sending...';
            
            const formData = new FormData();
            formData.append('input_search', currentSearchInput);

            fetch('<?= BASE_URL ?>actions/kirim_otp_tiket.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                btn.textContent = 'Kirim Ulang Kode OTP';
                if (data.status === 'success') {
                    showAlert(2, 'success', 'Kode OTP baru telah dikirimkan ke email Anda!');
                } else {
                    showAlert(2, 'error', data.message);
                }
            });
        }

        function handleVerifyOtp(e) {
            e.preventDefault();
            const boxes = document.querySelectorAll('.otp-box');
            let otpVal = '';
            boxes.forEach(b => otpVal += b.value);

            if (otpVal.length < 6) {
                showAlert(2, 'error', 'Silakan masukkan 6-digit kode OTP lengkap.');
                return;
            }

            const btn = document.getElementById('btnVerifyOtp');
            btn.disabled = true;
            btn.innerHTML = '<span>Memverifikasi...</span>';

            const formData = new FormData();
            formData.append('otp_code', otpVal);

            fetch('<?= BASE_URL ?>actions/verifikasi_otp_tiket.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                btn.disabled = false;
                btn.innerHTML = '<span>Verifikasi OTP & Buka Tiket</span>';

                if (data.status === 'success') {
                    window.location.href = data.redirect_url;
                } else {
                    showAlert(2, 'error', data.message);
                }
            })
            .catch(err => {
                btn.disabled = false;
                btn.innerHTML = '<span>Verifikasi OTP & Buka Tiket</span>';
                showAlert(2, 'error', 'Terjadi kesalahan jaringan.');
            });
        }

        function backToStep1() {
            document.getElementById('step2-card').classList.add('hidden');
            document.getElementById('step1-card').classList.remove('hidden');
        }

        document.addEventListener('DOMContentLoaded', () => {
            const boxes = document.querySelectorAll('.otp-box');
            boxes.forEach((box, idx) => {
                box.addEventListener('input', (e) => {
                    if (e.target.value.length === 1) {
                        if (idx < boxes.length - 1) {
                            boxes[idx + 1].focus();
                        }
                    }
                });

                box.addEventListener('keydown', (e) => {
                    if (e.key === 'Backspace' && !box.value && idx > 0) {
                        boxes[idx - 1].focus();
                    }
                });
            });
        });
    </script>
</body>
</html>
