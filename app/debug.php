<?php
// debug.php - Debug your cPanel setup
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h2>🔍 Debugging cPanel Setup</h2>";
echo "<hr>";

// Test 1: Basic PHP
echo "✅ PHP is working<br>";
echo "📊 PHP Version: " . phpversion() . "<br>";

// Test 2: Database connection
echo "<br><h3>Database Connection Test:</h3>";
try {
    if (file_exists('config/database.php')) {
        echo "✅ database.php file exists<br>";
        require_once 'config/database.php';
        echo "✅ database.php included successfully<br>";
        
        $database = new Database();
        echo "✅ Database class instantiated<br>";
        
        $db = $database->getConnection();
        echo "✅ Database connection successful<br>";
        echo "📊 Connection type: " . get_class($db) . "<br>";
        
        // Test assets table
        $result = $db->query("SELECT COUNT(*) as count FROM assets");
        if ($result) {
            $row = $result->fetch_assoc();
            echo "✅ Assets table accessible - {$row['count']} assets found<br>";
        }
    } else {
        echo "❌ config/database.php file not found<br>";
    }
} catch (Exception $e) {
    echo "❌ Database error: " . $e->getMessage() . "<br>";
}

// Test 3: Required classes
echo "<br><h3>Class Files Test:</h3>";
$required_classes = [
    'classes/Asset.php',
    'classes/Analytics.php', 
    'classes/MaintenanceTracker.php'
];

foreach ($required_classes as $class_file) {
    if (file_exists($class_file)) {
        echo "✅ {$class_file} exists<br>";
        try {
            require_once $class_file;
            echo "✅ {$class_file} included successfully<br>";
        } catch (Exception $e) {
            echo "❌ {$class_file} error: " . $e->getMessage() . "<br>";
        }
    } else {
        echo "❌ {$class_file} NOT FOUND<br>";
    }
}

// Test 4: Session
echo "<br><h3>Session Test:</h3>";
if (!session_id()) {
    session_start();
}
echo "✅ Session started<br>";
echo "📊 Session ID: " . session_id() . "<br>";

// Test 5: File permissions
echo "<br><h3>File Permissions:</h3>";
$files_to_check = [
    'dashboard_advanced.php',
    'maintenance.php',
    'config/',
    'classes/'
];

foreach ($files_to_check as $file) {
    if (file_exists($file)) {
        $perms = substr(sprintf('%o', fileperms($file)), -4);
        echo "✅ {$file}: {$perms}<br>";
    } else {
        echo "❌ {$file}: NOT FOUND<br>";
    }
}

// Test 6: Try loading dashboard directly
echo "<br><h3>Dashboard Direct Test:</h3>";
try {
    // Simulate logged in user
    $_SESSION['user_id'] = 1;
    $_SESSION['username'] = 'admin';
    
    echo "✅ Session variables set<br>";
    
    // Try to instantiate Analytics
    if (class_exists('Analytics')) {
        $analytics = new Analytics($db);
        echo "✅ Analytics class working<br>";
        
        $test_data = $analytics->getSummaryMetrics();
        echo "✅ Analytics data retrieved<br>";
        echo "📊 Sample data: " . json_encode($test_data) . "<br>";
    } else {
        echo "❌ Analytics class not found<br>";
    }
    
} catch (Exception $e) {
    echo "❌ Dashboard test error: " . $e->getMessage() . "<br>";
}

echo "<br><div style='background: #e8f5e8; padding: 15px; border-radius: 8px; margin: 20px 0;'>";
echo "🔧 <strong>Next Steps:</strong><br>";
echo "1. Check the errors above<br>";
echo "2. Make sure all required class files exist<br>";
echo "3. Verify database connection works<br>";
echo "4. Check file permissions on cPanel<br>";
echo "</div>";
?>

<style>
body {
    font-family: Arial, sans-serif;
    max-width: 1000px;
    margin: 0 auto;
    padding: 20px;
    background: #f5f5f5;
}
h2, h3 {
    color: #333;
    border-bottom: 2px solid #ddd;
    padding-bottom: 10px;
}
</style>