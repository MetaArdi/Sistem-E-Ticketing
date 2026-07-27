<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../../auth/login.php");
    exit;
}

require_once __DIR__ . '/../../config/koneksi.php';

$is_ajax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest');

function returnResponse($status, $message) {
    global $is_ajax;
    if ($is_ajax) {
        header('Content-Type: application/json');
        echo json_encode(['status' => $status, 'message' => $message]);
        exit;
    } else {
        if ($status === 'success') {
            $_SESSION['success'] = $message;
        } else {
            $_SESSION['error'] = $message;
        }
        header("Location: ../manage_transactions.php");
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    returnResponse('error', 'Metode request tidak diizinkan.');
}

$ticket_id = (int)($_POST['ticket_id'] ?? 0);
$action = trim($_POST['action'] ?? '');

if ($ticket_id <= 0 || !in_array($action, ['approve', 'reject'])) {
    returnResponse('error', 'Parameter verifikasi tidak valid.');
}

// Fetch ticket details
$stmt = $conn->prepare("SELECT t.*, e.judul FROM tickets t JOIN events e ON t.id_event = e.id WHERE t.id = ?");
if (!$stmt) {
    returnResponse('error', 'Gagal memproses query tiket.');
}
$stmt->bind_param("i", $ticket_id);
$stmt->execute();
$ticket = $stmt->get_result()->fetch_assoc();

if (!$ticket) {
    returnResponse('error', 'Data tiket tidak ditemukan.');
}

if ($action === 'approve') {
    $stmtUpd = $conn->prepare("UPDATE tickets SET status = 'lunas' WHERE id = ?");
    if ($stmtUpd) {
        $stmtUpd->bind_param("i", $ticket_id);
        $stmtUpd->execute();
    }

    // Kirim email tiket secara aman
    if (!empty($ticket['email_pembeli']) && !empty($ticket['token_qr'])) {
        $pdf_url = (defined('BASE_URL') ? BASE_URL : 'http://localhost/') . "user/download_tiket.php?token=" . urlencode($ticket['token_qr']);
        $to = $ticket['email_pembeli'];
        $subject = "E-Ticket Anda: " . ($ticket['judul'] ?? 'HaloTiket');
        $message = "<html><body style='font-family: Arial, sans-serif;'><h2 style='color: #00c2cb;'>Halo, " . htmlspecialchars($ticket['nama_pembeli'] ?? 'Pembeli') . "!</h2><p>Pembayaran Anda untuk event <strong>" . htmlspecialchars($ticket['judul'] ?? '') . "</strong> telah berhasil diverifikasi LUNAS.</p><p><a href='$pdf_url' style='background-color: #00c2cb; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; font-weight: bold;'>Unduh E-Ticket (PDF)</a></p></body></html>";
        $headers = "MIME-Version: 1.0\r\nContent-type:text/html;charset=UTF-8\r\nFrom: no-reply@halotiket.com\r\n";
        @mail($to, $subject, $message, $headers);
    }

    // Kirim Notifikasi WA Pembayaran Lunas
    if (function_exists('notifyPaymentSuccessWA')) {
        $variant_name = '';
        if (!empty($ticket['id_ticket_variant'])) {
            $resVar = $conn->query("SELECT nama_varian, harga FROM event_ticket_variants WHERE id = " . (int)$ticket['id_ticket_variant']);
            if ($resVar && $rowVar = $resVar->fetch_assoc()) {
                $variant_name = $rowVar['nama_varian'];
            }
        }
        @notifyPaymentSuccessWA($ticket, $ticket['judul'] ?? '', $variant_name);
    }

    logActivity($conn, $_SESSION['user_id'], 'Verifikasi Pembayaran', "Admin menyetujui pembayaran manual untuk tiket Order ID #" . $ticket['order_id']);

    returnResponse('success', "Pembayaran untuk Order ID #" . $ticket['order_id'] . " berhasil disetujui & tiket telah aktif.");
} elseif ($action === 'reject') {
    // Set status batal dan kembalikan stok varian & event
    $stmtUpd = $conn->prepare("UPDATE tickets SET status = 'batal' WHERE id = ?");
    if ($stmtUpd) {
        $stmtUpd->bind_param("i", $ticket_id);
        $stmtUpd->execute();
    }

    // Revert stok
    if ($ticket['id_ticket_variant'] > 0) {
        $conn->query("UPDATE event_ticket_variants SET sisa_stok = sisa_stok + 1 WHERE id = " . (int)$ticket['id_ticket_variant']);
    }
    if ($ticket['id_event'] > 0) {
        $conn->query("UPDATE events SET stok = stok + 1 WHERE id = " . (int)$ticket['id_event']);
    }

    logActivity($conn, $_SESSION['user_id'], 'Tolak Pembayaran', "Admin menolak pembayaran manual untuk tiket Order ID #" . $ticket['order_id']);

    returnResponse('success', "Pesanan Order ID #" . $ticket['order_id'] . " telah ditolak dan stok dikembalikan.");
}
?>
