<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'validator') {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}
require_once '../config/koneksi.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['token'])) {
    $token = $_POST['token'];
    
    // Cari tiket
    $stmt = $conn->prepare("SELECT t.*, e.judul FROM tickets t JOIN events e ON t.id_event = e.id WHERE t.token_qr = ?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $tiket = $result->fetch_assoc();
        
        if ($tiket['status'] == 'scanned') {
            echo json_encode(['status' => 'error', 'message' => 'Tiket sudah digunakan sebelumnya!']);
        } elseif ($tiket['status'] == 'pending') {
            echo json_encode(['status' => 'error', 'message' => 'Tiket belum dibayar / lunas!']);
        } else {
            // Update status menjadi scanned
            $update = $conn->prepare("UPDATE tickets SET status = 'scanned' WHERE id = ?");
            $update->bind_param("i", $tiket['id']);
            $update->execute();
            
            logActivity($conn, $_SESSION['user_id'], 'Scan Ticket', "Berhasil scan tiket ID: " . $tiket['id'] . " atas nama: " . $tiket['nama_pembeli']);
            
            echo json_encode([
                'status' => 'success', 
                'message' => 'Tiket valid',
                'data' => [
                    'nama_pembeli' => $tiket['nama_pembeli'],
                    'judul' => $tiket['judul']
                ]
            ]);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'QR Code tidak dikenali / tidak valid!']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid Request']);
}
?>
