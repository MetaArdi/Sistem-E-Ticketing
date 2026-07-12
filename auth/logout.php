<?php
session_start();
require_once '../config/koneksi.php';

if (isset($_SESSION['user_id'])) {
    logActivity($conn, $_SESSION['user_id'], 'Logout', "User " . $_SESSION['email'] . " berhasil logout.");
}

session_destroy();
header("Location: login.php");
exit;
?>
