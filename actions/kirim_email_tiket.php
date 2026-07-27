<?php
session_start();
require_once '../config/koneksi.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['token'])) {
    echo json_encode(['status' => 'error', 'message' => 'Permintaan tidak valid.']);
    exit;
}

$token = $_POST['token'];

$stmt = $conn->prepare("SELECT t.*, e.judul FROM tickets t JOIN events e ON t.id_event = e.id WHERE t.token_qr = ? AND t.status IN ('lunas','scanned')");
$stmt->bind_param("s", $token);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    echo json_encode(['status' => 'error', 'message' => 'Tiket tidak valid atau belum lunas.']);
    exit;
}

$tiket = $result->fetch_assoc();
$email_pembeli = $tiket['email_pembeli'];
$nama_pembeli = $tiket['nama_pembeli'];
$judul_event = $tiket['judul'];

// URL untuk generate PDF
$pdf_url = BASE_URL . "user/download_tiket.php?token=" . $token;

$to = $email_pembeli;
$subject = "E-Ticket Resmi: " . $judul_event . " - HaloTiket";

$message = "
<!DOCTYPE html>
<html lang='id'>
<head><meta charset='UTF-8'></head>
<body style='font-family: Arial, sans-serif; background-color: #f8fafc; padding: 30px; margin: 0; color: #1e293b;'>
    <div style='max-width: 520px; margin: 0 auto; background: #ffffff; border-radius: 20px; border: 1px solid #e2e8f0; overflow: hidden; box-shadow: 0 10px 25px rgba(0,0,0,0.05);'>
        <div style='background: #0f1c3f; color: #ffffff; padding: 25px; text-align: center;'>
            <h1 style='margin: 0; font-size: 24px; color: #00c2cb;'>HaloTiket</h1>
            <p style='margin: 5px 0 0 0; font-size: 12px; color: #94a3b8;'>E-Ticket Resmi Pembelian Event</p>
        </div>
        <div style='padding: 30px;'>
            <h2 style='margin-top: 0; color: #0f172a; font-size: 18px;'>Halo, " . htmlspecialchars($nama_pembeli) . "! 👋</h2>
            <p style='font-size: 14px; color: #475569; line-height: 1.6;'>Terima kasih telah melakukan pemesanan tiket <strong>" . htmlspecialchars($judul_event) . "</strong> di HaloTiket.</p>
            <p style='font-size: 14px; color: #475569; line-height: 1.6;'>Pembayaran Anda telah berhasil kami verifikasi <strong>LUNAS</strong>. Silakan unduh E-Ticket PDF Anda melalui tombol di bawah ini:</p>
            
            <div style='text-align: center; margin: 30px 0;'>
                <a href='$pdf_url' style='background-color: #00c2cb; color: #ffffff; padding: 14px 28px; text-decoration: none; border-radius: 12px; font-weight: bold; font-size: 14px; display: inline-block; box-shadow: 0 4px 12px rgba(0,194,203,0.3);'>Unduh E-Ticket (PDF)</a>
            </div>

            <div style='background: #f1f5f9; border-left: 4px solid #00c2cb; padding: 12px 16px; border-radius: 8px; font-size: 12px; color: #64748b;'>
                📌 <strong>Petunjuk Penting:</strong> Simpan E-Ticket ini dan tunjukkan file PDF / QR Code kepada petugas di lokasi acara saat check-in.
            </div>
        </div>
        <div style='background: #f8fafc; padding: 15px; text-align: center; font-size: 11px; color: #94a3b8; border-top: 1px solid #f1f5f9;'>
            &copy; " . date('Y') . " HaloTiket. All rights reserved.
        </div>
    </div>
</body>
</html>
";

$headers = "MIME-Version: 1.0\r\n";
$headers .= "Content-Type: text/html; charset=UTF-8\r\n";
$headers .= "From: HaloTiket <no-reply@halotiket.com>\r\n";
$headers .= "Reply-To: no-reply@halotiket.com\r\n";
$headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";

$mail_sent = @mail($to, $subject, $message, $headers, "-f no-reply@halotiket.com");
if (!$mail_sent) {
    @mail($to, $subject, $message, $headers);
}

echo json_encode(['status' => 'success', 'message' => 'Email e-ticket berhasil dikirimkan ke inbox pembeli.']);
exit;
