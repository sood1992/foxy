<?php
// debug_purpose.php - Check what's happening with purpose data
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!session_id()) {
    ini_set('session.save_path', sys_get_temp_dir());
    session_start();
}

require_once 'config/database.php';
require_once 'classes/Asset.php';

$database = new Database();
$db = $database->getConnection();
$asset = new Asset($db);

echo "<h2>🔍 Purpose Data Debug</h2>";

// Test 1: Check if transactions table has purpose data
echo "<h3>1. Recent Transactions with Purpose:</h3>";
try {
    $query = "SELECT asset_id, borrower_name, transaction_type, purpose, transaction_date 
              FROM transactions 
              WHERE transaction_type = 'checkout' 
              ORDER BY transaction_date DESC 
              LIMIT 10";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($transactions)) {
        echo "❌ No checkout transactions found<br>";
    } else {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>Asset ID</th><th>Borrower</th><th>Type</th><th>Purpose</th><th>Date</th></tr>";
        foreach ($transactions as $t) {
            $purpose = $t['purpose'] ?? 'NULL';
            if (empty($purpose)) $purpose = '<em>EMPTY</em>';
            echo "<tr>";
            echo "<td>" . htmlspecialchars($t['asset_id']) . "</td>";
            echo "<td>" . htmlspecialchars($t['borrower_name']) . "</td>";
            echo "<td>" . htmlspecialchars($t['transaction_type']) . "</td>";
            echo "<td>" . htmlspecialchars($purpose) . "</td>";
            echo "<td>" . htmlspecialchars($t['transaction_date']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
}

// Test 2: Check currently checked out assets
echo "<h3>2. Currently Checked Out Assets (Original Method):</h3>";
try {
    $checked_out_old = $asset->getCheckedOutAssets();
    echo "Found " . count($checked_out_old) . " checked out items:<br>";
    foreach ($checked_out_old as $item) {
        echo "- " . htmlspecialchars($item['asset_name']) . " (ID: " . htmlspecialchars($item['asset_id']) . ") - " . htmlspecialchars($item['current_borrower']) . "<br>";
    }
} catch (Exception $e) {
    echo "❌ Error with old method: " . $e->getMessage() . "<br>";
}

// Test 3: Check with new method
echo "<h3>3. Checked Out Assets with Purpose (New Method):</h3>";
try {
    $checked_out_new = $asset->getCheckedOutAssetsWithPurpose();
    echo "Found " . count($checked_out_new) . " checked out items with purpose:<br>";
    
    if (empty($checked_out_new)) {
        echo "❌ No items returned from new method<br>";
    } else {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>Asset Name</th><th>Asset ID</th><th>Borrower</th><th>Purpose</th><th>Checkout Date</th></tr>";
        foreach ($checked_out_new as $item) {
            $purpose = $item['purpose'] ?? 'NULL';
            if (empty($purpose)) $purpose = '<em>EMPTY</em>';
            echo "<tr>";
            echo "<td>" . htmlspecialchars($item['asset_name']) . "</td>";
            echo "<td>" . htmlspecialchars($item['asset_id']) . "</td>";
            echo "<td>" . htmlspecialchars($item['current_borrower']) . "</td>";
            echo "<td>" . htmlspecialchars($purpose) . "</td>";
            echo "<td>" . htmlspecialchars($item['checkout_date']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
} catch (Exception $e) {
    echo "❌ Error with new method: " . $e->getMessage() . "<br>";
    echo "Error details: " . $e->getTraceAsString() . "<br>";
}

// Test 4: Test raw SQL query
echo "<h3>4. Raw SQL Test:</h3>";
try {
    $query = "SELECT a.asset_name, a.asset_id, a.current_borrower, t.purpose 
              FROM assets a 
              LEFT JOIN transactions t ON a.asset_id = t.asset_id AND t.transaction_type = 'checkout'
              WHERE a.status = 'checked_out'
              ORDER BY t.transaction_date DESC";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    $raw_results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Raw query returned " . count($raw_results) . " rows:<br>";
    foreach ($raw_results as $row) {
        $purpose = $row['purpose'] ?? 'NULL';
        if (empty($purpose)) $purpose = '<em>EMPTY</em>';
        echo "- " . htmlspecialchars($row['asset_name']) . " → Purpose: " . htmlspecialchars($purpose) . "<br>";
    }
} catch (Exception $e) {
    echo "❌ Raw query error: " . $e->getMessage() . "<br>";
}

// Test 5: Check MySQL version for ROW_NUMBER support
echo "<h3>5. MySQL Version Check:</h3>";
try {
    $version_query = "SELECT VERSION() as version";
    $stmt = $db->prepare($version_query);
    $stmt->execute();
    $version = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "MySQL Version: " . $version['version'] . "<br>";
    
    $version_num = floatval($version['version']);
    if ($version_num < 8.0) {
        echo "⚠️ Warning: ROW_NUMBER() function requires MySQL 8.0+. Your version may not support it.<br>";
    } else {
        echo "✅ Version supports ROW_NUMBER()<br>";
    }
} catch (Exception $e) {
    echo "❌ Version check error: " . $e->getMessage() . "<br>";
}

echo "<hr>";
echo "<h3>📋 Next Steps:</h3>";
echo "<p>1. Check if transactions have purpose data in Test 1</p>";
echo "<p>2. If purpose data exists but Test 3 shows EMPTY, the JOIN might be wrong</p>";
echo "<p>3. If MySQL version < 8.0, we need a different query</p>";
echo "<p>4. Share the results with me to fix the issue!</p>";

?>

<style>
body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
table { margin: 10px 0; }
th, td { padding: 8px; text-align: left; }
th { background: #333; color: white; }
h2, h3 { color: #333; }
</style>