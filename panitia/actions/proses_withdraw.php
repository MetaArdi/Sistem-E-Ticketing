<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'panitia') {
    header("Location: ../../auth/login.php");
    exit;
}
require_once '../../config/koneksi.php';

$user_id = (int)$_SESSION['user_id'];
$action = $_POST['action'] ?? '';

if ($action === 'update_bank') {
    $nama_bank = trim($_POST['nama_bank'] ?? '');
    $no_rekening = trim($_POST['no_rekening'] ?? '');
    $nama_rekening = trim($_POST['nama_rekening'] ?? '');

    if (empty($nama_bank) || empty($no_rekening) || empty($nama_rekening)) {
        $_SESSION['error_withdraw'] = "Semua bidang informasi rekening bank wajib diisi.";
    } else {
        $stmt = $conn->prepare("UPDATE users SET nama_bank = ?, no_rekening = ?, nama_rekening = ? WHERE id = ?");
        $stmt->bind_param("sssi", $nama_bank, $no_rekening, $nama_rekening, $user_id);
        if ($stmt->execute()) {
            logActivity($conn, $user_id, 'Update Rekening Bank', 'Vendor memperbarui informasi rekening bank penampung.');
            $_SESSION['success_withdraw'] = "Informasi rekening bank berhasil disimpan.";
        } else {
            $_SESSION['error_withdraw'] = "Gagal menyimpan data rekening bank.";
        }
        $stmt->close();
    }
    header("Location: ../profile.php#withdrawal");
    exit;
}

if ($action === 'request_withdraw') {
    $amount = (float)($_POST['amount'] ?? 0);

    // Ambil data rekening bank vendor saat ini
    $stmt = $conn->prepare("SELECT nama_bank, no_rekening, nama_rekening FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user_bank = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (empty($user_bank['nama_bank']) || empty($user_bank['no_rekening']) || empty($user_bank['nama_rekening'])) {
        $_SESSION['error_withdraw'] = "Silakan lengkapi informasi rekening bank Anda terlebih dahulu sebelum mengajukan penarikan dana.";
        header("Location: ../profile.php#withdrawal");
        exit;
    }

    if ($amount < 10000) {
        $_SESSION['error_withdraw'] = "Minimal penarikan dana adalah Rp 10.000.";
        header("Location: ../profile.php#withdrawal");
        exit;
    }

    // Hitung total pendapatan vendor (harga tiket lunas/scanned)
    $stmt_rev = $conn->prepare("
        SELECT SUM(COALESCE(v.harga, e.harga)) as total_earnings
        FROM tickets t
        JOIN events e ON t.id_event = e.id
        LEFT JOIN event_ticket_variants v ON t.id_ticket_variant = v.id
        WHERE e.id_panitia = ? AND t.status IN ('lunas', 'scanned')
    ");
    $stmt_rev->bind_param("i", $user_id);
    $stmt_rev->execute();
    $total_earnings = (float)($stmt_rev->get_result()->fetch_assoc()['total_earnings'] ?? 0);
    $stmt_rev->close();

    // Hitung total penarikan dana yang pending/approved
    $stmt_wd = $conn->prepare("
        SELECT SUM(amount) as total_withdrawn
        FROM withdrawals
        WHERE user_id = ? AND status IN ('pending', 'approved')
    ");
    $stmt_wd->bind_param("i", $user_id);
    $stmt_wd->execute();
    $total_withdrawn = (float)($stmt_wd->get_result()->fetch_assoc()['total_withdrawn'] ?? 0);
    $stmt_wd->close();

    $available_balance = $total_earnings - $total_withdrawn;

    if ($amount > $available_balance) {
        $_SESSION['error_withdraw'] = "Saldo tersedia Anda tidak mencukupi untuk penarikan Rp " . number_format($amount, 0, ',', '.') . ". Saldo maksimal: Rp " . number_format($available_balance, 0, ',', '.');
        header("Location: ../profile.php#withdrawal");
        exit;
    }

    // Simpan pengajuan penarikan dana
    $stmt_ins = $conn->prepare("INSERT INTO withdrawals (user_id, amount, bank_name, account_number, account_name, status) VALUES (?, ?, ?, ?, ?, 'pending')");
    $stmt_ins->bind_param("idsss", $user_id, $amount, $user_bank['nama_bank'], $user_bank['no_rekening'], $user_bank['nama_rekening']);
    
    if ($stmt_ins->execute()) {
        logActivity($conn, $user_id, 'Pengajuan Tarik Tunai', "Vendor mengajukan penarikan dana sebesar Rp " . number_format($amount, 0, ',', '.'));
        $_SESSION['success_withdraw'] = "Pengajuan penarikan dana sebesar Rp " . number_format($amount, 0, ',', '.') . " berhasil dikirim. Menunggu verifikasi admin.";
    } else {
        $_SESSION['error_withdraw'] = "Gagal memproses pengajuan penarikan dana.";
    }
    $stmt_ins->close();

    header("Location: ../profile.php#withdrawal");
    exit;
}

header("Location: ../profile.php");
exit;
?>
