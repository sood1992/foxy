<?php
// setup_maintenance_db_fixed.php - Create maintenance tables (MySQL compatible)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

if (!$db) {
    die("Database connection failed!");
}

// Check if columns exist before adding them
function columnExists($db, $table, $column) {
    try {
        $result = $db->query("SHOW COLUMNS FROM `{$table}` LIKE '{$column}'");
        return $result->num_rows > 0;
    } catch (Exception $e) {
        return false;
    }
}

// SQL to create maintenance tables
$maintenance_tables = [
    'maintenance_schedule' => "
        CREATE TABLE IF NOT EXISTS maintenance_schedule (
            id INT AUTO_INCREMENT PRIMARY KEY,
            asset_id VARCHAR(50) NOT NULL,
            maintenance_type ENUM('routine', 'preventive', 'repair', 'calibration', 'inspection') NOT NULL,
            scheduled_date DATETIME NOT NULL,
            next_maintenance_date DATETIME,
            completed_date DATETIME,
            assigned_to VARCHAR(100),
            priority ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
            status ENUM('scheduled', 'in_progress', 'completed', 'cancelled') DEFAULT 'scheduled',
            notes TEXT,
            completion_notes TEXT,
            estimated_duration INT,
            actual_duration INT,
            cost_estimate DECIMAL(10,2),
            actual_cost DECIMAL(10,2),
            completed_by VARCHAR(100),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_asset_id (asset_id),
            INDEX idx_scheduled_date (scheduled_date),
            INDEX idx_status (status)
        )
    ",
    
    'maintenance_issues' => "
        CREATE TABLE IF NOT EXISTS maintenance_issues (
            id INT AUTO_INCREMENT PRIMARY KEY,
            asset_id VARCHAR(50) NOT NULL,
            reported_by VARCHAR(100) NOT NULL,
            issue_type ENUM('mechanical', 'electrical', 'software', 'cosmetic', 'performance', 'safety') NOT NULL,
            description TEXT NOT NULL,
            severity ENUM('low', 'medium', 'high', 'critical') NOT NULL,
            status ENUM('open', 'in_progress', 'resolved', 'closed') DEFAULT 'open',
            reported_date DATETIME NOT NULL,
            resolved_date DATETIME,
            resolved_by VARCHAR(100),
            resolution_notes TEXT,
            resolution_cost DECIMAL(10,2),
            images TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_asset_id (asset_id),
            INDEX idx_status (status),
            INDEX idx_severity (severity)
        )
    ",
    
    'maintenance_history' => "
        CREATE TABLE IF NOT EXISTS maintenance_history (
            id INT AUTO_INCREMENT PRIMARY KEY,
            maintenance_id INT,
            action VARCHAR(100) NOT NULL,
            details TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_maintenance_id (maintenance_id)
        )
    ",
    
    'notification_log' => "
        CREATE TABLE IF NOT EXISTS notification_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            type ENUM('checkout', 'checkin', 'overdue_reminder', 'maintenance_reminder') NOT NULL,
            asset_id VARCHAR(50),
            recipient_email VARCHAR(100),
            borrower_name VARCHAR(100),
            status ENUM('sent', 'failed', 'pending') NOT NULL,
            details TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_type (type),
            INDEX idx_status (status)
        )
    ",
    
    'transactions' => "
        CREATE TABLE IF NOT EXISTS transactions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            asset_id VARCHAR(50) NOT NULL,
            transaction_type ENUM('checkout', 'checkin') NOT NULL,
            borrower_name VARCHAR(100) NOT NULL,
            transaction_date DATETIME NOT NULL,
            expected_return_date DATETIME,
            actual_return_date DATETIME,
            purpose TEXT,
            condition_on_checkout ENUM('excellent', 'good', 'fair', 'poor') DEFAULT 'good',
            condition_on_return ENUM('excellent', 'good', 'fair', 'poor'),
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_asset_id (asset_id),
            INDEX idx_transaction_date (transaction_date),
            INDEX idx_transaction_type (transaction_type),
            INDEX idx_borrower (borrower_name)
        )
    "
];

// Columns to add to assets table (check if they exist first)
$asset_columns = [
    'last_maintenance_date' => "DATETIME",
    'next_maintenance_due' => "DATETIME", 
    'maintenance_interval_days' => "INT DEFAULT 90",
    'last_returned_date' => "DATETIME",
    'total_checkouts' => "INT DEFAULT 0"
];

echo "<h2>🔧 Setting up Maintenance Database Tables</h2>";
echo "<hr>";

$success_count = 0;
$error_count = 0;

// Create maintenance tables
foreach ($maintenance_tables as $table_name => $sql) {
    try {
        $db->query($sql);
        echo "✅ <strong>{$table_name}</strong> table created successfully<br>";
        $success_count++;
    } catch (Exception $e) {
        echo "❌ <strong>{$table_name}</strong> table creation failed: " . $e->getMessage() . "<br>";
        $error_count++;
    }
}

// Add columns to assets table (check if they exist first)
foreach ($asset_columns as $column_name => $column_definition) {
    try {
        if (!columnExists($db, 'assets', $column_name)) {
            $sql = "ALTER TABLE assets ADD COLUMN {$column_name} {$column_definition}";
            $db->query($sql);
            echo "✅ Added <strong>{$column_name}</strong> column to assets table<br>";
            $success_count++;
        } else {
            echo "ℹ️ Column <strong>{$column_name}</strong> already exists<br>";
        }
    } catch (Exception $e) {
        echo "❌ Failed to add <strong>{$column_name}</strong> column: " . $e->getMessage() . "<br>";
        $error_count++;
    }
}

echo "<hr>";
echo "<h3>📊 Setup Summary</h3>";
echo "<p><strong>✅ Successful:</strong> {$success_count}</p>";
echo "<p><strong>❌ Errors:</strong> {$error_count}</p>";

// Add some sample transaction data for testing
if ($error_count == 0) {
    echo "<div style='background: #e8f5e8; border: 2px solid #4caf50; border-radius: 10px; padding: 20px; margin: 20px 0;'>";
    echo "<h4 style='color: #2e7d32; margin: 0 0 10px 0;'>🎉 Database Setup Complete!</h4>";
    echo "<p style='margin: 0;'>Your maintenance and analytics system is now ready to use.</p>";
    echo "</div>";
    
    // Insert some sample data for testing
    echo "<h4>📝 Adding Sample Data</h4>";
    
    try {
        // Get first few assets for sample data
        $stmt = $db->query("SELECT asset_id, asset_name FROM assets LIMIT 3");
        $sample_assets = [];
        while ($row = $stmt->fetch_assoc()) {
            $sample_assets[] = $row;
        }
        
        if (!empty($sample_assets)) {
            // Add sample transactions for analytics
            foreach ($sample_assets as $i => $asset) {
                // Add some checkout/checkin history
                for ($j = 0; $j < rand(2, 5); $j++) {
                    $checkout_date = date('Y-m-d H:i:s', strtotime("-" . rand(1, 30) . " days"));
                    $checkin_date = date('Y-m-d H:i:s', strtotime($checkout_date . " +" . rand(1, 7) . " days"));
                    $borrower_names = ['John Smith', 'Sarah Johnson', 'Mike Davis', 'Emily Chen', 'Alex Wilson'];
                    $borrower = $borrower_names[array_rand($borrower_names)];
                    
                    // Checkout transaction
                    $stmt = $db->prepare("INSERT IGNORE INTO transactions (asset_id, transaction_type, borrower_name, transaction_date, expected_return_date, condition_on_checkout) VALUES (?, 'checkout', ?, ?, ?, 'good')");
                    $expected_return = date('Y-m-d H:i:s', strtotime($checkout_date . " +3 days"));
                    $stmt->bind_param('ssss', $asset['asset_id'], $borrower, $checkout_date, $expected_return);
                    $stmt->execute();
                    
                    // Checkin transaction
                    $stmt = $db->prepare("INSERT IGNORE INTO transactions (asset_id, transaction_type, borrower_name, transaction_date, condition_on_return) VALUES (?, 'checkin', ?, ?, 'good')");
                    $stmt->bind_param('sss', $asset['asset_id'], $borrower, $checkin_date);
                    $stmt->execute();
                }
            }
            
            // Add sample maintenance task
            $sample_maintenance = "
                INSERT IGNORE INTO maintenance_schedule 
                (asset_id, maintenance_type, scheduled_date, next_maintenance_date, priority, notes, assigned_to) 
                VALUES 
                (?, 'routine', DATE_ADD(NOW(), INTERVAL 7 DAY), DATE_ADD(NOW(), INTERVAL 97 DAY), 'medium', 'Sample routine maintenance task', 'Tech Support')
            ";
            $stmt = $db->prepare($sample_maintenance);
            $stmt->bind_param('s', $sample_assets[0]['asset_id']);
            $stmt->execute();
            
            // Add sample issue
            $sample_issue = "
                INSERT IGNORE INTO maintenance_issues 
                (asset_id, reported_by, issue_type, description, severity, reported_date) 
                VALUES 
                (?, 'Admin', 'performance', 'Sample equipment issue for testing', 'medium', NOW())
            ";
            $stmt = $db->prepare($sample_issue);
            $stmt->bind_param('s', $sample_assets[0]['asset_id']);
            $stmt->execute();
            
            echo "✅ Sample transaction data, maintenance tasks, and issues added for testing<br>";
        }
    } catch (Exception $e) {
        echo "ℹ️ Sample data already exists or couldn't be added: " . $e->getMessage() . "<br>";
    }
}

echo "<div style='margin: 30px 0; text-align: center;'>";
echo "<a href='maintenance.php' style='background: #FFD700; color: #000; padding: 15px 30px; text-decoration: none; border-radius: 10px; font-weight: bold; border: 2px solid #000; margin-right: 10px;'>🔧 MAINTENANCE DASHBOARD</a>";
echo "<a href='dashboard_advanced.php' style='background: #4CAF50; color: #fff; padding: 15px 30px; text-decoration: none; border-radius: 10px; font-weight: bold; border: 2px solid #000;'>📊 ANALYTICS DASHBOARD</a>";
echo "</div>";

echo "<p style='margin-top: 30px; padding: 20px; background: #fff9c4; border-radius: 10px; border: 2px solid #ffd700;'>";
echo "💡 <strong>Note:</strong> You can now delete this setup file (setup_maintenance_db_fixed.php) as it's no longer needed.";
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