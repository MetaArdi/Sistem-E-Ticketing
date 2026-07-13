<?php
session_start();
require_once '../config/koneksi.php';
require_once '../config/google_oauth.php';

if (isset($_GET['code'])) {
    // Exchange the authorization code for an access token
    $token = $google_client->fetchAccessTokenWithAuthCode($_GET['code']);
    
    if (isset($token['error'])) {
        $_SESSION['error'] = 'Terjadi kesalahan saat otentikasi Google: ' . $token['error_description'];
        header('Location: login.php');
        exit();
    }
    
    // Set the access token used for requests
    $google_client->setAccessToken($token['access_token']);
    
    // Get profile info
    $google_oauth = new Google\Service\Oauth2($google_client);
    $google_account_info = $google_oauth->userinfo->get();
    
    $google_id = $google_account_info->id;
    $email = $google_account_info->email;
    $name = $google_account_info->name;
    $picture = $google_account_info->picture;
    
    // Check if user exists in database by google_id or email
    $stmt = $conn->prepare("SELECT id, email, role, nama_lengkap, foto_profil, google_id FROM users WHERE google_id = ? OR email = ?");
    $stmt->bind_param("ss", $google_id, $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        // User exists, log them in
        $user = $result->fetch_assoc();
        
        // Update google_id if it's empty (linking account)
        if (empty($user['google_id'])) {
            $update_stmt = $conn->prepare("UPDATE users SET google_id = ? WHERE id = ?");
            $update_stmt->bind_param("si", $google_id, $user['id']);
            $update_stmt->execute();
        }
        
        // Optional: Update profile picture if they don't have one
        if (empty($user['foto_profil']) && !empty($picture)) {
            // For simplicity, we'll just store the Google URL in foto_profil.
            // In a real app, you might want to download the image to assets/images/profil/
            // Note: Our display logic might need a tweak to handle full URLs vs local filenames.
            // To be safe, we won't overwrite a local avatar with a Google URL unless we adapt the display logic.
        }
        
        // Create session
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['nama_lengkap'] = $user['nama_lengkap'];
        $_SESSION['foto_profil'] = $user['foto_profil'];
        
        logActivity($conn, $user['id'], 'Login', "User " . $user['email'] . " berhasil login via Google.");
        
        // Redirect based on role
        if ($user['role'] == 'admin') {
            header("Location: ../admin/index.php");
        } elseif ($user['role'] == 'panitia') {
            header("Location: ../panitia/index.php");
        } else {
            header("Location: ../validator/index.php");
        }
        exit();
        
    } else {
        // User doesn't exist, register them as 'panitia' by default
        $default_role = 'panitia';
        $random_password = password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT);
        
        $insert_stmt = $conn->prepare("INSERT INTO users (email, password, role, nama_lengkap, google_id) VALUES (?, ?, ?, ?, ?)");
        $insert_stmt->bind_param("sssss", $email, $random_password, $default_role, $name, $google_id);
        
        if ($insert_stmt->execute()) {
            $new_user_id = $conn->insert_id;
            
            // Create session
            session_regenerate_id(true);
            $_SESSION['user_id'] = $new_user_id;
            $_SESSION['email'] = $email;
            $_SESSION['role'] = $default_role;
            $_SESSION['nama_lengkap'] = $name;
            $_SESSION['foto_profil'] = null; // Can enhance to download Google picture later
            
            logActivity($conn, $new_user_id, 'Register', "User " . $email . " mendaftar via Google.");
            
            // Redirect to panitia dashboard
            header("Location: ../panitia/index.php");
            exit();
        } else {
            $_SESSION['error'] = 'Gagal mendaftarkan akun. Silakan coba lagi.';
            header('Location: login.php');
            exit();
        }
    }
} else {
    // If no code is present, redirect back to login
    header('Location: login.php');
    exit();
}
?>
