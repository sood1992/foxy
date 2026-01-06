<?php
// bulk_scanner_enhanced.php - Enhanced Bulk Scanner with Manual Search & QR Scanning
// Debug mode - remove in production
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

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

$database = new Database();
$db = $database->getConnection();
$asset = new Asset($db);

// Create equipment_photos table if it doesn't exist
try {
    $create_photos_table = "
    CREATE TABLE IF NOT EXISTS equipment_photos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        asset_id VARCHAR(50) NOT NULL,
        photo_type ENUM('checkout', 'checkin', 'maintenance', 'general') NOT NULL,
        photo_path VARCHAR(255) NOT NULL,
        borrower_name VARCHAR(100),
        upload_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        notes TEXT,
        file_size INT,
        mime_type VARCHAR(100),
        FOREIGN KEY (asset_id) REFERENCES assets(asset_id) ON DELETE CASCADE,
        INDEX idx_asset_date (asset_id, upload_date)
    )";
    $db->exec($create_photos_table);
    
    // Create uploads directory if it doesn't exist
    $upload_dir = 'uploads/equipment_photos';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
} catch (Exception $e) {
    error_log("Error creating photos table: " . $e->getMessage());
}

// Get all users for dropdown
$users_query = "SELECT username FROM users WHERE role IN ('admin', 'team_member') ORDER BY username";
$users_stmt = $db->prepare($users_query);
$users_stmt->execute();
$users = $users_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get categories for filtering
$categories_query = "SELECT DISTINCT category FROM assets ORDER BY category";
$categories_stmt = $db->prepare($categories_query);
$categories_stmt->execute();
$categories = $categories_stmt->fetchAll(PDO::FETCH_COLUMN);

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json');
    
    try {
        // Upload photo endpoint
        if ($_POST['action'] == 'upload_photo') {
            $asset_id = $_POST['asset_id'] ?? '';
            $photo_type = $_POST['photo_type'] ?? '';
            $borrower_name = $_POST['borrower_name'] ?? '';
            $notes = $_POST['notes'] ?? '';
            
            if (empty($asset_id) || empty($photo_type)) {
                echo json_encode(['success' => false, 'error' => 'Asset ID and photo type required']);
                exit();
            }
            
            if (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
                echo json_encode(['success' => false, 'error' => 'No photo uploaded or upload error']);
                exit();
            }
            
            $file = $_FILES['photo'];
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $max_size = 10 * 1024 * 1024; // 10MB
            
            // Validate file type
            if (!in_array($file['type'], $allowed_types)) {
                echo json_encode(['success' => false, 'error' => 'Invalid file type. Only JPEG, PNG, GIF, and WebP allowed']);
                exit();
            }
            
            // Validate file size
            if ($file['size'] > $max_size) {
                echo json_encode(['success' => false, 'error' => 'File too large. Maximum 10MB allowed']);
                exit();
            }
            
            // Generate unique filename
            $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = $asset_id . '_' . $photo_type . '_' . date('Y-m-d_H-i-s') . '_' . uniqid() . '.' . $file_extension;
            $upload_path = 'uploads/equipment_photos/' . $filename;
            
            // Move uploaded file
            if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                // Save to database
                $query = "INSERT INTO equipment_photos (asset_id, photo_type, photo_path, borrower_name, notes, file_size, mime_type) 
                         VALUES (:asset_id, :photo_type, :photo_path, :borrower_name, :notes, :file_size, :mime_type)";
                
                $stmt = $db->prepare($query);
                $stmt->bindParam(':asset_id', $asset_id);
                $stmt->bindParam(':photo_type', $photo_type);
                $stmt->bindParam(':photo_path', $upload_path);
                $stmt->bindParam(':borrower_name', $borrower_name);
                $stmt->bindParam(':notes', $notes);
                $stmt->bindParam(':file_size', $file['size']);
                $stmt->bindParam(':mime_type', $file['type']);
                
                if ($stmt->execute()) {
                    echo json_encode([
                        'success' => true, 
                        'photo_id' => $db->lastInsertId(),
                        'photo_path' => $upload_path,
                        'message' => 'Photo uploaded successfully'
                    ]);
                } else {
                    unlink($upload_path); // Delete file if database insert fails
                    echo json_encode(['success' => false, 'error' => 'Failed to save photo information']);
                }
            } else {
                echo json_encode(['success' => false, 'error' => 'Failed to upload photo']);
            }
            exit();
        }
        
        // Get equipment photos endpoint
        if ($_POST['action'] == 'get_equipment_photos') {
            $asset_id = $_POST['asset_id'] ?? '';
            
            if (empty($asset_id)) {
                echo json_encode(['success' => false, 'error' => 'Asset ID required']);
                exit();
            }
            
            $query = "SELECT * FROM equipment_photos WHERE asset_id = :asset_id ORDER BY upload_date DESC LIMIT 10";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':asset_id', $asset_id);
            $stmt->execute();
            $photos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(['success' => true, 'photos' => $photos]);
            exit();
        }
        
        // Search assets endpoint
        if ($_POST['action'] == 'search_assets') {
            $search_term = trim($_POST['search_term'] ?? '');
            $category_filter = trim($_POST['category_filter'] ?? '');
            $status_filter = trim($_POST['status_filter'] ?? '');
            $limit = intval($_POST['limit'] ?? 20);
            
            $where_conditions = [];
            $params = [];
            
            if (!empty($search_term)) {
                $where_conditions[] = "(asset_name LIKE :search OR asset_id LIKE :search OR description LIKE :search OR serial_number LIKE :search)";
                $params[':search'] = '%' . $search_term . '%';
            }
            
            if (!empty($category_filter)) {
                $where_conditions[] = "category = :category";
                $params[':category'] = $category_filter;
            }
            
            if (!empty($status_filter)) {
                $where_conditions[] = "status = :status";
                $params[':status'] = $status_filter;
            }
            
            $where_clause = empty($where_conditions) ? '' : 'WHERE ' . implode(' AND ', $where_conditions);
            
            $query = "SELECT asset_id, asset_name, category, status, current_borrower, description, serial_number 
                     FROM assets 
                     {$where_clause} 
                     ORDER BY asset_name 
                     LIMIT :limit";
            
            $stmt = $db->prepare($query);
            
            // Bind search parameters
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value, PDO::PARAM_STR);
            }
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            
            $stmt->execute();
            $assets = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(['success' => true, 'assets' => $assets]);
            exit();
        }
        
        // Debug endpoint - remove in production
        if ($_POST['action'] == 'debug_data') {
            $debug_info = [
                'post_data' => $_POST,
                'operation' => $_POST['operation'] ?? 'not_set',
                'asset_ids_raw' => $_POST['asset_ids'] ?? 'not_set',
                'asset_ids_decoded' => json_decode($_POST['asset_ids'] ?? '[]', true),
                'json_error' => json_last_error_msg(),
                'borrower' => $_POST['borrower_name'] ?? 'not_set',
                'return_date' => $_POST['expected_return_date'] ?? 'not_set',
                'current_time' => date('Y-m-d H:i:s'),
                'php_version' => PHP_VERSION,
                'asset_class_methods' => get_class_methods($asset)
            ];
            
            echo json_encode(['debug' => $debug_info]);
            exit();
        }
        
        if ($_POST['action'] == 'get_asset_info') {
            $asset_id = $_POST['asset_id'] ?? '';
            
            if (empty($asset_id)) {
                echo json_encode(['success' => false, 'message' => 'Asset ID is required']);
                exit();
            }
            
            $asset_info = $asset->getByAssetId($asset_id);
            
            if ($asset_info) {
                echo json_encode(['success' => true, 'asset' => $asset_info]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Asset not found']);
            }
            exit();
        }
        
        if ($_POST['action'] == 'process_batch') {
            // Validate required fields first
            if (!isset($_POST['operation']) || !isset($_POST['asset_ids'])) {
                echo json_encode(['success' => false, 'error' => 'Missing required fields: operation or asset_ids']);
                exit();
            }
            
            $operation = $_POST['operation'];
            
            // Validate JSON input
            $asset_ids_json = $_POST['asset_ids'];
            $asset_ids = json_decode($asset_ids_json, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                echo json_encode(['success' => false, 'error' => 'Invalid JSON in asset_ids: ' . json_last_error_msg()]);
                exit();
            }
            
            if (!is_array($asset_ids) || empty($asset_ids)) {
                echo json_encode(['success' => false, 'error' => 'Asset IDs must be a non-empty array']);
                exit();
            }
            
            $results = [];
            
            foreach ($asset_ids as $asset_id) {
                try {
                    // Validate asset_id format
                    if (empty($asset_id) || !is_string($asset_id)) {
                        $results[] = ['asset_id' => $asset_id, 'success' => false, 'message' => 'Invalid asset ID format'];
                        continue;
                    }
                    
                    // Check if asset exists
                    if (!method_exists($asset, 'getByAssetId')) {
                        $results[] = ['asset_id' => $asset_id, 'success' => false, 'message' => 'Asset class method getByAssetId not found'];
                        continue;
                    }
                    
                    $asset_info = $asset->getByAssetId($asset_id);
                    
                    if (!$asset_info) {
                        $results[] = ['asset_id' => $asset_id, 'success' => false, 'message' => 'Asset not found in database'];
                        continue;
                    }
                    
                    if ($operation == 'bulk_checkout') {
                        // Validate checkout requirements
                        if (!isset($_POST['borrower_name']) || !isset($_POST['expected_return_date'])) {
                            $results[] = ['asset_id' => $asset_id, 'asset_name' => $asset_info['asset_name'], 'success' => false, 'message' => 'Missing checkout data'];
                            continue;
                        }
                        
                        $borrower = trim($_POST['borrower_name']);
                        $expected_return = $_POST['expected_return_date'];
                        $purpose = isset($_POST['purpose']) ? trim($_POST['purpose']) : '';
                        
                        // Validate required fields
                        if (empty($borrower)) {
                            $results[] = ['asset_id' => $asset_id, 'asset_name' => $asset_info['asset_name'], 'success' => false, 'message' => 'Borrower name required'];
                            continue;
                        }
                        
                        if (empty($expected_return)) {
                            $results[] = ['asset_id' => $asset_id, 'asset_name' => $asset_info['asset_name'], 'success' => false, 'message' => 'Return date required'];
                            continue;
                        }
                        
                        // Convert and validate date format
                        $return_timestamp = strtotime($expected_return);
                        if ($return_timestamp === false) {
                            $results[] = ['asset_id' => $asset_id, 'asset_name' => $asset_info['asset_name'], 'success' => false, 'message' => 'Invalid date format: ' . $expected_return];
                            continue;
                        }
                        
                        $expected_return_formatted = date('Y-m-d H:i:s', $return_timestamp);
                        
                        // Check asset status
                        if (!isset($asset_info['status']) || $asset_info['status'] != 'available') {
                            $status = isset($asset_info['status']) ? $asset_info['status'] : 'unknown';
                            $results[] = ['asset_id' => $asset_id, 'asset_name' => $asset_info['asset_name'], 'success' => false, 'message' => 'Not available (Status: ' . $status . ')'];
                            continue;
                        }
                        
                        // Check if checkOut method exists
                        if (!method_exists($asset, 'checkOut')) {
                            $results[] = ['asset_id' => $asset_id, 'asset_name' => $asset_info['asset_name'], 'success' => false, 'message' => 'Asset class method checkOut not found'];
                            continue;
                        }
                        
                        // Attempt checkout
                        try {
                            $checkout_result = $asset->checkOut($asset_id, $borrower, $expected_return_formatted, $purpose);
                            if ($checkout_result) {
                                // Handle photo upload if provided
                                $photo_uploaded = false;
                                if (isset($_FILES['checkout_photos']) && isset($_FILES['checkout_photos']['tmp_name'][$asset_id])) {
                                    $photo_uploaded = handleEquipmentPhoto($asset_id, 'checkout', $borrower, $db);
                                }
                                
                                $message = 'Checked out successfully';
                                if ($photo_uploaded) {
                                    $message .= ' (with photo)';
                                }
                                
                                $results[] = ['asset_id' => $asset_id, 'asset_name' => $asset_info['asset_name'], 'success' => true, 'message' => $message];
                            } else {
                                $results[] = ['asset_id' => $asset_id, 'asset_name' => $asset_info['asset_name'], 'success' => false, 'message' => 'Checkout failed - database operation returned false'];
                            }
                        } catch (Exception $checkout_error) {
                            $results[] = ['asset_id' => $asset_id, 'asset_name' => $asset_info['asset_name'], 'success' => false, 'message' => 'Checkout error: ' . $checkout_error->getMessage()];
                        }
                        
                    } elseif ($operation == 'bulk_checkin') {
                        // Validate checkin requirements
                        if (!isset($_POST['condition'])) {
                            $results[] = ['asset_id' => $asset_id, 'asset_name' => $asset_info['asset_name'], 'success' => false, 'message' => 'Missing condition data'];
                            continue;
                        }
                        
                        $condition = trim($_POST['condition']);
                        $notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';
                        
                        // Validate required fields
                        if (empty($condition)) {
                            $results[] = ['asset_id' => $asset_id, 'asset_name' => $asset_info['asset_name'], 'success' => false, 'message' => 'Condition required'];
                            continue;
                        }
                        
                        // Validate condition value
                        $valid_conditions = ['excellent', 'good', 'needs_repair'];
                        if (!in_array($condition, $valid_conditions)) {
                            $results[] = ['asset_id' => $asset_id, 'asset_name' => $asset_info['asset_name'], 'success' => false, 'message' => 'Invalid condition value: ' . $condition];
                            continue;
                        }
                        
                        // Check asset status
                        if (!isset($asset_info['status']) || $asset_info['status'] != 'checked_out') {
                            $status = isset($asset_info['status']) ? $asset_info['status'] : 'unknown';
                            $results[] = ['asset_id' => $asset_id, 'asset_name' => $asset_info['asset_name'], 'success' => false, 'message' => 'Not checked out (Status: ' . $status . ')'];
                            continue;
                        }
                        
                        // Check if checkIn method exists
                        if (!method_exists($asset, 'checkIn')) {
                            $results[] = ['asset_id' => $asset_id, 'asset_name' => $asset_info['asset_name'], 'success' => false, 'message' => 'Asset class method checkIn not found'];
                            continue;
                        }
                        
                        // Attempt checkin
                        try {
                            $checkin_result = $asset->checkIn($asset_id, $condition, $notes);
                            if ($checkin_result) {
                                // Handle photo upload if provided
                                $photo_uploaded = false;
                                if (isset($_FILES['checkin_photos']) && isset($_FILES['checkin_photos']['tmp_name'][$asset_id])) {
                                    $photo_uploaded = handleEquipmentPhoto($asset_id, 'checkin', $borrower, $db);
                                }
                                
                                $message = 'Checked in successfully';
                                if ($photo_uploaded) {
                                    $message .= ' (with photo)';
                                }
                                
                                $results[] = ['asset_id' => $asset_id, 'asset_name' => $asset_info['asset_name'], 'success' => true, 'message' => $message];
                            } else {
                                $results[] = ['asset_id' => $asset_id, 'asset_name' => $asset_info['asset_name'], 'success' => false, 'message' => 'Checkin failed - database operation returned false'];
                            }
                        } catch (Exception $checkin_error) {
                            $results[] = ['asset_id' => $asset_id, 'asset_name' => $asset_info['asset_name'], 'success' => false, 'message' => 'Checkin error: ' . $checkin_error->getMessage()];
                        }
                        
                    } else {
                        $results[] = ['asset_id' => $asset_id, 'success' => false, 'message' => 'Invalid operation: ' . $operation];
                    }
                    
                } catch (Exception $item_error) {
                    $results[] = ['asset_id' => $asset_id, 'success' => false, 'message' => 'Item processing error: ' . $item_error->getMessage()];
                }
            }
            
            echo json_encode(['success' => true, 'results' => $results]);
            exit();
        }
        
        // If we get here, unknown action
        echo json_encode(['success' => false, 'error' => 'Unknown action: ' . ($_POST['action'] ?? 'not_set')]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage(), 'trace' => $e->getTraceAsString()]);
    }
    
    exit();
}

// Function to handle equipment photo uploads
function handleEquipmentPhoto($asset_id, $photo_type, $borrower_name, $db) {
    try {
        $file_key = $photo_type . '_photos';
        
        if (!isset($_FILES[$file_key]) || !isset($_FILES[$file_key]['tmp_name'][$asset_id])) {
            return false;
        }
        
        $file = [
            'name' => $_FILES[$file_key]['name'][$asset_id],
            'type' => $_FILES[$file_key]['type'][$asset_id],
            'tmp_name' => $_FILES[$file_key]['tmp_name'][$asset_id],
            'size' => $_FILES[$file_key]['size'][$asset_id],
            'error' => $_FILES[$file_key]['error'][$asset_id]
        ];
        
        if ($file['error'] !== UPLOAD_ERR_OK || empty($file['tmp_name'])) {
            return false;
        }
        
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $max_size = 10 * 1024 * 1024; // 10MB
        
        if (!in_array($file['type'], $allowed_types) || $file['size'] > $max_size) {
            return false;
        }
        
        // Generate unique filename
        $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = $asset_id . '_' . $photo_type . '_' . date('Y-m-d_H-i-s') . '_' . uniqid() . '.' . $file_extension;
        $upload_path = 'uploads/equipment_photos/' . $filename;
        
        if (move_uploaded_file($file['tmp_name'], $upload_path)) {
            // Save to database
            $query = "INSERT INTO equipment_photos (asset_id, photo_type, photo_path, borrower_name, file_size, mime_type) 
                     VALUES (:asset_id, :photo_type, :photo_path, :borrower_name, :file_size, :mime_type)";
            
            $stmt = $db->prepare($query);
            $stmt->bindParam(':asset_id', $asset_id);
            $stmt->bindParam(':photo_type', $photo_type);
            $stmt->bindParam(':photo_path', $upload_path);
            $stmt->bindParam(':borrower_name', $borrower_name);
            $stmt->bindParam(':file_size', $file['size']);
            $stmt->bindParam(':mime_type', $file['type']);
            
            return $stmt->execute();
        }
        
        return false;
    } catch (Exception $e) {
        error_log("Photo upload error: " . $e->getMessage());
        return false;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bulk Equipment Scanner - Neofox Gear Control</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
    <style>
        :root {
            --yellow-primary: #FFD700;
            --yellow-light: #FFF8DC;
            --yellow-dark: #DAA520;
            --black-primary: #1a1a1a;
            --black-light: #2d2d2d;
            --gray-light: #f8f9fa;
            --shadow-soft: 0 2px 10px rgba(0,0,0,0.1);
            --shadow-hover: 0 4px 20px rgba(0,0,0,0.15);
        }

        body {
            background: linear-gradient(135deg, var(--yellow-primary) 0%, var(--yellow-light) 100%);
            min-height: 100vh;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
        }

        .navbar {
            background: var(--black-primary) !important;
            border-bottom: 3px solid var(--yellow-primary);
            box-shadow: var(--shadow-soft);
        }

        .navbar-brand, .nav-link {
            color: var(--yellow-primary) !important;
            font-weight: 600;
        }

        .nav-link:hover {
            color: white !important;
            transform: translateY(-1px);
        }

        /* Main Container */
        .main-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem 1rem;
        }

        .page-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .page-title {
            color: var(--black-primary);
            font-size: 2.2rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
        }

        /* Step Cards */
        .step-card {
            background: white;
            border-radius: 15px;
            box-shadow: var(--shadow-soft);
            margin-bottom: 1.5rem;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .step-card:hover {
            box-shadow: var(--shadow-hover);
        }

        .step-card.disabled {
            opacity: 0.6;
            pointer-events: none;
        }

        .step-header {
            background: var(--black-primary);
            color: var(--yellow-primary);
            padding: 1rem 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .step-number {
            background: var(--yellow-primary);
            color: var(--black-primary);
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.9rem;
        }

        .step-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin: 0;
        }

        .step-body {
            padding: 1.5rem;
        }

        /* Mode Selection */
        .mode-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .mode-card {
            background: var(--gray-light);
            border: 3px solid transparent;
            border-radius: 12px;
            padding: 1.5rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .mode-card:hover {
            border-color: var(--yellow-dark);
            transform: translateY(-2px);
        }

        .mode-card.active {
            border-color: var(--yellow-primary);
            background: rgba(255, 215, 0, 0.1);
        }

        .mode-card.checkout.active {
            background: rgba(40, 167, 69, 0.1);
            border-color: #28a745;
        }

        .mode-card.checkin.active {
            background: rgba(255, 193, 7, 0.1);
            border-color: #ffc107;
        }

        .mode-icon {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }

        .mode-name {
            font-weight: 600;
            margin: 0;
        }

        /* Borrower Selection */
        .borrower-section {
            background: rgba(40, 167, 69, 0.1);
            border: 2px solid rgba(40, 167, 69, 0.3);
            border-radius: 10px;
            padding: 1rem;
            margin-top: 1rem;
            display: none;
        }

        /* Add Equipment Methods */
        .method-tabs {
            display: flex;
            background: var(--gray-light);
            border-radius: 10px;
            padding: 0.25rem;
            margin-bottom: 1rem;
        }

        .method-tab {
            flex: 1;
            background: transparent;
            border: none;
            padding: 0.75rem;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .method-tab.active {
            background: white;
            color: var(--black-primary);
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .method-content {
            display: none;
        }

        .method-content.active {
            display: block;
        }

        /* Scanner */
        .scanner-area {
            background: var(--gray-light);
            border: 2px dashed #ddd;
            border-radius: 12px;
            padding: 2rem;
            text-align: center;
            margin-bottom: 1rem;
        }

        .scanner-area.ready {
            border-color: var(--yellow-primary);
            background: rgba(255, 215, 0, 0.05);
        }

        #qr-reader {
            border-radius: 10px;
            overflow: hidden;
            margin: 1rem 0;
        }

        /* Search */
        .search-grid {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .search-results {
            max-height: 300px;
            overflow-y: auto;
            border: 1px solid #ddd;
            border-radius: 8px;
            background: white;
            margin-top: 1rem;
        }

        .search-item {
            padding: 1rem;
            border-bottom: 1px solid #f0f0f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: background 0.2s;
        }

        .search-item:hover {
            background: var(--yellow-light);
        }

        .search-item:last-child {
            border-bottom: none;
        }

        /* Selected Items */
        .selected-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 1.5rem;
        }

        .items-list {
            background: white;
            border-radius: 12px;
            padding: 1rem;
            max-height: 400px;
            overflow-y: auto;
        }

        .item-card {
            background: var(--gray-light);
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 0.75rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .item-card:last-child {
            margin-bottom: 0;
        }

        /* Photo Upload */
        .photo-section {
            background: white;
            border-radius: 12px;
            padding: 1rem;
        }

        .photo-item {
            background: var(--gray-light);
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
        }

        .photo-item:last-child {
            margin-bottom: 0;
        }

        .photo-preview {
            width: 60px;
            height: 60px;
            border-radius: 8px;
            object-fit: cover;
            border: 2px solid var(--yellow-primary);
            margin-top: 0.5rem;
            cursor: pointer;
        }

        /* Process Section */
        .process-section {
            background: var(--gray-light);
            padding: 1rem;
            border-radius: 10px;
            margin-top: 1rem;
        }

        /* Buttons */
        .btn-yellow {
            background: var(--yellow-primary);
            border: none;
            color: var(--black-primary);
            font-weight: 600;
            border-radius: 8px;
            padding: 0.5rem 1rem;
        }

        .btn-yellow:hover {
            background: var(--yellow-dark);
            color: var(--black-primary);
            transform: translateY(-1px);
        }

        .btn-process {
            width: 100%;
            padding: 1rem;
            font-size: 1.1rem;
            font-weight: 700;
            border-radius: 10px;
        }

        /* Status Badges */
        .status-badge {
            padding: 0.25rem 0.5rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .status-available { background: rgba(40, 167, 69, 0.1); color: #28a745; }
        .status-checked-out { background: rgba(255, 193, 7, 0.1); color: #856404; }
        .status-maintenance { background: rgba(220, 53, 69, 0.1); color: #dc3545; }

        /* Form Elements */
        .form-control, .form-select {
            border-radius: 8px;
            border: 2px solid #e9ecef;
            padding: 0.75rem;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--yellow-primary);
            box-shadow: 0 0 0 0.2rem rgba(255, 215, 0, 0.25);
        }

        /* Empty States */
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: #6c757d;
        }

        .empty-state i {
            font-size: 3rem;
            color: var(--yellow-primary);
            margin-bottom: 1rem;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .mode-grid {
                grid-template-columns: 1fr;
            }
            
            .search-grid {
                grid-template-columns: 1fr;
            }
            
            .selected-grid {
                grid-template-columns: 1fr;
            }
            
            .page-title {
                font-size: 1.8rem;
            }
        }

        /* Results */
        .results-card {
            margin-top: 1.5rem;
        }

        .result-item {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 0.75rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .result-success {
            background: rgba(40, 167, 69, 0.1);
            border-left: 4px solid #28a745;
        }

        .result-error {
            background: rgba(220, 53, 69, 0.1);
            border-left: 4px solid #dc3545;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-cogs"></i> Neofox Gear Control
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="index.php">Dashboard</a>
                <a class="nav-link" href="assets.php">Assets</a>
                <a class="nav-link" href="bulk_manage.php">Bulk Manage</a>
                <a class="nav-link" href="logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <div class="main-container">
        <!-- Page Header -->
        <div class="page-header">
            <h1 class="page-title">
                <i class="fas fa-qrcode"></i> Bulk Equipment Scanner
            </h1>
            <p class="text-muted">Quick checkout and checkin for multiple items</p>
        </div>

        <!-- Step 1: Select Mode -->
        <div class="step-card" id="step-1">
            <div class="step-header">
                <div class="step-number">1</div>
                <h3 class="step-title">Choose Operation</h3>
            </div>
            <div class="step-body">
                <div class="mode-grid">
                    <div class="mode-card checkout" onclick="setMode('checkout')" id="checkout-card">
                        <div class="mode-icon">
                            <i class="fas fa-sign-out-alt text-success"></i>
                        </div>
                        <h5 class="mode-name">Checkout</h5>
                        <small class="text-muted">Lend equipment</small>
                    </div>
                    <div class="mode-card checkin" onclick="setMode('checkin')" id="checkin-card">
                        <div class="mode-icon">
                            <i class="fas fa-sign-in-alt text-warning"></i>
                        </div>
                        <h5 class="mode-name">Check-in</h5>
                        <small class="text-muted">Return equipment</small>
                    </div>
                </div>
                
                <!-- Borrower Selection (only for checkout) -->
                <div class="borrower-section" id="borrower-section">
                    <label class="form-label fw-bold">
                        <i class="fas fa-user"></i> Select Borrower
                    </label>
                    <select class="form-select" id="borrower-select">
                        <option value="">Choose team member...</option>
                        <?php foreach ($users as $user): ?>
                        <option value="<?php echo htmlspecialchars($user['username']); ?>">
                            <?php echo htmlspecialchars($user['username']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <!-- Step 2: Add Equipment -->
        <div class="step-card disabled" id="step-2">
            <div class="step-header">
                <div class="step-number">2</div>
                <h3 class="step-title">Add Equipment</h3>
            </div>
            <div class="step-body">
                <!-- Method Tabs -->
                <div class="method-tabs">
                    <button class="method-tab active" onclick="switchMethod('scanner')">
                        <i class="fas fa-qrcode"></i> QR Scanner
                    </button>
                    <button class="method-tab" onclick="switchMethod('search')">
                        <i class="fas fa-search"></i> Manual Search
                    </button>
                </div>

                <!-- QR Scanner Method -->
                <div class="method-content active" id="scanner-method">
                    <div class="scanner-area" id="scanner-area">
                        <i class="fas fa-qrcode fa-3x text-muted mb-2"></i>
                        <h5>QR Code Scanner</h5>
                        <p class="text-muted mb-3">Point camera at equipment QR codes</p>
                        <button class="btn btn-yellow" onclick="toggleScanner()" id="scanner-btn">
                            <i class="fas fa-play"></i> Start Scanner
                        </button>
                    </div>
                    <div id="qr-reader" style="display: none;"></div>
                </div>

                <!-- Manual Search Method -->
                <div class="method-content" id="search-method">
                    <div class="search-grid">
                        <div>
                            <label class="form-label">Search Equipment</label>
                            <input type="text" class="form-control" id="search-input" placeholder="Equipment name, ID, or serial..." oninput="searchEquipment()">
                        </div>
                        <div>
                            <label class="form-label">Category</label>
                            <select class="form-select" id="category-filter" onchange="searchEquipment()">
                                <option value="">All Categories</option>
                                <?php foreach ($categories as $category): ?>
                                <option value="<?php echo htmlspecialchars($category); ?>">
                                    <?php echo htmlspecialchars($category); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Status</label>
                            <select class="form-select" id="status-filter" onchange="searchEquipment()">
                                <option value="">All Status</option>
                                <option value="available">Available</option>
                                <option value="checked_out">Checked Out</option>
                                <option value="maintenance">Maintenance</option>
                            </select>
                        </div>
                    </div>
                    
                    <div id="search-results" class="search-results" style="display: none;">
                        <!-- Search results will appear here -->
                    </div>
                </div>
            </div>
        </div>

        <!-- Step 3: Review & Upload Photos -->
        <div class="step-card disabled" id="step-3">
            <div class="step-header">
                <div class="step-number">3</div>
                <h3 class="step-title">Review & Photos</h3>
                <div class="ms-auto">
                    <span class="badge bg-light text-dark" id="items-count">0 items</span>
                </div>
            </div>
            <div class="step-body">
                <div class="selected-grid">
                    <!-- Selected Items -->
                    <div>
                        <h6 class="fw-bold mb-3">Selected Equipment</h6>
                        <div class="items-list" id="selected-items">
                            <div class="empty-state">
                                <i class="fas fa-plus-circle"></i>
                                <h6>No Equipment Selected</h6>
                                <p class="mb-0">Add equipment using QR scanner or search</p>
                            </div>
                        </div>
                    </div>

                    <!-- Photo Upload -->
                    <div>
                        <h6 class="fw-bold mb-3">
                            <i class="fas fa-camera"></i> Equipment Photos
                            <small class="text-muted fw-normal">(Optional)</small>
                        </h6>
                        <div class="photo-section" id="photo-uploads">
                            <div class="empty-state">
                                <i class="fas fa-camera"></i>
                                <p class="mb-0">Add equipment to upload photos</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Additional Settings -->
                <div id="additional-settings" style="display: none;">
                    <!-- Checkout Settings -->
                    <div id="checkout-settings" style="display: none;">
                        <h6 class="fw-bold mt-4 mb-3">Checkout Details</h6>
                        <div class="row">
                            <div class="col-md-6">
                                <label class="form-label">Expected Return Date *</label>
                                <input type="datetime-local" class="form-control" id="return-date">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Purpose</label>
                                <input type="text" class="form-control" id="purpose" placeholder="Project or shoot purpose...">
                            </div>
                        </div>
                    </div>

                    <!-- Checkin Settings -->
                    <div id="checkin-settings" style="display: none;">
                        <h6 class="fw-bold mt-4 mb-3">Return Details</h6>
                        <div class="row">
                            <div class="col-md-6">
                                <label class="form-label">Equipment Condition *</label>
                                <select class="form-select" id="condition">
                                    <option value="">Select condition...</option>
                                    <option value="excellent">Excellent</option>
                                    <option value="good">Good</option>
                                    <option value="needs_repair">Needs Repair</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Return Notes</label>
                                <input type="text" class="form-control" id="notes" placeholder="Any issues or observations...">
                            </div>
                        </div>
                    </div>

                    <!-- Process Section -->
                    <div class="process-section">
                        <button class="btn btn-success btn-process" onclick="processItems()" id="process-btn">
                            <i class="fas fa-check"></i> Process Items
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Results -->
        <div class="step-card results-card" id="results-card" style="display: none;">
            <div class="step-header">
                <div class="step-number">✓</div>
                <h3 class="step-title">Results</h3>
            </div>
            <div class="step-body">
                <div id="results-content">
                    <!-- Results will appear here -->
                </div>
            </div>
        </div>
    </div>

    <!-- Photo History Modal -->
    <div class="modal fade" id="photoModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-camera"></i> Equipment Photo History
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="photo-gallery">
                        <!-- Photos will be loaded here -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Global variables
        let currentMode = null;
        let selectedItems = new Map();
        let html5Scanner = null;
        let isScanning = false;
        let searchTimeout = null;

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            updateUI();
        });

        function setMode(mode) {
            currentMode = mode;
            
            // Update mode cards
            document.querySelectorAll('.mode-card').forEach(card => {
                card.classList.remove('active');
            });
            document.getElementById(mode + '-card').classList.add('active');
            
            // Show/hide borrower section
            const borrowerSection = document.getElementById('borrower-section');
            if (mode === 'checkout') {
                borrowerSection.style.display = 'block';
                
                // Set default return date
                const tomorrow = new Date();
                tomorrow.setDate(tomorrow.getDate() + 1);
                tomorrow.setHours(17, 0, 0, 0);
                document.getElementById('return-date').value = tomorrow.toISOString().slice(0, 16);
            } else {
                borrowerSection.style.display = 'none';
            }
            
            // Enable step 2
            document.getElementById('step-2').classList.remove('disabled');
            
            updateUI();
        }

        function switchMethod(method) {
            // Update tabs
            document.querySelectorAll('.method-tab').forEach(tab => {
                tab.classList.remove('active');
            });
            event.target.classList.add('active');
            
            // Update content
            document.querySelectorAll('.method-content').forEach(content => {
                content.classList.remove('active');
            });
            document.getElementById(method + '-method').classList.add('active');
            
            // Stop scanner if switching away
            if (method !== 'scanner' && isScanning) {
                stopScanner();
            }
        }

        function toggleScanner() {
            if (!currentMode) {
                alert('Please select a mode first');
                return;
            }
            
            if (isScanning) {
                stopScanner();
            } else {
                startScanner();
            }
        }

        function startScanner() {
            html5Scanner = new Html5QrcodeScanner("qr-reader", {
                fps: 10,
                qrbox: { width: 250, height: 250 }
            });
            
            html5Scanner.render(onScanSuccess, onScanError);
            
            document.getElementById('qr-reader').style.display = 'block';
            document.getElementById('scanner-area').classList.add('ready');
            document.getElementById('scanner-btn').innerHTML = '<i class="fas fa-stop"></i> Stop Scanner';
            isScanning = true;
        }

        function stopScanner() {
            if (html5Scanner) {
                html5Scanner.clear();
                document.getElementById('qr-reader').style.display = 'none';
                document.getElementById('scanner-area').classList.remove('ready');
                document.getElementById('scanner-btn').innerHTML = '<i class="fas fa-play"></i> Start Scanner';
                isScanning = false;
            }
        }

        // UPDATED QR SCANNING FUNCTION - HANDLES BOTH OLD AND NEW FORMATS
        function onScanSuccess(decodedText) {
            let assetId = null;
            
            // Try new format first (just asset ID)
            // Check if it looks like an asset ID (CAM001, AUD002, etc.)
            const assetIdPattern = /^[A-Z]{3}\d{3}$/;
            if (assetIdPattern.test(decodedText.trim())) {
                assetId = decodedText.trim();
            } else {
                // Try old format (URL with asset_id parameter)
                const match = decodedText.match(/asset_id=([^&]+)/);
                if (match) {
                    assetId = match[1];
                } else {
                    // Try even more flexible parsing for custom asset IDs
                    const trimmed = decodedText.trim();
                    // If it's a short string (6 chars or less) and alphanumeric, assume it's an asset ID
                    if (trimmed.length <= 10 && /^[A-Za-z0-9]+$/.test(trimmed)) {
                        assetId = trimmed;
                    }
                }
            }
            
            if (assetId) {
                if (!selectedItems.has(assetId)) {
                    addEquipment(assetId);
                    showToast('success', `Scanned: ${assetId}`);
                } else {
                    showToast('warning', 'Equipment already added');
                }
            } else {
                showToast('error', 'Invalid QR code format');
                console.log('Unrecognized QR content:', decodedText); // For debugging
            }
        }

        function onScanError(error) {
            // Silent
        }

        function searchEquipment() {
            if (searchTimeout) clearTimeout(searchTimeout);
            
            searchTimeout = setTimeout(() => {
                const searchTerm = document.getElementById('search-input').value.trim();
                const category = document.getElementById('category-filter').value;
                const status = document.getElementById('status-filter').value;
                
                if (searchTerm || category || status) {
                    performSearch(searchTerm, category, status);
                } else {
                    document.getElementById('search-results').style.display = 'none';
                }
            }, 300);
        }

        function performSearch(searchTerm, category, status) {
            const formData = new FormData();
            formData.append('ajax', '1');
            formData.append('action', 'search_assets');
            formData.append('search_term', searchTerm);
            formData.append('category_filter', category);
            formData.append('status_filter', status);
            
            fetch('bulk_scanner_v2.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displaySearchResults(data.assets);
                }
            })
            .catch(error => {
                console.error('Search error:', error);
                showToast('error', 'Search failed');
            });
        }

        function displaySearchResults(assets) {
            const container = document.getElementById('search-results');
            
            if (assets.length === 0) {
                container.innerHTML = '<div class="text-center p-3 text-muted">No equipment found</div>';
                container.style.display = 'block';
                return;
            }
            
            let html = '';
            assets.forEach(asset => {
                const isAdded = selectedItems.has(asset.asset_id);
                const canAdd = (currentMode === 'checkout' && asset.status === 'available') ||
                              (currentMode === 'checkin' && asset.status === 'checked_out');
                
                const statusClass = asset.status.replace('_', '-');
                
                html += `
                    <div class="search-item">
                        <div>
                            <div class="fw-bold">${asset.asset_name}</div>
                            <small class="text-muted">${asset.category} - ${asset.asset_id}</small>
                            <div class="mt-1">
                                <span class="status-badge status-${statusClass}">${asset.status.replace('_', ' ')}</span>
                                ${asset.current_borrower ? '<small class="text-info ms-2">with ' + asset.current_borrower + '</small>' : ''}
                            </div>
                        </div>
                        <div class="d-flex gap-2">
                            <button class="btn btn-sm btn-outline-info" onclick="viewPhotos('${asset.asset_id}')" title="View Photos">
                                <i class="fas fa-images"></i>
                            </button>
                            ${isAdded ? 
                                '<button class="btn btn-sm btn-outline-success" disabled><i class="fas fa-check"></i> Added</button>' :
                                (canAdd ? 
                                    `<button class="btn btn-sm btn-yellow" onclick="addEquipment('${asset.asset_id}')"><i class="fas fa-plus"></i> Add</button>` :
                                    '<button class="btn btn-sm btn-outline-secondary" disabled>Not Available</button>'
                                )
                            }
                        </div>
                    </div>
                `;
            });
            
            container.innerHTML = html;
            container.style.display = 'block';
        }

        function addEquipment(assetId) {
            const formData = new FormData();
            formData.append('ajax', '1');
            formData.append('action', 'get_asset_info');
            formData.append('asset_id', assetId);
            
            fetch('bulk_scanner_v2.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    selectedItems.set(assetId, data.asset);
                    updateUI();
                    showToast('success', `Added: ${data.asset.asset_name}`);
                    
                    // Refresh search results
                    if (document.getElementById('search-results').style.display === 'block') {
                        searchEquipment();
                    }
                } else {
                    showToast('error', data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('error', 'Failed to add equipment');
            });
        }

        function removeEquipment(assetId) {
            selectedItems.delete(assetId);
            updateUI();
            
            // Refresh search results
            if (document.getElementById('search-results').style.display === 'block') {
                searchEquipment();
            }
        }

        function updateUI() {
            const count = selectedItems.size;
            
            // Update count
            document.getElementById('items-count').textContent = count + ' items';
            
            // Enable/disable step 3
            if (count > 0) {
                document.getElementById('step-3').classList.remove('disabled');
                document.getElementById('additional-settings').style.display = 'block';
                
                // Show mode-specific settings
                if (currentMode === 'checkout') {
                    document.getElementById('checkout-settings').style.display = 'block';
                    document.getElementById('checkin-settings').style.display = 'none';
                } else if (currentMode === 'checkin') {
                    document.getElementById('checkout-settings').style.display = 'none';
                    document.getElementById('checkin-settings').style.display = 'block';
                }
            } else {
                document.getElementById('step-3').classList.add('disabled');
                document.getElementById('additional-settings').style.display = 'none';
            }
            
            updateSelectedItems();
            updatePhotos();
            updateProcessButton();
        }

        function updateSelectedItems() {
            const container = document.getElementById('selected-items');
            
            if (selectedItems.size === 0) {
                container.innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-plus-circle"></i>
                        <h6>No Equipment Selected</h6>
                        <p class="mb-0">Add equipment using QR scanner or search</p>
                    </div>
                `;
                return;
            }
            
            let html = '';
            selectedItems.forEach((asset, assetId) => {
                const statusClass = asset.status.replace('_', '-');
                
                html += `
                    <div class="item-card">
                        <div>
                            <div class="fw-bold">${asset.asset_name}</div>
                            <small class="text-muted">${asset.category} - ${assetId}</small>
                            <div class="mt-1">
                                <span class="status-badge status-${statusClass}">${asset.status.replace('_', ' ')}</span>
                            </div>
                        </div>
                        <button class="btn btn-sm btn-outline-danger" onclick="removeEquipment('${assetId}')">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                `;
            });
            
            container.innerHTML = html;
        }

        function updatePhotos() {
            const container = document.getElementById('photo-uploads');
            
            if (selectedItems.size === 0) {
                container.innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-camera"></i>
                        <p class="mb-0">Add equipment to upload photos</p>
                    </div>
                `;
                return;
            }
            
            let html = '';
            selectedItems.forEach((asset, assetId) => {
                html += `
                    <div class="photo-item">
                        <label class="form-label fw-bold small">
                            <i class="fas fa-camera text-warning"></i> ${asset.asset_name}
                        </label>
                        <input type="file" class="form-control form-control-sm" 
                               name="${currentMode}_photos[${assetId}]" 
                               accept="image/*" 
                               onchange="previewPhoto(this, '${assetId}')">
                        <img id="preview-${assetId}" class="photo-preview" style="display: none;" onclick="openPhoto(this.src)">
                    </div>
                `;
            });
            
            container.innerHTML = html;
        }

        function previewPhoto(input, assetId) {
            const preview = document.getElementById('preview-' + assetId);
            
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                };
                reader.readAsDataURL(input.files[0]);
            } else {
                preview.style.display = 'none';
            }
        }

        function openPhoto(src) {
            window.open(src, '_blank');
        }

        function updateProcessButton() {
            const btn = document.getElementById('process-btn');
            const count = selectedItems.size;
            
            if (currentMode === 'checkout') {
                btn.innerHTML = `<i class="fas fa-sign-out-alt"></i> Checkout ${count} Items`;
                btn.className = 'btn btn-success btn-process';
            } else if (currentMode === 'checkin') {
                btn.innerHTML = `<i class="fas fa-sign-in-alt"></i> Check-in ${count} Items`;
                btn.className = 'btn btn-warning btn-process';
            } else {
                btn.innerHTML = `<i class="fas fa-check"></i> Process ${count} Items`;
                btn.className = 'btn btn-primary btn-process';
            }
        }

        function processItems() {
            if (selectedItems.size === 0) {
                showToast('error', 'No items to process');
                return;
            }
            
            if (!validateForm()) {
                return;
            }
            
            if (!confirm(`Process ${selectedItems.size} items?`)) {
                return;
            }
            
            const formData = new FormData();
            formData.append('ajax', '1');
            formData.append('action', 'process_batch');
            formData.append('operation', 'bulk_' + currentMode);
            formData.append('asset_ids', JSON.stringify(Array.from(selectedItems.keys())));
            
            if (currentMode === 'checkout') {
                formData.append('borrower_name', document.getElementById('borrower-select').value);
                formData.append('expected_return_date', document.getElementById('return-date').value);
                formData.append('purpose', document.getElementById('purpose').value);
            } else {
                formData.append('condition', document.getElementById('condition').value);
                formData.append('notes', document.getElementById('notes').value);
            }
            
            // Add photos
            document.querySelectorAll('input[type="file"]').forEach(input => {
                if (input.files && input.files[0]) {
                    const match = input.name.match(/\[(.*?)\]/);
                    if (match) {
                        formData.append(input.name, input.files[0]);
                    }
                }
            });
            
            // Show loading
            const btn = document.getElementById('process-btn');
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
            btn.disabled = true;
            
            fetch('bulk_scanner_v2.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(text => {
                try {
                    const data = JSON.parse(text);
                    if (data.success) {
                        showResults(data.results);
                        selectedItems.clear();
                        updateUI();
                    } else {
                        showToast('error', data.error || 'Processing failed');
                    }
                } catch (e) {
                    showToast('error', 'Invalid response format');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('error', 'Network error');
            })
            .finally(() => {
                btn.innerHTML = originalText;
                btn.disabled = false;
            });
        }

        function validateForm() {
            if (currentMode === 'checkout') {
                const borrower = document.getElementById('borrower-select').value;
                const returnDate = document.getElementById('return-date').value;
                
                if (!borrower) {
                    showToast('error', 'Please select a borrower');
                    return false;
                }
                
                if (!returnDate) {
                    showToast('error', 'Please set return date');
                    return false;
                }
                
                if (new Date(returnDate) <= new Date()) {
                    showToast('error', 'Return date must be in the future');
                    return false;
                }
            } else if (currentMode === 'checkin') {
                const condition = document.getElementById('condition').value;
                
                if (!condition) {
                    showToast('error', 'Please select equipment condition');
                    return false;
                }
            }
            
            return true;
        }

        function showResults(results) {
            let successCount = 0;
            let errorCount = 0;
            let html = '';
            
            results.forEach(result => {
                if (result.success) {
                    successCount++;
                    html += `
                        <div class="result-item result-success">
                            <div>
                                <div class="fw-bold">${result.asset_name || result.asset_id}</div>
                                <small class="text-muted">${result.message}</small>
                            </div>
                            <i class="fas fa-check-circle text-success"></i>
                        </div>
                    `;
                } else {
                    errorCount++;
                    html += `
                        <div class="result-item result-error">
                            <div>
                                <div class="fw-bold">${result.asset_name || result.asset_id}</div>
                                <small class="text-muted">${result.message}</small>
                            </div>
                            <i class="fas fa-times-circle text-danger"></i>
                        </div>
                    `;
                }
            });
            
            html = `
                <div class="d-flex justify-content-center gap-3 mb-3">
                    <span class="badge bg-success fs-6">${successCount} Successful</span>
                    <span class="badge bg-danger fs-6">${errorCount} Failed</span>
                </div>
                ${html}
            `;
            
            document.getElementById('results-content').innerHTML = html;
            document.getElementById('results-card').style.display = 'block';
            
            // Scroll to results
            document.getElementById('results-card').scrollIntoView({ behavior: 'smooth' });
        }

        function viewPhotos(assetId) {
            const formData = new FormData();
            formData.append('ajax', '1');
            formData.append('action', 'get_equipment_photos');
            formData.append('asset_id', assetId);
            
            fetch('bulk_scanner_v2.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showPhotoGallery(data.photos, assetId);
                } else {
                    showToast('error', 'Failed to load photos');
                }
            })
            .catch(error => {
                showToast('error', 'Network error');
            });
        }

        function showPhotoGallery(photos, assetId) {
            const modal = new bootstrap.Modal(document.getElementById('photoModal'));
            const gallery = document.getElementById('photo-gallery');
            
            if (photos.length === 0) {
                gallery.innerHTML = `
                    <div class="text-center p-4">
                        <i class="fas fa-camera fa-3x text-muted mb-3"></i>
                        <h5>No Photos Available</h5>
                        <p class="text-muted">No photos have been uploaded for this equipment yet.</p>
                    </div>
                `;
            } else {
                let html = '<div class="row g-3">';
                photos.forEach(photo => {
                    const date = new Date(photo.upload_date).toLocaleDateString();
                    html += `
                        <div class="col-md-4">
                            <div class="card">
                                <img src="${photo.photo_path}" class="card-img-top" style="height: 200px; object-fit: cover;" onclick="window.open('${photo.photo_path}', '_blank')">
                                <div class="card-body p-2">
                                    <small class="text-muted">${date}</small>
                                    <div><span class="badge bg-primary">${photo.photo_type}</span></div>
                                    ${photo.borrower_name ? '<small class="text-info">by ' + photo.borrower_name + '</small>' : ''}
                                </div>
                            </div>
                        </div>
                    `;
                });
                html += '</div>';
                gallery.innerHTML = html;
            }
            
            modal.show();
        }

        function showToast(type, message) {
            const toast = document.createElement('div');
            const bgClass = type === 'success' ? 'bg-success' : type === 'warning' ? 'bg-warning' : 'bg-danger';
            
            toast.className = `toast position-fixed top-0 end-0 m-3 ${bgClass} text-white`;
            toast.style.zIndex = '9999';
            toast.innerHTML = `
                <div class="toast-body">
                    <i class="fas fa-${type === 'success' ? 'check' : type === 'warning' ? 'exclamation-triangle' : 'times'}-circle me-2"></i>
                    ${message}
                    <button type="button" class="btn-close btn-close-white float-end" data-bs-dismiss="toast"></button>
                </div>
            `;
            
            document.body.appendChild(toast);
            
            const bsToast = new bootstrap.Toast(toast);
            bsToast.show();
            
            setTimeout(() => {
                if (toast.parentNode) {
                    toast.parentNode.removeChild(toast);
                }
            }, 5000);
        }
    </script>
</body>
</html>