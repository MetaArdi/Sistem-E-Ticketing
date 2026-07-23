<?php
// Auto-redirect jika pengguna/aplikasi mengakses actions/checkout.php secara tidak sengaja
session_start();
require_once __DIR__ . '/../config/koneksi.php';

$query = !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '';
header("Location: " . BASE_URL . "checkout.php" . $query);
exit;
