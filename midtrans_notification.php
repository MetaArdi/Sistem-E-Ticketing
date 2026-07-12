<?php
require_once 'config/koneksi.php';
require_once 'vendor/autoload.php';

\Midtrans\Config::$serverKey = MIDTRANS_SERVER_KEY;
\Midtrans\Config::$isProduction = MIDTRANS_IS_PRODUCTION;

try {
    $notif = new \Midtrans\Notification();
    
    $transaction = $notif->transaction_status;
    $type = $notif->payment_type;
    $order_id = $notif->order_id;
    $fraud = $notif->fraud_status;

    if ($transaction == 'capture') {
        if ($type == 'credit_card'){
            if($fraud == 'challenge'){
                // TODO set payment status in merchant's database to 'Challenge by FDS'
                // $transaction status = 'challenge';
            } else {
                $conn->query("UPDATE tickets SET status = 'lunas' WHERE order_id = '$order_id'");
            }
        }
    } else if ($transaction == 'settlement'){
        $conn->query("UPDATE tickets SET status = 'lunas' WHERE order_id = '$order_id'");
    } else if($transaction == 'pending'){
        // $conn->query("UPDATE tickets SET status = 'pending' WHERE order_id = '$order_id'");
    } else if ($transaction == 'deny') {
        // $conn->query("UPDATE tickets SET status = 'failed' WHERE order_id = '$order_id'");
    } else if ($transaction == 'expire') {
        // $conn->query("UPDATE tickets SET status = 'failed' WHERE order_id = '$order_id'");
    } else if ($transaction == 'cancel') {
        // $conn->query("UPDATE tickets SET status = 'failed' WHERE order_id = '$order_id'");
    }
} catch (\Exception $e) {
    exit($e->getMessage());
}
?>
