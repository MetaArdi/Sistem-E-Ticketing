<?php
session_start();
require_once __DIR__ . '/config/koneksi.php';

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
                        <a href="<?= $_SESSION['role'] ?>/index.php" class="bg-primary text-white hover:bg-blue-700 px-6 py-2.5 rounded-full text-sm font-semibold transition-all shadow-md hover:shadow-lg">Dashboard</a>
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
                <p class="text-slate-600 font-medium text-sm sm:text-base mb-1.5 leading-relaxed">
                    Masukkan <span class="text-slate-900 font-extrabold">Email Pembelian</span> atau <span class="text-slate-900 font-extrabold">Kode Order ID</span> Anda
                </p>
                <p class="text-xs text-slate-400 font-medium mb-4">
                    (contoh Order ID: <code class="px-2 py-0.5 bg-slate-100 text-primary border border-slate-200 rounded-md font-mono font-bold">HTK-17482...</code>)
                </p>
                
                <div class="inline-flex items-center gap-2 bg-blue-50/80 text-blue-700 text-xs font-semibold px-4 py-2 rounded-full border border-blue-100 mb-8 shadow-sm">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-blue-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    <span>Kode verifikasi OTP 6-digit akan dikirimkan ke email Anda.</span>
                </div>
                
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
    <footer class="bg-slate-100 text-slate-600 py-12 mt-auto border-t border-slate-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-8 lg:gap-10 items-start">

                <!-- Col 1: Logo & Copyright -->
                <div class="flex flex-col items-start gap-3">
                    <?php if (isset($global_site_logo) && $global_site_logo): ?>
                        <a href="<?= BASE_URL ?>index.php" class="hover:opacity-80 transition-opacity">
                            <img src="<?= $global_site_logo ?>" alt="Logo" class="w-36 md:w-44 h-auto object-contain">
                        </a>
                    <?php else: ?>
                        <a href="<?= BASE_URL ?>index.php" class="flex items-center gap-2 hover:opacity-80 transition-opacity">
                            <div class="w-8 h-8 rounded-lg bg-primary flex items-center justify-center text-white font-bold text-sm shadow-sm">H</div>
                            <span class="text-xl font-extrabold text-slate-900 tracking-tight">HaloTiket</span>
                        </a>
                    <?php endif; ?>
                    <p class="text-xs text-slate-500 leading-relaxed">Platform e-ticketing aman & terpercaya untuk berbagai event, konser, dan festival favorit Anda.</p>
                    <p class="text-xs font-semibold text-slate-400 mt-1">&copy; <?= date('Y') ?> HaloTiket. All rights reserved.</p>
                </div>

                <!-- Col 2: Kantor Pusat -->
                <div class="flex flex-col items-start">
                    <h3 class="text-xs font-extrabold text-slate-900 mb-4 uppercase tracking-widest">Kantor Pusat</h3>
                    <p class="text-xs text-slate-600 leading-relaxed">
                        <?= nl2br(htmlspecialchars($global_contact_address ?? 'Kudus, Jawa Tengah, Indonesia')) ?>
                    </p>
                </div>

                <!-- Col 3: Informasi & Lainnya -->
                <div class="flex flex-col items-start">
                    <h3 class="text-xs font-extrabold text-slate-900 mb-4 uppercase tracking-widest">Informasi & Lainnya</h3>
                    <ul class="space-y-2.5 text-xs font-medium text-slate-600">
                        <li>
                            <button onclick="openTermsModal()" class="hover:text-primary transition-colors flex items-center gap-1.5 cursor-pointer">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                                <span>Syarat & Ketentuan</span>
                            </button>
                        </li>
                        <li>
                            <button onclick="openPrivacyModal()" class="hover:text-primary transition-colors flex items-center gap-1.5 cursor-pointer">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>
                                <span>Kebijakan Privasi</span>
                            </button>
                        </li>
                        <li>
                            <button onclick="openCsModal()" class="hover:text-primary transition-colors flex items-center gap-1.5 cursor-pointer">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                <span>Bantuan & FAQ</span>
                            </button>
                        </li>
                    </ul>
                </div>

                <!-- Col 4: Media Sosial -->
                <div class="flex flex-col items-start lg:items-end w-full">
                    <h3 class="text-xs font-extrabold text-slate-900 mb-4 uppercase tracking-widest">Media Sosial</h3>

                    <div class="flex flex-col items-start lg:items-end w-full">
                        <!-- Socials -->
                        <div class="flex items-center gap-3">
                            <?php if (!empty($global_link_ig)): ?>
                                <a href="<?= htmlspecialchars($global_link_ig) ?>" target="_blank" aria-label="Instagram"
                                    class="w-10 h-10 rounded-full bg-white border border-slate-300 flex items-center justify-center text-slate-600 hover:text-pink-600 hover:border-pink-600 transition-colors shadow-sm hover:shadow">
                                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path fill-rule="evenodd" d="M12.315 2c2.43 0 2.784.013 3.808.06 1.064.049 1.791.218 2.427.465a4.902 4.902 0 011.772 1.153 4.902 4.902 0 011.153 1.772c.247.636.416 1.363.465 2.427.048 1.067.06 1.407.06 4.123v.08c0 2.643-.012 2.987-.06 4.043-.049 1.064-.218 1.791-.465 2.427a4.902 4.902 0 01-1.153 1.772 4.902 4.902 0 01-1.772 1.153c-.636.247-1.363.416-2.427.465-1.067.048-1.407.06-4.123.06h-.08c-2.643 0-2.987-.012-4.043-.06-1.064-.049-1.791-.218-2.427-.465a4.902 4.902 0 01-1.772-1.153 4.902 4.902 0 01-1.153-1.772c-.247-.636-.416-1.363-.465-2.427-.047-1.024-.06-1.379-.06-3.808v-.63c0-2.43.013-2.784.06-3.808.049-1.064.218-1.791.465-2.427a4.902 4.902 0 011.153-1.772A4.902 4.902 0 015.45 2.525c.636-.247 1.363-.416 2.427-.465C8.901 2.013 9.256 2 11.685 2h.63zm-.081 1.802h-.468c-2.456 0-2.784.011-3.807.058-.975.045-1.504.207-1.857.344-.467.182-.8.398-1.15.748-.35.35-.566.683-.748 1.15-.137.353-.3.882-.344 1.857-.047 1.023-.058 1.351-.058 3.807v.468c0 2.456.011 2.784.058 3.807.045.975.207 1.504.344 1.857.182.466.399.8.748 1.15.35.35.683.566 1.15.748.353.137.882.3 1.857.344 1.054.048 1.37.058 4.041.058h.08c2.597 0 2.917-.01 3.96-.058.976-.045 1.505-.207 1.858-.344.466-.182.8-.398 1.15-.748.35-.35.566-.683.748-1.15.137-.353.3-.882.344-1.857.048-1.055.058-1.37.058-4.041v-.08c0-2.597-.01-2.917-.058-3.96-.045-.976-.207-1.505-.344-1.858a3.097 3.097 0 00-.748-1.15 3.098 3.098 0 00-1.15-.748c-.353-.137-.882-.3-1.857-.344-1.023-.047-1.351-.058-3.807-.058zM12 6.865a5.135 5.135 0 110 10.27 5.135 5.135 0 010-10.27zm0 1.802a3.333 3.333 0 100 6.666 3.333 3.333 0 000-6.666zm5.338-3.205a1.2 1.2 0 110 2.4 1.2 1.2 0 010-2.4z" clip-rule="evenodd" /></svg>
                                </a>
                            <?php endif; ?>
                            <?php if (!empty($global_link_tiktok)): ?>
                                <a href="<?= htmlspecialchars($global_link_tiktok) ?>" target="_blank" aria-label="TikTok"
                                    class="w-10 h-10 rounded-full bg-white border border-slate-300 flex items-center justify-center text-slate-600 hover:text-black hover:border-black transition-colors shadow-sm hover:shadow">
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12.525.02c1.31-.02 2.61-.01 3.91-.02.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.05-2.89-.35-4.2-.97-.57-.26-1.1-.59-1.62-.93-.01 2.92.01 5.84-.02 8.75-.08 1.4-.54 2.79-1.35 3.94-1.31 1.92-3.58 3.17-5.91 3.21-1.43.08-2.86-.31-4.08-1.03-2.02-1.12-3.44-3.17-3.61-5.66-.21-3.11 2.05-5.9 5.09-6.32 1.5-.2 3.05.02 4.41.67v4.06c-1.12-.49-2.42-.57-3.58-.2-1.4.45-2.31 1.76-2.32 3.2-.04 1.48.97 2.87 2.41 3.26 1.43.37 3.02.05 4.09-1.02.73-.72 1.18-1.74 1.2-2.77-.04-5.32-.03-10.64-.03-15.96z" /></svg>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
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

            fetch('actions/kirim_otp_tiket.php', {
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

            fetch('actions/kirim_otp_tiket.php', {
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

            fetch('actions/verifikasi_otp_tiket.php', {
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

    <!-- MOBILE / DESKTOP SIDEBAR DRAWER -->
    <div id="mobile-sidebar-overlay"
        class="fixed inset-0 bg-slate-900/50 z-50 hidden opacity-0 transition-opacity duration-300"></div>
    <div id="mobile-sidebar"
        class="fixed inset-y-0 left-0 w-[280px] bg-white z-50 transform -translate-x-full transition-transform duration-300 shadow-2xl flex flex-col">
        <div class="p-5 flex items-center justify-between border-b border-slate-100">
            <div class="flex items-center gap-2">
                <?php if (isset($global_site_logo) && $global_site_logo): ?>
                    <img src="<?= $global_site_logo ?>" alt="Logo" class="w-28 h-auto object-contain">
                <?php else: ?>
                    <div class="w-7 h-7 rounded-lg bg-primary flex items-center justify-center text-white font-bold text-xs shadow-sm">H</div>
                    <span class="text-lg font-extrabold text-slate-900 tracking-tight">HaloTiket</span>
                <?php endif; ?>
            </div>
            <button id="close-sidebar-btn"
                class="text-slate-400 hover:text-slate-700 bg-slate-50 hover:bg-slate-100 rounded-full p-2 focus:outline-none transition-colors">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <div class="flex flex-col p-4 gap-2 overflow-y-auto">
            <div class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-2 mt-2 px-2">Menu Utama</div>
            <a href="<?= BASE_URL ?>index.php"
                class="flex items-center gap-3 px-4 py-3.5 rounded-xl text-slate-600 hover:bg-slate-50 font-semibold transition-colors">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                    <path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z" />
                </svg>
                Home
            </a>
            <a href="cek_tiket.php"
                class="flex items-center gap-3 px-4 py-3.5 rounded-xl bg-blue-50 text-primary font-bold transition-colors">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z" />
                </svg>
                Cek & Akses Tiket
            </a>

            <div class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-2 mt-6 px-2">Bantuan</div>
            <button onclick="openCsModal()"
                class="w-full flex items-center gap-3 px-4 py-3.5 rounded-xl text-slate-600 hover:bg-slate-50 font-semibold transition-colors text-left cursor-pointer">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z" />
                </svg>
                Customer Service
            </button>
        </div>

        <div class="mt-auto p-5 border-t border-slate-100 bg-slate-50">
            <?php if (isset($_SESSION['user_id'])): ?>
                <a href="<?= $_SESSION['role'] ?>/index.php"
                    class="flex items-center justify-center w-full bg-primary text-white py-3.5 rounded-xl font-bold shadow-md hover:bg-blue-700 hover:shadow-lg transition-all">
                    Dashboard Panel
                </a>
            <?php else: ?>
                <div class="flex flex-col gap-3">
                    <a href="<?= BASE_URL ?>auth/login.php"
                        class="flex items-center justify-center w-full bg-slate-900 text-white py-3.5 rounded-xl font-bold shadow-md hover:bg-slate-800 hover:shadow-lg transition-all">
                        Masuk
                    </a>
                    <a href="<?= BASE_URL ?>auth/register.php"
                        class="flex items-center justify-center w-full bg-white text-slate-900 border border-slate-300 py-3.5 rounded-xl font-bold shadow-sm hover:bg-slate-50 hover:shadow transition-all">
                        Buat Akun
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- FLOATING CS BUTTON -->
    <button onclick="openCsModal()" aria-label="Customer Service" class="fixed bottom-6 right-6 z-40 bg-slate-900 hover:bg-emerald-600 text-white p-2.5 sm:px-4 sm:py-3 rounded-full shadow-2xl shadow-slate-900/40 hover:shadow-emerald-500/30 border border-slate-700/50 hover:border-emerald-500 hover:scale-105 transition-all duration-300 flex items-center gap-3 group cursor-pointer">
        <div class="relative flex items-center justify-center w-9 h-9 rounded-full bg-emerald-500 text-white shrink-0 shadow-sm">
            <svg class="w-4 h-4 fill-current" viewBox="0 0 24 24">
                <path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-1.002 3.66 3.745-.983z"/>
            </svg>
            <span class="absolute -top-0.5 -right-0.5 flex h-3 w-3">
                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-300 opacity-75"></span>
                <span class="relative inline-flex rounded-full h-3 w-3 bg-emerald-400 border-2 border-slate-900 group-hover:border-emerald-600 transition-colors"></span>
            </span>
        </div>
        <div class="hidden sm:flex flex-col items-start pr-1 text-left">
            <span class="text-[9px] font-bold text-slate-400 group-hover:text-emerald-100 uppercase tracking-widest leading-none mb-1">Bantuan 24/7</span>
            <span class="text-xs font-extrabold text-white tracking-wide leading-none">Customer Service</span>
        </div>
    </button>

    <!-- POP-UP MODAL CUSTOMER SERVICE -->
    <div id="cs-modal-overlay" onclick="closeCsModal()" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 hidden opacity-0 transition-opacity duration-300"></div>
    <div id="cs-modal" class="fixed top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[92%] max-w-md bg-white rounded-[2.5rem] shadow-2xl border border-slate-100 z-50 hidden opacity-0 scale-95 transition-all duration-300 p-6 sm:p-8">
        <div class="flex items-center justify-between pb-4 border-b border-slate-100 mb-5">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl bg-emerald-500/10 text-emerald-600 flex items-center justify-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z" />
                    </svg>
                </div>
                <div>
                    <h3 class="text-base font-extrabold text-slate-900">Customer Service 👋</h3>
                    <p class="text-[11px] font-semibold text-emerald-600 flex items-center gap-1">
                        <span class="w-2 h-2 rounded-full bg-emerald-500 inline-block animate-pulse"></span>
                        Online 24/7 • Siap Membantu
                    </p>
                </div>
            </div>
            <button onclick="closeCsModal()" class="text-slate-400 hover:text-slate-700 bg-slate-100 hover:bg-slate-200 p-2 rounded-full transition-colors cursor-pointer">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
        </div>

        <div class="space-y-4">
            <p class="text-xs text-slate-600 leading-relaxed">Punya pertanyaan seputar pemesanan tiket, kendala pembayaran, atau verifikasi OTP? Tim Customer Service kami siap melayani Anda.</p>

            <a href="https://wa.me/<?= htmlspecialchars($global_contact_cs ?? '') ?>" target="_blank"
                class="w-full flex items-center justify-center gap-3 bg-emerald-500 hover:bg-emerald-600 text-white font-extrabold py-3.5 px-5 rounded-2xl transition-all shadow-lg shadow-emerald-500/25 hover:shadow-emerald-600/30 text-sm">
                <svg class="w-5 h-5 fill-current" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-1.002 3.66 3.745-.983z"/></svg>
                <span>Chat WhatsApp CS</span>
            </a>

            <div class="bg-slate-50 border border-slate-100 rounded-2xl p-4 space-y-2.5">
                <h4 class="text-xs font-bold text-slate-800 uppercase tracking-wider">Topik Bantuan Cepat:</h4>
                <ul class="text-xs text-slate-600 space-y-1.5 list-disc list-inside">
                    <li>Kendala penerimaan kode OTP via Email</li>
                    <li>Status verifikasi pembayaran E-Wallet / QRIS</li>
                </ul>
            </div>
        </div>
    </div>

    <!-- POP-UP MODAL SYARAT & KETENTUAN -->
    <div id="terms-modal-overlay" onclick="closeTermsModal()" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 hidden opacity-0 transition-opacity duration-300"></div>
    <div id="terms-modal" class="fixed top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[92%] max-w-2xl bg-white rounded-[2.5rem] shadow-2xl border border-slate-100 z-50 hidden opacity-0 scale-95 transition-all duration-300 p-6 sm:p-8 max-h-[85vh] flex flex-col">
        <div class="flex items-center justify-between pb-4 border-b border-slate-100 mb-4 shrink-0">
            <h3 class="text-lg font-extrabold text-slate-900 flex items-center gap-2">
                <span>📋 Syarat & Ketentuan</span>
            </h3>
            <button onclick="closeTermsModal()" class="text-slate-400 hover:text-slate-700 bg-slate-100 hover:bg-slate-200 p-2 rounded-full transition-colors cursor-pointer">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
        </div>
        <div class="overflow-y-auto space-y-4 text-xs text-slate-600 leading-relaxed pr-2">
            <div>
                <h4 class="font-extrabold text-slate-900 mb-1 text-sm">1. Ketentuan Pemesanan Tiket</h4>
                <p>Pembelian tiket dilakukan secara online melalui platform resmi HaloTiket. Pembeli wajib mengisi data diri dengan valid dan sesuai identitas asli (KTP/SIM/Paspor).</p>
            </div>
            <div>
                <h4 class="font-extrabold text-slate-900 mb-1 text-sm">2. Verifikasi Kode OTP & Transaksi</h4>
                <p>Demi keamanan transaksi, sistem HaloTiket menggunakan metode verifikasi OTP (One-Time Password) 6-digit yang dikirimkan ke email terdaftar. Jangan pernah memberikan kode OTP kepada pihak manapun.</p>
            </div>
            <div>
                <h4 class="font-extrabold text-slate-900 mb-1 text-sm">3. Pembatalan & Pengembalian Dana (Refund)</h4>
                <p>Tiket yang sudah dibeli dan terkonfirmasi tidak dapat ditukar atau dikembalikan (non-refundable), kecuali dalam kondisi event dibatalkan secara resmi oleh Penyelenggara Event.</p>
            </div>
            <div>
                <h4 class="font-extrabold text-slate-900 mb-1 text-sm">4. Penukaran E-Tiket & Hak Masuk</h4>
                <p>E-tiket resmi akan diterbitkan setelah pembayaran terkonfirmasi. Pembeli wajib menukarkan e-tiket/QR code sesuai jadwal dan lokasi penukaran yang ditentukan oleh pihak penyelenggara.</p>
            </div>
        </div>
        <div class="pt-4 border-t border-slate-100 mt-4 text-right shrink-0">
            <button onclick="closeTermsModal()" class="bg-slate-900 hover:bg-primary text-white font-bold px-6 py-2.5 rounded-full text-xs transition-colors cursor-pointer">Saya Mengerti</button>
        </div>
    </div>

    <!-- POP-UP MODAL KEBIJAKAN PRIVASI -->
    <div id="privacy-modal-overlay" onclick="closePrivacyModal()" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 hidden opacity-0 transition-opacity duration-300"></div>
    <div id="privacy-modal" class="fixed top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[92%] max-w-2xl bg-white rounded-[2.5rem] shadow-2xl border border-slate-100 z-50 hidden opacity-0 scale-95 transition-all duration-300 p-6 sm:p-8 max-h-[85vh] flex flex-col">
        <div class="flex items-center justify-between pb-4 border-b border-slate-100 mb-4 shrink-0">
            <h3 class="text-lg font-extrabold text-slate-900 flex items-center gap-2">
                <span>🔒 Kebijakan Privasi (Privacy Policy)</span>
            </h3>
            <button onclick="closePrivacyModal()" class="text-slate-400 hover:text-slate-700 bg-slate-100 hover:bg-slate-200 p-2 rounded-full transition-colors cursor-pointer">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
        </div>
        <div class="overflow-y-auto space-y-4 text-xs text-slate-600 leading-relaxed pr-2">
            <div>
                <h4 class="font-extrabold text-slate-900 mb-1 text-sm">1. Pengumpulan Data Pribadi</h4>
                <p>HaloTiket mengumpulkan informasi pribadi seperti Nama Lengkap, Alamat Email, Nomor WhatsApp/Telepon, serta riwayat pemesanan untuk proses pemesanan dan penerbitan tiket resmi.</p>
            </div>
            <div>
                <h4 class="font-extrabold text-slate-900 mb-1 text-sm">2. Penggunaan Informasi</h4>
                <p>Data pribadi Anda hanya digunakan untuk verifikasi akun, pengiriman kode OTP, penerbitan e-tiket, serta layanan purna jual Customer Service. Kami tidak pernah memperjualbelikan data Anda kepada pihak ketiga.</p>
            </div>
            <div>
                <h4 class="font-extrabold text-slate-900 mb-1 text-sm">3. Keamanan Data</h4>
                <p>Seluruh transaksi data di enkripsi menggunakan standar keamanan terbaru untuk mencegah akses tidak sah, pembocoran, atau perubahan data pengguna.</p>
            </div>
        </div>
        <div class="pt-4 border-t border-slate-100 mt-4 text-right shrink-0">
            <button onclick="closePrivacyModal()" class="bg-slate-900 hover:bg-primary text-white font-bold px-6 py-2.5 rounded-full text-xs transition-colors cursor-pointer">Tutup</button>
        </div>
    </div>

    <script>
        // Modal Helper Functions
        function openCsModal() {
            const m = document.getElementById('cs-modal');
            const o = document.getElementById('cs-modal-overlay');
            if(!m || !o) return;
            o.classList.remove('hidden');
            m.classList.remove('hidden');
            setTimeout(() => {
                o.classList.remove('opacity-0');
                o.classList.add('opacity-100');
                m.classList.remove('opacity-0', 'scale-95');
                m.classList.add('opacity-100', 'scale-100');
            }, 10);
        }
        function closeCsModal() {
            const m = document.getElementById('cs-modal');
            const o = document.getElementById('cs-modal-overlay');
            if(!m || !o) return;
            o.classList.remove('opacity-100');
            o.classList.add('opacity-0');
            m.classList.remove('opacity-100', 'scale-100');
            m.classList.add('opacity-0', 'scale-95');
            setTimeout(() => {
                o.classList.add('hidden');
                m.classList.add('hidden');
            }, 300);
        }

        function openTermsModal() {
            const m = document.getElementById('terms-modal');
            const o = document.getElementById('terms-modal-overlay');
            if(!m || !o) return;
            o.classList.remove('hidden');
            m.classList.remove('hidden');
            setTimeout(() => {
                o.classList.remove('opacity-0');
                o.classList.add('opacity-100');
                m.classList.remove('opacity-0', 'scale-95');
                m.classList.add('opacity-100', 'scale-100');
            }, 10);
        }
        function closeTermsModal() {
            const m = document.getElementById('terms-modal');
            const o = document.getElementById('terms-modal-overlay');
            if(!m || !o) return;
            o.classList.remove('opacity-100');
            o.classList.add('opacity-0');
            m.classList.remove('opacity-100', 'scale-100');
            m.classList.add('opacity-0', 'scale-95');
            setTimeout(() => {
                o.classList.add('hidden');
                m.classList.add('hidden');
            }, 300);
        }

        function openPrivacyModal() {
            const m = document.getElementById('privacy-modal');
            const o = document.getElementById('privacy-modal-overlay');
            if(!m || !o) return;
            o.classList.remove('hidden');
            m.classList.remove('hidden');
            setTimeout(() => {
                o.classList.remove('opacity-0');
                o.classList.add('opacity-100');
                m.classList.remove('opacity-0', 'scale-95');
                m.classList.add('opacity-100', 'scale-100');
            }, 10);
        }
        function closePrivacyModal() {
            const m = document.getElementById('privacy-modal');
            const o = document.getElementById('privacy-modal-overlay');
            if(!m || !o) return;
            o.classList.remove('opacity-100');
            o.classList.add('opacity-0');
            m.classList.remove('opacity-100', 'scale-100');
            m.classList.add('opacity-0', 'scale-95');
            setTimeout(() => {
                o.classList.add('hidden');
                m.classList.add('hidden');
            }, 300);
        }

        document.addEventListener('DOMContentLoaded', function () {
            const btn = document.getElementById('mobile-menu-btn');
            const desktopBtn = document.getElementById('desktop-menu-btn');
            const closeBtn = document.getElementById('close-sidebar-btn');
            const sidebar = document.getElementById('mobile-sidebar');
            const overlay = document.getElementById('mobile-sidebar-overlay');

            function openSidebar() {
                if (!sidebar || !overlay) return;
                sidebar.classList.remove('-translate-x-full');
                overlay.classList.remove('hidden');
                setTimeout(() => {
                    overlay.classList.remove('opacity-0');
                    overlay.classList.add('opacity-100');
                }, 10);
                document.body.style.overflow = 'hidden';
            }

            function closeSidebar() {
                if (!sidebar || !overlay) return;
                sidebar.classList.add('-translate-x-full');
                overlay.classList.remove('opacity-100');
                overlay.classList.add('opacity-0');
                setTimeout(() => {
                    overlay.classList.add('hidden');
                }, 300);
                document.body.style.overflow = '';
            }

            if (btn) btn.addEventListener('click', openSidebar);
            if (desktopBtn) desktopBtn.addEventListener('click', openSidebar);
            if (closeBtn) closeBtn.addEventListener('click', closeSidebar);
            if (overlay) overlay.addEventListener('click', closeSidebar);
        });
    </script>
</body>
</html>
