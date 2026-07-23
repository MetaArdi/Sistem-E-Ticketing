<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'panitia') {
    header("Location: ../../auth/login.php");
    exit;
}
require_once '../../config/koneksi.php';

// Ensure withdrawals table & user bank columns exist
try {
    $conn->query("
        CREATE TABLE IF NOT EXISTS withdrawals (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            amount DECIMAL(15,2) NOT NULL,
            bank_name VARCHAR(100) NOT NULL,
            account_number VARCHAR(50) NOT NULL,
            account_name VARCHAR(100) NOT NULL,
            status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
            admin_note TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $cols = $conn->query("SHOW COLUMNS FROM users LIKE 'nama_bank'");
    if ($cols && $cols->num_rows == 0) {
        @$conn->query("ALTER TABLE users ADD COLUMN nama_bank VARCHAR(100) NULL");
        @$conn->query("ALTER TABLE users ADD COLUMN no_rekening VARCHAR(50) NULL");
        @$conn->query("ALTER TABLE users ADD COLUMN nama_rekening VARCHAR(100) NULL");
    }
} catch (\Throwable $e) {}

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
        if ($stmt) {
            $stmt->bind_param("sssi", $nama_bank, $no_rekening, $nama_rekening, $user_id);
            if ($stmt->execute()) {
                if (function_exists('logActivity')) {
                    logActivity($conn, $user_id, 'Update Rekening Bank', 'Vendor memperbarui informasi rekening bank penampung.');
                }
                $_SESSION['success_withdraw'] = "Informasi rekening bank berhasil disimpan.";
            } else {
                $_SESSION['error_withdraw'] = "Gagal menyimpan data rekening bank.";
            }
            $stmt->close();
        }
    }
    header("Location: ../withdraw.php");
    exit;
}

if ($action === 'request_withdraw') {
    $amount = (float)($_POST['amount'] ?? 0);

    // Ambil data rekening bank vendor saat ini
    $user_bank = ['nama_bank' => '', 'no_rekening' => '', 'nama_rekening' => ''];
    $stmt = $conn->prepare("SELECT nama_bank, no_rekening, nama_rekening FROM users WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && $row = $res->fetch_assoc()) {
            $user_bank = $row;
        }
        $stmt->close();
    }

    if (empty($user_bank['nama_bank']) || empty($user_bank['no_rekening']) || empty($user_bank['nama_rekening'])) {
        $_SESSION['error_withdraw'] = "Silakan lengkapi informasi rekening bank Anda terlebih dahulu sebelum mengajukan penarikan dana.";
        header("Location: ../withdraw.php");
        exit;
    }

    if ($amount < 10000) {
        $_SESSION['error_withdraw'] = "Minimal penarikan dana adalah Rp 10.000.";
        header("Location: ../withdraw.php");
        exit;
    }

    // Hitung total pendapatan vendor (harga tiket lunas/scanned)
    $total_earnings = 0;
    $stmt_rev = $conn->prepare("
        SELECT SUM(COALESCE(v.harga, e.harga)) as total_earnings
        FROM tickets t
        JOIN events e ON t.id_event = e.id
        LEFT JOIN event_ticket_variants v ON t.id_ticket_variant = v.id
        WHERE e.id_panitia = ? AND t.status IN ('lunas', 'scanned')
    ");
    if ($stmt_rev) {
        $stmt_rev->bind_param("i", $user_id);
        $stmt_rev->execute();
        $res = $stmt_rev->get_result();
        if ($res) {
            $total_earnings = (float)($res->fetch_assoc()['total_earnings'] ?? 0);
        }
        $stmt_rev->close();
    }

    // Hitung total penarikan dana yang pending/approved
    $total_withdrawn = 0;
    $stmt_wd = $conn->prepare("
        SELECT SUM(amount) as total_withdrawn
        FROM withdrawals
        WHERE user_id = ? AND status IN ('pending', 'approved')
    ");
    if ($stmt_wd) {
        $stmt_wd->bind_param("i", $user_id);
        $stmt_wd->execute();
        $res = $stmt_wd->get_result();
        if ($res) {
            $total_withdrawn = (float)($res->fetch_assoc()['total_withdrawn'] ?? 0);
        }
        $stmt_wd->close();
    }

    $available_balance = $total_earnings - $total_withdrawn;

    if ($amount > $available_balance) {
        $_SESSION['error_withdraw'] = "Saldo tersedia Anda tidak mencukupi untuk penarikan Rp " . number_format($amount, 0, ',', '.') . ". Saldo maksimal: Rp " . number_format($available_balance, 0, ',', '.');
        header("Location: ../withdraw.php");
        exit;
    }

    // Simpan pengajuan penarikan dana
    $stmt_ins = $conn->prepare("INSERT INTO withdrawals (user_id, amount, bank_name, account_number, account_name, status) VALUES (?, ?, ?, ?, ?, 'pending')");
    if ($stmt_ins) {
        $stmt_ins->bind_param("idsss", $user_id, $amount, $user_bank['nama_bank'], $user_bank['no_rekening'], $user_bank['nama_rekening']);
        
        if ($stmt_ins->execute()) {
            if (function_exists('logActivity')) {
                logActivity($conn, $user_id, 'Pengajuan Tarik Tunai', "Vendor mengajukan penarikan dana sebesar Rp " . number_format($amount, 0, ',', '.'));
            }
            $_SESSION['success_withdraw'] = "Pengajuan penarikan dana sebesar Rp " . number_format($amount, 0, ',', '.') . " berhasil dikirim. Menunggu verifikasi admin.";
        } else {
            $_SESSION['error_withdraw'] = "Gagal memproses pengajuan penarikan dana.";
        }
        $stmt_ins->close();
    }

    header("Location: ../withdraw.php");
    exit;
}

header("Location: ../profile.php");
exit;
?>
