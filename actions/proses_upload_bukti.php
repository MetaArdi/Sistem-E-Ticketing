<?php
session_start();
require_once __DIR__ . '/../config/koneksi.php';

$is_ajax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest');

function returnUploadError($msg, $order_id = '') {
    global $is_ajax;
    if ($is_ajax) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => $msg]);
        exit;
    } else {
        $_SESSION['error'] = $msg;
        $url = defined('BASE_URL') ? BASE_URL . 'pembayaran.php?order_id=' . urlencode($order_id) : '../pembayaran.php?order_id=' . urlencode($order_id);
        header("Location: " . $url);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: " . (defined('BASE_URL') ? BASE_URL : '../') . "index.php");
    exit;
}

$order_id = trim($_POST['order_id'] ?? '');
if (empty($order_id)) {
    returnUploadError("Order ID tidak valid.");
}

$stmt = $conn->prepare("SELECT id, status FROM tickets WHERE order_id = ?");
if (!$stmt) {
    returnUploadError("Terjadi kesalahan database.");
}
$stmt->bind_param("s", $order_id);
$stmt->execute();
$ticket = $stmt->get_result()->fetch_assoc();

if (!$ticket) {
    returnUploadError("Data tiket tidak ditemukan.", $order_id);
}

if (!isset($_FILES['bukti_pembayaran']) || $_FILES['bukti_pembayaran']['error'] !== UPLOAD_ERR_OK) {
    returnUploadError("Silakan pilih file bukti pembayaran terlebih dahulu.", $order_id);
}

$file = $_FILES['bukti_pembayaran'];
$maxSize = 5 * 1024 * 1024; // 5MB

if ($file['size'] > $maxSize) {
    returnUploadError("Ukuran file terlalu besar. Maksimal 5 MB.", $order_id);
}

$fileName = $file['name'];
$fileTmp = $file['tmp_name'];
$ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
$allowedExts = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];

if (!in_array($ext, $allowedExts)) {
    returnUploadError("Format file tidak didukung. Harap unggah foto JPG, PNG, WEBP, atau PDF.", $order_id);
}

$uploadDir = __DIR__ . '/../uploads/bukti_pembayaran/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

$newFileName = 'BUKTI_' . $order_id . '_' . time() . '.' . $ext;
$targetPath = $uploadDir . $newFileName;

if (!move_uploaded_file($fileTmp, $targetPath)) {
    returnUploadError("Gagal mengunggah file ke server. Silakan coba lagi.", $order_id);
}

// Update database
$stmtUpd = $conn->prepare("UPDATE tickets SET bukti_pembayaran = ?, tanggal_upload_bukti = NOW() WHERE id = ?");
if ($stmtUpd) {
    $stmtUpd->bind_param("si", $newFileName, $ticket['id']);
    $stmtUpd->execute();
}

$_SESSION['success'] = "Bukti pembayaran berhasil diunggah! Mohon tunggu verifikasi dari Admin/Panitia.";

if ($is_ajax) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'success', 'message' => 'Bukti pembayaran berhasil diunggah']);
    exit;
}

header("Location: " . (defined('BASE_URL') ? BASE_URL : '../') . "pembayaran.php?order_id=" . urlencode($order_id) . "&upload_success=1");
exit;
?>
