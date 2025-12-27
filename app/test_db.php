<?php
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

if ($db) {
    echo "✅ Database connection successful!<br>";
    
    // Test if users table exists and has data
    $query = "SELECT username FROM users WHERE username = 'admin'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        echo "✅ Admin user found in database!<br>";
    } else {
        echo "❌ Admin user NOT found in database!<br>";
    }
} else {
    echo "❌ Database connection failed!<br>";
}
?>