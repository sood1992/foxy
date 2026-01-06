<?php
// delete_asset.php - Asset Deletion Handler
// Fix session path issue
if (!session_id()) {
    ini_set('session.save_path', sys_get_temp_dir());
    session_start();
}

require_once 'config/database.php';
require_once 'classes/Asset.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit();
}

// Get asset ID from URL
$asset_id = $_GET['id'] ?? null;
if (!$asset_id) {
    $_SESSION['error'] = "No asset specified for deletion.";
    header("Location: assets.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();
$asset = new Asset($db);

// Get asset data to check if it exists and get its name
$asset_data = $asset->getById($asset_id);
if (!$asset_data) {
    $_SESSION['error'] = "Asset not found.";
    header("Location: assets.php");
    exit();
}

// Check if asset is currently checked out
if ($asset_data['status'] == 'checked_out') {
    $_SESSION['error'] = "Cannot delete asset '{$asset_data['asset_name']}' because it is currently checked out to {$asset_data['current_borrower']}.";
    header("Location: edit_asset.php?id=" . $asset_id);
    exit();
}

// Attempt to delete the asset
if ($asset->delete($asset_id)) {
    $_SESSION['success'] = "Asset '{$asset_data['asset_name']}' has been successfully deleted.";
} else {
    $errors = $asset->getErrors();
    $_SESSION['error'] = !empty($errors) ? implode(', ', $errors) : "Failed to delete asset.";
}

// Redirect back to assets page
header("Location: assets.php");
exit();
?>