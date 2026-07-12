<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'validator') {
    header("Location: ../auth/login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Validator Scanner - HaloTiket</title>
    <?php if (isset($global_site_favicon) && $global_site_favicon): ?>
        <link rel="icon" href="<?= $global_site_favicon ?>" type="image/x-icon">
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
    <style>
        .split-bg {
            background-color: #0f172a;
            background-image: radial-gradient(at 0% 0%, hsla(239,100%,70%,0.15) 0px, transparent 50%),
                              radial-gradient(at 100% 100%, hsla(267,100%,74%,0.15) 0px, transparent 50%);
        }
    </style>

    <!-- Custom Stylesheet -->
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
</head>
<body class="split-bg font-sans antialiased flex flex-col min-h-screen selection:bg-primary selection:text-white">
    <!-- Premium Navbar -->
    <nav class="bg-dark/80 backdrop-blur-md shadow-sm sticky top-0 z-50 border-b border-slate-800">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <div class="flex items-center gap-3">
                    <?php if (isset($global_site_logo) && $global_site_logo): ?>
                    <img src="<?= $global_site_logo ?>" alt="Logo" class="w-40 md:w-48 h-auto object-contain">
                <?php else: ?>
                    <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-primary to-secondary flex items-center justify-center text-white font-bold text-sm shadow-md shadow-indigo-500/20">V</div>
                    <span class="text-xl font-extrabold text-white tracking-tight">Validator Portal</span>
                <?php endif; ?>
                </div>
                <div class="flex items-center gap-4">
                    <span class="text-slate-400 text-sm hidden sm:block font-medium">Hello, <?= htmlspecialchars($_SESSION['nama_lengkap']) ?></span>
                    <a href="../auth/logout.php" class="text-red-400 hover:text-white hover:bg-red-500/20 font-bold border border-red-500/30 px-4 py-2 rounded-xl transition-colors text-xs uppercase tracking-wider">Logout</a>
                </div>
            </div>
        </div>
    </nav>
    
    <main class="flex-grow flex items-center justify-center p-4 py-12 relative z-10">
        <div class="max-w-xl w-full">
            <div class="bg-white rounded-[2rem] shadow-2xl shadow-indigo-500/10 border border-slate-100 overflow-hidden relative">
                <!-- Top Decorative Line -->
                <div class="absolute top-0 left-0 w-full h-2 bg-gradient-to-r from-primary to-secondary"></div>
                
                <div class="bg-slate-50 border-b border-slate-100 p-8 text-center">
                    <div class="w-16 h-16 bg-white rounded-2xl flex items-center justify-center mx-auto mb-4 shadow-sm border border-slate-100 text-primary">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm14 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" /></svg>
                    </div>
                    <h2 class="text-2xl font-extrabold text-slate-900 tracking-tight">QR Code Scanner</h2>
                    <p class="text-slate-500 mt-1 font-medium text-sm">Arahkan kamera ke QR Code tiket pengunjung.</p>
                </div>
                
                <div class="p-8">
                    <!-- Scanner Container -->
                    <div id="reader" class="rounded-2xl overflow-hidden border-2 border-dashed border-slate-300 bg-slate-50 relative min-h-[300px] flex items-center justify-center"></div>
                    
                    <!-- Result Box -->
                    <div id="scan-result" class="mt-8 p-6 rounded-2xl text-center hidden border-2 transition-all duration-300 transform scale-95 opacity-0">
                        <!-- Result akan dimuat via JS -->
                    </div>
                </div>
            </div>
            
            <div class="mt-8 text-center text-sm font-medium text-slate-400 bg-dark/50 backdrop-blur-sm p-4 rounded-xl border border-slate-800">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 inline-block mr-1 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                Izinkan akses kamera di browser Anda untuk menggunakan fitur ini.
            </div>
        </div>
    </main>

    <script src="../assets/js/html5-qrcode.min.js"></script>
    <script>
        let isScanning = true;
        const html5QrcodeScanner = new Html5QrcodeScanner("reader", { fps: 10, qrbox: {width: 250, height: 250} }, false);

        function onScanSuccess(decodedText, decodedResult) {
            if(!isScanning) return;
            isScanning = false;
            
            html5QrcodeScanner.pause();
            
            const resultBox = document.getElementById('scan-result');
            resultBox.className = 'mt-8 p-6 rounded-2xl text-center border-2 transition-all duration-300 transform scale-100 opacity-100 bg-indigo-50 border-indigo-200 block';
            resultBox.innerHTML = '<div class="flex flex-col items-center justify-center gap-3"><svg class="animate-spin h-8 w-8 text-primary" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> <span class="font-bold text-primary">Memverifikasi tiket...</span></div>';

            fetch('proses_scan.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'token=' + encodeURIComponent(decodedText)
            })
            .then(response => response.json())
            .then(data => {
                if(data.status === 'success') {
                    resultBox.className = 'mt-8 p-6 rounded-2xl text-center border-2 transition-all duration-300 transform scale-100 opacity-100 bg-emerald-50 border-emerald-200 block';
                    resultBox.innerHTML = `
                        <div class="w-12 h-12 bg-emerald-100 text-emerald-600 rounded-full flex items-center justify-center mx-auto mb-3">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" /></svg>
                        </div>
                        <div class="font-extrabold text-xl mb-3 text-emerald-700">Akses Diberikan</div>
                        <div class="bg-white rounded-xl p-4 border border-emerald-100 text-left shadow-sm">
                            <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Nama Pemilik</p>
                            <p class="font-bold text-slate-900 mb-3">${data.data.nama_pembeli}</p>
                            <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Tiket Event</p>
                            <p class="font-bold text-slate-900">${data.data.judul}</p>
                        </div>
                    `;
                } else {
                    resultBox.className = 'mt-8 p-6 rounded-2xl text-center border-2 transition-all duration-300 transform scale-100 opacity-100 bg-red-50 border-red-200 block';
                    resultBox.innerHTML = `
                        <div class="w-12 h-12 bg-red-100 text-red-600 rounded-full flex items-center justify-center mx-auto mb-3">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M6 18L18 6M6 6l12 12" /></svg>
                        </div>
                        <div class="font-extrabold text-xl mb-2 text-red-700">Akses Ditolak</div>
                        <div class="text-sm font-medium text-red-600">${data.message}</div>
                    `;
                }
                
                // Lanjut scan setelah 3.5 detik
                setTimeout(() => {
                    resultBox.className = 'mt-8 p-6 rounded-2xl text-center border-2 transition-all duration-300 transform scale-95 opacity-0 hidden';
                    isScanning = true;
                    html5QrcodeScanner.resume();
                }, 3500);
            })
            .catch(error => {
                resultBox.className = 'mt-8 p-6 rounded-2xl text-center border-2 transition-all duration-300 transform scale-100 opacity-100 bg-red-50 border-red-200 block';
                resultBox.innerHTML = '<div class="font-bold text-red-700">Terjadi kesalahan sistem/jaringan.</div>';
                
                setTimeout(() => {
                    resultBox.className = 'mt-8 p-6 rounded-2xl text-center border-2 transition-all duration-300 transform scale-95 opacity-0 hidden';
                    isScanning = true;
                    html5QrcodeScanner.resume();
                }, 3000);
            });
        }

        html5QrcodeScanner.render(onScanSuccess);
        
        // Hide header branding from the library
        setTimeout(() => {
            const scanRegion = document.getElementById('reader__dashboard_section_csr');
            if(scanRegion) { scanRegion.style.display = 'none'; }
        }, 100);
    </script>
</body>
</html>
