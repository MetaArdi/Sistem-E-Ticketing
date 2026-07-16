<?php
session_start();
require_once '../config/koneksi.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action == 'send_otp' || $action == 'resend_otp') {
        $email = '';
        if ($action == 'send_otp') {
            $email = trim($_POST['email']);
        } else {
            $email = $_SESSION['reset_email'] ?? '';
        }

        if (empty($email)) {
            $_SESSION['error'] = "Email tidak valid.";
            header("Location: forgot_password.php");
            exit;
        }

        // Check if email exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            $user_id = $user['id'];

            // Generate 6 digit OTP
            $otp = sprintf("%06d", mt_rand(100000, 999999));
            // Set expiry to 5 minutes from now
            $expiry = date('Y-m-d H:i:s', strtotime('+5 minutes'));

            // Update database
            $update_stmt = $conn->prepare("UPDATE users SET reset_otp = ?, reset_otp_expiry = ? WHERE id = ?");
            $update_stmt->bind_param("ssi", $otp, $expiry, $user_id);
            $update_stmt->execute();

            // Simpan state di session
            $_SESSION['reset_email'] = $email;
            $_SESSION['reset_user_id'] = $user_id;
            
            // SIMULASI PENGIRIMAN EMAIL/WA (HANYA UNTUK TESTING)
            // Di sistem nyata, jalankan fungsi mail() atau curl ke API WA disini.
            $_SESSION['mock_otp'] = $otp;

            $_SESSION['success'] = "Kode OTP telah dikirim ke email Anda.";
        } else {
            // Untuk alasan keamanan, jangan beri tahu jika email tidak terdaftar. 
            // Tetap berikan pesan sukses yang ambigu agar tidak terjadi email enumeration.
            $_SESSION['error'] = "Email tidak ditemukan di sistem kami.";
            // Note: In strict systems, we say "Jika email terdaftar, OTP telah dikirim"
            // But for ease of use here, we just say not found.
            if ($action == 'resend_otp') {
                unset($_SESSION['reset_email']);
            }
        }
        
        header("Location: forgot_password.php");
        exit;
    } 
    
    elseif ($action == 'verify_otp') {
        if (!isset($_SESSION['reset_user_id'])) {
            header("Location: forgot_password.php");
            exit;
        }

        $user_id = $_SESSION['reset_user_id'];
        $input_otp = trim($_POST['otp']);

        $stmt = $conn->prepare("SELECT reset_otp, reset_otp_expiry FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            $now = date('Y-m-d H:i:s');
            
            if ($user['reset_otp'] === $input_otp) {
                if ($user['reset_otp_expiry'] >= $now) {
                    // OTP Valid
                    $_SESSION['reset_otp_verified'] = true;
                    $_SESSION['success'] = "OTP berhasil diverifikasi. Silakan buat password baru.";
                } else {
                    $_SESSION['error'] = "Kode OTP sudah kedaluwarsa. Silakan minta ulang.";
                }
            } else {
                $_SESSION['error'] = "Kode OTP tidak valid.";
            }
        } else {
            $_SESSION['error'] = "Terjadi kesalahan sistem.";
        }
        
        header("Location: forgot_password.php");
        exit;
    }
    
    elseif ($action == 'reset_password') {
        if (!isset($_SESSION['reset_otp_verified']) || $_SESSION['reset_otp_verified'] !== true) {
            header("Location: forgot_password.php");
            exit;
        }

        $user_id = $_SESSION['reset_user_id'];
        $password = $_POST['password'];
        $password_confirm = $_POST['password_confirm'];

        if (strlen($password) < 6) {
            $_SESSION['error'] = "Password harus minimal 6 karakter.";
            header("Location: forgot_password.php");
            exit;
        }

        if ($password !== $password_confirm) {
            $_SESSION['error'] = "Konfirmasi password tidak cocok.";
            header("Location: forgot_password.php");
            exit;
        }

        // Hash new password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        // Update password and clear OTP
        $stmt = $conn->prepare("UPDATE users SET password = ?, reset_otp = NULL, reset_otp_expiry = NULL WHERE id = ?");
        $stmt->bind_param("si", $hashed_password, $user_id);
        
        if ($stmt->execute()) {
            // Bersihkan semua session reset
            unset($_SESSION['reset_email']);
            unset($_SESSION['reset_user_id']);
            unset($_SESSION['reset_otp_verified']);
            
            $_SESSION['success'] = "Password Anda berhasil diperbarui. Silakan login dengan password baru.";
            header("Location: login.php");
        } else {
            $_SESSION['error'] = "Gagal memperbarui password. Coba lagi.";
            header("Location: forgot_password.php");
        }
        exit;
    }
    
    elseif ($action == 'cancel_reset') {
        unset($_SESSION['reset_email']);
        unset($_SESSION['reset_user_id']);
        unset($_SESSION['reset_otp_verified']);
        unset($_SESSION['mock_otp']);
        header("Location: login.php");
        exit;
    }
}

header("Location: login.php");
exit;
