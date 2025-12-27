<?php
class QRGenerator {
    public static function generateQRCode($text, $size = 20) { // Changed from 200 to 30
        $url = "https://quickchart.io/qr?text=" . urlencode($text) . "&size={$size}";
        return $url;
    }

    public static function generateAssetQR($asset_id, $base_url) {
        $checkout_url = $base_url . "/checkout.php?asset_id=" . $asset_id;
        return self::generateQRCode($checkout_url, 20); // 30px = ~1cm at 96 DPI
    }
}