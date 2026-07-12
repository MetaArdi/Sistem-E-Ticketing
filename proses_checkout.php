<?php
session_start();
require_once 'config/koneksi.php';
require_once 'vendor/autoload.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit;
}

$id_event = (int)$_POST['id_event'];
$nama = trim($_POST['nama']);
$email = trim($_POST['email']);
$no_hp = trim($_POST['no_hp']);

// Validasi Event & Stok
$stmt = $conn->prepare("SELECT * FROM events WHERE id = ? AND status_approval = 'approved'");
$stmt->bind_param("i", $id_event);
$stmt->execute();
$event = $stmt->get_result()->fetch_assoc();

if (!$event || $event['stok'] < 1 || strtotime($event['tanggal']) < strtotime(date('Y-m-d'))) {
    $_SESSION['error'] = "Tiket tidak tersedia atau event sudah berlalu.";
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
$stmt = $conn->prepare("UPDATE events SET stok = stok - 1 WHERE id = ? AND stok > 0");
$stmt->bind_param("i", $id_event);
$stmt->execute();
if ($conn->affected_rows === 0) {
    $_SESSION['error'] = "Maaf, tiket sudah habis terjual saat Anda memproses.";
    header("Location: detail_event.php?id=" . $id_event);
    exit;
}

// Simpan transaksi pending ke database
$stmt = $conn->prepare("INSERT INTO tickets (id_event, nama_pembeli, email_pembeli, no_hp, status, token_qr, order_id) VALUES (?, ?, ?, ?, 'pending', ?, ?)");
$stmt->bind_param("isssss", $id_event, $nama, $email, $no_hp, $token_qr, $order_id);
if (!$stmt->execute()) {
    // Revert stok jika gagal
    $conn->query("UPDATE events SET stok = stok + 1 WHERE id = $id_event");
    $_SESSION['error'] = "Gagal membuat pesanan. Silakan coba lagi.";
    header("Location: checkout.php?id=" . $id_event);
    exit;
}

// Parameter Midtrans
$params = [
    'transaction_details' => [
        'order_id' => $order_id,
        'gross_amount' => (int)$event['harga'],
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
    $conn->query("UPDATE events SET stok = stok + 1 WHERE id = $id_event");
    $conn->query("DELETE FROM tickets WHERE order_id = '$order_id'");
    
    $_SESSION['error'] = "Layanan pembayaran sedang gangguan. Coba lagi nanti.";
    header("Location: checkout.php?id=" . $id_event);
    exit;
}
?>
