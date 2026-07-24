<?php
session_start();
require_once 'config/koneksi.php';

// Filter
$search = isset($_GET['q']) ? $_GET['q'] : '';
$kategori = isset($_GET['kategori']) ? $_GET['kategori'] : '';

// Base queries
$newest_sql = "SELECT * FROM events WHERE status_approval = 'approved'";
$upcoming_sql = "SELECT * FROM events WHERE tanggal >= CURDATE() AND status_approval = 'approved'";

$params_newest = [];
$types_newest = "";

$params_upcoming = [];
$types_upcoming = "";

if ($search != '') {
    $newest_sql .= " AND (judul LIKE ? OR lokasi LIKE ?)";
    $upcoming_sql .= " AND (judul LIKE ? OR lokasi LIKE ?)";
    $search_param = "%$search%";

    $params_newest[] = $search_param;
    $params_newest[] = $search_param;
    $types_newest .= "ss";

    $params_upcoming[] = $search_param;
    $params_upcoming[] = $search_param;
    $types_upcoming .= "ss";
}

if ($kategori != '') {
    $newest_sql .= " AND kategori = ?";
    $upcoming_sql .= " AND kategori = ?";

    $params_newest[] = $kategori;
    $types_newest .= "s";

    $params_upcoming[] = $kategori;
    $types_upcoming .= "s";
}

$newest_sql .= " ORDER BY created_at DESC LIMIT 4";
$upcoming_sql .= " ORDER BY tanggal ASC LIMIT 10";

// Fetch Newest Events
$stmt_newest = $conn->prepare($newest_sql);
if ($types_newest != "") {
    $stmt_newest->bind_param($types_newest, ...$params_newest);
}
$stmt_newest->execute();
$res_newest = $stmt_newest->get_result();
$newest_events = [];
while ($row = $res_newest->fetch_assoc()) {
    $newest_events[] = $row;
}

// Fetch Upcoming Events
$stmt_upcoming = $conn->prepare($upcoming_sql);
if ($types_upcoming != "") {
    $stmt_upcoming->bind_param($types_upcoming, ...$params_upcoming);
}
$stmt_upcoming->execute();
$res_upcoming = $stmt_upcoming->get_result();
$upcoming_events = [];
while ($row = $res_upcoming->fetch_assoc()) {
    $upcoming_events[] = $row;
}

// Fetch Hero Slider settings
$hero_slides = [];
if (isset($global_settings['landing_hero_slider']) && !empty($global_settings['landing_hero_slider'])) {
    $hero_slides = json_decode($global_settings['landing_hero_slider'], true) ?: [];
}

// Fallback to default slides if no custom slides uploaded
if (empty($hero_slides)) {
    $hero_slides = [
        [
            'id' => 'default_1',
            'image_url' => 'https://images.unsplash.com/photo-1470225620780-dba8ba36b745?auto=format&fit=crop&w=1400&q=80',
            'title' => 'Ciptakan Momen & Konser Musik Terbaik',
            'subtitle' => 'Temukan ribuan event seru, festival musik, dan pertunjukan favoritmu di HaloTiket.',
            'link' => ''
        ],
        [
            'id' => 'default_2',
            'image_url' => 'https://images.unsplash.com/photo-1501386761578-eac5c94b800a?auto=format&fit=crop&w=1400&q=80',
            'title' => 'Pengalaman Berkesan Tanpa Batas',
            'subtitle' => 'Nikmati pemesanan tiket kilat, transaksi instan, dan QR Code pemindaian cepat.',
            'link' => ''
        ]
    ];
} else {
    foreach ($hero_slides as &$slide) {
        $slide['image_url'] = BASE_URL . 'assets/images/slider/' . $slide['image'];
    }
    unset($slide);
}

?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>HaloTiket - Ciptakan Momen Terbaikmu</title>
    
    <!-- PWA Meta Tags & Manifest -->
    <link rel="manifest" href="manifest.json.php">
    <meta name="theme-color" content="#0f1c3f">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="HaloTiket">

    <!-- Early PWA Prompt Capture & Auto Trigger Native Install Modal -->
    <script>
        window.deferredPwaPrompt = null;
        window.addEventListener('beforeinstallprompt', (e) => {
            e.preventDefault();
            window.deferredPwaPrompt = e;
            console.log('PWA beforeinstallprompt event successfully captured!');
            
            // Otomatis picu modal native "Install app" resmi browser (Chrome/Edge/Android)
            setTimeout(() => {
                if (window.deferredPwaPrompt) {
                    window.deferredPwaPrompt.prompt();
                }
            }, 600);
        });

        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function() {
                navigator.serviceWorker.register('sw.js').then(function(reg) {
                    console.log('PWA ServiceWorker registered:', reg.scope);
                }).catch(function(err) {
                    console.log('PWA ServiceWorker registration failed:', err);
                });
            });
        }
    </script>

    <?php if (isset($global_site_favicon) && $global_site_favicon): ?>
        <link rel="icon" href="<?= $global_site_favicon ?>">
        <link rel="shortcut icon" href="<?= $global_site_favicon ?>">
        <link rel="apple-touch-icon" href="<?= $global_site_favicon ?>">
    <?php elseif (isset($global_site_logo) && $global_site_logo): ?>
        <link rel="icon" href="<?= $global_site_logo ?>">
        <link rel="apple-touch-icon" href="<?= $global_site_logo ?>">
    <?php endif; ?>

    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                    },
                    colors: {
                        primary: '#00c2cb',
                        bgLight: '#F8F9FA',
                    }
                }
            }
        }
    </script>
    <!-- Custom Stylesheet -->
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
</head>

<body
    class="bg-bgLight text-slate-800 font-sans antialiased selection:bg-primary selection:text-white min-h-screen flex flex-col">

    <!-- DESKTOP NAVBAR -->
    <nav class="hidden md:block bg-white shadow-sm sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-20">

                <div class="flex items-center gap-4 shrink-0">
                    <!-- Desktop Hamburger -->
                    <button id="desktop-menu-btn"
                        class="text-slate-900 focus:outline-none hover:bg-slate-50 p-2 rounded-lg transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>
                </div>

                <!-- Desktop Search -->
                <div class="flex-grow max-w-2xl mx-8">
                    <form action="index.php" method="GET" class="relative">
                        <?php if ($kategori != ''): ?>
                            <input type="hidden" name="kategori" value="<?= htmlspecialchars($kategori) ?>">
                        <?php endif; ?>
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-slate-400" fill="none"
                                viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </div>
                        <input type="text" name="q" value="<?= htmlspecialchars($search) ?>"
                            placeholder="Search events, artists, venues..."
                            class="w-full pl-11 pr-4 py-3 bg-slate-50 border border-slate-200 rounded-full text-sm font-medium focus:outline-none focus:ring-2 focus:ring-primary/20 focus:bg-white transition-all text-slate-700 placeholder-slate-400">
                    </form>
                </div>

                <!-- Desktop Actions & Logo -->
                <div class="flex items-center space-x-6 shrink-0">
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <a href="<?= $_SESSION['role'] ?>/index.php"
                            class="bg-primary text-white hover:bg-blue-700 px-6 py-2.5 rounded-full text-sm font-semibold transition-all shadow-md hover:shadow-lg">Dashboard</a>
                    <?php endif; ?>

                    <!-- Logo moved to the right -->
                    <a href="index.php" class="flex items-center gap-2 group">
                        <?php if (isset($global_site_logo) && $global_site_logo): ?>
                            <img src="<?= $global_site_logo ?>" alt="Logo"
                                class="w-40 md:w-48 lg:w-56 h-auto object-contain group-hover:scale-105 transition-transform duration-300">
                        <?php else: ?>
                            <div
                                class="w-10 h-10 rounded-xl bg-primary flex items-center justify-center text-white font-bold text-xl shadow-md group-hover:scale-105 transition-transform duration-300">
                                H
                            </div>
                            <span class="text-2xl font-extrabold text-slate-900 tracking-tight">HaloTiket</span>
                        <?php endif; ?>
                    </a>
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

    <!-- MAIN CONTENT -->
    <main class="flex-grow max-w-7xl mx-auto w-full px-5 md:px-6 lg:px-8 pt-6 pb-12">

        <!-- Mobile Search Bar (Hidden on Desktop) -->
        <div class="md:hidden mb-6">
            <form action="index.php" method="GET" class="relative">
                <?php if ($kategori != ''): ?>
                    <input type="hidden" name="kategori" value="<?= htmlspecialchars($kategori) ?>">
                <?php endif; ?>
                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-slate-400" fill="none"
                        viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </div>
                <input type="text" name="q" value="<?= htmlspecialchars($search) ?>"
                    placeholder="Search events, artists..."
                    class="w-full pl-11 pr-4 py-3.5 bg-white border border-slate-200 rounded-full text-sm font-medium focus:outline-none focus:ring-2 focus:ring-primary/20 shadow-sm text-slate-700 placeholder-slate-400">
            </form>
        </div>

        <?php if ($search != '' || $kategori != ''): ?>
            <div
                class="mb-6 bg-blue-50 text-blue-700 px-4 py-3 rounded-xl text-sm font-medium flex items-center justify-between border border-blue-100">
                <div>
                    Menampilkan hasil untuk:
                    <?php if ($search != '')
                        echo "<span class='font-bold'>\"" . htmlspecialchars($search) . "\"</span>"; ?>
                    <?php if ($search != '' && $kategori != '')
                        echo " dalam kategori "; ?>
                    <?php if ($kategori != '')
                        echo "<span class='font-bold bg-white px-2 py-0.5 rounded-md text-primary ml-1 border border-blue-200'>" . htmlspecialchars($kategori) . "</span>"; ?>
                </div>
                <a href="index.php" class="text-blue-500 hover:text-blue-800 font-bold underline text-xs">Reset</a>
            </div>
        <?php endif; ?>

        <!-- HERO IMAGE SLIDER / CAROUSEL -->
        <div class="mb-10 relative group rounded-3xl overflow-hidden shadow-xl border border-slate-200 bg-slate-900">
            <div id="heroCarousel" class="relative w-full aspect-[16/5] min-h-[160px] sm:min-h-[220px] md:min-h-[300px] lg:min-h-[360px] max-h-[440px] overflow-hidden">
                <?php foreach ($hero_slides as $index => $slide): ?>
                    <div class="hero-slide absolute inset-0 transition-opacity duration-700 ease-in-out opacity-0 z-0 flex items-end <?= $index === 0 ? 'opacity-100 z-10' : '' ?>" data-slide-index="<?= $index ?>">
                        <!-- Slide Background Image -->
                        <img src="<?= htmlspecialchars($slide['image_url']) ?>" alt="Banner Slide <?= $index + 1 ?>" class="w-full h-full object-cover object-center">
                        
                        <?php if (!empty($slide['title']) || !empty($slide['subtitle']) || !empty($slide['link'])): ?>
                            <!-- Dark Overlay Gradient -->
                            <div class="absolute inset-0 bg-gradient-to-t from-slate-950/85 via-slate-950/30 to-transparent"></div>

                            <!-- Slide Content Overlay -->
                            <div class="relative z-10 p-4 sm:p-6 md:p-8 max-w-2xl text-white">
                                <span class="inline-block px-3 py-1 bg-primary/90 text-white text-[10px] md:text-xs font-extrabold uppercase tracking-wider rounded-full mb-2 shadow-sm backdrop-blur-md">HaloTiket Featured</span>
                                <?php if (!empty($slide['title'])): ?>
                                    <h2 class="text-lg sm:text-2xl md:text-3xl font-extrabold tracking-tight drop-shadow-md leading-tight text-white mb-1">
                                        <?= htmlspecialchars($slide['title']) ?>
                                    </h2>
                                <?php endif; ?>
                                <?php if (!empty($slide['subtitle'])): ?>
                                    <p class="text-xs sm:text-sm text-slate-200 font-medium line-clamp-2 mb-3 drop-shadow">
                                        <?= htmlspecialchars($slide['subtitle']) ?>
                                    </p>
                                <?php endif; ?>
                                <?php if (!empty($slide['link'])): ?>
                                    <a href="<?= htmlspecialchars($slide['link']) ?>" class="inline-flex items-center gap-2 bg-primary hover:bg-primary/90 text-white text-xs font-bold px-4 py-2 rounded-full shadow-lg transition-transform duration-300 transform hover:-translate-y-0.5">
                                        Lihat Detail Event
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" /></svg>
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Navigation Controls -->
            <?php if (count($hero_slides) > 1): ?>
                <button id="sliderPrevBtn" aria-label="Previous Slide" class="absolute left-3 top-1/2 -translate-y-1/2 z-20 w-10 h-10 rounded-full bg-slate-900/50 hover:bg-slate-900/80 text-white backdrop-blur-sm border border-white/20 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-all duration-300 cursor-pointer">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7" /></svg>
                </button>
                <button id="sliderNextBtn" aria-label="Next Slide" class="absolute right-3 top-1/2 -translate-y-1/2 z-20 w-10 h-10 rounded-full bg-slate-900/50 hover:bg-slate-900/80 text-white backdrop-blur-sm border border-white/20 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-all duration-300 cursor-pointer">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7" /></svg>
                </button>

                <!-- Indicator Dots -->
                <div class="absolute bottom-4 right-6 z-20 flex items-center gap-2" id="sliderDotsContainer">
                    <?php foreach ($hero_slides as $index => $slide): ?>
                        <button aria-label="Slide <?= $index + 1 ?>" class="slider-dot w-2.5 h-2.5 rounded-full transition-all duration-300 cursor-pointer <?= $index === 0 ? 'bg-primary w-7' : 'bg-white/50 hover:bg-white' ?>" data-dot-index="<?= $index ?>"></button>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Categories -->
        <div class="mb-10 md:mb-12">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg md:text-2xl font-bold text-slate-800">Kategori Event</h2>
            </div>
            <div
                class="flex items-center gap-4 md:gap-8 overflow-x-auto pb-4 scrollbar-hide -mx-5 px-5 md:mx-0 md:px-0">

                <?php
                // Ambil kategori dari tabel event_categories
                $cat_db = $conn->query("SELECT nama, ikon FROM event_categories ORDER BY nama ASC");
                $categories = [];
                if ($cat_db) {
                    while ($cr = $cat_db->fetch_assoc()) {
                        $categories[] = [
                            'nama' => $cr['nama'],
                            'ikon' => $cr['ikon'] ?? 'star'
                        ];
                    }
                }
                if (empty($categories)) {
                    $categories = [
                        ['nama' => 'Musik', 'ikon' => 'music'],
                        ['nama' => 'Olahraga', 'ikon' => 'sports'],
                        ['nama' => 'Kuliner', 'ikon' => 'food'],
                        ['nama' => 'Seni', 'ikon' => 'arts']
                    ];
                }

                foreach ($categories as $cat_item):
                    $cat = $cat_item['nama'];
                    $cat_icon_key = $cat_item['ikon'];

                    if (empty($cat)) continue;
                    $isActive = ($kategori == $cat);
                    $url = "index.php?kategori=" . urlencode($cat);
                    if ($search != '') $url .= "&q=" . urlencode($search);
                    
                    $icon_svg = getCategoryIconSvg($cat_icon_key, $cat);
                    ?>
                    <a href="<?= $url ?>"
                        class="flex flex-col items-center gap-2 md:gap-3 min-w-[70px] md:min-w-[90px] shrink-0 group">
                        <div
                            class="w-16 h-16 md:w-20 md:h-20 rounded-2xl md:rounded-[1.5rem] <?= $isActive ? 'bg-primary text-white shadow-md border-primary' : 'bg-white text-slate-700 shadow-sm border-slate-200' ?> group-hover:bg-primary flex items-center justify-center group-hover:text-white border transition-colors duration-300">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 md:h-10 md:w-10" viewBox="0 0 24 24"
                                stroke-linecap="round" stroke-linejoin="round">
                                <?= $icon_svg ?>
                            </svg>
                        </div>
                        <span
                            class="text-[11px] md:text-sm font-semibold <?= $isActive ? 'text-primary' : 'text-slate-600' ?> group-hover:text-primary transition-colors"><?= htmlspecialchars($cat) ?></span>
                    </a>
                <?php endforeach; ?>

                <!-- Reset Category Option -->
                <?php if ($kategori != ''): ?>
                    <a href="index.php<?= $search != '' ? '?q=' . urlencode($search) : '' ?>"
                        class="flex flex-col items-center gap-2 md:gap-3 min-w-[70px] md:min-w-[90px] shrink-0 group">
                        <div
                            class="w-16 h-16 md:w-20 md:h-20 rounded-2xl md:rounded-[1.5rem] bg-slate-100 text-slate-500 shadow-sm border border-slate-200 flex items-center justify-center hover:bg-slate-200 transition-colors duration-300">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 md:h-8 md:w-8" fill="none"
                                viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </div>
                        <span class="text-[11px] md:text-sm font-semibold text-slate-500">All</span>
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Event Terbaru -->
        <div class="mb-12 md:mb-16">
            <h2 class="text-lg md:text-3xl font-bold text-slate-800 mb-4 md:mb-6 flex items-center gap-2">
                Event Terbaru
                <span
                    class="bg-red-100 text-red-600 text-[10px] md:text-xs px-2 py-1 rounded-md uppercase tracking-wider">New</span>
            </h2>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 md:gap-6">
                <?php if (!empty($newest_events)): ?>
                    <?php foreach ($newest_events as $event): ?>
                        <a href="detail_event.php?id=<?= $event['id'] ?>"
                            class="block relative w-full h-48 md:h-[250px] rounded-[1.5rem] overflow-hidden shadow-md shadow-slate-200/50 group">
                            <?php if ($event['banner_image']): ?>
                                <img src="<?= BASE_URL ?>assets/images/events/<?= $event['banner_image'] ?>" alt="Event"
                                    class="absolute inset-0 w-full h-full object-cover group-hover:scale-110 transition-transform duration-700">
                            <?php else: ?>
                                <img src="https://images.unsplash.com/photo-1492684223066-81342ee5ff30?ixlib=rb-4.0.3&auto=format&fit=crop&w=600&q=80"
                                    alt="Event"
                                    class="absolute inset-0 w-full h-full object-cover group-hover:scale-110 transition-transform duration-700">
                            <?php endif; ?>

                            <div class="absolute inset-0 bg-gradient-to-t from-slate-900/90 via-slate-900/30 to-transparent">
                            </div>

                            <div class="absolute bottom-0 left-0 right-0 p-4 flex flex-col justify-end">
                                <span class="text-white text-xs font-bold flex items-center gap-1 mb-1">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24"
                                        stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                    <?= date('M d, Y', strtotime($event['tanggal'])) ?>
                                </span>
                                <h3
                                    class="text-white text-lg font-extrabold mb-1 leading-tight group-hover:text-blue-100 line-clamp-1">
                                    <?= htmlspecialchars($event['judul']) ?>
                                </h3>
                                <p class="text-slate-300 text-xs font-medium flex items-center gap-1 line-clamp-1">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 text-primary shrink-0" fill="none"
                                        viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                    </svg>
                                    <?= htmlspecialchars($event['lokasi']) ?>
                                </p>
                            </div>
                            <div class="absolute top-3 right-3">
                                <span
                                    class="bg-white/90 backdrop-blur-md text-slate-800 text-[10px] font-bold px-2 py-1 rounded-md shadow-sm">
                                    <?= $event['kategori'] ?>
                                </span>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div
                        class="col-span-full bg-white rounded-2xl p-8 text-center text-slate-500 border border-slate-200 border-dashed">
                        Belum ada event terbaru.
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Event Akan Datang -->
        <div class="mb-12">
            <div class="flex items-center justify-between mb-4 md:mb-8">
                <h2 class="text-lg md:text-3xl font-bold text-slate-800">Event Akan Datang</h2>
            </div>

            <!-- Responsive Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 md:gap-6 lg:gap-8">
                <?php if (!empty($upcoming_events)): ?>
                    <?php foreach ($upcoming_events as $event): ?>
                        <a href="detail_event.php?id=<?= $event['id'] ?>"
                            class="bg-white rounded-[1.5rem] p-3 md:p-5 flex flex-row md:flex-col gap-4 md:gap-5 items-center md:items-start shadow-sm border border-slate-100 hover:shadow-xl hover:-translate-y-1 transition-all duration-300 group">

                            <!-- Image Section -->
                            <div
                                class="w-24 h-24 md:w-full md:h-56 shrink-0 rounded-xl md:rounded-[1.25rem] overflow-hidden bg-slate-100 relative">
                                <?php if ($event['banner_image']): ?>
                                    <img src="<?= BASE_URL ?>assets/images/events/<?= $event['banner_image'] ?>" alt="Event"
                                        class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500">
                                <?php else: ?>
                                    <img src="https://images.unsplash.com/photo-1514525253161-7a46d19cd819?ixlib=rb-4.0.3&auto=format&fit=crop&w=600&q=80"
                                        alt="Event"
                                        class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500">
                                <?php endif; ?>

                                <!-- Hover Overlay for Desktop -->
                                <div
                                    class="hidden md:flex absolute inset-0 bg-black/20 opacity-0 group-hover:opacity-100 transition-opacity items-center justify-center">
                                    <span
                                        class="bg-white text-slate-900 text-sm font-bold px-4 py-2 rounded-full shadow-lg transform translate-y-4 group-hover:translate-y-0 transition-all">Lihat
                                        Detail</span>
                                </div>
                            </div>

                            <!-- Details Section -->
                            <div class="flex-grow min-w-0 md:w-full flex flex-col justify-between md:h-full">
                                <div>
                                    <div class="flex justify-between items-start mb-1.5">
                                        <p class="text-primary text-[10px] md:text-xs font-bold uppercase tracking-wider">
                                            <?= date('M d', strtotime($event['tanggal'])) ?> •
                                            <?= date('H:i', strtotime($event['waktu'])) ?>        <?= $event['waktu_selesai'] ? ' - ' . date('H:i', strtotime($event['waktu_selesai'])) : '' ?>
                                        </p>
                                        <span
                                            class="text-[9px] md:text-[10px] font-bold bg-slate-100 text-slate-500 px-2 py-0.5 rounded-md hidden md:block">
                                            <?= htmlspecialchars($event['kategori']) ?>
                                        </span>
                                    </div>

                                    <h3
                                        class="text-slate-900 font-bold text-sm md:text-lg mb-1 md:mb-2 leading-tight truncate md:whitespace-normal md:line-clamp-2 group-hover:text-primary transition-colors">
                                        <?= htmlspecialchars($event['judul']) ?>
                                    </h3>
                                    <p class="text-slate-500 text-xs md:text-sm flex items-center gap-1 mb-2 md:mb-4 truncate">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 md:h-4 md:w-4 shrink-0"
                                            fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                        </svg>
                                        <?= htmlspecialchars($event['lokasi']) ?>
                                    </p>
                                </div>
                                <div class="mt-auto md:border-t md:border-slate-100 md:pt-4 flex items-center justify-between">
                                    <p class="text-slate-900 font-extrabold text-sm md:text-lg">
                                        <?= $event['harga'] > 0 ? 'Rp ' . number_format($event['harga'], 0, ',', '.') : 'Free Entry' ?>
                                    </p>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div
                        class="col-span-full text-center text-slate-400 text-sm md:text-base py-8 bg-white rounded-3xl border border-slate-200 border-dashed">
                        Belum ada event mendatang.</div>
                <?php endif; ?>
            </div>
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
                            <img src="<?= $global_site_logo ?>" alt="Logo"
                                class="w-36 md:w-44 lg:w-52 h-auto object-contain">
                        </a>
                    <?php else: ?>
                        <a href="<?= BASE_URL ?>" class="flex items-center gap-2 hover:opacity-80 transition-opacity">
                            <div
                                class="w-8 h-8 rounded-lg bg-primary flex items-center justify-center text-white font-bold text-sm shadow-sm">
                                H</div>
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
                    <h3 class="text-sm font-bold text-gray-900 mb-4 uppercase tracking-wider hidden md:block">Hubungi
                        Kami</h3>

                    <div class="flex flex-col items-center w-full md:w-auto">
                        <!-- CS Button -->
                        <a href="https://wa.me/<?= htmlspecialchars($global_contact_cs) ?>" target="_blank"
                            class="inline-flex items-center justify-center gap-2 bg-primary text-white px-6 py-3 rounded-full text-sm font-bold hover:bg-blue-700 transition-colors shadow-md hover:shadow-lg mb-4 w-full md:w-auto">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20"
                                fill="currentColor">
                                <path
                                    d="M2 3a1 1 0 011-1h2.153a1 1 0 01.986.836l.74 4.435a1 1 0 01-.54 1.06l-1.548.773a11.037 11.037 0 006.105 6.105l.774-1.548a1 1 0 011.059-.54l4.435.74a1 1 0 01.836.986V17a1 1 0 01-1 1h-2C7.82 18 2 12.18 2 5V3z" />
                            </svg>
                            Customer Service (24/7)
                        </a>

                        <!-- Socials -->
                        <div class="flex items-center justify-center gap-4">
                            <?php if (!empty($global_link_ig)): ?>
                                <a href="<?= htmlspecialchars($global_link_ig) ?>" target="_blank"
                                    class="w-10 h-10 rounded-full bg-white border border-gray-300 flex items-center justify-center text-gray-600 hover:text-pink-600 hover:border-pink-600 transition-colors shadow-sm hover:shadow">
                                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path fill-rule="evenodd"
                                            d="M12.315 2c2.43 0 2.784.013 3.808.06 1.064.049 1.791.218 2.427.465a4.902 4.902 0 011.772 1.153 4.902 4.902 0 011.153 1.772c.247.636.416 1.363.465 2.427.048 1.067.06 1.407.06 4.123v.08c0 2.643-.012 2.987-.06 4.043-.049 1.064-.218 1.791-.465 2.427a4.902 4.902 0 01-1.153 1.772 4.902 4.902 0 01-1.772 1.153c-.636.247-1.363.416-2.427.465-1.067.048-1.407.06-4.123.06h-.08c-2.643 0-2.987-.012-4.043-.06-1.064-.049-1.791-.218-2.427-.465a4.902 4.902 0 01-1.772-1.153 4.902 4.902 0 01-1.153-1.772c-.247-.636-.416-1.363-.465-2.427-.047-1.024-.06-1.379-.06-3.808v-.63c0-2.43.013-2.784.06-3.808.049-1.064.218-1.791.465-2.427a4.902 4.902 0 011.153-1.772A4.902 4.902 0 015.45 2.525c.636-.247 1.363-.416 2.427-.465C8.901 2.013 9.256 2 11.685 2h.63zm-.081 1.802h-.468c-2.456 0-2.784.011-3.807.058-.975.045-1.504.207-1.857.344-.467.182-.8.398-1.15.748-.35.35-.566.683-.748 1.15-.137.353-.3.882-.344 1.857-.047 1.023-.058 1.351-.058 3.807v.468c0 2.456.011 2.784.058 3.807.045.975.207 1.504.344 1.857.182.466.399.8.748 1.15.35.35.683.566 1.15.748.353.137.882.3 1.857.344 1.054.048 1.37.058 4.041.058h.08c2.597 0 2.917-.01 3.96-.058.976-.045 1.505-.207 1.858-.344.466-.182.8-.398 1.15-.748.35-.35.566-.683.748-1.15.137-.353.3-.882.344-1.857.048-1.055.058-1.37.058-4.041v-.08c0-2.597-.01-2.917-.058-3.96-.045-.976-.207-1.505-.344-1.858a3.097 3.097 0 00-.748-1.15 3.098 3.098 0 00-1.15-.748c-.353-.137-.882-.3-1.857-.344-1.023-.047-1.351-.058-3.807-.058zM12 6.865a5.135 5.135 0 110 10.27 5.135 5.135 0 010-10.27zm0 1.802a3.333 3.333 0 100 6.666 3.333 3.333 0 000-6.666zm5.338-3.205a1.2 1.2 0 110 2.4 1.2 1.2 0 010-2.4z"
                                            clip-rule="evenodd" />
                                    </svg>
                                </a>
                            <?php endif; ?>
                            <?php if (!empty($global_link_tiktok)): ?>
                                <a href="<?= htmlspecialchars($global_link_tiktok) ?>" target="_blank"
                                    class="w-10 h-10 rounded-full bg-white border border-gray-300 flex items-center justify-center text-gray-600 hover:text-black hover:border-black transition-colors shadow-sm hover:shadow">
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path
                                            d="M12.525.02c1.31-.02 2.61-.01 3.91-.02.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.05-2.89-.35-4.2-.97-.57-.26-1.1-.59-1.62-.93-.01 2.92.01 5.84-.02 8.75-.08 1.4-.54 2.79-1.35 3.94-1.31 1.92-3.58 3.17-5.91 3.21-1.43.08-2.86-.31-4.08-1.03-2.02-1.12-3.44-3.17-3.61-5.66-.21-3.11 2.05-5.9 5.09-6.32 1.5-.2 3.05.02 4.41.67v4.06c-1.12-.49-2.42-.57-3.58-.2-1.4.45-2.31 1.76-2.32 3.2-.04 1.48.97 2.87 2.41 3.26 1.43.37 3.02.05 4.09-1.02.73-.72 1.18-1.74 1.2-2.77-.04-5.32-.03-10.64-.03-15.96z" />
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
    <div id="mobile-sidebar-overlay"
        class="fixed inset-0 bg-slate-900/50 z-50 hidden opacity-0 transition-opacity duration-300"></div>
    <div id="mobile-sidebar"
        class="fixed inset-y-0 left-0 w-[280px] bg-white z-50 transform -translate-x-full transition-transform duration-300 shadow-2xl flex flex-col">
        <div class="p-5 flex items-center justify-between border-b border-slate-100">
            <div class="flex items-center gap-2">
                <?php if (isset($global_site_logo) && $global_site_logo): ?>
                    <img src="<?= $global_site_logo ?>" alt="Logo" class="w-28 h-auto object-contain">
                <?php else: ?>
                    <div
                        class="w-7 h-7 rounded-lg bg-primary flex items-center justify-center text-white font-bold text-xs shadow-sm">
                        H</div>
                    <span class="text-lg font-extrabold text-slate-900 tracking-tight">HaloTiket</span>
                <?php endif; ?>
            </div>
            <button id="close-sidebar-btn"
                class="text-slate-400 hover:text-slate-700 bg-slate-50 hover:bg-slate-100 rounded-full p-2 focus:outline-none transition-colors">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24"
                    stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <div class="flex flex-col p-4 gap-2 overflow-y-auto">
            <div class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-2 mt-2 px-2">Menu Utama</div>
            <a href="index.php"
                class="flex items-center gap-3 px-4 py-3.5 rounded-xl bg-blue-50 text-primary font-bold transition-colors">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                    <path
                        d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z" />
                </svg>
                Home
            </a>
            <a href="cek_tiket.php"
                class="flex items-center gap-3 px-4 py-3.5 rounded-xl text-slate-600 hover:bg-slate-50 font-semibold transition-colors">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24"
                    stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z" />
                </svg>
                Riwayat Tiket
            </a>

            <div class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-2 mt-6 px-2">Bantuan</div>
            <a href="https://wa.me/<?= htmlspecialchars($global_contact_cs) ?>" target="_blank"
                class="flex items-center gap-3 px-4 py-3.5 rounded-xl text-slate-600 hover:bg-slate-50 font-semibold transition-colors">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24"
                    stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z" />
                </svg>
                Customer Service
            </a>
        </div>

        <div class="mt-auto p-5 border-t border-slate-100 bg-slate-50">
            <?php if (isset($_SESSION['user_id'])): ?>
                <a href="<?= $_SESSION['role'] ?>/index.php"
                    class="flex items-center justify-center w-full bg-primary text-white py-3.5 rounded-xl font-bold shadow-md hover:bg-blue-700 hover:shadow-lg transition-all">
                    Dashboard Panel
                </a>
            <?php else: ?>
                <div class="flex flex-col gap-3">
                    <a href="auth/login.php"
                        class="flex items-center justify-center w-full bg-slate-900 text-white py-3.5 rounded-xl font-bold shadow-md hover:bg-slate-800 hover:shadow-lg transition-all">
                        Login
                    </a>
                    <a href="auth/register.php"
                        class="flex items-center justify-center w-full bg-white text-slate-900 border border-slate-300 py-3.5 rounded-xl font-bold shadow-sm hover:bg-slate-50 hover:shadow transition-all">
                        Register
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
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

            if (btn && sidebar) {
                btn.addEventListener('click', openSidebar);
            }
            if (desktopBtn && sidebar) {
                desktopBtn.addEventListener('click', openSidebar);
            }
            if (sidebar) {
                closeBtn.addEventListener('click', closeSidebar);
                overlay.addEventListener('click', closeSidebar);
            }

            // Hero Slider Carousel Logic
            const slides = document.querySelectorAll('.hero-slide');
            const dots = document.querySelectorAll('.slider-dot');
            const prevBtn = document.getElementById('sliderPrevBtn');
            const nextBtn = document.getElementById('sliderNextBtn');

            if (slides.length > 1) {
                let currentSlide = 0;
                let autoPlayTimer = null;

                function goToSlide(index) {
                    slides[currentSlide].classList.remove('opacity-100', 'z-10');
                    slides[currentSlide].classList.add('opacity-0', 'z-0');
                    if (dots[currentSlide]) {
                        dots[currentSlide].classList.remove('bg-primary', 'w-7');
                        dots[currentSlide].classList.add('bg-white/50');
                    }

                    currentSlide = (index + slides.length) % slides.length;

                    slides[currentSlide].classList.remove('opacity-0', 'z-0');
                    slides[currentSlide].classList.add('opacity-100', 'z-10');
                    if (dots[currentSlide]) {
                        dots[currentSlide].classList.remove('bg-white/50');
                        dots[currentSlide].classList.add('bg-primary', 'w-7');
                    }
                }

                function startAutoPlay() {
                    stopAutoPlay();
                    autoPlayTimer = setInterval(() => {
                        goToSlide(currentSlide + 1);
                    }, 5000);
                }

                function stopAutoPlay() {
                    if (autoPlayTimer) clearInterval(autoPlayTimer);
                }

                if (nextBtn) {
                    nextBtn.addEventListener('click', () => {
                        goToSlide(currentSlide + 1);
                        startAutoPlay();
                    });
                }

                if (prevBtn) {
                    prevBtn.addEventListener('click', () => {
                        goToSlide(currentSlide - 1);
                        startAutoPlay();
                    });
                }

                dots.forEach((dot, idx) => {
                    dot.addEventListener('click', () => {
                        goToSlide(idx);
                        startAutoPlay();
                    });
                });

                const carouselContainer = document.getElementById('heroCarousel');
                if (carouselContainer) {
                    carouselContainer.addEventListener('mouseenter', stopAutoPlay);
                    carouselContainer.addEventListener('mouseleave', startAutoPlay);
                }

                startAutoPlay();
            }
        });
    </script>

    <!-- PWA INSTALLATION BOTTOM BANNER POPUP -->
    <div id="pwaInstallBanner" class="fixed bottom-4 left-4 right-4 sm:left-auto sm:right-6 sm:bottom-6 z-[999] max-w-sm sm:max-w-md w-full bg-slate-900/95 text-white p-5 rounded-3xl shadow-2xl backdrop-blur-xl border border-slate-700/60 transform translate-y-32 opacity-0 transition-all duration-500 hidden flex flex-col gap-4">
        <div class="flex items-start gap-4">
            <div class="w-12 h-12 rounded-2xl bg-white/10 p-2 flex items-center justify-center shrink-0 border border-white/10 shadow-inner overflow-hidden">
                <?php if (isset($global_site_favicon) && $global_site_favicon): ?>
                    <img src="<?= $global_site_favicon ?>" alt="PWA Logo" class="w-full h-full object-contain">
                <?php elseif (isset($global_site_logo) && $global_site_logo): ?>
                    <img src="<?= $global_site_logo ?>" alt="PWA Logo" class="w-full h-full object-contain">
                <?php else: ?>
                    <div class="w-full h-full bg-primary rounded-xl flex items-center justify-center font-extrabold text-white text-lg">H</div>
                <?php endif; ?>
            </div>
            <div class="flex-grow min-w-0">
                <div class="flex items-center justify-between gap-2">
                    <h3 class="font-extrabold text-base text-white tracking-tight">Ingin install sebagai aplikasi?</h3>
                    <button type="button" onclick="dismissPwaBanner()" class="text-slate-400 hover:text-white text-xl font-bold leading-none p-1">&times;</button>
                </div>
                <p class="text-xs text-slate-300 font-medium mt-1 leading-relaxed">
                    Dapatkan akses cepat, notifikasi tiket, dan kemudahan bertransaksi langsung dari layar HP Anda.
                </p>
            </div>
        </div>
        <div class="flex items-center gap-2 pt-1 border-t border-white/10">
            <button type="button" onclick="dismissPwaBanner()" class="flex-1 px-4 py-2.5 rounded-xl font-bold text-xs text-slate-300 hover:text-white bg-white/5 hover:bg-white/10 transition-colors text-center">
                Nanti Saja
            </button>
            <button type="button" id="btnInstallPwa" onclick="installPwaApp()" class="flex-1 px-5 py-2.5 rounded-xl font-extrabold text-xs text-slate-900 bg-primary hover:bg-primary/90 transition-all shadow-md flex items-center justify-center gap-1.5">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" /></svg>
                <span>Install Aplikasi</span>
            </button>
        </div>
    </div>

    <!-- PWA Service Worker & Install Logic -->
    <script>
        const pwaBanner = document.getElementById('pwaInstallBanner');

        // Tampilkan otomatis banner jika pengguna belum pernah menutup dan belum terpasang
        document.addEventListener('DOMContentLoaded', () => {
            if (!window.matchMedia('(display-mode: standalone)').matches) {
                const lastDismissed = localStorage.getItem('pwa_banner_dismissed');
                if (!lastDismissed || window.deferredPwaPrompt) {
                    setTimeout(showPwaBanner, 1000);
                }
            }
        });

        function showPwaBanner() {
            if (!pwaBanner) return;
            pwaBanner.classList.remove('hidden');
            setTimeout(() => {
                pwaBanner.classList.remove('translate-y-32', 'opacity-0');
                pwaBanner.classList.add('translate-y-0', 'opacity-100');
            }, 100);
        }

        function dismissPwaBanner() {
            if (!pwaBanner) return;
            pwaBanner.classList.remove('translate-y-0', 'opacity-100');
            pwaBanner.classList.add('translate-y-32', 'opacity-0');
            setTimeout(() => {
                pwaBanner.classList.add('hidden');
            }, 500);
            localStorage.setItem('pwa_banner_dismissed', Date.now().toString());
        }

        function installPwaApp() {
            const promptEvent = window.deferredPwaPrompt;
            if (promptEvent) {
                promptEvent.prompt();
                promptEvent.userChoice.then((choiceResult) => {
                    if (choiceResult.outcome === 'accepted') {
                        console.log('User accepted the PWA install prompt');
                    }
                    window.deferredPwaPrompt = null;
                    dismissPwaBanner();
                });
            } else {
                dismissPwaBanner();
            }
        }

        window.addEventListener('appinstalled', () => {
            window.deferredPwaPrompt = null;
            if (pwaBanner) pwaBanner.classList.add('hidden');
            console.log('HaloTiket PWA successfully installed!');
        });
    </script>
</body>

</html>