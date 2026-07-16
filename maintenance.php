<?php
require_once 'config/koneksi.php';

// Jika maintenance mode mati, redirect ke home
if (!isset($global_settings['maintenance_mode']) || $global_settings['maintenance_mode'] != '1') {
    header("Location: index.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Under Maintenance - HaloTiket</title>
    <?php if (isset($global_site_favicon) && $global_site_favicon): ?>
        <link rel="icon" href="<?= $global_site_favicon ?>" type="image/x-icon">
    <?php endif; ?>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">
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

<body
    class="bg-slate-50 font-sans antialiased min-h-screen flex items-center justify-center text-slate-800 relative py-10 px-4">
    <div class="absolute top-0 left-0 w-full h-full overflow-hidden -z-10 pointer-events-none">
        <div class="absolute top-[10%] left-[20%] w-[30%] h-[30%] rounded-full bg-primary/10 blur-[80px]"></div>
        <div class="absolute bottom-[10%] right-[20%] w-[30%] h-[30%] rounded-full bg-amber-400/10 blur-[80px]"></div>
    </div>

    <div
        class="w-full max-w-lg bg-white rounded-3xl shadow-xl shadow-slate-200/50 p-8 sm:p-12 relative z-10 border border-slate-100 text-center">
        <?php if (isset($global_site_logo) && $global_site_logo): ?>
            <img src="<?= $global_site_logo ?>" alt="Logo"
                class="w-48 h-auto object-contain mx-auto mb-8 grayscale opacity-80">
        <?php else: ?>
            <div
                class="w-16 h-16 rounded-2xl bg-slate-200 flex items-center justify-center text-slate-400 font-extrabold text-3xl mb-8 mx-auto">
                H</div>
        <?php endif; ?>

        <div class="mb-6 flex justify-center">
            <img src="https://webdesigncompany.lk/images/services/creative-2-website-maintanance-services-company-in-sri-lanka.gif" alt="Maintenance Animation" class="w-64 h-auto object-contain">
        </div>

        <h1 class="text-3xl font-extrabold text-slate-900 mb-4 tracking-tight">Sistem Sedang Maintenance</h1>
        <p class="text-slate-500 font-medium leading-relaxed mb-8">
            Kami sedang melakukan pembaruan sistem untuk meningkatkan pengalaman Anda. Silakan kembali beberapa saat
            lagi.
        </p>

        <a href="auth/login.php"
            class="inline-flex items-center text-sm font-bold text-slate-400 hover:text-primary transition-colors">
            Login Internal <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 ml-1" fill="none" viewBox="0 0 24 24"
                stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
            </svg>
        </a>
    </div>
</body>

</html>