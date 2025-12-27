<?php
// simple_bulk_import.php - Import assets without session dependency
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'config/database.php';
require_once 'classes/Asset.php';
require_once 'classes/QRGenerator.php';

// Simple password protection (remove after use)
$import_password = "neofox2024"; // Change this password

if (!isset($_GET['password']) || $_GET['password'] !== $import_password) {
    echo "<div style='background: #FFD700; padding: 20px; text-align: center; margin: 50px auto; max-width: 500px; border: 3px solid #000; border-radius: 20px;'>";
    echo "<h2 style='margin: 0 0 20px 0;'>🔐 Bulk Asset Import</h2>";
    echo "<p>Enter the import password:</p>";
    echo "<form method='GET'>";
    echo "<input type='password' name='password' style='padding: 10px; border: 2px solid #000; border-radius: 5px; margin: 10px;' placeholder='Enter password' required>";
    echo "<br><button type='submit' style='background: #000; color: #FFD700; padding: 10px 20px; border: none; border-radius: 5px; font-weight: bold; margin: 10px;'>Access Import</button>";
    echo "</form>";
    echo "<p style='font-size: 0.9rem; color: #666;'>Password: <code>{$import_password}</code></p>";
    echo "</div>";
    exit();
}

$database = new Database();
$db = $database->getConnection();
$asset = new Asset($db);

// Your existing assets data
$assets_data = [
    ['name' => 'Sony A7 III', 'category' => 'Camera'],
    ['name' => 'Zoom Recorder', 'category' => 'Audio'],
    ['name' => 'GoPro 9 Black', 'category' => 'Camera'],
    ['name' => 'Insta 360', 'category' => 'Camera'],
    ['name' => 'DJI Osmo', 'category' => 'Camera'],
    ['name' => 'Sony Handycam 60x', 'category' => 'Camera'],
    ['name' => 'Rode V-Mic D3 Pro', 'category' => 'Audio'],
    ['name' => 'Rode VideoMic Pro', 'category' => 'Audio'],
    ['name' => 'Sony A73', 'category' => 'Camera'],
    ['name' => 'DJI Mic2 Receiver', 'category' => 'Audio'],
    ['name' => 'GoPro 11 Black', 'category' => 'Camera'],
    ['name' => '85mm Samyang', 'category' => 'Lens'],
    ['name' => '50mm Zeiss', 'category' => 'Lens'],
    ['name' => '85mm Canon Image Stabilizer Ultrasonic', 'category' => 'Lens'],
    ['name' => '100mm Canon', 'category' => 'Lens'],
    ['name' => '16-35mm Canon Ultrasonic', 'category' => 'Lens'],
    ['name' => '85mm Canon Ultrasonic', 'category' => 'Lens'],
    ['name' => '10mm Samyang', 'category' => 'Lens'],
    ['name' => '35mm Samyang', 'category' => 'Lens'],
    ['name' => '62mm SLR Magic', 'category' => 'Lens'],
    ['name' => 'USB-C Cable', 'category' => 'Support']
];

$success_count = 0;
$error_count = 0;
$errors = [];

echo "<h2>🎬 Neofox Gear Control - Bulk Asset Import</h2>";
echo "<p>Importing " . count($assets_data) . " assets...</p>";
echo "<hr>";

// Base URL for QR codes
$base_url = 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']);

foreach ($assets_data as $index => $item) {
    $asset_number = str_pad($index + 1, 3, '0', STR_PAD_LEFT);
    $asset_id = 'NF' . $asset_number;
    
    // Generate QR code
    $qr_url = QRGenerator::generateAssetQR($asset_id, $base_url);
    
    // Prepare asset data
    $data = [
        'asset_id' => $asset_id,
        'asset_name' => $item['name'],
        'category' => $item['category'],
        'description' => 'Imported from existing system',
        'serial_number' => '', // Add manually later if needed
        'qr_code' => $qr_url,
        'condition_status' => 'good',
        'notes' => 'Bulk imported on ' . date('Y-m-d')
    ];
    
    try {
        if ($asset->create($data)) {
            $success_count++;
            echo "✅ <strong>{$asset_id}</strong> - {$item['name']} ({$item['category']}) - <span style='color: green;'>SUCCESS</span><br>";
        } else {
            $error_count++;
            $asset_errors = $asset->getErrors();
            $error_msg = !empty($asset_errors) ? implode(', ', $asset_errors) : 'Unknown error';
            $errors[] = "{$asset_id} - {$item['name']}: {$error_msg}";
            echo "❌ <strong>{$asset_id}</strong> - {$item['name']} ({$item['category']}) - <span style='color: red;'>FAILED: {$error_msg}</span><br>";
        }
    } catch (Exception $e) {
        $error_count++;
        $errors[] = "{$asset_id} - {$item['name']}: " . $e->getMessage();
        echo "❌ <strong>{$asset_id}</strong> - {$item['name']} ({$item['category']}) - <span style='color: red;'>ERROR: " . $e->getMessage() . "</span><br>";
    }
    
    // Small delay to prevent overwhelming the system
    usleep(100000); // 0.1 second delay
}

echo "<hr>";
echo "<h3>📊 Import Summary</h3>";
echo "<p><strong>Total Assets:</strong> " . count($assets_data) . "</p>";
echo "<p><strong>✅ Successfully Imported:</strong> <span style='color: green; font-weight: bold;'>{$success_count}</span></p>";
echo "<p><strong>❌ Failed:</strong> <span style='color: red; font-weight: bold;'>{$error_count}</span></p>";

if (!empty($errors)) {
    echo "<h4>❌ Errors:</h4>";
    echo "<ul>";
    foreach ($errors as $error) {
        echo "<li style='color: red;'>{$error}</li>";
    }
    echo "</ul>";
}

if ($success_count > 0) {
    echo "<h3>🎉 Next Steps:</h3>";
    echo "<ul>";
    echo "<li>✅ Go to <a href='assets.php'><strong>Assets Management</strong></a> to view your imported equipment</li>";
    echo "<li>📝 Update serial numbers and detailed descriptions as needed</li>";
    echo "<li>🖨️ Print QR codes for each asset</li>";
    echo "<li>📱 Test QR code scanning with your mobile device</li>";
    echo "<li>🏷️ Physically label your equipment with the generated QR codes</li>";
    echo "</ul>";
    
    echo "<div style='background: #e8f5e8; border: 2px solid #4caf50; border-radius: 10px; padding: 20px; margin: 20px 0;'>";
    echo "<h4 style='color: #2e7d32; margin: 0 0 10px 0;'>🎯 Asset ID Format:</h4>";
    echo "<p style='margin: 0;'><strong>Format:</strong> NF001, NF002, NF003... (Neofox + 3-digit number)</p>";
    echo "<p style='margin: 0;'><strong>QR Codes:</strong> Each asset has a unique QR code for easy checkout/checkin</p>";
    echo "</div>";
}

echo "<div style='margin: 30px 0; text-align: center;'>";
echo "<a href='assets.php' style='background: #FFD700; color: #000; padding: 15px 30px; text-decoration: none; border-radius: 10px; font-weight: bold; border: 2px solid #000;'>📦 VIEW ALL ASSETS</a> ";
echo "<a href='index.php' style='background: #4CAF50; color: #fff; padding: 15px 30px; text-decoration: none; border-radius: 10px; font-weight: bold; border: 2px solid #000; margin-left: 10px;'>🏠 BACK TO DASHBOARD</a>";
echo "</div>";

echo "<p style='margin-top: 30px; padding: 20px; background: #fff9c4; border-radius: 10px; border: 2px solid #ffd700;'>";
echo "💡 <strong>Security Note:</strong> Please delete this import file (simple_bulk_import.php) after successful import for security reasons.";
echo "</p>";
?>

<style>
body {
    font-family: 'Inter', Arial, sans-serif;
    max-width: 1000px;
    margin: 0 auto;
    padding: 20px;
    background: #FFD700;
    color: #000;
}

h2, h3, h4 {
    color: #000;
    border-bottom: 2px solid #000;
    padding-bottom: 10px;
}

a {
    color: #000;
    font-weight: bold;
}

a:hover {
    color: #333;
}
</style>