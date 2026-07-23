<?php
session_start();
require_once __DIR__ . '/../config/koneksi.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['input_search'])) {
    echo json_encode(['status' => 'error', 'message' => 'Silakan masukkan email atau kode Order ID.']);
    exit;
}

$input = trim($_POST['input_search']);

// Cari tiket di database
$stmt = $conn->prepare("SELECT email_pembeli, order_id FROM tickets WHERE email_pembeli = ? OR order_id = ? LIMIT 1");
$stmt->bind_param("ss", $input, $input);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    echo json_encode(['status' => 'error', 'message' => 'Tidak ditemukan tiket dengan Email atau Order ID tersebut.']);
    exit;
}

$ticketData = $res->fetch_assoc();
$emailTarget = $ticketData['email_pembeli'];

// Generate 6 digit OTP random
$otp = sprintf("%06d", mt_rand(100000, 999999));

$_SESSION['otp_ticket_code'] = $otp;
$_SESSION['otp_ticket_email'] = $emailTarget;
$_SESSION['otp_ticket_expires'] = time() + 300; // Berlaku 5 menit

// Kirim Email OTP resmi ke inbox email pembeli
$to = $emailTarget;
$subject = "[$otp] Kode OTP Verifikasi E-Ticket - HaloTiket";
$message = "
<!DOCTYPE html>
<html lang='id'>
<head><meta charset='UTF-8'></head>
<body style='font-family: Arial, sans-serif; background-color: #f8fafc; padding: 30px; margin: 0; color: #1e293b;'>
    <div style='max-width: 520px; margin: 0 auto; background: #ffffff; border-radius: 20px; border: 1px solid #e2e8f0; overflow: hidden; box-shadow: 0 10px 25px rgba(0,0,0,0.05);'>
        <div style='background: #003846; color: #ffffff; padding: 25px; text-align: center;'>
            <h1 style='margin: 0; font-size: 22px; color: #ffffff;'>HaloTiket</h1>
            <p style='margin: 5px 0 0 0; font-size: 12px; color: #94a3b8;'>Verifikasi Keamanan Akses Tiket</p>
        </div>
        <div style='padding: 30px; text-align: center;'>
            <h3 style='margin-top: 0; color: #0f172a;'>Kode Verifikasi OTP Anda</h3>
            <p style='font-size: 14px; color: #64748b; margin-bottom: 25px;'>Gunakan kode 6-digit berikut untuk masuk dan melihat E-Ticket Anda di HaloTiket:</p>
            
            <div style='background: #f1f5f9; border: 2px dashed #00c2cb; padding: 18px; text-align: center; border-radius: 14px; font-size: 32px; font-weight: 800; letter-spacing: 8px; color: #008b94; margin: 20px auto; max-width: 280px;'>
                $otp
            </div>

            <p style='font-size: 12px; color: #94a3b8; margin-top: 25px;'>Kode ini hanya berlaku selama <strong>5 menit</strong>. Harap tidak membagikan kode OTP ini kepada siapapun demi keamanan tiket Anda.</p>
        </div>
        <div style='background: #f8fafc; padding: 15px; text-align: center; font-size: 11px; color: #94a3b8; border-top: 1px solid #f1f5f9;'>
            &copy; " . date('Y') . " HaloTiket. All rights reserved.
        </div>
    </div>
</body>
</html>
";

$headers = "MIME-Version: 1.0" . "\r\n";
$headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
$headers .= "From: HaloTiket <no-reply@halotiket.com>" . "\r\n";

@mail($to, $subject, $message, $headers);

echo json_encode([
    'status' => 'success',
    'message' => 'Kode OTP telah dikirimkan ke email: ' . $emailTarget,
    'email' => $emailTarget
]);
exit;
?>
