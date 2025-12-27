<?php
class QRGenerator {
    // Original method - keep for backward compatibility
    public static function generateQRCode($text, $size = 150) {
        // Add optimization for 15mm labels when size is large
        if ($size >= 300) {
            // Ultra-compact QR optimized for 15mm printing
            $url = "https://quickchart.io/qr?text=" . urlencode($text) . 
                   "&size={$size}&format=png&ecc=M&margin=1&qzone=0";
        } else {
            // Standard QR for screen display
            $url = "https://quickchart.io/qr?text=" . urlencode($text) . "&size={$size}&format=png&ecc=M";
        }
        return $url;
    }

    // Original method - keep for backward compatibility  
    public static function generateAssetQR($asset_id, $base_url) {
        $checkout_url = $base_url . "/checkout.php?asset_id=" . $asset_id;
        return self::generateQRCode($checkout_url, 150);
    }

    // NEW: Ultra-compact QR for 15mm labels (asset ID only)
    public static function generateUltraCompactQR($text, $size = 350) {
        $url = "https://quickchart.io/qr?text=" . urlencode($text) . 
               "&size={$size}&format=png&ecc=M&margin=1&qzone=0";
        return $url;
    }

    // NEW: Generate 15mm optimized QR with just asset ID
    public static function generate15mmAssetIdQR($asset_id) {
        return self::generateUltraCompactQR($asset_id, 350);
    }

    // NEW: Create minimal URL for QR codes
    public static function createMinimalUrl($asset_id, $base_domain = null) {
        if (!$base_domain) {
            $base_domain = $_SERVER['HTTP_HOST'] ?? 'yourdomain.com';
        }
        return "https://{$base_domain}/" . $asset_id;
    }

    // NEW: Generate 15mm QR with minimal URL
    public static function generate15mmQR($asset_id, $base_domain = null) {
        $minimal_url = self::createMinimalUrl($asset_id, $base_domain);
        return self::generateUltraCompactQR($minimal_url, 350);
    }

    // NEW: Compare QR complexity for testing
    public static function compareQRComplexity($asset_id, $base_domain = null) {
        if (!$base_domain) {
            $base_domain = $_SERVER['HTTP_HOST'] ?? 'yourdomain.com';
        }
        
        $full_url = "https://{$base_domain}/checkout.php?asset_id=" . $asset_id;
        $minimal_url = self::createMinimalUrl($asset_id, $base_domain);
        $asset_only = $asset_id;
        
        return [
            'full_url' => ['text' => $full_url, 'length' => strlen($full_url)],
            'minimal_url' => ['text' => $minimal_url, 'length' => strlen($minimal_url)],
            'asset_only' => ['text' => $asset_only, 'length' => strlen($asset_only)]
        ];
    }
}
?>