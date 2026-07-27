<?php
// Mencegah output buffer berlebihan
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/koneksi.php';

// Validasi Keamanan Akses Cron Job
$is_cli = (PHP_SAPI === 'cli');
$is_admin = (isset($_SESSION['role']) && $_SESSION['role'] === 'admin');
$provided_key = $_GET['key'] ?? $_POST['key'] ?? '';
$secret_key = defined('CRON_SECRET_KEY') ? CRON_SECRET_KEY : ($global_cron_secret_key ?? 'HTK_CRON_SECRET_KEY_2026');
$is_valid_key = (!empty($provided_key) && hash_equals($secret_key, $provided_key));

if (!$is_cli && !$is_admin && !$is_valid_key) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'error',
        'message' => 'Akses Ditolak. Kunci rahasia Cron Job tidak valid atau Anda tidak memiliki akses admin.'
    ]);
    exit;
}

$is_json = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') || isset($_GET['json']);

$log_output = [];
$cancelled_count = 0;
$reminded_payment_count = 0;
$reminded_h1_count = 0;

$expiry_hours = (int)($global_settings['cron_expiry_hours'] ?? 24);
if ($expiry_hours <= 0) $expiry_hours = 24;

// =========================================================================
// TASK 1: AUTO-CANCEL TIKET KADALUARSA & RELEASE STOK
// =========================================================================
if (($global_settings['cron_enable_auto_cancel'] ?? '1') === '1') {
    $stmtExp = $conn->prepare("
        SELECT t.*, e.judul, v.nama_varian, v.harga 
        FROM tickets t 
        JOIN events e ON t.id_event = e.id 
        LEFT JOIN event_ticket_variants v ON t.id_ticket_variant = v.id 
        WHERE t.status = 'pending' 
        AND t.created_at < NOW() - INTERVAL ? HOUR
    ");
    
    if ($stmtExp) {
        $stmtExp->bind_param("i", $expiry_hours);
        $stmtExp->execute();
        $resExp = $stmtExp->get_result();

        while ($ticket = $resExp->fetch_assoc()) {
            $ticket_id = (int)$ticket['id'];
            $order_id = $ticket['order_id'];
            $id_variant = (int)$ticket['id_ticket_variant'];
            $id_event = (int)$ticket['id_event'];
            $nama_pembeli = $ticket['nama_pembeli'];
            $no_hp = $ticket['no_hp'];
            $event_title = $ticket['judul'];

            // 1. Ubah status tiket menjadi batal
            $conn->query("UPDATE tickets SET status = 'batal' WHERE id = $ticket_id");

            // 2. Kembalikan stok varian & event
            if ($id_variant > 0) {
                $conn->query("UPDATE event_ticket_variants SET sisa_stok = sisa_stok + 1 WHERE id = $id_variant");
            }
            if ($id_event > 0) {
                $conn->query("UPDATE events SET stok = stok + 1 WHERE id = $id_event");
            }

            // 3. Kirim WA pemberitahuan pembatalan ke pembeli jika notifikasi pembeli aktif
            if (($global_settings['fonnte_enable_buyer_notif'] ?? '1') === '1' && !empty($no_hp)) {
                $msgBuyer = "⚠️ *PEMBERITAHUAN PEMBATALAN PESANAN*\n\n";
                $msgBuyer .= "Halo *" . $nama_pembeli . "*,\n";
                $msgBuyer .= "Pesanan tiket Anda dengan Order ID *" . $order_id . "* untuk event *" . $event_title . "* telah *DIBATALKAN OTOMATIS* oleh sistem karena telah melewati batas waktu pembayaran (" . $expiry_hours . " jam).\n\n";
                $msgBuyer .= "Stok tiket telah dikembalikan ke sistem. Jika Anda masih berminat, silakan lakukan memesan tiket ulang di HaloTiket. Terima kasih!";
                
                @sendFonnteWA($no_hp, $msgBuyer);
            }

            $cancelled_count++;
            $log_output[] = "Tiket #$order_id ($event_title) dibatalkan & stok dikembalikan.";
        }

        // Rangkuman ke Group WA Admin jika ada tiket yang dibatalkan
        if ($cancelled_count > 0 && ($global_settings['fonnte_enable_group_notif'] ?? '1') === '1') {
            $group_id = trim($global_settings['fonnte_wa_group'] ?? '120363412788674882@g.us');
            if (!empty($group_id)) {
                $msgGroup = "ℹ️ *[CRON SYSTEM] PEMBATALAN TIKET KADALUARSA*\n";
                $msgGroup .= "----------------------------------------\n";
                $msgGroup .= "Sebanyak *" . $cancelled_count . " tiket pending* yang melebih batas waktu (" . $expiry_hours . " jam) telah dibatalkan otomatis & stok telah dikembalikan ke sistem.\n";
                $msgGroup .= "Waktu Eksekusi: " . date('d/m/Y H:i') . " WIB";
                @sendFonnteWA($group_id, $msgGroup);
            }
        }
    }
}

// =========================================================================
// TASK 2: PENGINGAT PEMBAYARAN WA (PAYMENT REMINDER > 1 JAM)
// =========================================================================
if (($global_settings['cron_enable_reminder_payment'] ?? '1') === '1') {
    $stmtRem = $conn->prepare("
        SELECT t.*, e.judul, v.nama_varian, v.harga 
        FROM tickets t 
        JOIN events e ON t.id_event = e.id 
        LEFT JOIN event_ticket_variants v ON t.id_ticket_variant = v.id 
        WHERE t.status = 'pending' 
        AND (t.reminder_payment_sent IS NULL OR t.reminder_payment_sent = 0)
        AND t.created_at < NOW() - INTERVAL 1 HOUR
    ");

    if ($stmtRem) {
        $stmtRem->execute();
        $resRem = $stmtRem->get_result();

        while ($ticket = $resRem->fetch_assoc()) {
            $ticket_id = (int)$ticket['id'];
            $order_id = $ticket['order_id'];
            $nama_pembeli = $ticket['nama_pembeli'];
            $no_hp = $ticket['no_hp'];
            $event_title = $ticket['judul'];
            $variant_name = $ticket['nama_varian'] ?? 'Reguler';
            $site_url = defined('BASE_URL') ? BASE_URL : 'http://localhost/';
            $linkBayar = $site_url . 'pembayaran.php?order_id=' . urlencode($order_id);

            // Update status pengingat sudah terkirim
            $conn->query("UPDATE tickets SET reminder_payment_sent = 1 WHERE id = $ticket_id");

            // Kirim WA Reminder ke Pembeli
            if (($global_settings['fonnte_enable_buyer_notif'] ?? '1') === '1' && !empty($no_hp)) {
                $msgRem = "⏰ *PENGINGAT PEMBAYARAN TIKET*\n\n";
                $msgRem .= "Halo *" . $nama_pembeli . "*,\n";
                $msgRem .= "Kami menginfokan bahwa pesanan tiket Anda dengan Order ID *" . $order_id . "* untuk event *" . $event_title . "* (" . $variant_name . ") masih menunggu pembayaran.\n\n";
                $msgRem .= "Silakan selesaikan pembayaran sebelum batas waktu berakhir agar tiket Anda tidak dibatalkan otomatis.\n";
                $msgRem .= "👉 Selesaikan Pembayaran: " . $linkBayar . "\n\n";
                $msgRem .= "Abaikan pesan ini jika Anda sudah melakukan pembayaran. Terima kasih!";
                
                @sendFonnteWA($no_hp, $msgRem);
            }

            $reminded_payment_count++;
            $log_output[] = "Pengingat pembayaran dikirim untuk Tiket #$order_id.";
        }
    }
}

// =========================================================================
// TASK 3: PENGINGAT H-1 EVENT WA (EVENT REMINDER)
// =========================================================================
if (($global_settings['cron_enable_reminder_event'] ?? '1') === '1') {
    $stmtH1 = $conn->prepare("
        SELECT t.*, e.judul, e.tanggal, e.waktu, e.lokasi, v.nama_varian 
        FROM tickets t 
        JOIN events e ON t.id_event = e.id 
        LEFT JOIN event_ticket_variants v ON t.id_ticket_variant = v.id 
        WHERE t.status IN ('lunas', 'scanned') 
        AND (t.reminder_h1_sent IS NULL OR t.reminder_h1_sent = 0)
        AND e.tanggal = DATE_ADD(CURDATE(), INTERVAL 1 DAY)
    ");

    if ($stmtH1) {
        $stmtH1->execute();
        $resH1 = $stmtH1->get_result();

        while ($ticket = $resH1->fetch_assoc()) {
            $ticket_id = (int)$ticket['id'];
            $order_id = $ticket['order_id'];
            $nama_pembeli = $ticket['nama_pembeli'];
            $no_hp = $ticket['no_hp'];
            $event_title = $ticket['judul'];
            $waktu = $ticket['waktu'];
            $lokasi = $ticket['lokasi'];
            $site_url = defined('BASE_URL') ? BASE_URL : 'http://localhost/';
            $linkTiket = $site_url . 'user/riwayat_pembelian.php?order_id=' . urlencode($order_id);

            // Update status pengingat H-1 terkirim
            $conn->query("UPDATE tickets SET reminder_h1_sent = 1 WHERE id = $ticket_id");

            // Kirim WA H-1 ke Pembeli
            if (($global_settings['fonnte_enable_buyer_notif'] ?? '1') === '1' && !empty($no_hp)) {
                $msgH1 = "🎉 *PENGINGAT H-1 EVENT REMINDER!*\n\n";
                $msgH1 .= "Halo *" . $nama_pembeli . "*,\n";
                $msgH1 .= "Besok adalah hari pelaksanaan event *" . $event_title . "*!\n\n";
                $msgH1 .= "🗓️ *Tanggal:* Besok (" . date('d M Y', strtotime($ticket['tanggal'])) . ")\n";
                $msgH1 .= "⏰ *Waktu:* " . $waktu . " WIB\n";
                $msgH1 .= "📍 *Lokasi:* " . $lokasi . "\n\n";
                $msgH1 .= "Jangan lupa untuk menyiapkan QR Code E-Ticket Anda di venue:\n";
                $msgH1 .= "👉 Lihat E-Ticket: " . $linkTiket . "\n\n";
                $msgH1 .= "Sampai jumpa di lokasi event! 🚀";
                
                @sendFonnteWA($no_hp, $msgH1);
            }

            $reminded_h1_count++;
            $log_output[] = "Pengingat H-1 Event dikirim ke #$order_id ($nama_pembeli).";
        }
    }
}

// Respon Eksekusi Cron Job
$summary_message = "Cron Job Berhasil Dijalankan! ($cancelled_count tiket dibatalkan, $reminded_payment_count pengingat bayar terkirim, $reminded_h1_count pengingat H-1 terkirim)";

if ($is_json) {
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'success',
        'message' => $summary_message,
        'cancelled_count' => $cancelled_count,
        'reminded_payment_count' => $reminded_payment_count,
        'reminded_h1_count' => $reminded_h1_count,
        'logs' => $log_output,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit;
} else {
    echo "<h1>HaloTiket Cron Job Engine</h1>";
    echo "<p><strong>Status:</strong> Success</p>";
    echo "<p><strong>Ringkasan:</strong> " . htmlspecialchars($summary_message) . "</p>";
    echo "<h3>Log Aktivitas:</h3>";
    echo "<ul>";
    if (empty($log_output)) {
        echo "<li>Tidak ada antrean tugas yang diproses saat ini.</li>";
    } else {
        foreach ($log_output as $log) {
            echo "<li>" . htmlspecialchars($log) . "</li>";
        }
    }
    echo "</ul>";
    echo "<p><small>Waktu Eksekusi: " . date('Y-m-d H:i:s') . " WIB</small></p>";
}
?>
