<?php
/**
 * FOXY Data Migration Script
 * 
 * USAGE:
 * 1. Upload your old JSON export as: api/data/old_assets.json
 * 2. Visit: https://foxy.neofoxmedia.com/api/migrate-data.php
 * 3. DELETE THIS FILE after migration!
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/utils/JsonDatabase.php';

echo "<html><head><title>FOXY Migration</title>";
echo "<style>body{font-family:monospace;background:#1a1a2e;color:#eee;padding:20px;} 
.success{color:#4ade80;} .error{color:#f87171;} .info{color:#60a5fa;}
pre{background:#0d0d1a;padding:15px;border-radius:8px;overflow:auto;}
h1{color:#facc15;} h2{color:#60a5fa;border-bottom:1px solid #333;padding-bottom:10px;}
</style></head><body>";

echo "<h1>🦊 FOXY Data Migration</h1>";

// Check for uploaded file
$oldDataFile = DATA_PATH . '/old_assets.json';

if (!file_exists($oldDataFile)) {
    echo "<div class='error'>❌ File not found: <code>api/data/old_assets.json</code></div>";
    echo "<h2>Instructions:</h2>";
    echo "<ol>";
    echo "<li>Export your old database as JSON from phpMyAdmin</li>";
    echo "<li>Upload it to <code>api/data/old_assets.json</code></li>";
    echo "<li>Refresh this page</li>";
    echo "</ol>";
    echo "<h2>Expected location:</h2>";
    echo "<pre>" . $oldDataFile . "</pre>";
    exit;
}

echo "<div class='success'>✅ Found: old_assets.json</div>";

// Read old data
$rawData = file_get_contents($oldDataFile);
$oldData = json_decode($rawData, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    echo "<div class='error'>❌ Invalid JSON: " . json_last_error_msg() . "</div>";
    exit;
}

// Handle phpMyAdmin JSON export format (may be nested)
if (isset($oldData[0]['data'])) {
    // phpMyAdmin format: [{"type":"table","name":"assets","data":[...]}]
    $oldData = $oldData[0]['data'];
} elseif (isset($oldData['data'])) {
    $oldData = $oldData['data'];
}

echo "<div class='info'>📊 Found " . count($oldData) . " records</div>";

// Show sample record
echo "<h2>Sample Record (first item):</h2>";
echo "<pre>" . json_encode($oldData[0] ?? [], JSON_PRETTY_PRINT) . "</pre>";

// Detect columns
$sampleRecord = $oldData[0] ?? [];
$columns = array_keys($sampleRecord);
echo "<h2>Detected Columns:</h2>";
echo "<pre>" . implode(", ", $columns) . "</pre>";

// Migration
if (isset($_GET['confirm']) && $_GET['confirm'] === 'yes') {
    echo "<h2>🚀 Running Migration...</h2>";
    
    $migrated = 0;
    $errors = [];
    $newAssets = [];
    
    foreach ($oldData as $index => $old) {
        try {
            // Map old fields to new format
            // Adjust these mappings based on your actual column names!
            $asset = [
                'asset_id' => $old['asset_id'] ?? $old['id'] ?? $old['AssetID'] ?? ('IMP-' . str_pad($index + 1, 3, '0', STR_PAD_LEFT)),
                'asset_name' => $old['asset_name'] ?? $old['name'] ?? $old['AssetName'] ?? $old['equipment_name'] ?? 'Unknown',
                'category' => mapCategory($old['category'] ?? $old['Category'] ?? $old['type'] ?? 'Other'),
                'description' => $old['description'] ?? $old['Description'] ?? '',
                'serial_number' => $old['serial_number'] ?? $old['SerialNumber'] ?? $old['serial'] ?? '',
                'qr_code' => 'https://quickchart.io/qr?text=' . urlencode($old['asset_id'] ?? $old['id'] ?? 'IMP-' . ($index + 1)) . '&size=350',
                'status' => mapStatus($old['status'] ?? $old['Status'] ?? 'available'),
                'current_borrower' => $old['current_borrower'] ?? $old['borrower'] ?? $old['checked_out_to'] ?? null,
                'checkout_date' => $old['checkout_date'] ?? $old['checked_out_date'] ?? null,
                'expected_return_date' => $old['expected_return_date'] ?? $old['return_date'] ?? $old['due_date'] ?? null,
                'condition_status' => mapCondition($old['condition_status'] ?? $old['condition'] ?? 'excellent'),
                'storage_location' => $old['storage_location'] ?? $old['location'] ?? $old['room'] ?? '',
                'shelf' => $old['shelf'] ?? $old['shelf_number'] ?? '',
                'notes' => $old['notes'] ?? $old['Notes'] ?? $old['remarks'] ?? '',
                'total_checkouts' => intval($old['total_checkouts'] ?? $old['checkout_count'] ?? $old['times_used'] ?? 0),
                'created_at' => $old['created_at'] ?? $old['date_added'] ?? $old['CreatedAt'] ?? date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            // Clean up null borrower for available items
            if ($asset['status'] === 'available') {
                $asset['current_borrower'] = null;
                $asset['checkout_date'] = null;
                $asset['expected_return_date'] = null;
            }
            
            $newAssets[] = $asset;
            $migrated++;
            
        } catch (Exception $e) {
            $errors[] = "Row {$index}: " . $e->getMessage();
        }
    }
    
    // Save to database
    if ($migrated > 0) {
        // Backup existing assets first
        $existingAssets = JsonDatabase::getAll('assets');
        if (!empty($existingAssets)) {
            file_put_contents(DATA_PATH . '/assets_backup_' . date('Y-m-d_H-i-s') . '.json', 
                json_encode($existingAssets, JSON_PRETTY_PRINT));
            echo "<div class='info'>📦 Backed up " . count($existingAssets) . " existing assets</div>";
        }
        
        // Save new assets
        file_put_contents(DATA_PATH . '/assets.json', json_encode($newAssets, JSON_PRETTY_PRINT));
        echo "<div class='success'>✅ Migrated {$migrated} assets successfully!</div>";
    }
    
    if (!empty($errors)) {
        echo "<h3 class='error'>Errors:</h3>";
        echo "<pre>" . implode("\n", array_slice($errors, 0, 10)) . "</pre>";
    }
    
    echo "<h2>Sample Migrated Record:</h2>";
    echo "<pre>" . json_encode($newAssets[0] ?? [], JSON_PRETTY_PRINT) . "</pre>";
    
    echo "<h2>✅ Migration Complete!</h2>";
    echo "<p><strong style='color:#f87171;'>DELETE THIS FILE NOW!</strong></p>";
    echo "<p><a href='/' style='color:#facc15;'>Go to FOXY Dashboard →</a></p>";
    
} else {
    // Show confirmation
    echo "<h2>⚠️ Ready to Migrate</h2>";
    echo "<p>This will import " . count($oldData) . " records into FOXY.</p>";
    echo "<p><a href='?confirm=yes' style='background:#facc15;color:#000;padding:10px 20px;text-decoration:none;border-radius:5px;font-weight:bold;'>✅ Confirm Migration</a></p>";
    echo "<p style='color:#f87171;margin-top:20px;'>Make sure your column mappings are correct before confirming!</p>";
}

echo "</body></html>";

// Helper functions
function mapStatus($status) {
    $status = strtolower(trim($status));
    $map = [
        'available' => 'available',
        'in' => 'available',
        'in stock' => 'available',
        'out' => 'checked_out',
        'checked_out' => 'checked_out',
        'checked out' => 'checked_out',
        'borrowed' => 'checked_out',
        'repair' => 'maintenance',
        'maintenance' => 'maintenance',
        'broken' => 'maintenance',
        'lost' => 'lost'
    ];
    return $map[$status] ?? 'available';
}

function mapCategory($category) {
    $category = trim($category);
    $valid = ['Camera', 'Lens', 'Audio', 'Lighting', 'Storage', 'Monitor', 'Tripod', 'Cables', 'Other'];
    
    // Try exact match first
    foreach ($valid as $v) {
        if (strcasecmp($category, $v) === 0) return $v;
    }
    
    // Try partial match
    $lower = strtolower($category);
    if (strpos($lower, 'cam') !== false) return 'Camera';
    if (strpos($lower, 'lens') !== false) return 'Lens';
    if (strpos($lower, 'audio') !== false || strpos($lower, 'mic') !== false || strpos($lower, 'sound') !== false) return 'Audio';
    if (strpos($lower, 'light') !== false) return 'Lighting';
    if (strpos($lower, 'storage') !== false || strpos($lower, 'card') !== false || strpos($lower, 'drive') !== false) return 'Storage';
    if (strpos($lower, 'monitor') !== false || strpos($lower, 'screen') !== false) return 'Monitor';
    if (strpos($lower, 'tripod') !== false || strpos($lower, 'stand') !== false) return 'Tripod';
    if (strpos($lower, 'cable') !== false || strpos($lower, 'wire') !== false) return 'Cables';
    
    return 'Other';
}

function mapCondition($condition) {
    $condition = strtolower(trim($condition));
    $map = [
        'excellent' => 'excellent',
        'good' => 'good',
        'fair' => 'good',
        'poor' => 'needs_repair',
        'needs_repair' => 'needs_repair',
        'needs repair' => 'needs_repair',
        'broken' => 'needs_repair',
        'damaged' => 'needs_repair'
    ];
    return $map[$condition] ?? 'excellent';
}
