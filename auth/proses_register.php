<?php
session_start();
require_once '../config/koneksi.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nama_lengkap = trim($_POST['nama_lengkap']);
    $email = trim($_POST['email']);
    $no_hp = trim($_POST['no_hp']);
    $alamat = trim($_POST['alamat']);
    $password = $_POST['password'];

    // Cek apakah email sudah terdaftar
    $stmt_check = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt_check->bind_param("s", $email);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();

    if ($result_check->num_rows > 0) {
        $_SESSION['error'] = "Email sudah terdaftar. Silakan gunakan email lain atau login.";
        header("Location: register.php");
        exit;
    }

    // Hash password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $role = 'panitia'; // Default role untuk registrasi publik

    // Insert user baru
    $stmt = $conn->prepare("INSERT INTO users (email, password, role, nama_lengkap, no_hp, alamat) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssss", $email, $hashed_password, $role, $nama_lengkap, $no_hp, $alamat);
    
    if ($stmt->execute()) {
        $new_user_id = $conn->insert_id;
        logActivity($conn, $new_user_id, 'Register', "User baru mendaftar dengan email: " . $email);
        
        $_SESSION['success'] = "Pendaftaran berhasil! Silakan login.";
        header("Location: login.php");
        exit;
    } else {
        $_SESSION['error'] = "Terjadi kesalahan saat mendaftar. Silakan coba lagi.";
        header("Location: register.php");
        exit;
    }
} else {
    header("Location: register.php");
    exit;
}
?>
