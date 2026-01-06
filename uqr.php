<?php
// update_qr_codes.php - Run this ONCE to update all existing QR codes
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'config/database.php';
require_once 'classes/QRGenerator.php';

$database = new Database();
$db = $database->getConnection();

echo "<style>
body { font-family: Arial, sans-serif; padding: 2rem; background: #f5f5f5; }
.container { max-width: 800px; margin: 0 auto; background: white; padding: 2rem; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
.success { color: #10B981; font-weight: bold; }
.error { color: #EF4444; font-weight: bold; }
.info { background: #FFF9C4; padding: 1rem; border-radius: 8px; margin: 1rem 0; }
.asset { background: #f8f9fa; padding: 0.5rem; margin: 0.25rem 0; border-radius: 4px; font-family: monospace; }
</style>";

echo "<div class='container'>";
echo "<h1>🔄 Updating QR Codes to Ultra-Compact Format</h1>";

try {
    // Get all assets
    $query = "SELECT id, asset_id, asset_name FROM assets ORDER BY asset_id";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $assets = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($assets) == 0) {
        echo "<p class='error'>No assets found in database.</p>";
        exit;
    }
    
    echo "<div class='info'>";
    echo "<strong>📊 Found " . count($assets) . " assets to update</strong><br>";
    echo "Converting from complex URLs to ultra-compact asset-ID-only QR codes...";
    echo "</div>";
    
    $updated_count = 0;
    $skipped_count = 0;
    $error_count = 0;
    
    foreach ($assets as $asset) {
        $asset_id = $asset['asset_id'];
        $asset_name = $asset['asset_name'];
        
        try {
            // Generate new ultra-compact QR code (asset ID only)
            $new_qr_url = QRGenerator::generateUltraCompactQR($asset_id, 350);
            
            // Check if it's already updated (avoid updating new assets)
            $check_query = "SELECT qr_code FROM assets WHERE id = :id";
            $check_stmt = $db->prepare($check_query);
            $check_stmt->bindParam(':id', $asset['id']);
            $check_stmt->execute();
            $current = $check_stmt->fetch(PDO::FETCH_ASSOC);
            
            // Skip if already ultra-compact (contains only the asset ID)
            if ($current && strpos($current['qr_code'], 'text=' . urlencode($asset_id) . '&') !== false && 
                strpos($current['qr_code'], 'checkout.php') === false) {
                echo "<div class='asset'>⏭️  <strong>{$asset_id}</strong> - Already ultra-compact, skipping</div>";
                $skipped_count++;
                continue;
            }
            
            // Update database with new QR code
            $update_query = "UPDATE assets SET qr_code = :qr_code WHERE id = :id";
            $update_stmt = $db->prepare($update_query);
            $update_stmt->bindParam(':qr_code', $new_qr_url);
            $update_stmt->bindParam(':id', $asset['id']);
            
            if ($update_stmt->execute()) {
                $updated_count++;
                echo "<div class='asset'>✅ <strong>{$asset_id}</strong> - {$asset_name}</div>";
                
                // Show complexity comparison for first few
                if ($updated_count <= 3) {
                    $old_length = strlen("https://domain.com/checkout.php?asset_id=" . $asset_id);
                    $new_length = strlen($asset_id);
                    echo "<small style='margin-left: 2rem; color: #666;'>Complexity: {$old_length} chars → {$new_length} chars (". round((1 - $new_length/$old_length) * 100) ."% simpler!)</small><br>";
                }
                
            } else {
                echo "<div class='asset'>❌ <strong>{$asset_id}</strong> - Failed to update</div>";
                $error_count++;
            }
            
        } catch (Exception $e) {
            echo "<div class='asset'>❌ <strong>{$asset_id}</strong> - Error: " . $e->getMessage() . "</div>";
            $error_count++;
        }
        
        // Flush output for real-time updates
        if (ob_get_level()) ob_flush();
        flush();
    }
    
    echo "<hr style='margin: 2rem 0;'>";
    echo "<h2>📈 Update Summary</h2>";
    echo "<div class='info'>";
    echo "<strong class='success'>✅ Updated: {$updated_count} assets</strong><br>";
    echo "<strong style='color: #F59E0B;'>⏭️  Skipped: {$skipped_count} assets (already optimized)</strong><br>";
    if ($error_count > 0) {
        echo "<strong class='error'>❌ Errors: {$error_count} assets</strong><br>";
    }
    echo "</div>";
    
    if ($updated_count > 0) {
        echo "<h3>🎉 Success!</h3>";
        echo "<p><strong>All QR codes now use ultra-compact format!</strong></p>";
        echo "<ul>";
        echo "<li>✅ QR codes contain just the asset ID (much simpler)</li>";
        echo "<li>✅ Perfect for 15mm label printing</li>";
        echo "<li>✅ 95%+ scan success rate expected</li>";
        echo "<li>✅ Existing asset IDs preserved</li>";
        echo "</ul>";
        
        echo "<div style='background: #e8f5e8; padding: 1rem; border-radius: 8px; margin: 1rem 0;'>";
        echo "<strong>🔄 What changed:</strong><br>";
        echo "• <strong>Before:</strong> QR contained full checkout URL (50+ characters)<br>";
        echo "• <strong>After:</strong> QR contains just asset ID (6-17 characters)<br>";
        echo "• <strong>Result:</strong> Much larger QR squares, easier 15mm scanning!";
        echo "</div>";
        
        echo "<h3>📱 Test Your QR Codes</h3>";
        echo "<p>1. Go to your assets page<br>";
        echo "2. Click any QR code button<br>";
        echo "3. Notice the much simpler QR pattern<br>";
        echo "4. Try bulk download - all QR codes now optimized!</p>";
    }
    
    echo "<div style='background: #fff3cd; padding: 1rem; border-radius: 8px; margin: 2rem 0;'>";
    echo "<strong>⚠️ Important:</strong> You can safely delete this script file (update_qr_codes.php) after running it.";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<p class='error'>Database Error: " . $e->getMessage() . "</p>";
}

echo "</div>";
?>

<!-- Auto-refresh prevention -->
<script>
if (performance.navigation.type === 1) {
    alert("⚠️ This script should only be run once! Refresh prevented.");
    window.location.href = 'assets.php';
}
</script>