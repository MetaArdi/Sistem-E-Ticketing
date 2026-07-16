<?php
session_start();
require_once '../config/koneksi.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    // Perlindungan Brute Force
    if (isset($_SESSION['login_attempts']) && $_SESSION['login_attempts'] >= 5) {
        if (time() - $_SESSION['last_login_attempt'] < 300) { // Blokir 5 menit
            $_SESSION['error'] = "Terlalu banyak percobaan login gagal. Harap tunggu 5 menit sebelum mencoba lagi.";
            header("Location: login.php");
            exit;
        } else {
            // Reset setelah 5 menit berlalu
            $_SESSION['login_attempts'] = 0;
        }
    }

    $stmt = $conn->prepare("SELECT id, email, password, role, nama_lengkap, foto_profil, status_approval FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            
            // Cek Maintenance Mode
            if (isset($is_maintenance) && $is_maintenance && $user['role'] != 'admin') {
                $_SESSION['error'] = "Sistem sedang dalam masa pemeliharaan (Maintenance). Hanya Admin yang diizinkan untuk login saat ini.";
                header("Location: login.php");
                exit;
            }
            
            if ($user['role'] == 'validator' && $user['status_approval'] != 'approved') {
                $_SESSION['error'] = "Akun validator Anda belum aktif. Menunggu persetujuan Admin atau telah ditolak.";
                header("Location: login.php");
                exit;
            }

            session_regenerate_id(true); // Mencegah Session Fixation
            $_SESSION['login_attempts'] = 0; // Reset counter
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['nama_lengkap'] = $user['nama_lengkap'];
            $_SESSION['foto_profil'] = $user['foto_profil'];

            logActivity($conn, $user['id'], 'Login', "User " . $user['email'] . " berhasil login.");

            if ($user['role'] == 'admin') {
                header("Location: ../admin/index.php");
            } elseif ($user['role'] == 'panitia') {
                header("Location: ../panitia/index.php");
            } else {
                header("Location: ../validator/index.php");
            }
            exit;
        } else {
            $_SESSION['login_attempts'] = ($_SESSION['login_attempts'] ?? 0) + 1;
            $_SESSION['last_login_attempt'] = time();
            $_SESSION['error'] = "Password salah!";
            header("Location: login.php");
            exit;
        }
    } else {
        $_SESSION['login_attempts'] = ($_SESSION['login_attempts'] ?? 0) + 1;
        $_SESSION['last_login_attempt'] = time();
        $_SESSION['error'] = "Email tidak ditemukan!";
        header("Location: login.php");
        exit;
    }
}
?>
