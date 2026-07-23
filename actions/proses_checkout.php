<?php
session_start();
require_once __DIR__ . '/../config/koneksi.php';

$is_ajax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest');

function returnError($message, $redirect_url = null) {
    global $is_ajax;
    if ($is_ajax) {
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'error',
            'message' => $message
        ]);
        exit;
    } else {
        $_SESSION['error'] = $message;
        $url = $redirect_url ? $redirect_url : (defined('BASE_URL') ? BASE_URL . 'index.php' : '../index.php');
        header("Location: " . $url);
        exit;
    }
}

// Cek keberadaan vendor composer
$autoloadPath = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($autoloadPath)) {
    returnError("Folder 'vendor' tidak ditemukan di server. Pastikan folder vendor sudah di-upload ke cPanel.");
}
require_once $autoloadPath;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: " . (defined('BASE_URL') ? BASE_URL . "index.php" : "../index.php"));
    exit;
}

$id_event = (int)($_POST['id_event'] ?? 0);
$id_ticket_variant = (int)($_POST['id_ticket_variant'] ?? 0);
$nama = trim($_POST['nama'] ?? '');
$email = trim($_POST['email'] ?? '');
$no_hp = trim($_POST['no_hp'] ?? '');

if ($id_event <= 0 || $id_ticket_variant <= 0 || empty($nama) || empty($email)) {
    returnError("Data pesanan tidak lengkap. Silakan periksa kembali formulir Anda.", defined('BASE_URL') ? BASE_URL . "detail_event.php?id=" . $id_event : "../detail_event.php?id=" . $id_event);
}

try {
    // Validasi Event & Stok
    $stmt = $conn->prepare("SELECT * FROM events WHERE id = ? AND status_approval = 'approved'");
    if (!$stmt) {
        throw new Exception("Gagal menyiapkan query event: " . $conn->error);
    }
    $stmt->bind_param("i", $id_event);
    $stmt->execute();
    $event = $stmt->get_result()->fetch_assoc();

    $stmt_var = $conn->prepare("SELECT * FROM event_ticket_variants WHERE id = ? AND id_event = ? AND sisa_stok > 0");
    if (!$stmt_var) {
        throw new Exception("Gagal menyiapkan query varian tiket: " . $conn->error);
    }
    $stmt_var->bind_param("ii", $id_ticket_variant, $id_event);
    $stmt_var->execute();
    $variant = $stmt_var->get_result()->fetch_assoc();

    if (!$event || !$variant || strtotime($event['tanggal']) < strtotime(date('Y-m-d'))) {
        returnError("Tiket tidak tersedia atau event sudah berlalu.", defined('BASE_URL') ? BASE_URL . "detail_event.php?id=" . $id_event : "../detail_event.php?id=" . $id_event);
    }

    // Cek Maksimal 1 Tiket per Transaksi/Email
    $stmtCheck = $conn->prepare("SELECT id FROM tickets WHERE email_pembeli = ? AND id_event = ? AND status IN ('pending', 'lunas', 'scanned')");
    if (!$stmtCheck) {
        throw new Exception("Gagal menyiapkan query pengecekan tiket: " . $conn->error);
    }
    $stmtCheck->bind_param("si", $email, $id_event);
    $stmtCheck->execute();
    if ($stmtCheck->get_result()->num_rows > 0) {
        returnError("Satu akun/email hanya diizinkan membeli 1 tiket per event.", defined('BASE_URL') ? BASE_URL . "detail_event.php?id=" . $id_event : "../detail_event.php?id=" . $id_event);
    }

    // Cek Kunci Midtrans
    $serverKey = defined('MIDTRANS_SERVER_KEY') ? trim(MIDTRANS_SERVER_KEY) : '';
    if (empty($serverKey)) {
        throw new Exception("Kunci Midtrans Server Key belum diisi di Pengaturan Sistem Admin.");
    }

    // Konfigurasi Midtrans dari koneksi.php
    \Midtrans\Config::$serverKey = $serverKey;
    \Midtrans\Config::$isProduction = defined('MIDTRANS_IS_PRODUCTION') ? MIDTRANS_IS_PRODUCTION : false;
    \Midtrans\Config::$isSanitized = true;
    \Midtrans\Config::$is3ds = true;

    $order_id = 'HTK-' . time() . '-' . rand(100, 999);
    $token_qr = bin2hex(random_bytes(16));

    // Amankan Stok (Optimistic Locking)
    $stmt = $conn->prepare("UPDATE event_ticket_variants SET sisa_stok = sisa_stok - 1 WHERE id = ? AND sisa_stok > 0");
    if (!$stmt) {
        throw new Exception("Gagal memperbarui stok varian tiket: " . $conn->error);
    }
    $stmt->bind_param("i", $id_ticket_variant);
    $stmt->execute();
    if ($conn->affected_rows === 0) {
        returnError("Maaf, tiket jenis ini sudah habis terjual saat Anda memproses.", defined('BASE_URL') ? BASE_URL . "detail_event.php?id=" . $id_event : "../detail_event.php?id=" . $id_event);
    }

    // Update total stok event
    $conn->query("UPDATE events SET stok = stok - 1 WHERE id = $id_event AND stok > 0");

    // Simpan transaksi pending ke database
    $stmt = $conn->prepare("INSERT INTO tickets (id_event, id_ticket_variant, nama_pembeli, email_pembeli, no_hp, status, token_qr, order_id) VALUES (?, ?, ?, ?, ?, 'pending', ?, ?)");
    if (!$stmt) {
        // Revert stok jika gagal prepare
        $conn->query("UPDATE event_ticket_variants SET sisa_stok = sisa_stok + 1 WHERE id = $id_ticket_variant");
        $conn->query("UPDATE events SET stok = stok + 1 WHERE id = $id_event");
        throw new Exception("Gagal menyiapkan simpan tiket: " . $conn->error);
    }
    $stmt->bind_param("iisssss", $id_event, $id_ticket_variant, $nama, $email, $no_hp, $token_qr, $order_id);
    if (!$stmt->execute()) {
        // Revert stok jika gagal
        $conn->query("UPDATE event_ticket_variants SET sisa_stok = sisa_stok + 1 WHERE id = $id_ticket_variant");
        $conn->query("UPDATE events SET stok = stok + 1 WHERE id = $id_event");
        returnError("Gagal membuat pesanan. Silakan coba lagi.", defined('BASE_URL') ? BASE_URL . "checkout.php?id=" . $id_event : "../checkout.php?id=" . $id_event);
    }

    $markup_type = $global_settings['admin_markup_type'] ?? 'nominal';
    $markup_value = (float)($global_settings['admin_markup_value'] ?? 5000);

    if ($markup_type == 'percent') {
        $biaya_admin = $variant['harga'] * ($markup_value / 100);
    } else {
        $biaya_admin = $markup_value;
    }

    $gross_amount = (int)$variant['harga'] + (int)$biaya_admin;

    // Parameter Midtrans
    $params = [
        'transaction_details' => [
            'order_id' => $order_id,
            'gross_amount' => $gross_amount,
        ],
        'item_details' => [
            [
                'id' => 'TIKET-' . $id_event . '-' . $id_ticket_variant,
                'price' => (int)$variant['harga'],
                'quantity' => 1,
                'name' => substr($variant['nama_varian'], 0, 50)
            ],
            [
                'id' => 'ADMIN-FEE',
                'price' => (int)$biaya_admin,
                'quantity' => 1,
                'name' => 'Biaya Layanan Platform'
            ]
        ],
        'customer_details' => [
            'first_name' => $nama,
            'email' => $email,
            'phone' => $no_hp,
        ]
    ];

    $snapRes = \Midtrans\Snap::createTransaction($params);
    $snapToken = $snapRes->token ?? '';
    $paymentUrl = $snapRes->redirect_url ?? '';

    // Update snap token & url ke database
    $stmtUpd = $conn->prepare("UPDATE tickets SET snap_token = ?, snap_redirect_url = ? WHERE order_id = ?");
    if ($stmtUpd) {
        $stmtUpd->bind_param("sss", $snapToken, $paymentUrl, $order_id);
        $stmtUpd->execute();
    }

    $_SESSION['last_order_id'] = $order_id;
    $target_pembayaran_url = (defined('BASE_URL') ? BASE_URL : '../') . 'pembayaran.php?order_id=' . urlencode($order_id);

    if ($is_ajax) {
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'success',
            'order_id' => $order_id,
            'snap_token' => $snapToken,
            'payment_url' => $paymentUrl,
            'redirect_url' => $target_pembayaran_url
        ]);
        exit;
    }

    header("Location: " . $target_pembayaran_url);
    exit;

} catch (\Throwable $e) {
    // Jika API Midtrans atau sistem bermasalah, kembalikan stok & hapus tiket jika order_id sudah terbentuk
    if (isset($id_ticket_variant)) {
        $conn->query("UPDATE event_ticket_variants SET sisa_stok = sisa_stok + 1 WHERE id = $id_ticket_variant");
    }
    if (isset($id_event)) {
        $conn->query("UPDATE events SET stok = stok + 1 WHERE id = $id_event");
    }
    if (isset($order_id)) {
        $conn->query("DELETE FROM tickets WHERE order_id = '$order_id'");
    }
    
    returnError("Layanan pembayaran mengalami masalah: " . $e->getMessage(), defined('BASE_URL') ? BASE_URL . "checkout.php?id=" . $id_event : "../checkout.php?id=" . $id_event);
}
?>
