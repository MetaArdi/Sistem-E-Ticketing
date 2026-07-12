<?php
require_once 'config/koneksi.php';
require_once 'vendor/autoload.php';

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use chillerlan\QRCode\Data\QRMatrix;
use setasign\Fpdf\Fpdf;

if (!isset($_GET['token'])) {
    die("Token tidak valid.");
}

$token = $_GET['token'];
$stmt = $conn->prepare("SELECT t.*, e.judul, e.tanggal, e.waktu, e.lokasi, u.nama_lengkap as penyelenggara 
                        FROM tickets t 
                        JOIN events e ON t.id_event = e.id 
                        JOIN users u ON e.id_panitia = u.id
                        WHERE t.token_qr = ? AND t.status IN ('lunas','scanned')");
$stmt->bind_param("s", $token);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    die("Tiket tidak ditemukan atau belum lunas.");
}

$tiket = $result->fetch_assoc();

// Generate QR Code menggunakan chillerlan/php-qrcode
$options = new QROptions([
    'version'    => 5,
    'outputType' => QRCode::OUTPUT_IMAGE_PNG,
    'eccLevel'   => QRCode::ECC_L,
    'scale'      => 5,
]);

$qrcode = new QRCode($options);
$qrImageBase64 = $qrcode->render($token); // returns data:image/png;base64,...
$qrImageBase64 = str_replace('data:image/png;base64,', '', $qrImageBase64);
$qrImageData = base64_decode($qrImageBase64);

// Simpan QR Image sementara
$tempQrFile = tempnam(sys_get_temp_dir(), 'qr_') . '.png';
file_put_contents($tempQrFile, $qrImageData);

// Generate PDF
$pdf = new Fpdf('P', 'mm', 'A4');
$pdf->AddPage();

// Header PDF
$pdf->SetFont('Arial', 'B', 24);
$pdf->SetTextColor(13, 110, 253); // Primary Color
$pdf->Cell(0, 20, 'HALOTIKET E-TICKET', 0, 1, 'C');

$pdf->SetDrawColor(200, 200, 200);
$pdf->Line(10, 30, 200, 30);
$pdf->Ln(10);

// Info Event
$pdf->SetFont('Arial', 'B', 16);
$pdf->SetTextColor(33, 37, 41);
$pdf->Cell(0, 10, strtoupper($tiket['judul']), 0, 1, 'C');

$pdf->SetFont('Arial', '', 12);
$pdf->Cell(0, 8, 'Tanggal: ' . date('d F Y', strtotime($tiket['tanggal'])) . ' | Waktu: ' . $tiket['waktu'], 0, 1, 'C');
$pdf->Cell(0, 8, 'Lokasi: ' . $tiket['lokasi'], 0, 1, 'C');
$pdf->Cell(0, 8, 'Penyelenggara: ' . $tiket['penyelenggara'], 0, 1, 'C');

$pdf->Ln(10);

// Info Pembeli
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(50, 10, 'Order ID', 0, 0);
$pdf->SetFont('Arial', '', 12);
$pdf->Cell(0, 10, ': ' . $tiket['order_id'], 0, 1);

$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(50, 10, 'Nama Pembeli', 0, 0);
$pdf->SetFont('Arial', '', 12);
$pdf->Cell(0, 10, ': ' . $tiket['nama_pembeli'], 0, 1);

$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(50, 10, 'Email', 0, 0);
$pdf->SetFont('Arial', '', 12);
$pdf->Cell(0, 10, ': ' . $tiket['email_pembeli'], 0, 1);

$pdf->Ln(10);

// QR Code
$pdf->Image($tempQrFile, 75, 140, 60, 60);

$pdf->SetXY(10, 210);
$pdf->SetFont('Arial', 'I', 10);
$pdf->SetTextColor(108, 117, 125);
$pdf->Cell(0, 10, '* Tunjukkan QR Code ini kepada petugas validator di pintu masuk.', 0, 1, 'C');
$pdf->Cell(0, 10, '* Jangan bagikan QR Code ini kepada orang lain.', 0, 1, 'C');

// Output PDF
$pdf->Output('I', 'Tiket_' . $tiket['order_id'] . '.pdf');

// Clean up temp file
unlink($tempQrFile);
?>
