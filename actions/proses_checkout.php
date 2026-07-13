<?php
session_start();
require_once '../config/koneksi.php';
require_once '../vendor/autoload.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit;
}

$id_event = (int)$_POST['id_event'];
$id_ticket_variant = (int)$_POST['id_ticket_variant'];
$nama = trim($_POST['nama']);
$email = trim($_POST['email']);
$no_hp = trim($_POST['no_hp']);

// Validasi Event & Stok
$stmt = $conn->prepare("SELECT * FROM events WHERE id = ? AND status_approval = 'approved'");
$stmt->bind_param("i", $id_event);
$stmt->execute();
$event = $stmt->get_result()->fetch_assoc();

$stmt_var = $conn->prepare("SELECT * FROM event_ticket_variants WHERE id = ? AND id_event = ? AND sisa_stok > 0");
$stmt_var->bind_param("ii", $id_ticket_variant, $id_event);
$stmt_var->execute();
$variant = $stmt_var->get_result()->fetch_assoc();

if (!$event || !$variant || strtotime($event['tanggal']) < strtotime(date('Y-m-d'))) {
    $_SESSION['error'] = "Tiket tidak tersedia atau event sudah berlalu.";
    header("Location: detail_event.php?id=" . $id_event);
    exit;
}

// Cek Maksimal 1 Tiket per Transaksi/Email
$stmtCheck = $conn->prepare("SELECT id FROM tickets WHERE email_pembeli = ? AND id_event = ? AND status IN ('pending', 'lunas', 'scanned')");
$stmtCheck->bind_param("si", $email, $id_event);
$stmtCheck->execute();
if ($stmtCheck->get_result()->num_rows > 0) {
    $_SESSION['error'] = "Satu akun/email hanya diizinkan membeli 1 tiket per event.";
    header("Location: detail_event.php?id=" . $id_event);
    exit;
}

// Konfigurasi Midtrans dari koneksi.php
\Midtrans\Config::$serverKey = MIDTRANS_SERVER_KEY;
\Midtrans\Config::$isProduction = MIDTRANS_IS_PRODUCTION;
\Midtrans\Config::$isSanitized = true;
\Midtrans\Config::$is3ds = true;

$order_id = 'HTK-' . time() . '-' . rand(100, 999);
$token_qr = bin2hex(random_bytes(16));

// Amankan Stok (Optimistic Locking)
$stmt = $conn->prepare("UPDATE event_ticket_variants SET sisa_stok = sisa_stok - 1 WHERE id = ? AND sisa_stok > 0");
$stmt->bind_param("i", $id_ticket_variant);
$stmt->execute();
if ($conn->affected_rows === 0) {
    $_SESSION['error'] = "Maaf, tiket jenis ini sudah habis terjual saat Anda memproses.";
    header("Location: detail_event.php?id=" . $id_event);
    exit;
}

// Update total stok event
$conn->query("UPDATE events SET stok = stok - 1 WHERE id = $id_event AND stok > 0");

// Simpan transaksi pending ke database
$stmt = $conn->prepare("INSERT INTO tickets (id_event, id_ticket_variant, nama_pembeli, email_pembeli, no_hp, status, token_qr, order_id) VALUES (?, ?, ?, ?, ?, 'pending', ?, ?)");
$stmt->bind_param("iisssss", $id_event, $id_ticket_variant, $nama, $email, $no_hp, $token_qr, $order_id);
if (!$stmt->execute()) {
    // Revert stok jika gagal
    $conn->query("UPDATE event_ticket_variants SET sisa_stok = sisa_stok + 1 WHERE id = $id_ticket_variant");
    $conn->query("UPDATE events SET stok = stok + 1 WHERE id = $id_event");
    $_SESSION['error'] = "Gagal membuat pesanan. Silakan coba lagi.";
    header("Location: checkout.php?id=" . $id_event);
    exit;
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
            'price' => $biaya_admin,
            'quantity' => 1,
            'name' => 'Biaya Layanan Platform'
        ]
    ],
    'customer_details' => [
        'first_name' => $nama,
        'email' => $email,
        'phone' => $no_hp,
    ],
    'enabled_payments' => ['qris'], // Hanya memunculkan QRIS
];

try {
    // Dapatkan URL Snap Midtrans dan langsung Redirect
    $paymentUrl = \Midtrans\Snap::createTransaction($params)->redirect_url;
    header("Location: " . $paymentUrl);
    exit;
} catch (Exception $e) {
    // Jika API Midtrans bermasalah, kembalikan stok & hapus tiket
    $conn->query("UPDATE event_ticket_variants SET sisa_stok = sisa_stok + 1 WHERE id = $id_ticket_variant");
    $conn->query("UPDATE events SET stok = stok + 1 WHERE id = $id_event");
    $conn->query("DELETE FROM tickets WHERE order_id = '$order_id'");
    $_SESSION['error'] = "Layanan pembayaran sedang gangguan. Coba lagi nanti.";
    header("Location: checkout.php?id=" . $id_event);
    exit;
}
?>
