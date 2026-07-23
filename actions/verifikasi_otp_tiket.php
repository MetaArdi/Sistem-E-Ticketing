<?php
session_start();
require_once __DIR__ . '/../config/koneksi.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['otp_code'])) {
    echo json_encode(['status' => 'error', 'message' => 'Silakan masukkan kode OTP.']);
    exit;
}

$userOtp = trim($_POST['otp_code']);
$sessionOtp = $_SESSION['otp_ticket_code'] ?? '';
$sessionEmail = $_SESSION['otp_ticket_email'] ?? '';
$sessionExpires = $_SESSION['otp_ticket_expires'] ?? 0;

if (empty($sessionOtp) || empty($sessionEmail)) {
    echo json_encode(['status' => 'error', 'message' => 'Sesi OTP tidak ditemukan. Silakan minta kode OTP baru.']);
    exit;
}

if (time() > $sessionExpires) {
    echo json_encode(['status' => 'error', 'message' => 'Kode OTP telah kadaluarsa. Silakan minta kode OTP baru.']);
    exit;
}

if ($userOtp !== $sessionOtp) {
    echo json_encode(['status' => 'error', 'message' => 'Kode OTP yang Anda masukkan salah.']);
    exit;
}

// OTP Valid! Berikan akses verifikasi tiket
$_SESSION['verified_ticket_email'] = $sessionEmail;
unset($_SESSION['otp_ticket_code']);
unset($_SESSION['otp_ticket_expires']);

echo json_encode([
    'status' => 'success',
    'message' => 'Verifikasi OTP berhasil!',
    'redirect_url' => BASE_URL . 'user/riwayat_pembelian.php?email=' . urlencode($sessionEmail)
]);
exit;
?>
