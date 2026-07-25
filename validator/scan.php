<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'validator') {
    header("Location: ../auth/login.php");
    exit;
}
require_once '../config/koneksi.php';
$active_menu = 'scan';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scan Tiket - Validator - HaloTiket</title>
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
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
</head>
<body class="bg-slate-50 font-sans antialiased text-slate-800 selection:bg-primary selection:text-white overflow-hidden">
    <div class="flex h-screen w-full">
        
        <!-- Mobile Overlay -->
        <div id="sidebarOverlay" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-40 hidden md:hidden transition-opacity"></div>

        <?php include 'components/sidebar.php'; ?>

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
                    <span class="text-xl font-extrabold text-slate-800 md:hidden">Scan Tiket</span>
                </div>
                
                <div class="flex items-center gap-4">
                    <a href="profile.php" class="flex items-center gap-3 mr-2 px-3 py-1.5 rounded-full border border-slate-100 bg-slate-50 hover:bg-slate-100 hover:border-slate-200 transition-colors group cursor-pointer">
                        <?php if (isset($_SESSION['foto_profil']) && !empty($_SESSION['foto_profil']) && file_exists('../assets/images/profil/'.$_SESSION['foto_profil'])): ?>
                            <img src="../assets/images/profil/<?= htmlspecialchars($_SESSION['foto_profil']) ?>" class="w-8 h-8 rounded-full object-cover shadow-sm">
                        <?php else: ?>
                            <div class="w-8 h-8 rounded-full bg-gradient-to-br from-primary to-secondary text-white flex items-center justify-center font-bold text-sm shadow-sm group-hover:shadow transition-all">
                                <?= strtoupper(substr($_SESSION['nama_lengkap'], 0, 1)) ?>
                            </div>
                        <?php endif; ?>
                        <span class="text-sm font-bold text-slate-700 pr-2 group-hover:text-primary transition-colors"><?= htmlspecialchars($_SESSION['nama_lengkap']) ?></span>
                    </a>
                </div>
            </header>

            <main class="flex-1 overflow-y-auto p-4 md:p-6 lg:p-8 flex flex-col items-center relative">
                <div class="max-w-xl w-full my-auto py-6">
                    <div class="bg-white rounded-[2rem] shadow-xl border border-slate-100 overflow-hidden relative">
                        <!-- Top Decorative Line -->
                        <div class="absolute top-0 left-0 w-full h-2 bg-gradient-to-r from-primary to-secondary"></div>
                        
                        <div class="bg-white border-b border-slate-100 p-6 md:p-8 text-center pt-8">
                            <div class="w-16 h-16 bg-gradient-to-br from-primary/10 to-teal-500/20 rounded-2xl flex items-center justify-center mx-auto mb-4 shadow-sm border border-primary/20 text-primary">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm14 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" /></svg>
                            </div>
                            <h2 class="text-2xl font-extrabold text-slate-900 tracking-tight">QR Code Scanner</h2>
                            <p class="text-slate-500 mt-1 font-medium text-sm">Arahkan kamera ke QR Code e-ticket pengunjung.</p>
                        </div>
                        
                        <div class="p-6 md:p-8">
                            <!-- Custom Camera Controls -->
                            <div class="flex flex-wrap gap-4 justify-center mb-6">
                                <button id="btn-toggle-camera" onclick="toggleCamera()" class="bg-primary hover:bg-teal-600 text-white font-bold py-3 px-6 rounded-xl shadow-sm transition-all flex items-center gap-2">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" /></svg>
                                    <span id="text-toggle-camera">Mulai Kamera</span>
                                </button>
                                
                                <button id="btn-switch-camera" onclick="switchCamera()" class="bg-slate-200 hover:bg-slate-300 text-slate-700 font-bold py-3 px-6 rounded-xl shadow-sm transition-all flex items-center gap-2">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
                                    <span>Ganti Kamera</span>
                                </button>
                            </div>

                            <!-- Scanner Container -->
                            <div id="reader" class="rounded-2xl overflow-hidden border-2 border-dashed border-slate-300 bg-slate-50 relative min-h-[300px] flex items-center justify-center">
                                <div id="reader-placeholder" class="text-slate-400 text-center font-medium flex flex-col items-center gap-2">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 mx-auto" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                                    Kamera dimatikan
                                </div>
                            </div>
                            
                            <!-- Result Box (Modal Overlay) -->
                            <div id="scan-result" style="display: none;" class="fixed inset-0 z-[100] items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4 transition-all duration-300 opacity-0">
                                <!-- Result akan dimuat via JS -->
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Script for Sidebar Toggle -->
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const sidebar = document.getElementById('sidebar');
            const hamburgerBtn = document.getElementById('hamburgerBtn');
            const closeSidebar = document.getElementById('closeSidebar');
            const sidebarOverlay = document.getElementById('sidebarOverlay');

            function toggleSidebar() {
                if (window.innerWidth < 768) {
                    sidebar.classList.toggle('-translate-x-full');
                    if(sidebarOverlay) sidebarOverlay.classList.toggle('hidden');
                } else {
                    sidebar.classList.toggle('md:hidden');
                }
            }

            if(hamburgerBtn) hamburgerBtn.addEventListener('click', toggleSidebar);
            
            if(closeSidebar) {
                closeSidebar.addEventListener('click', () => {
                    sidebar.classList.add('-translate-x-full');
                    if(sidebarOverlay) sidebarOverlay.classList.add('hidden');
                });
            }

            if(sidebarOverlay) {
                sidebarOverlay.addEventListener('click', () => {
                    sidebar.classList.add('-translate-x-full');
                    sidebarOverlay.classList.add('hidden');
                });
            }

            window.addEventListener('resize', () => {
                if (window.innerWidth >= 768) {
                    sidebar.classList.remove('-translate-x-full');
                    if(sidebarOverlay) sidebarOverlay.classList.add('hidden');
                } else {
                    sidebar.classList.remove('md:hidden');
                }
            });
        });
    </script>

    <script src="<?= BASE_URL ?>assets/js/html5-qrcode.min.js"></script>
    <script>
        let isScanning = false;
        let isProcessing = false;
        let html5QrCode;
        let cameras = [];
        let currentCameraIndex = 0;
        let scanTimeout = null;
        
        // Fungsi inisialisasi Html5Qrcode secara aman
        function initScanner() {
            if (typeof Html5Qrcode === "undefined") {
                alert("Error: Library QR Code Scanner (html5-qrcode.min.js) gagal dimuat! Periksa lokasi file di assets/js/html5-qrcode.min.js");
                return;
            }
            if (!html5QrCode) {
                try {
                    html5QrCode = new Html5Qrcode("reader");
                } catch (e) {
                    console.error("Gagal menginisialisasi Html5Qrcode:", e);
                }
            }
        }

        // Fungsi untuk menutup hasil scan secara instan
        function dismissResult() {
            if (scanTimeout) {
                clearTimeout(scanTimeout);
                scanTimeout = null;
            }
            const resultBox = document.getElementById('scan-result');
            if (resultBox) {
                resultBox.style.display = 'none';
                resultBox.className = 'fixed inset-0 z-[100] items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4 transition-all duration-300 opacity-0';
                resultBox.innerHTML = '';
            }
            isProcessing = false;
            if (html5QrCode && isScanning) {
                try {
                    html5QrCode.resume();
                } catch (e) {
                    console.error("Gagal melanjutkan scan:", e);
                }
            }
        }

        // Coba inisialisasi langsung jika DOM sudah siap, atau tunggu DOMContentLoaded
        if (document.readyState === "complete" || document.readyState === "interactive") {
            initScanner();
        } else {
            document.addEventListener('DOMContentLoaded', initScanner);
        }

        function updateUI() {
            const btnToggle = document.getElementById("btn-toggle-camera");
            const textToggle = document.getElementById("text-toggle-camera");
            const placeholder = document.getElementById("reader-placeholder");
            const readerContainer = document.getElementById("reader");
            
            if (isScanning) {
                textToggle.innerText = "Matikan Kamera";
                btnToggle.classList.replace("bg-primary", "bg-rose-500");
                btnToggle.classList.replace("hover:bg-teal-600", "hover:bg-rose-600");
                if (placeholder) placeholder.style.display = 'none';
                if (readerContainer) {
                    readerContainer.classList.remove("flex", "items-center", "justify-center");
                }
            } else {
                textToggle.innerText = "Mulai Kamera";
                btnToggle.classList.replace("bg-rose-500", "bg-primary");
                btnToggle.classList.replace("hover:bg-rose-600", "hover:bg-teal-600");
                if (placeholder) placeholder.style.display = 'flex';
                if (readerContainer) {
                    readerContainer.classList.add("flex", "items-center", "justify-center");
                }
            }
        }

        function toggleCamera() {
            initScanner();
            if (isScanning) {
                if (html5QrCode) {
                    html5QrCode.stop().then(() => {
                        isScanning = false;
                        updateUI();
                    }).catch(err => {
                        console.error("Failed to stop scanner", err);
                    });
                }
            } else {
                startCamera();
            }
        }

        function switchCamera() {
            if (cameras.length > 1) {
                currentCameraIndex = (currentCameraIndex + 1) % cameras.length;
                if (isScanning) {
                    initScanner();
                    if (html5QrCode) {
                        html5QrCode.stop().then(() => {
                            startScannerWithCurrentCamera();
                        }).catch(err => console.error("Failed to stop scanner for switching", err));
                    }
                }
            } else {
                alert("Hanya ada satu kamera yang terdeteksi di perangkat Anda.");
            }
        }

        function startCamera() {
            // Cek apakah browser mendukung akses kamera (HTTPS / Localhost)
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                alert("Browser memblokir akses kamera! Pastikan Anda mengakses website melalui HTTPS atau Localhost.");
                return;
            }

            initScanner();

            if (cameras.length === 0) {
                Html5Qrcode.getCameras().then(devices => {
                    if (devices && devices.length) {
                        cameras = devices;
                        currentCameraIndex = 0;
                        // Prioritaskan kamera belakang jika ada
                        for (let i = 0; i < cameras.length; i++) {
                            let label = cameras[i].label.toLowerCase();
                            if (label.includes("back") || label.includes("belakang") || label.includes("environment")) {
                                currentCameraIndex = i;
                                break;
                            }
                        }
                        startScannerWithCurrentCamera();
                    } else {
                        alert("Tidak ada kamera yang ditemukan pada perangkat Anda.");
                    }
                }).catch(err => {
                    console.error("Error getting cameras", err);
                    alert("Gagal mendapatkan izin akses kamera. Pastikan Anda telah memberikan izin kamera pada browser Anda.");
                });
            } else {
                startScannerWithCurrentCamera();
            }
        }

        function startScannerWithCurrentCamera() {
            initScanner();
            if (!html5QrCode) {
                alert("Kamera gagal dimuat karena pemindai belum terinisialisasi.");
                return;
            }
            let cameraId = cameras[currentCameraIndex].id;
            
            // Konfigurasi qrbox dinamis agar pas dengan ukuran layar (responsive)
            const qrboxFunction = (viewfinderWidth, viewfinderHeight) => {
                const minEdge = Math.min(viewfinderWidth, viewfinderHeight);
                const size = Math.floor(minEdge * 0.7);
                return {
                    width: size < 200 ? Math.min(200, viewfinderWidth) : size,
                    height: size < 200 ? Math.min(200, viewfinderHeight) : size
                };
            };

            html5QrCode.start(
                cameraId,
                { fps: 10, qrbox: qrboxFunction },
                onScanSuccess
            ).then(() => {
                isScanning = true;
                updateUI();
            }).catch(err => {
                console.error("Camera start error:", err);
                alert("Kamera gagal dimuat: " + err);
            });
        }

        function onScanSuccess(decodedText, decodedResult) {
            if(isProcessing) return;
            isProcessing = true;
            
            try {
                if (html5QrCode) {
                    html5QrCode.pause();
                }
            } catch (e) {
                console.warn("Gagal melakukan pause pada scanner:", e);
            }
            
            const resultBox = document.getElementById('scan-result');
            if (resultBox) {
                resultBox.style.display = 'flex';
                resultBox.className = 'fixed inset-0 z-[100] items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4 transition-all duration-300 opacity-100';
                resultBox.innerHTML = '<div class="w-full max-w-sm bg-white rounded-2xl shadow-2xl p-6 text-center transform scale-100 transition-all duration-300"><div class="flex flex-col items-center justify-center gap-3"><svg class="animate-spin h-8 w-8 text-primary" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> <span class="font-bold text-primary">Memverifikasi tiket...</span></div></div>';
            }

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
                    // Tampilkan informasi pembeli dan pop-up centang secara instan
                    if (resultBox) {
                        resultBox.style.display = 'flex';
                        resultBox.className = 'fixed inset-0 z-[100] items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4 transition-all duration-300 opacity-100';
                        resultBox.innerHTML = `
                            <div class="w-full max-w-sm bg-white rounded-2xl shadow-2xl p-6 text-left transform scale-100 transition-all duration-300 relative border-t-4 border-emerald-500">
                                <!-- Tombol Silang -->
                                <button onclick="dismissResult()" class="absolute top-4 right-4 text-slate-400 hover:text-slate-600 transition-colors p-1 rounded-lg hover:bg-slate-100">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                                
                                <div class="w-12 h-12 bg-emerald-100 text-emerald-600 rounded-full flex items-center justify-center mx-auto mb-3">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" /></svg>
                                </div>
                                <div class="font-extrabold text-lg mb-2 text-emerald-700 text-center">${data.data.nama_pembeli} - ${data.data.jenis_tiket} Berhasil Divalidasi</div>
                                <div class="font-extrabold text-lg mb-4 text-slate-800 text-center border-b pb-3">Informasi Tiket</div>
                                <div class="grid grid-cols-2 gap-y-3 gap-x-4">
                                    <div>
                                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-0.5">ID Pembeli</p>
                                        <p class="font-bold text-slate-900 text-sm">${data.data.id_pembeli}</p>
                                    </div>
                                    <div>
                                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-0.5">Nama Pembeli</p>
                                        <p class="font-bold text-slate-900 text-sm">${data.data.nama_pembeli}</p>
                                    </div>
                                    <div>
                                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-0.5">Jenis Tiket</p>
                                        <p class="font-bold text-slate-900 text-sm">${data.data.jenis_tiket}</p>
                                    </div>
                                    <div>
                                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-0.5">Nama Acara</p>
                                        <p class="font-bold text-slate-900 text-sm">${data.data.judul}</p>
                                    </div>
                                    <div>
                                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-0.5">Harga</p>
                                        <p class="font-bold text-slate-900 text-sm">Rp ${parseInt(data.data.harga).toLocaleString('id-ID')}</p>
                                    </div>
                                    <div>
                                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-0.5">Waktu Beli</p>
                                        <p class="font-bold text-slate-900 text-sm">${data.data.tanggal_beli}</p>
                                    </div>
                                </div>
                                
                                <!-- Tombol OK -->
                                <div class="mt-6 flex justify-center">
                                    <button onclick="dismissResult()" class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-2.5 px-8 rounded-xl shadow-sm transition-all text-sm w-full">
                                        Tutup Hasil (OK)
                                    </button>
                                </div>
                            </div>
                        `;
                    }
                    
                    // Tampilkan selama 15 detik, lalu sembunyikan dan resume scan
                    scanTimeout = setTimeout(dismissResult, 15000);

                } else {
                    let failMsg = data.message;
                    if(data.data && data.data.nama_pembeli && data.data.jenis_tiket) {
                        failMsg = `Mohon maaf gagal memvalidasi ${data.data.nama_pembeli} - ${data.data.jenis_tiket} silahkan ulangi ke validator`;
                    } else {
                        failMsg = `Mohon maaf gagal memvalidasi, silahkan ulangi ke validator (${data.message})`;
                    }
                    if (resultBox) {
                        resultBox.style.display = 'flex';
                        resultBox.className = 'fixed inset-0 z-[100] items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4 transition-all duration-300 opacity-100';
                        resultBox.innerHTML = `
                            <div class="w-full max-w-sm bg-white rounded-2xl shadow-2xl p-6 text-center transform scale-100 transition-all duration-300 relative border-t-4 border-red-500">
                                <!-- Tombol Silang -->
                                <button onclick="dismissResult()" class="absolute top-4 right-4 text-red-400 hover:text-red-600 transition-colors p-1 rounded-lg hover:bg-red-100">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                                
                                <div class="w-12 h-12 bg-red-100 text-red-600 rounded-full flex items-center justify-center mx-auto mb-3">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M6 18L18 6M6 6l12 12" /></svg>
                                </div>
                                <div class="font-extrabold text-lg mb-2 text-red-700">Akses Ditolak</div>
                                <div class="text-sm font-medium text-red-600 mb-4">${failMsg}</div>
                                
                                <!-- Tombol OK -->
                                <div class="mt-4 flex justify-center">
                                    <button onclick="dismissResult()" class="bg-red-600 hover:bg-red-700 text-white font-bold py-2.5 px-8 rounded-xl shadow-sm transition-all text-sm w-full">
                                        Tutup Hasil (OK)
                                    </button>
                                </div>
                            </div>
                        `;
                    }
                    
                    // Tampilkan pop up gagal selama 15 detik
                    scanTimeout = setTimeout(dismissResult, 15000);
                }
            })
            .catch(error => {
                if (resultBox) {
                    resultBox.style.display = 'flex';
                    resultBox.className = 'fixed inset-0 z-[100] items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4 transition-all duration-300 opacity-100';
                    resultBox.innerHTML = `
                        <div class="w-full max-w-sm bg-white rounded-2xl shadow-2xl p-6 text-center transform scale-100 transition-all duration-300 relative border-t-4 border-red-500">
                            <!-- Tombol Silang -->
                            <button onclick="dismissResult()" class="absolute top-4 right-4 text-red-400 hover:text-red-600 transition-colors p-1 rounded-lg hover:bg-red-100">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                            <div class="font-bold text-red-600 mb-2 mt-4">Terjadi Kesalahan Koneksi</div>
                            <p class="text-sm mb-4 text-slate-600">Tidak dapat menghubungi server.</p>
                            
                            <!-- Tombol OK -->
                            <div class="mt-4 flex justify-center">
                                <button onclick="dismissResult()" class="bg-red-600 hover:bg-red-700 text-white font-bold py-2.5 px-8 rounded-xl shadow-sm transition-all text-sm w-full">
                                    Tutup Hasil (OK)
                                </button>
                            </div>
                        </div>
                    `;
                }
                scanTimeout = setTimeout(dismissResult, 3500);
            });
        }
    </script>
</body>
</html>
