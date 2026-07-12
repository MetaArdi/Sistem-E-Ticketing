<?php
session_start();
require_once 'config/koneksi.php';



?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cek Tiket - HaloTiket</title>
    <?php if (isset($global_site_favicon) && $global_site_favicon): ?>
        <link rel="icon" href="<?= $global_site_favicon ?>">
        <link rel="shortcut icon" href="<?= $global_site_favicon ?>">
        <link rel="apple-touch-icon" href="<?= $global_site_favicon ?>">
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

    <!-- Custom Stylesheet -->
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
</head>
<body class="bg-slate-50 text-slate-800 font-sans antialiased flex flex-col min-h-screen selection:bg-primary selection:text-white relative">
    <!-- Decorative Background -->
    <div class="absolute inset-0 pointer-events-none overflow-hidden">
        <div class="absolute -top-1/4 -right-1/4 w-1/2 h-1/2 bg-primary/5 rounded-full blur-3xl"></div>
        <div class="absolute -bottom-1/4 -left-1/4 w-1/2 h-1/2 bg-secondary/5 rounded-full blur-3xl"></div>
    </div>

    <nav class="hidden md:block bg-white shadow-sm sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-20 relative">
                
                <div class="flex items-center shrink-0">
                    <!-- Desktop Hamburger -->
                    <button id="desktop-menu-btn" class="text-slate-900 focus:outline-none hover:bg-slate-50 p-2 rounded-lg transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>
                </div>

                <!-- Centered Logo -->
                <div class="absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2">
                    <a href="index.php" class="flex items-center gap-2 group">
                        <?php if (isset($global_site_logo) && $global_site_logo): ?>
                            <img src="<?= $global_site_logo ?>" alt="Logo" class="w-40 md:w-48 lg:w-56 h-auto object-contain group-hover:scale-105 transition-transform duration-300">
                        <?php else: ?>
                            <div class="w-10 h-10 rounded-xl bg-primary flex items-center justify-center text-white font-bold text-xl shadow-md group-hover:scale-105 transition-transform duration-300">
                                H
                            </div>
                            <span class="text-2xl font-extrabold text-slate-900 tracking-tight">HaloTiket</span>
                        <?php endif; ?>
                    </a>
                </div>

                <!-- Desktop Actions -->
                <div class="flex items-center shrink-0">
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <a href="<?= $_SESSION['role'] ?>/index.php"
                            class="bg-primary text-white hover:bg-blue-700 px-6 py-2.5 rounded-full text-sm font-semibold transition-all shadow-md hover:shadow-lg">Dashboard</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <!-- MOBILE HEADER -->
    <header class="md:hidden px-5 py-4 flex items-center justify-between bg-white sticky top-0 z-40 shadow-sm">
        <button id="mobile-menu-btn" class="text-slate-900 focus:outline-none">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24"
                stroke="currentColor" stroke-width="2">
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
        <!-- Spacer untuk menjaga logo tetap di tengah -->
        <div class="w-6"></div>
    </header>

    <main class="flex-grow flex items-center justify-center p-4 sm:p-6 lg:p-8 w-full relative z-10">
        <div class="bg-white rounded-[2rem] shadow-xl shadow-slate-200/50 border border-slate-100 p-8 sm:p-12 w-full max-w-xl text-center">
            
            <div class="w-20 h-20 bg-indigo-50 rounded-full flex items-center justify-center mx-auto mb-6">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z" /></svg>
            </div>
            
            <h1 class="text-3xl font-extrabold text-slate-900 tracking-tight mb-3">Cari E-Ticket Anda</h1>
            <p class="text-slate-500 font-medium mb-8">Masukkan alamat email yang Anda gunakan saat membeli tiket. Kami akan menampilkan seluruh riwayat pesanan Anda.</p>
            
            <form action="riwayat_pembelian.php" method="POST" class="space-y-4">
                
                <!-- Honeypot for Bot Protection -->
                <div class="hidden" aria-hidden="true">
                    <label for="website_url">Leave this field empty if you are human</label>
                    <input type="text" name="website_url" id="website_url" autocomplete="off" tabindex="-1">
                </div>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>
                    </div>
                    <input type="email" name="email" required placeholder="Masukan Email Anda" class="w-full pl-11 pr-5 py-4 bg-slate-50 border border-slate-200 rounded-2xl focus:ring-2 focus:ring-primary/20 focus:border-primary focus:bg-white transition-all text-sm font-medium">
                </div>
                <button type="submit" class="w-full bg-slate-900 hover:bg-primary text-white font-bold py-4 px-6 rounded-2xl transition-all duration-300 shadow-lg shadow-slate-900/20 hover:shadow-indigo-500/30 hover:-translate-y-0.5 text-lg">
                    Cari Tiket
                </button>
            </form>
            
        </div>
    </main>
<!-- FOOTER -->
    <footer class="bg-gray-200 text-gray-600 py-10 mt-auto border-t border-gray-300">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-10 md:gap-8 items-start">
                
                <!-- Left: Logo & Copyright -->
                <div class="flex flex-col items-start gap-4">
                    <?php if (isset($global_site_logo) && $global_site_logo): ?>
                        <a href="<?= BASE_URL ?>" class="hover:opacity-80 transition-opacity">
                            <img src="<?= $global_site_logo ?>" alt="Logo" class="w-36 md:w-44 lg:w-52 h-auto object-contain">
                        </a>
                    <?php else: ?>
                        <a href="<?= BASE_URL ?>" class="flex items-center gap-2 hover:opacity-80 transition-opacity">
                            <div class="w-8 h-8 rounded-lg bg-primary flex items-center justify-center text-white font-bold text-sm shadow-sm">H</div>
                            <span class="text-xl font-extrabold text-gray-900 tracking-tight">HaloTiket</span>
                        </a>
                    <?php endif; ?>
                    <p class="text-sm text-gray-500 mt-2">&copy; <?= date('Y') ?> HaloTiket. All rights reserved.</p>
                </div>

                <!-- Middle: Address -->
                <div class="flex flex-col items-start">
                    <h3 class="text-sm font-bold text-gray-900 mb-4 uppercase tracking-wider">Kantor Pusat</h3>
                    <p class="text-sm text-gray-600 leading-relaxed">
                        <?= nl2br(htmlspecialchars($global_contact_address)) ?>
                    </p>
                </div>

                <!-- Right: Social & CS -->
                <div class="flex flex-col items-start md:items-end w-full">
                    <h3 class="text-sm font-bold text-gray-900 mb-4 uppercase tracking-wider hidden md:block">Hubungi Kami</h3>
                    
                    <div class="flex flex-col items-center w-full md:w-auto">
                        <!-- CS Button -->
                        <a href="https://wa.me/<?= htmlspecialchars($global_contact_cs) ?>" target="_blank" class="inline-flex items-center justify-center gap-2 bg-primary text-white px-6 py-3 rounded-full text-sm font-bold hover:bg-blue-700 transition-colors shadow-md hover:shadow-lg mb-4 w-full md:w-auto">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                              <path d="M2 3a1 1 0 011-1h2.153a1 1 0 01.986.836l.74 4.435a1 1 0 01-.54 1.06l-1.548.773a11.037 11.037 0 006.105 6.105l.774-1.548a1 1 0 011.059-.54l4.435.74a1 1 0 01.836.986V17a1 1 0 01-1 1h-2C7.82 18 2 12.18 2 5V3z" />
                            </svg>
                            Customer Service (24/7)
                        </a>

                        <!-- Socials -->
                        <div class="flex items-center justify-center gap-4">
                            <?php if(!empty($global_link_ig)): ?>
                            <a href="<?= htmlspecialchars($global_link_ig) ?>" target="_blank" class="w-10 h-10 rounded-full bg-white border border-gray-300 flex items-center justify-center text-gray-600 hover:text-pink-600 hover:border-pink-600 transition-colors shadow-sm hover:shadow">
                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M12.315 2c2.43 0 2.784.013 3.808.06 1.064.049 1.791.218 2.427.465a4.902 4.902 0 011.772 1.153 4.902 4.902 0 011.153 1.772c.247.636.416 1.363.465 2.427.048 1.067.06 1.407.06 4.123v.08c0 2.643-.012 2.987-.06 4.043-.049 1.064-.218 1.791-.465 2.427a4.902 4.902 0 01-1.153 1.772 4.902 4.902 0 01-1.772 1.153c-.636.247-1.363.416-2.427.465-1.067.048-1.407.06-4.123.06h-.08c-2.643 0-2.987-.012-4.043-.06-1.064-.049-1.791-.218-2.427-.465a4.902 4.902 0 01-1.772-1.153 4.902 4.902 0 01-1.153-1.772c-.247-.636-.416-1.363-.465-2.427-.047-1.024-.06-1.379-.06-3.808v-.63c0-2.43.013-2.784.06-3.808.049-1.064.218-1.791.465-2.427a4.902 4.902 0 011.153-1.772A4.902 4.902 0 015.45 2.525c.636-.247 1.363-.416 2.427-.465C8.901 2.013 9.256 2 11.685 2h.63zm-.081 1.802h-.468c-2.456 0-2.784.011-3.807.058-.975.045-1.504.207-1.857.344-.467.182-.8.398-1.15.748-.35.35-.566.683-.748 1.15-.137.353-.3.882-.344 1.857-.047 1.023-.058 1.351-.058 3.807v.468c0 2.456.011 2.784.058 3.807.045.975.207 1.504.344 1.857.182.466.399.8.748 1.15.35.35.683.566 1.15.748.353.137.882.3 1.857.344 1.054.048 1.37.058 4.041.058h.08c2.597 0 2.917-.01 3.96-.058.976-.045 1.505-.207 1.858-.344.466-.182.8-.398 1.15-.748.35-.35.566-.683.748-1.15.137-.353.3-.882.344-1.857.048-1.055.058-1.37.058-4.041v-.08c0-2.597-.01-2.917-.058-3.96-.045-.976-.207-1.505-.344-1.858a3.097 3.097 0 00-.748-1.15 3.098 3.098 0 00-1.15-.748c-.353-.137-.882-.3-1.857-.344-1.023-.047-1.351-.058-3.807-.058zM12 6.865a5.135 5.135 0 110 10.27 5.135 5.135 0 010-10.27zm0 1.802a3.333 3.333 0 100 6.666 3.333 3.333 0 000-6.666zm5.338-3.205a1.2 1.2 0 110 2.4 1.2 1.2 0 010-2.4z" clip-rule="evenodd" />
                                </svg>
                            </a>
                            <?php endif; ?>
                            <?php if(!empty($global_link_tiktok)): ?>
                            <a href="<?= htmlspecialchars($global_link_tiktok) ?>" target="_blank" class="w-10 h-10 rounded-full bg-white border border-gray-300 flex items-center justify-center text-gray-600 hover:text-black hover:border-black transition-colors shadow-sm hover:shadow">
                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path d="M12.525.02c1.31-.02 2.61-.01 3.91-.02.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.05-2.89-.35-4.2-.97-.57-.26-1.1-.59-1.62-.93-.01 2.92.01 5.84-.02 8.75-.08 1.4-.54 2.79-1.35 3.94-1.31 1.92-3.58 3.17-5.91 3.21-1.43.08-2.86-.31-4.08-1.03-2.02-1.12-3.44-3.17-3.61-5.66-.21-3.11 2.05-5.9 5.09-6.32 1.5-.2 3.05.02 4.41.67v4.06c-1.12-.49-2.42-.57-3.58-.2-1.4.45-2.31 1.76-2.32 3.2-.04 1.48.97 2.87 2.41 3.26 1.43.37 3.02.05 4.09-1.02.73-.72 1.18-1.74 1.2-2.77-.04-5.32-.03-10.64-.03-15.96z"/>
                                </svg>
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </footer>

    <!-- MOBILE SIDEBAR -->
    <div id="mobile-sidebar-overlay" class="fixed inset-0 bg-slate-900/50 z-50 hidden opacity-0 transition-opacity duration-300"></div>
    <div id="mobile-sidebar" class="fixed inset-y-0 left-0 w-[280px] bg-white z-50 transform -translate-x-full transition-transform duration-300 shadow-2xl flex flex-col">
        <div class="p-5 flex items-center justify-between border-b border-slate-100">
            <div class="flex items-center gap-2">
                <?php if (isset($global_site_logo) && $global_site_logo): ?>
                    <img src="<?= $global_site_logo ?>" alt="Logo" class="w-28 h-auto object-contain">
                <?php else: ?>
                    <div class="w-7 h-7 rounded-lg bg-primary flex items-center justify-center text-white font-bold text-xs shadow-sm">H</div>
                    <span class="text-lg font-extrabold text-slate-900 tracking-tight">HaloTiket</span>
                <?php endif; ?>
            </div>
            <button id="close-sidebar-btn" class="text-slate-400 hover:text-slate-700 bg-slate-50 hover:bg-slate-100 rounded-full p-2 focus:outline-none transition-colors">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
        
        <div class="flex flex-col p-4 gap-2 overflow-y-auto">
            <div class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-2 mt-2 px-2">Menu Utama</div>
            <a href="index.php" class="flex items-center gap-3 px-4 py-3.5 rounded-xl text-slate-600 hover:bg-slate-50 font-semibold transition-colors">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                    <path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z" />
                </svg>
                Home
            </a>
            <a href="cek_tiket.php" class="flex items-center gap-3 px-4 py-3.5 rounded-xl bg-blue-50 text-primary font-bold transition-colors">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z" />
                </svg>
                Riwayat Tiket
            </a>
            
            <div class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-2 mt-6 px-2">Bantuan</div>
            <a href="https://wa.me/<?= htmlspecialchars($global_contact_cs) ?>" target="_blank" class="flex items-center gap-3 px-4 py-3.5 rounded-xl text-slate-600 hover:bg-slate-50 font-semibold transition-colors">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z" />
                </svg>
                Customer Service
            </a>
        </div>
        
        <div class="mt-auto p-5 border-t border-slate-100 bg-slate-50">
            <?php if (isset($_SESSION['user_id'])): ?>
                <a href="<?= $_SESSION['role'] ?>/index.php" class="flex items-center justify-center w-full bg-primary text-white py-3.5 rounded-xl font-bold shadow-md hover:bg-blue-700 hover:shadow-lg transition-all">
                    Dashboard Panel
                </a>
            <?php else: ?>
                <div class="flex flex-col gap-3">
                    <a href="auth/login.php" class="flex items-center justify-center w-full bg-slate-900 text-white py-3.5 rounded-xl font-bold shadow-md hover:bg-slate-800 hover:shadow-lg transition-all">
                        Login
                    </a>
                    <a href="auth/register.php" class="flex items-center justify-center w-full bg-white text-slate-900 border border-slate-300 py-3.5 rounded-xl font-bold shadow-sm hover:bg-slate-50 hover:shadow transition-all">
                        Register
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const btn = document.getElementById('mobile-menu-btn');
            const desktopBtn = document.getElementById('desktop-menu-btn');
            const closeBtn = document.getElementById('close-sidebar-btn');
            const sidebar = document.getElementById('mobile-sidebar');
            const overlay = document.getElementById('mobile-sidebar-overlay');

            function openSidebar() {
                sidebar.classList.remove('-translate-x-full');
                overlay.classList.remove('hidden');
                // Allow browser to render display:block before fading in
                setTimeout(() => { 
                    overlay.classList.remove('opacity-0'); 
                    overlay.classList.add('opacity-100'); 
                }, 10);
                document.body.style.overflow = 'hidden';
            }

            function closeSidebar() {
                sidebar.classList.add('-translate-x-full');
                overlay.classList.remove('opacity-100');
                overlay.classList.add('opacity-0');
                setTimeout(() => { 
                    overlay.classList.add('hidden'); 
                }, 300);
                document.body.style.overflow = '';
            }

            if(btn && sidebar) {
                btn.addEventListener('click', openSidebar);
            }
            if(desktopBtn && sidebar) {
                desktopBtn.addEventListener('click', openSidebar);
            }
            if (sidebar) {
                closeBtn.addEventListener('click', closeSidebar);
                overlay.addEventListener('click', closeSidebar);
            }
        });
    </script>
</body>

</html>
