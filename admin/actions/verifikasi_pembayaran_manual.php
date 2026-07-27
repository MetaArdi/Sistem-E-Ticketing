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
        $subject = "E-Ticket Resmi: " . ($ticket['judul'] ?? 'HaloTiket') . " - HaloTiket";
        $message = "<!DOCTYPE html><html lang='id'><head><meta charset='UTF-8'></head><body style='font-family: Arial, sans-serif; background-color: #f8fafc; padding: 30px; margin: 0; color: #1e293b;'><div style='max-width: 520px; margin: 0 auto; background: #ffffff; border-radius: 20px; border: 1px solid #e2e8f0; overflow: hidden;'><div style='background: #0f1c3f; color: #ffffff; padding: 25px; text-align: center;'><h1 style='margin: 0; font-size: 24px; color: #00c2cb;'>HaloTiket</h1><p style='margin: 5px 0 0 0; font-size: 12px; color: #94a3b8;'>E-Ticket Resmi Pembelian Event</p></div><div style='padding: 30px;'><h2 style='margin-top: 0; color: #0f172a; font-size: 18px;'>Halo, " . htmlspecialchars($ticket['nama_pembeli'] ?? 'Pembeli') . "! 👋</h2><p style='font-size: 14px; color: #475569; line-height: 1.6;'>Pembayaran Anda untuk event <strong>" . htmlspecialchars($ticket['judul'] ?? '') . "</strong> telah berhasil diverifikasi <strong>LUNAS</strong>.</p><div style='text-align: center; margin: 30px 0;'><a href='$pdf_url' style='background-color: #00c2cb; color: #ffffff; padding: 14px 28px; text-decoration: none; border-radius: 12px; font-weight: bold; font-size: 14px; display: inline-block;'>Unduh E-Ticket (PDF)</a></div></div><div style='background: #f8fafc; padding: 15px; text-align: center; font-size: 11px; color: #94a3b8;'>&copy; " . date('Y') . " HaloTiket. All rights reserved.</div></div></body></html>";
        $headers = "MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\nFrom: HaloTiket <no-reply@halotiket.com>\r\nReply-To: no-reply@halotiket.com\r\nX-Mailer: PHP/" . phpversion() . "\r\n";
        $m_ok = @mail($to, $subject, $message, $headers, "-f no-reply@halotiket.com");
        if (!$m_ok) {
            @mail($to, $subject, $message, $headers);
        }
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
