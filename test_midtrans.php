<?php
// Script Diagnosa Midtrans API & Keys
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/config/koneksi.php';

echo "<div style='font-family:sans-serif; max-width:700px; margin:20px auto; padding:20px; border:1px solid #ccc; border-radius:10px;'>";
echo "<h2>🔍 Diagnosa Koneksi Midtrans API</h2>";

$serverKey = defined('MIDTRANS_SERVER_KEY') ? trim(MIDTRANS_SERVER_KEY) : '';
$clientKey = defined('MIDTRANS_CLIENT_KEY') ? trim(MIDTRANS_CLIENT_KEY) : '';
$merchantId = defined('MIDTRANS_MERCHANT_ID') ? trim(MIDTRANS_MERCHANT_ID) : '';
$isProduction = defined('MIDTRANS_IS_PRODUCTION') ? MIDTRANS_IS_PRODUCTION : false;

echo "<p><b>Merchant ID:</b> <code>" . htmlspecialchars($merchantId) . "</code><br>";
echo "<b>Server Key:</b> <code>" . htmlspecialchars($serverKey) . "</code><br>";
echo "<b>Client Key:</b> <code>" . htmlspecialchars($clientKey) . "</code><br>";
echo "<b>Mode:</b> " . ($isProduction ? "<b style='color:red;'>PRODUCTION</b>" : "<b style='color:green;'>SANDBOX (Testing)</b>") . "</p>";

if (empty($serverKey)) {
    echo "<h3 style='color:red;'>❌ GAGAL: Server Key belum diisi di database/settings.php!</h3></div>";
    exit;
}

// Tentukan Endpoint API Midtrans
$url = MIDTRANS_IS_PRODUCTION 
    ? "https://app.midtrans.com/snap/v1/transactions" 
    : "https://app.sandbox.midtrans.com/snap/v1/transactions";

echo "<p><b>Testing Endpoint URL:</b> <code>$url</code></p><hr>";

// Buat tes payload transaksi
$order_id = 'TEST-' . time();
$payload = [
    'transaction_details' => [
        'order_id' => $order_id,
        'gross_amount' => 10000
    ],
    'credit_card' => [
        'secure' => true
    ]
];

$auth_header = "Basic " . base64_encode($serverKey . ":");

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json',
    'Authorization: ' . $auth_header
]);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

if ($curl_error) {
    echo "<h3 style='color:red;'>❌ Error cURL: $curl_error</h3>";
} else {
    echo "<h3>HTTP Status Code: <b>$http_code</b></h3>";
    echo "<b>Response dari Midtrans:</b><pre style='background:#f4f4f4; padding:10px; border-radius:5px; overflow-x:auto;'>" . htmlspecialchars($response) . "</pre>";
    
    if ($http_code == 201 || $http_code == 200) {
        echo "<h3 style='color:green;'>🎉 KUNCI SERVER KEY 100% VALID DAN BERHASIL CONNECT KE MIDTRANS!</h3>";
    } elseif ($http_code == 401) {
        echo "<h3 style='color:red;'>❌ ERROR 401 UNAUTHORIZED: Server Key ditolak oleh Midtrans!</h3>";
        echo "<p style='background:#fff3cd; padding:10px; border-radius:5px;'><b>SOLUSI:</b><br>";
        echo "1. Login ke <a href='https://dashboard.sandbox.midtrans.com' target='_blank'>Midtrans Sandbox Dashboard</a>.<br>";
        echo "2. Masuk ke menu <b>SETTINGS</b> > <b>Access Keys</b>.<br>";
        echo "3. Salin <b>Server Key</b> (yang diawali <code>SB-Mid-server-</code>) dan pastikan menyalin seluruh karakter tanpa ada yang terpotong.<br>";
        echo "4. Masukkan ke Admin Panel HaloTiket (<code>admin/settings.php</code>).</p>";
    }
}

echo "</div>";
?>
