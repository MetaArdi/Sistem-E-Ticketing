<?php
// Script Diagnosa Koneksi Database HaloTiket
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<div style='font-family:sans-serif; max-w:600px; margin:20px auto; padding:20px; border:1px solid #ccc; border-radius:10px;'>";
echo "<h2>🔍 Diagnosa Otomatis Koneksi Database</h2>";

$host = "localhost";
$user = "rjrooeerg_tikethalo";
$pass = "HaloTiket2026";
$db   = "rjrooeerg_halotiket_db";

echo "<p><b>Host:</b> $host<br>";
echo "<b>User:</b> $user<br>";
echo "<b>Database:</b> $db</p><hr>";

// Tes 1: Otentikasi User & Password
$conn_user = @new mysqli($host, $user, $pass);

if ($conn_user->connect_error) {
    echo "<h3 style='color:red;'>❌ PENGUJIAN 1 GAGAL: Username / Password Salah!</h3>";
    echo "<p><b>Pesan Server:</b> " . $conn_user->connect_error . "</p>";
    echo "<p style='background:#fff3cd; padding:10px; border-radius:5px;'><b>SOLUSI:</b> Username <code>$user</code> atau Password <code>$pass</code> belum terdaftar di cPanel.<br>👉 Buka cPanel > <b>MySQL Databases</b> > cari <b>$user</b> di tabel Current Users > klik <b>Change Password</b> lalu ganti passwordnya menjadi <code>$pass</code>.</p>";
} else {
    echo "<h3 style='color:green;'>✅ PENGUJIAN 1 BERHASIL: Username & Password Cocok!</h3>";
    
    // Tes 2: Hak Akses ke Database
    if (@$conn_user->select_db($db)) {
        echo "<h3 style='color:green;'>🎉 PENGUJIAN 2 BERHASIL: Database Ditemukan & Izin Akses Aktif!</h3>";
        echo "<p style='background:#d4edda; color:#155724; padding:10px; border-radius:5px;'><b>SELAMAT!</b> Koneksi database sudah 100% normal dan bisa digunakan.</p>";
    } else {
        echo "<h3 style='color:red;'>❌ PENGUJIAN 2 GAGAL: User Belum Memiliki Hak Akses ke Database!</h3>";
        echo "<p><b>Pesan Server:</b> " . $conn_user->error . "</p>";
        echo "<p style='background:#f8d7da; color:#721c24; padding:10px; border-radius:5px;'><b>SOLUSI:</b> User <code>$user</code> sudah ada, tapi BELUM diizinkan membuka database <code>$db</code>.<br>👉 Buka cPanel > <b>MySQL Databases</b> > scroll ke <b>Add User To Database</b> > pilih User: <code>$user</code> & Database: <code>$db</code> > klik <b>Add</b> > centang <b>ALL PRIVILEGES</b> > klik <b>Make Changes</b>.</p>";
    }
}

echo "</div>";
?>
