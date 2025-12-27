<?php
require_once 'classes/QRGenerator.php';

$test_url = "https://neofoxmedia.com/foxy/checkout.php?asset_id=TEST123";
$qr_url = QRGenerator::generateQRCode($test_url);

echo "<h3>QR Test</h3>";
echo "<p>QR URL: " . $qr_url . "</p>";
echo "<img src='" . $qr_url . "' alt='QR Code'>";
?>