<?php
require_once __DIR__ . '/../config/koneksi.php';
$autoloadPath = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoloadPath)) {
    require_once $autoloadPath;
}

$serverKey = defined('MIDTRANS_SERVER_KEY') ? trim(MIDTRANS_SERVER_KEY) : '';
\Midtrans\Config::$serverKey = $serverKey;
\Midtrans\Config::$isProduction = defined('MIDTRANS_IS_PRODUCTION') ? MIDTRANS_IS_PRODUCTION : false;

try {
    $notif = new \Midtrans\Notification();
    
    $transaction = $notif->transaction_status;
    $type = $notif->payment_type;
    $order_id = $notif->order_id;
    $fraud = $notif->fraud_status;

    // Ambil data tiket terkait
    $stmt_ticket = $conn->prepare("SELECT * FROM tickets WHERE order_id = ?");
    $stmt_ticket->bind_param("s", $order_id);
    $stmt_ticket->execute();
    $ticket = $stmt_ticket->get_result()->fetch_assoc();

    if ($ticket) {
        if ($transaction == 'capture') {
            if ($type == 'credit_card'){
                if ($fraud == 'challenge'){
                    $conn->query("UPDATE tickets SET status = 'pending' WHERE order_id = '$order_id'");
                } else {
                    $conn->query("UPDATE tickets SET status = 'lunas' WHERE order_id = '$order_id'");
                    // Kirim email e-ticket PDF resmi
                    if (function_exists('sendTicketEmailDirect') && !empty($ticket['token_qr'])) {
                        @sendTicketEmailDirect($conn, $ticket['token_qr']);
                    }
                    if (function_exists('notifyPaymentSuccessWA')) {
                        $stmtEv = $conn->query("SELECT e.judul, v.nama_varian FROM events e LEFT JOIN event_ticket_variants v ON v.id = " . (int)$ticket['id_ticket_variant'] . " WHERE e.id = " . (int)$ticket['id_event']);
                        $evRow = $stmtEv ? $stmtEv->fetch_assoc() : [];
                        @notifyPaymentSuccessWA($ticket, $evRow['judul'] ?? '', $evRow['nama_varian'] ?? '');
                    }
                }
            }
        } else if ($transaction == 'settlement'){
            $conn->query("UPDATE tickets SET status = 'lunas' WHERE order_id = '$order_id'");
            // Kirim email e-ticket PDF resmi
            if (function_exists('sendTicketEmailDirect') && !empty($ticket['token_qr'])) {
                @sendTicketEmailDirect($conn, $ticket['token_qr']);
            }
            if (function_exists('notifyPaymentSuccessWA')) {
                $stmtEv = $conn->query("SELECT e.judul, v.nama_varian FROM events e LEFT JOIN event_ticket_variants v ON v.id = " . (int)$ticket['id_ticket_variant'] . " WHERE e.id = " . (int)$ticket['id_event']);
                $evRow = $stmtEv ? $stmtEv->fetch_assoc() : [];
                @notifyPaymentSuccessWA($ticket, $evRow['judul'] ?? '', $evRow['nama_varian'] ?? '');
            }
        } else if ($transaction == 'pending'){
            $conn->query("UPDATE tickets SET status = 'pending' WHERE order_id = '$order_id'");
        } else if (in_array($transaction, ['deny', 'expire', 'cancel'])) {
            if ($ticket['status'] != 'batal') {
                $conn->query("UPDATE tickets SET status = 'batal' WHERE order_id = '$order_id'");
                // Revert Stok Tiket jika transaksi gagal/expire/batal
                $id_variant = (int)$ticket['id_ticket_variant'];
                $id_event = (int)$ticket['id_event'];
                $conn->query("UPDATE event_ticket_variants SET sisa_stok = sisa_stok + 1 WHERE id = $id_variant");
                $conn->query("UPDATE events SET stok = stok + 1 WHERE id = $id_event");
            }
        }
    }
    
    http_response_code(200);
    echo "OK";
} catch (\Exception $e) {
    http_response_code(500);
    exit($e->getMessage());
}
?>
