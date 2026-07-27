<?php
if (!function_exists('sendFonnteWA')) {
    /**
     * Kirim Pesan WhatsApp via Fonnte API
     * @param string $target Nomor HP (contoh: 08123456789) atau Group ID (contoh: 120363412788674882@g.us)
     * @param string $message Isi Pesan Text
     * @return array Respon dari API Fonnte
     */
    function sendFonnteWA($target, $message) {
        global $global_settings;

        $token = !empty($global_settings['fonnte_token']) ? trim($global_settings['fonnte_token']) : 'ep63gV3wUjrZ1RB4NXnW';
        if (empty($token) || empty($target) || empty($message)) {
            return ['status' => false, 'reason' => 'Token Fonnte, target, atau pesan kosong'];
        }

        // Support multiple comma-separated targets (contoh: group1@g.us, group2@g.us)
        $targets = array_filter(array_map('trim', explode(',', $target)));
        if (empty($targets)) {
            return ['status' => false, 'reason' => 'Target kosong'];
        }

        $responses = [];
        $overall_status = true;

        foreach ($targets as $singleTarget) {
            if (empty($singleTarget)) continue;

            // Format nomor jika nomor HP pribadi (bukan Group ID)
            if (strpos($singleTarget, '@g.us') === false) {
                $singleTarget = preg_replace('/[^0-9]/', '', $singleTarget);
                if (str_starts_with($singleTarget, '0')) {
                    $singleTarget = '62' . substr($singleTarget, 1);
                } elseif (str_starts_with($singleTarget, '8')) {
                    $singleTarget = '62' . $singleTarget;
                }
            }

            $curl = curl_init();
            curl_setopt_array($curl, array(
                CURLOPT_URL => 'https://api.fonnte.com/send',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 15,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => array(
                    'target' => $singleTarget,
                    'message' => $message,
                    'countryCode' => '62',
                ),
                CURLOPT_HTTPHEADER => array(
                    'Authorization: ' . $token
                ),
            ));

            $response = curl_exec($curl);
            $err = curl_error($curl);
            curl_close($curl);

            if ($err) {
                $overall_status = false;
                $responses[] = ['target' => $singleTarget, 'status' => false, 'reason' => $err];
            } else {
                $responses[] = ['target' => $singleTarget, 'status' => true, 'response' => json_decode($response, true) ?? $response];
            }
        }

        return [
            'status' => $overall_status,
            'details' => $responses
        ];
    }
}

if (!function_exists('notifyNewOrderWA')) {
    /**
     * Kirim notifikasi WA saat ada pesanan tiket baru
     */
    function notifyNewOrderWA($ticket_data, $event_title = '', $variant_name = '', $total_amount = 0) {
        global $global_settings;

        $enable_group = ($global_settings['fonnte_enable_group_notif'] ?? '1') !== '0';
        $enable_buyer = ($global_settings['fonnte_enable_buyer_notif'] ?? '1') !== '0';

        $order_id = $ticket_data['order_id'] ?? '';
        $nama = $ticket_data['nama_pembeli'] ?? '';
        $no_hp = $ticket_data['no_hp'] ?? '';
        $email = $ticket_data['email_pembeli'] ?? '';
        $metode = ($ticket_data['payment_method'] ?? 'midtrans') === 'manual' ? 'Transfer Bank / QRIS Manual' : 'Midtrans Gateway (Otomatis)';
        $total_formatted = 'Rp ' . number_format($total_amount, 0, ',', '.');
        $site_url = defined('BASE_URL') ? BASE_URL : 'http://localhost/';

        // Pesan untuk Group WA Admin
        if ($enable_group) {
            $group_id = !empty($global_settings['fonnte_wa_group']) ? trim($global_settings['fonnte_wa_group']) : '120363427898241334@g.us';
            if (!empty($group_id)) {
                $msgAdmin = "📢 *PESANAN TIKET BARU MASUK!*\n";
                $msgAdmin .= "----------------------------------------\n";
                $msgAdmin .= "📌 *Order ID:* #" . $order_id . "\n";
                $msgAdmin .= "🎫 *Event:* " . $event_title . "\n";
                $msgAdmin .= "🎟️ *Varian:* " . $variant_name . "\n";
                $msgAdmin .= "👤 *Pembeli:* " . $nama . "\n";
                $msgAdmin .= "📱 *No. WA:* " . $no_hp . "\n";
                $msgAdmin .= "📧 *Email:* " . $email . "\n";
                $msgAdmin .= "💰 *Total Tagihan:* " . $total_formatted . "\n";
                $msgAdmin .= "💳 *Metode:* " . $metode . "\n";
                $msgAdmin .= "----------------------------------------\n";
                $msgAdmin .= "⏰ *Waktu:* " . date('d/m/Y H:i') . " WIB\n";
                $msgAdmin .= "Silakan periksa dashboard admin untuk memantau transaksi.";
                
                sendFonnteWA($group_id, $msgAdmin);
            }
        }

        // Pesan untuk Pembeli
        if ($enable_buyer && !empty($no_hp)) {
            $linkBayar = $site_url . 'pembayaran.php?order_id=' . urlencode($order_id);
            $msgBuyer = "Halo *" . $nama . "*,\n\n";
            $msgBuyer .= "Terima kasih telah memesan tiket di *HaloTiket*!\n\n";
            $msgBuyer .= "📌 *Order ID:* #" . $order_id . "\n";
            $msgBuyer .= "🎫 *Event:* " . $event_title . " (" . $variant_name . ")\n";
            $msgBuyer .= "💰 *Total Bayar:* " . $total_formatted . "\n";
            $msgBuyer .= "💳 *Metode:* " . $metode . "\n\n";
            $msgBuyer .= "Silakan selesaikan pembayaran Anda melalui tautan berikut:\n";
            $msgBuyer .= "👉 " . $linkBayar . "\n\n";
            $msgBuyer .= "E-Ticket akan otomatis terbit setelah pembayaran dikonfirmasi. Terima kasih!";

            sendFonnteWA($no_hp, $msgBuyer);
        }
    }
}

if (!function_exists('notifyPaymentSuccessWA')) {
    /**
     * Kirim notifikasi WA saat pembayaran tiket LUNAS
     */
    function notifyPaymentSuccessWA($ticket_data, $event_title = '', $variant_name = '', $total_amount = 0) {
        global $global_settings;

        $enable_group = ($global_settings['fonnte_enable_group_notif'] ?? '1') !== '0';
        $enable_buyer = ($global_settings['fonnte_enable_buyer_notif'] ?? '1') !== '0';

        $order_id = $ticket_data['order_id'] ?? '';
        $nama = $ticket_data['nama_pembeli'] ?? '';
        $no_hp = $ticket_data['no_hp'] ?? '';
        $email = $ticket_data['email_pembeli'] ?? '';
        $token_qr = $ticket_data['token_qr'] ?? '';
        $total_formatted = $total_amount > 0 ? ('Rp ' . number_format($total_amount, 0, ',', '.')) : '-';
        $site_url = defined('BASE_URL') ? BASE_URL : 'http://localhost/';
        
        $linkPdf = !empty($token_qr) ? ($site_url . 'user/download_tiket.php?token=' . urlencode($token_qr)) : ($site_url . 'user/riwayat_pembelian.php?order_id=' . urlencode($order_id));

        // Pesan untuk Group WA Admin
        if ($enable_group) {
            $group_id = !empty($global_settings['fonnte_wa_group']) ? trim($global_settings['fonnte_wa_group']) : '120363427898241334@g.us';
            if (!empty($group_id)) {
                $msgAdmin = "✅ *PEMBAYARAN TIKET LUNAS!*\n";
                $msgAdmin .= "----------------------------------------\n";
                $msgAdmin .= "📌 *Order ID:* #" . $order_id . "\n";
                $msgAdmin .= "🎫 *Event:* " . $event_title . "\n";
                $msgAdmin .= "👤 *Pembeli:* " . $nama . " (" . $no_hp . ")\n";
                $msgAdmin .= "💰 *Total:* " . $total_formatted . "\n";
                $msgAdmin .= "Status: *LUNAS & E-TICKET TERBIT*\n";
                $msgAdmin .= "----------------------------------------\n";
                $msgAdmin .= "⏰ *Waktu Lunas:* " . date('d/m/Y H:i') . " WIB";
                
                sendFonnteWA($group_id, $msgAdmin);
            }
        }

        // Pesan untuk Pembeli
        if ($enable_buyer && !empty($no_hp)) {
            $msgBuyer = "🎉 *PEMBAYARAN DITERIMA & E-TICKET TERBIT!*\n\n";
            $msgBuyer .= "Halo *" . $nama . "*,\n";
            $msgBuyer .= "Pembayaran Anda untuk pesanan *" . $order_id . "* telah terverifikasi *LUNAS*! ✅\n\n";
            $msgBuyer .= "🎫 *Event:* " . $event_title . "\n";
            if (!empty($variant_name)) {
                $msgBuyer .= "🎟️ *Varian:* " . $variant_name . "\n";
            }
            $msgBuyer .= "\n📥 *Unduh E-Ticket (PDF) Resmi Anda:*\n";
            $msgBuyer .= "👉 " . $linkPdf . "\n\n";
            $msgBuyer .= "Harap simpan file PDF / QR Code ini dan tunjukkan kepada petugas di lokasi acara saat check-in. Sampai jumpa di lokasi! 🚀";

            sendFonnteWA($no_hp, $msgBuyer);
        }
    }
}
?>
