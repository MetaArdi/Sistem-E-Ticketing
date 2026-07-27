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

$action = trim($_POST['action'] ?? '');

if (!in_array($action, ['create', 'update', 'delete'])) {
    returnResponse('error', 'Aksi transaksi tidak valid.');
}

// =========================================================================
// ACTION: CREATE (TAMBAH TRANSAKSI MANUAL)
// =========================================================================
if ($action === 'create') {
    $id_event = (int)($_POST['id_event'] ?? 0);
    $id_ticket_variant = (int)($_POST['id_ticket_variant'] ?? 0);
    $nama_pembeli = trim($_POST['nama_pembeli'] ?? '');
    $email_pembeli = trim($_POST['email_pembeli'] ?? '');
    $no_hp = trim($_POST['no_hp'] ?? '');
    $status = trim($_POST['status'] ?? 'lunas');
    $payment_method = trim($_POST['payment_method'] ?? 'manual');

    if ($id_event <= 0 || empty($nama_pembeli) || empty($email_pembeli)) {
        returnResponse('error', 'Harap lengkapi semua kolom form tambah transaksi.');
    }

    if (!in_array($status, ['lunas', 'pending', 'batal'])) {
        $status = 'lunas';
    }
    if (!in_array($payment_method, ['manual', 'midtrans'])) {
        $payment_method = 'manual';
    }

    // Generate Order ID & QR Token
    $order_id = 'HTK-' . time() . '-' . rand(100, 999);
    $token_qr = bin2hex(random_bytes(16));

    // Kurangi Stok jika status bukan batal
    if ($status !== 'batal') {
        if ($id_ticket_variant > 0) {
            $conn->query("UPDATE event_ticket_variants SET sisa_stok = GREATEST(0, sisa_stok - 1) WHERE id = $id_ticket_variant");
        }
        if ($id_event > 0) {
            $conn->query("UPDATE events SET stok = GREATEST(0, stok - 1) WHERE id = $id_event");
        }
    }

    // Insert Ticket Record
    $stmt = $conn->prepare("INSERT INTO tickets (id_event, id_ticket_variant, nama_pembeli, email_pembeli, no_hp, status, token_qr, order_id, payment_method) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    if (!$stmt) {
        returnResponse('error', 'Gagal menyiapkan query simpan transaksi: ' . $conn->error);
    }

    $stmt->bind_param("iisssssss", $id_event, $id_ticket_variant, $nama_pembeli, $email_pembeli, $no_hp, $status, $token_qr, $order_id, $payment_method);
    
    if ($stmt->execute()) {
        logActivity($conn, $_SESSION['user_id'], 'Tambah Transaksi Manual', "Admin menambahkan transaksi manual Order ID #$order_id ($nama_pembeli)");
        
        // Kirim email e-ticket & WA jika status lunas
        if ($status === 'lunas' && !empty($token_qr)) {
            if (function_exists('sendTicketEmailDirect')) {
                @sendTicketEmailDirect($conn, $token_qr);
            }
            if (function_exists('notifyPaymentSuccessWA')) {
                $evTitle = '';
                $varTitle = '';
                $resEv = $conn->query("SELECT e.judul, v.nama_varian, v.harga FROM events e LEFT JOIN event_ticket_variants v ON v.id = $id_ticket_variant WHERE e.id = $id_event");
                if ($resEv && $rEv = $resEv->fetch_assoc()) {
                    $evTitle = $rEv['judul'] ?? '';
                    $varTitle = $rEv['nama_varian'] ?? '';
                }
                $ticket_info = [
                    'order_id' => $order_id,
                    'nama_pembeli' => $nama_pembeli,
                    'no_hp' => $no_hp,
                    'email_pembeli' => $email_pembeli,
                    'token_qr' => $token_qr
                ];
                @notifyPaymentSuccessWA($ticket_info, $evTitle, $varTitle);
            }
        }

        returnResponse('success', "Transaksi baru Order ID #$order_id berhasil ditambahkan. Email & WhatsApp E-Ticket telah dikirim ke $nama_pembeli.");
    } else {
        returnResponse('error', 'Gagal menyimpan transaksi: ' . $stmt->error);
    }
}

// =========================================================================
// ACTION: UPDATE (EDIT TRANSAKSI)
// =========================================================================
elseif ($action === 'update') {
    $ticket_id = (int)($_POST['ticket_id'] ?? 0);
    $nama_pembeli = trim($_POST['nama_pembeli'] ?? '');
    $email_pembeli = trim($_POST['email_pembeli'] ?? '');
    $no_hp = trim($_POST['no_hp'] ?? '');
    $status = trim($_POST['status'] ?? 'lunas');
    $payment_method = trim($_POST['payment_method'] ?? 'manual');
    $id_ticket_variant = (int)($_POST['id_ticket_variant'] ?? 0);

    if ($ticket_id <= 0 || empty($nama_pembeli) || empty($email_pembeli)) {
        returnResponse('error', 'Harap lengkapi semua data edit transaksi.');
    }

    // Ambil data lama transaksi
    $stmtOld = $conn->prepare("SELECT * FROM tickets WHERE id = ?");
    $stmtOld->bind_param("i", $ticket_id);
    $stmtOld->execute();
    $oldTicket = $stmtOld->get_result()->fetch_assoc();

    if (!$oldTicket) {
        returnResponse('error', 'Data tiket tidak ditemukan.');
    }

    $old_status = $oldTicket['status'];
    $old_variant = (int)$oldTicket['id_ticket_variant'];
    $id_event = (int)$oldTicket['id_event'];

    // Penyesuaian stok jika status berubah
    if ($old_status !== 'batal' && $status === 'batal') {
        // Status dari lunas/pending ke batal -> kembalikan stok
        if ($old_variant > 0) {
            $conn->query("UPDATE event_ticket_variants SET sisa_stok = sisa_stok + 1 WHERE id = $old_variant");
        }
        if ($id_event > 0) {
            $conn->query("UPDATE events SET stok = stok + 1 WHERE id = $id_event");
        }
    } elseif ($old_status === 'batal' && $status !== 'batal') {
        // Status dari batal ke lunas/pending -> kurangi stok
        $target_var = $id_ticket_variant > 0 ? $id_ticket_variant : $old_variant;
        if ($target_var > 0) {
            $conn->query("UPDATE event_ticket_variants SET sisa_stok = GREATEST(0, sisa_stok - 1) WHERE id = $target_var");
        }
        if ($id_event > 0) {
            $conn->query("UPDATE events SET stok = GREATEST(0, stok - 1) WHERE id = $id_event");
        }
    }

    // Update Record
    $stmtUpd = $conn->prepare("UPDATE tickets SET nama_pembeli = ?, email_pembeli = ?, no_hp = ?, status = ?, payment_method = ?, id_ticket_variant = ? WHERE id = ?");
    if (!$stmtUpd) {
        returnResponse('error', 'Gagal menyiapkan query update transaksi: ' . $conn->error);
    }

    $stmtUpd->bind_param("sssssii", $nama_pembeli, $email_pembeli, $no_hp, $status, $payment_method, $id_ticket_variant, $ticket_id);

    if ($stmtUpd->execute()) {
        logActivity($conn, $_SESSION['user_id'], 'Update Transaksi', "Admin memperbarui data transaksi Order ID #" . $oldTicket['order_id']);
        
        // Kirim email e-ticket & WA jika status berubah menjadi lunas
        if ($old_status !== 'lunas' && $status === 'lunas' && !empty($oldTicket['token_qr'])) {
            if (function_exists('sendTicketEmailDirect')) {
                @sendTicketEmailDirect($conn, $oldTicket['token_qr']);
            }
            if (function_exists('notifyPaymentSuccessWA')) {
                $evTitle = '';
                $varTitle = '';
                $target_var = $id_ticket_variant > 0 ? $id_ticket_variant : $old_variant;
                $resEv = $conn->query("SELECT e.judul, v.nama_varian, v.harga FROM events e LEFT JOIN event_ticket_variants v ON v.id = $target_var WHERE e.id = $id_event");
                if ($resEv && $rEv = $resEv->fetch_assoc()) {
                    $evTitle = $rEv['judul'] ?? '';
                    $varTitle = $rEv['nama_varian'] ?? '';
                }
                $ticket_info = [
                    'order_id' => $oldTicket['order_id'],
                    'nama_pembeli' => $nama_pembeli,
                    'no_hp' => $no_hp,
                    'email_pembeli' => $email_pembeli,
                    'token_qr' => $oldTicket['token_qr']
                ];
                @notifyPaymentSuccessWA($ticket_info, $evTitle, $varTitle);
            }
        }

        returnResponse('success', "Data transaksi Order ID #" . $oldTicket['order_id'] . " berhasil diperbarui.");
    } else {
        returnResponse('error', 'Gagal memperbarui transaksi: ' . $stmtUpd->error);
    }
}

// =========================================================================
// ACTION: DELETE (HAPUS TRANSAKSI)
// =========================================================================
elseif ($action === 'delete') {
    $ticket_id = (int)($_POST['ticket_id'] ?? 0);

    if ($ticket_id <= 0) {
        returnResponse('error', 'Parameter hapus transaksi tidak valid.');
    }

    // Ambil data transaksi sebelum dihapus
    $stmtDel = $conn->prepare("SELECT * FROM tickets WHERE id = ?");
    $stmtDel->bind_param("i", $ticket_id);
    $stmtDel->execute();
    $ticket = $stmtDel->get_result()->fetch_assoc();

    if (!$ticket) {
        returnResponse('error', 'Data tiket tidak ditemukan.');
    }

    // Kembalikan stok jika tiket yang dihapus statusnya bukan batal
    if ($ticket['status'] !== 'batal') {
        $id_variant = (int)$ticket['id_ticket_variant'];
        $id_event = (int)$ticket['id_event'];

        if ($id_variant > 0) {
            $conn->query("UPDATE event_ticket_variants SET sisa_stok = sisa_stok + 1 WHERE id = $id_variant");
        }
        if ($id_event > 0) {
            $conn->query("UPDATE events SET stok = stok + 1 WHERE id = $id_event");
        }
    }

    // Delete Record
    $conn->query("DELETE FROM tickets WHERE id = $ticket_id");

    logActivity($conn, $_SESSION['user_id'], 'Hapus Transaksi', "Admin menghapus transaksi Order ID #" . $ticket['order_id']);
    returnResponse('success', "Transaksi Order ID #" . $ticket['order_id'] . " berhasil dihapus dan stok dikembalikan.");
}
?>
