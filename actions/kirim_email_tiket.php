<?php
session_start();
require_once '../config/koneksi.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['token'])) {
    echo json_encode(['status' => 'error', 'message' => 'Permintaan tidak valid. Token tidak ditemukan.']);
    exit;
}

$token = trim($_POST['token']);

if (function_exists('sendTicketEmailDirect')) {
    $sent = sendTicketEmailDirect($conn, $token);
    if ($sent) {
        echo json_encode(['status' => 'success', 'message' => 'Email e-ticket berhasil dikirimkan ke inbox pembeli.']);
        exit;
    }
}

echo json_encode(['status' => 'error', 'message' => 'Tiket tidak ditemukan atau belum berstatus LUNAS.']);
exit;
