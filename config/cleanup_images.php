<?php
// Modul Cleanup & Auto Migration untuk Asset Gambar & War Ticket
if (!isset($conn) || !$conn) {
    require_once __DIR__ . '/koneksi.php';
}

// 1. Structural DB Setup: Image Trash Table & War Ticket Columns
$conn->query("CREATE TABLE IF NOT EXISTS image_trash (
    id INT AUTO_INCREMENT PRIMARY KEY,
    filename VARCHAR(255) NOT NULL,
    trashed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

$check_war = $conn->query("SHOW COLUMNS FROM events LIKE 'is_war_ticket'");
if ($check_war && $check_war->num_rows == 0) {
    $conn->query("ALTER TABLE events ADD COLUMN is_war_ticket TINYINT(1) DEFAULT 0, ADD COLUMN war_start_time DATETIME DEFAULT NULL");
}

/**
 * Catat gambar lama ke tabel trash agar dibersihkan setelah 24 jam
 */
if (!function_exists('moveToImageTrash')) {
    function moveToImageTrash($conn, $filename) {
        if (empty($filename)) return;
        $stmt = $conn->prepare("INSERT INTO image_trash (filename, trashed_at) VALUES (?, NOW())");
        if ($stmt) {
            $stmt->bind_param("s", $filename);
            $stmt->execute();
            $stmt->close();
        }
    }
}

/**
 * Bersihkan gambar di tabel trash yang usianya sudah lebih dari 24 jam
 */
if (!function_exists('cleanupOldImageTrash')) {
    function cleanupOldImageTrash($conn) {
        $result = $conn->query("SELECT id, filename FROM image_trash WHERE trashed_at <= DATE_SUB(NOW(), INTERVAL 24 HOUR)");
        if ($result && $result->num_rows > 0) {
            $base_dir = __DIR__ . '/../assets/images/events/';
            while ($row = $result->fetch_assoc()) {
                $id = (int)$row['id'];
                $fname = $row['filename'];
                
                // Pastikan file tidak dipakai di event manapun secara tak sengaja
                $chk_stmt = $conn->prepare("SELECT id FROM events WHERE banner_image = ? OR banner_image2 = ? OR banner_image3 = ? OR banner_image4 = ? OR tiket_header = ?");
                $chk_stmt->bind_param("sssss", $fname, $fname, $fname, $fname, $fname);
                $chk_stmt->execute();
                $is_used = $chk_stmt->get_result()->num_rows > 0;
                $chk_stmt->close();

                if (!$is_used) {
                    $filepath = $base_dir . $fname;
                    if (file_exists($filepath) && is_file($filepath)) {
                        @unlink($filepath);
                    }
                }
                $conn->query("DELETE FROM image_trash WHERE id = $id");
            }
        }
    }
}

// Jalankan otomatis cleanup ringan saat file ini di-include
cleanupOldImageTrash($conn);
?>
