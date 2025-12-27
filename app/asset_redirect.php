<?php
session_start();
$asset_id = $_GET['id'] ?? '';

if (empty($asset_id) || !preg_match('/^[A-Za-z0-9\-_]+$/', $asset_id)) {
    header("Location: /index.php");
    exit();
}

// Optional: Verify asset exists
require_once 'config/database.php';
try {
    $database = new Database();
    $db = $database->getConnection();
    
    $query = "SELECT asset_id FROM assets WHERE asset_id = :asset_id LIMIT 1";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':asset_id', $asset_id);
    $stmt->execute();
    
    if ($stmt->rowCount() == 0) {
        header("Location: /index.php?error=asset_not_found");
        exit();
    }
} catch (Exception $e) {
    // Continue even if database check fails
}

// Redirect to checkout
header("Location: /checkout.php?asset_id=" . urlencode($asset_id));
exit();
?>