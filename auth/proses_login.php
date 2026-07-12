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

    $stmt = $conn->prepare("SELECT id, email, password, role, nama_lengkap FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            session_regenerate_id(true); // Mencegah Session Fixation
            $_SESSION['login_attempts'] = 0; // Reset counter
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['nama_lengkap'] = $user['nama_lengkap'];

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
