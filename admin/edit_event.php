<?php
session_start();
require_once '../config/koneksi.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

if (!isset($_GET['id'])) {
    header("Location: manage_events.php");
    exit;
}

$id_event = (int)$_GET['id'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $judul = $_POST['judul'];
    $deskripsi = $_POST['deskripsi'];
    $tanggal = $_POST['tanggal'];
    $waktu = $_POST['waktu'];
    $waktu_selesai = !empty($_POST['waktu_selesai']) ? $_POST['waktu_selesai'] : null;
    $lokasi = $_POST['lokasi'];
    $link_gmaps = !empty($_POST['link_gmaps']) ? $_POST['link_gmaps'] : null;
    $kategori = $_POST['kategori'] ?? 'Music';
    $nama_vendor = !empty($_POST['nama_vendor']) ? $_POST['nama_vendor'] : null;
    $is_war_ticket = isset($_POST['is_war_ticket']) ? 1 : 0;
    $war_start_time = (!empty($_POST['war_start_time']) && $is_war_ticket) ? $_POST['war_start_time'] : null;
    
    // Hitung stok & harga dasar dari form
    $harga_dasar = (isset($_POST['harga_varian']) && is_array($_POST['harga_varian']) && count($_POST['harga_varian']) > 0) ? min($_POST['harga_varian']) : 0;
    $total_stok = (isset($_POST['stok_varian']) && is_array($_POST['stok_varian'])) ? array_sum($_POST['stok_varian']) : 0;

    // Update banner if provided
    $banner_query = "";
    if (isset($_FILES['banner_image']) && $_FILES['banner_image']['error'] == 0) {
        $filename = time() . '_' . basename($_FILES['banner_image']['name']);
        if (move_uploaded_file($_FILES['banner_image']['tmp_name'], "../assets/images/events/" . $filename)) {
            $banner_query .= ", banner_image = '$filename'";
        }
    }
    
    // Update tiket_header
    if (isset($_FILES['tiket_header']) && $_FILES['tiket_header']['error'] == 0) {
        $ext_header = strtolower(pathinfo($_FILES['tiket_header']['name'], PATHINFO_EXTENSION));
        if (in_array($ext_header, ['jpg', 'jpeg', 'png'])) {
            $fname_header = time() . '_header.' . $ext_header;
            if (move_uploaded_file($_FILES['tiket_header']['tmp_name'], "../assets/images/events/" . $fname_header)) {
                $banner_query .= ", tiket_header = '$fname_header'";
            }
        }
    }

    $banner_query .= ", is_war_ticket = " . (int)$is_war_ticket . ", war_start_time = " . ($war_start_time ? "'" . $conn->real_escape_string($war_start_time) . "'" : "NULL");

    $stmt_upd = $conn->prepare("UPDATE events SET judul=?, deskripsi=?, tanggal=?, waktu=?, waktu_selesai=?, lokasi=?, link_gmaps=?, kategori=?, nama_vendor=?, harga=?, stok=? $banner_query WHERE id=? ");
    $stmt_upd->bind_param("sssssssssdii", $judul, $deskripsi, $tanggal, $waktu, $waktu_selesai, $lokasi, $link_gmaps, $kategori, $nama_vendor, $harga_dasar, $total_stok, $id_event);
    $stmt_upd->execute();

    // Process Variants
    $variant_ids = $_POST['variant_id'] ?? [];
    $nama_periode = $_POST['nama_periode'] ?? [];
    $tgl_mulai_varian = $_POST['tgl_mulai_varian'] ?? [];
    $tgl_selesai_varian = $_POST['tgl_selesai_varian'] ?? [];
    $kategori_tempat = $_POST['kategori_tempat'] ?? [];
    $tipe_paket = $_POST['tipe_paket'] ?? [];
    $harga_variants = $_POST['harga_varian'] ?? [];
    $stok_variants = $_POST['stok_varian'] ?? [];

    $existing_ids = [];
    $q = $conn->query("SELECT id, stok, sisa_stok FROM event_ticket_variants WHERE id_event = $id_event");
    while($r = $q->fetch_assoc()) {
        $existing_ids[$r['id']] = $r;
    }

    // Safe Delete Missing
    foreach ($existing_ids as $eid => $edata) {
        if (!in_array($eid, $variant_ids)) {
            if ($edata['stok'] == $edata['sisa_stok']) {
                $conn->query("DELETE FROM event_ticket_variants WHERE id = $eid");
            } else {
                $conn->query("UPDATE event_ticket_variants SET sisa_stok = 0, tgl_selesai = DATE_SUB(NOW(), INTERVAL 1 DAY) WHERE id = $eid");
            }
        }
    }

    // Insert or Update Variants
    for ($i = 0; $i < count($nama_periode); $i++) {
        $vid = !empty($variant_ids[$i]) ? (int)$variant_ids[$i] : 0;
        $periode = trim($nama_periode[$i]);
        $tgl_mulai = $tgl_mulai_varian[$i];
        $tgl_selesai = $tgl_selesai_varian[$i];
        $kat = trim($kategori_tempat[$i]);
        $tipe = trim($tipe_paket[$i]);
        $h_var = (float)$harga_variants[$i];
        $new_stok = (int)$stok_variants[$i];
        $n_var = $periode . ' - ' . $kat . ' (' . $tipe . ')';

        if (!empty($periode) && $new_stok > 0) {
            if ($vid > 0 && isset($existing_ids[$vid])) {
                // Update
                $stmt_v = $conn->prepare("UPDATE event_ticket_variants SET nama_varian=?, harga=?, sisa_stok = sisa_stok + (? - stok), stok=?, tgl_mulai=?, tgl_selesai=?, kategori_tempat=?, tipe_paket=? WHERE id=?");
                $stmt_v->bind_param("sdiissssi", $n_var, $h_var, $new_stok, $new_stok, $tgl_mulai, $tgl_selesai, $kat, $tipe, $vid);
                $stmt_v->execute();
            } else {
                // Insert
                $stmt_v = $conn->prepare("INSERT INTO event_ticket_variants (id_event, nama_varian, harga, stok, sisa_stok, tgl_mulai, tgl_selesai, kategori_tempat, tipe_paket) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt_v->bind_param("isdiissss", $id_event, $n_var, $h_var, $new_stok, $new_stok, $tgl_mulai, $tgl_selesai, $kat, $tipe);
                $stmt_v->execute();
            }
        }
    }

    header("Location: manage_events.php");
    exit;
}

$stmt = $conn->prepare("SELECT * FROM events WHERE id = ?");
$stmt->bind_param("i", $id_event);
$stmt->execute();
$event = $stmt->get_result()->fetch_assoc();

if (!$event) {
    header("Location: manage_events.php");
    exit;
}

$variants_q = $conn->query("SELECT * FROM event_ticket_variants WHERE id_event = $id_event ORDER BY id ASC");
$variants = [];
while ($v = $variants_q->fetch_assoc()) {
    $variants[] = $v;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Event - HaloTiket</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Outfit', 'sans-serif'] },
                    colors: { primary: '#00c2cb', secondary: '#0f1c3f' }
                }
            }
        }
    </script>
</head>
<body class="bg-slate-50 text-slate-800 font-sans antialiased min-h-screen">
    <div class="max-w-5xl mx-auto px-4 py-8">
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight">Edit Event</h1>
                <p class="text-slate-500 font-medium text-sm">Update informasi event dan paket tiket.</p>
            </div>
            <a href="manage_events.php" class="text-slate-500 hover:text-slate-700 font-bold text-sm bg-white px-4 py-2 rounded-lg border border-slate-200">Batal</a>
        </div>

        <div class="bg-white rounded-3xl shadow-sm border border-slate-200 p-8">
            <form action="" method="POST" enctype="multipart/form-data" class="space-y-6">
                <input type="hidden" name="id_event" value="<?= $event['id'] ?>">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="md:col-span-2">
                        <label class="block text-xs font-bold text-slate-500 uppercase mb-2">Judul Event</label>
                        <input type="text" name="judul" required value="<?= htmlspecialchars($event['judul']) ?>" class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary text-sm outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase mb-2">Kategori</label>
                        <select name="kategori" required class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary text-sm outline-none">
                            <option value="Music" <?= $event['kategori']=='Music'?'selected':'' ?>>Music</option>
                            <option value="Sports" <?= $event['kategori']=='Sports'?'selected':'' ?>>Sports</option>
                            <option value="Conference" <?= $event['kategori']=='Conference'?'selected':'' ?>>Conference</option>
                            <option value="Exhibition" <?= $event['kategori']=='Exhibition'?'selected':'' ?>>Exhibition</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase mb-2">Nama Penyelenggara</label>
                        <input type="text" name="nama_vendor" value="<?= htmlspecialchars($event['nama_vendor']) ?>" class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary text-sm outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase mb-2">Tanggal Event</label>
                        <input type="date" name="tanggal" required value="<?= $event['tanggal'] ?>" class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary text-sm outline-none">
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase mb-2">Mulai</label>
                            <input type="time" name="waktu" required value="<?= $event['waktu'] ?>" class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary text-sm outline-none">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase mb-2">Selesai</label>
                            <input type="time" name="waktu_selesai" value="<?= $event['waktu_selesai'] ?>" class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary text-sm outline-none">
                        </div>
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-xs font-bold text-slate-500 uppercase mb-2">Lokasi / Venue</label>
                        <input type="text" name="lokasi" required value="<?= htmlspecialchars($event['lokasi']) ?>" class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary text-sm outline-none">
                    </div>
                    
                    <div class="md:col-span-2">
                        <div class="flex justify-between items-center mb-3">
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider">Paket Tiket Lengkap</label>
                            <button type="button" onclick="addVariant()" class="text-xs font-bold text-primary hover:text-secondary bg-primary/10 px-3 py-1.5 rounded-full transition-colors flex items-center gap-1">+ Tambah Paket</button>
                        </div>
                        <div id="variants_container" class="space-y-4">
                            <?php foreach($variants as $index => $v): ?>
                            <div class="variant-row bg-slate-50 border border-slate-200 p-5 rounded-2xl relative shadow-sm">
                                <input type="hidden" name="variant_id[]" value="<?= $v['id'] ?>">
                                <button type="button" onclick="removeVariant(this)" class="absolute top-4 right-4 p-1.5 text-slate-400 hover:text-red-500 hover:bg-red-50 rounded-lg <?= count($variants) > 1 ? '' : 'hidden' ?> btn-remove-variant" title="Hapus Paket">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                </button>
                                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
                                    <div class="md:col-span-2">
                                        <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Periode Penjualan</label>
                                        <?php 
                                        $periode_parts = explode(' - ', $v['nama_varian']);
                                        $periode_name = $periode_parts[0] ?? '';
                                        ?>
                                        <input type="text" name="nama_periode[]" required value="<?= htmlspecialchars($periode_name) ?>" class="w-full px-4 py-2.5 bg-white border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary/20 text-sm outline-none">
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Dibuka Tanggal</label>
                                        <input type="datetime-local" name="tgl_mulai_varian[]" required value="<?= date('Y-m-d\TH:i', strtotime($v['tgl_mulai'])) ?>" class="w-full px-4 py-2.5 bg-white border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary/20 text-sm outline-none">
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Ditutup Tanggal</label>
                                        <input type="datetime-local" name="tgl_selesai_varian[]" required value="<?= date('Y-m-d\TH:i', strtotime($v['tgl_selesai'])) ?>" class="w-full px-4 py-2.5 bg-white border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary/20 text-sm outline-none">
                                    </div>
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                                    <div>
                                        <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Kategori / Ploting</label>
                                        <input type="text" name="kategori_tempat[]" required value="<?= htmlspecialchars($v['kategori_tempat']) ?>" class="w-full px-4 py-2.5 bg-white border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary/20 text-sm outline-none">
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Tipe Paket</label>
                                        <select name="tipe_paket[]" required class="w-full px-4 py-2.5 bg-white border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary/20 text-sm outline-none">
                                            <option value="Sendiri" <?= $v['tipe_paket']=='Sendiri'?'selected':'' ?>>Sendiri (1 Orang)</option>
                                            <option value="Couple" <?= $v['tipe_paket']=='Couple'?'selected':'' ?>>Couple (2 Orang)</option>
                                            <option value="Grup" <?= $v['tipe_paket']=='Grup'?'selected':'' ?>>Grup (Rombongan)</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Harga (Rp)</label>
                                        <input type="number" name="harga_varian[]" required min="0" value="<?= (int)$v['harga'] ?>" class="w-full px-4 py-2.5 bg-white border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary/20 text-sm outline-none">
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Stok Tersedia</label>
                                        <input type="number" name="stok_varian[]" required min="1" value="<?= (int)$v['stok'] ?>" class="w-full px-4 py-2.5 bg-white border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary/20 text-sm outline-none">
                                        <div class="text-[9px] text-slate-400 mt-1">Sisa saat ini: <?= $v['sisa_stok'] ?></div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <!-- Feature War Ticket Toggle & Waktu -->
                    <div class="md:col-span-2 bg-gradient-to-r from-amber-50 to-orange-50 p-4 rounded-2xl border border-amber-200/80 mb-2">
                        <div class="flex items-center justify-between">
                            <div>
                                <label for="is_war_ticket" class="text-sm font-extrabold text-amber-900 flex items-center gap-2 cursor-pointer">
                                    ⚡ Aktifkan Fitur War Ticket
                                </label>
                                <p class="text-xs text-amber-700 mt-0.5">Tahan pembelian tiket di landing page hingga waktu hitung mundur (countdown) dimulai.</p>
                            </div>
                            <input type="checkbox" id="is_war_ticket" name="is_war_ticket" value="1" <?= (!empty($event['is_war_ticket']) && $event['is_war_ticket'] == 1) ? 'checked' : '' ?> onchange="document.getElementById('war_time_container').classList.toggle('hidden', !this.checked)" class="w-5 h-5 text-amber-600 rounded border-amber-300 focus:ring-amber-500 cursor-pointer">
                        </div>
                        <div id="war_time_container" class="mt-4 <?= (!empty($event['is_war_ticket']) && $event['is_war_ticket'] == 1) ? '' : 'hidden' ?>">
                            <label class="block text-xs font-bold text-amber-800 uppercase tracking-wider mb-2">Waktu Mulai War Ticket</label>
                            <input type="datetime-local" name="war_start_time" value="<?= !empty($event['war_start_time']) ? date('Y-m-d\TH:i', strtotime($event['war_start_time'])) : '' ?>" class="w-full md:w-1/2 px-4 py-2 bg-white border border-amber-200 rounded-xl focus:ring-2 focus:ring-amber-400 focus:border-amber-400 text-sm font-medium outline-none">
                        </div>
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-xs font-bold text-slate-500 uppercase mb-2">Deskripsi</label>
                        <textarea name="deskripsi" rows="3" class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary/20 text-sm outline-none"><?= htmlspecialchars($event['deskripsi']) ?></textarea>
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-xs font-bold text-slate-500 uppercase mb-2">Desain Header Tiket PDF (Update Opsional)</label>
                        <input type="file" id="adminEditHeaderInput" name="tiket_header" accept=".jpg,.jpeg,.png" class="w-full text-sm font-medium file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-bold file:bg-indigo-50 file:text-indigo-600 hover:file:bg-indigo-100 transition-all">
                        <?php if(!empty($event['tiket_header']) && file_exists('../assets/images/events/'.$event['tiket_header'])): ?>
                            <p class="text-[10px] text-emerald-500 mt-1 font-bold">✓ Header tiket saat ini sudah terpasang. Unggah baru jika ingin mengganti.</p>
                            <div class="mt-2">
                                <img src="../assets/images/events/<?= htmlspecialchars($event['tiket_header']) ?>" class="h-24 w-full max-w-md object-cover rounded-xl border border-slate-200">
                            </div>
                        <?php else: ?>
                            <p class="text-[10px] text-slate-400 mt-1">*Gambar ini akan dipasang di bagian paling atas PDF Tiket (seperti desain tiket fisik).</p>
                        <?php endif; ?>
                        
                        <!-- Live Preview Box for New Header Tiket -->
                        <div id="adminEditHeaderPreviewContainer" class="hidden mt-3 p-3 bg-indigo-50/50 border border-indigo-100 rounded-2xl max-w-md">
                            <p class="text-xs font-bold text-indigo-900 mb-2">Pratinjau Desain Header Tiket PDF Baru</p>
                            <img id="adminEditHeaderPreviewImg" class="w-full h-28 object-cover rounded-xl border border-indigo-200 shadow-sm">
                        </div>
                    </div>
                    <div class="md:col-span-2 flex justify-end">
                        <button type="submit" class="bg-primary hover:opacity-90 text-white font-bold py-3 px-8 rounded-xl shadow-md">Simpan Perubahan</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    <script>
        function addVariant() {
            const container = document.getElementById('variants_container');
            const rows = container.getElementsByClassName('variant-row');
            const newRow = rows[0].cloneNode(true);
            
            // Reset values
            newRow.querySelector('input[name="variant_id[]"]').value = '';
            const inputs = newRow.querySelectorAll('input:not([type="hidden"])');
            inputs.forEach(input => input.value = '');
            
            // Remove text guide
            const guide = newRow.querySelector('.text-\\[9px\\]');
            if(guide) guide.remove();
            
            newRow.querySelector('.btn-remove-variant').classList.remove('hidden');
            container.appendChild(newRow);
            updateRemoveButtons();
        }
        
        function removeVariant(btn) {
            if(confirm('Yakin ingin menghapus varian ini?')) {
                const row = btn.closest('.variant-row');
                row.remove();
                updateRemoveButtons();
            }
        }
        
        function updateRemoveButtons() {
            const container = document.getElementById('variants_container');
            const rows = container.getElementsByClassName('variant-row');
            if (rows.length > 1) {
                Array.from(rows).forEach(row => row.querySelector('.btn-remove-variant').classList.remove('hidden'));
            } else {
                rows[0].querySelector('.btn-remove-variant').classList.add('hidden');
            }
        }

        // Live Header Preview Handler
        document.addEventListener('DOMContentLoaded', () => {
            const adminEditHeaderInput = document.getElementById('adminEditHeaderInput');
            const adminEditHeaderContainer = document.getElementById('adminEditHeaderPreviewContainer');
            const adminEditHeaderPreviewImg = document.getElementById('adminEditHeaderPreviewImg');

            if (adminEditHeaderInput && adminEditHeaderContainer && adminEditHeaderPreviewImg) {
                adminEditHeaderInput.addEventListener('change', function(e) {
                    const file = e.target.files[0];
                    if (file) {
                        const reader = new FileReader();
                        reader.onload = function(evt) {
                            adminEditHeaderPreviewImg.src = evt.target.result;
                            adminEditHeaderContainer.classList.remove('hidden');
                        };
                        reader.readAsDataURL(file);
                    } else {
                        adminEditHeaderContainer.classList.add('hidden');
                    }
                });
            }
        });
    </script>
</body>
</html>