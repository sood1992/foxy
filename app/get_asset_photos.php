<?php
// get_asset_photos.php - API endpoint to fetch asset photos
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

// Fix session path issue
if (!session_id()) {
    ini_set('session.save_path', sys_get_temp_dir());
    session_start();
}

require_once 'config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Validate asset_id parameter
if (!isset($_GET['asset_id']) || empty($_GET['asset_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Asset ID is required']);
    exit();
}

$asset_id = $_GET['asset_id'];

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Check if asset_photos table exists, if not create it
    $check_table = "SHOW TABLES LIKE 'asset_photos'";
    $stmt = $db->prepare($check_table);
    $stmt->execute();
    
    if ($stmt->rowCount() == 0) {
        // Create the table if it doesn't exist
        $create_table = "
        CREATE TABLE asset_photos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            asset_id VARCHAR(50) NOT NULL,
            photo_path VARCHAR(255) NOT NULL,
            photo_type ENUM('main', 'detail', 'serial', 'condition') DEFAULT 'main',
            upload_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            file_size INT,
            mime_type VARCHAR(100),
            description TEXT,
            INDEX idx_asset_id (asset_id)
        )";
        $db->exec($create_table);
        
        // Return empty array since table was just created
        echo json_encode([]);
        exit();
    }
    
    // Get photos for the asset
    $query = "SELECT 
                id,
                asset_id,
                photo_path,
                photo_type,
                upload_date,
                file_size,
                mime_type,
                description
              FROM asset_photos 
              WHERE asset_id = :asset_id 
              ORDER BY 
                CASE photo_type 
                    WHEN 'main' THEN 1 
                    WHEN 'detail' THEN 2 
                    WHEN 'serial' THEN 3 
                    WHEN 'condition' THEN 4 
                    ELSE 5 
                END, 
                upload_date ASC";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':asset_id', $asset_id, PDO::PARAM_STR);
    $stmt->execute();
    
    $photos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Process photos to ensure paths are correct and files exist
    $processed_photos = [];
    
    foreach ($photos as $photo) {
        // Check if file exists
        if (file_exists($photo['photo_path'])) {
            // Convert file size to human readable format
            $photo['file_size_formatted'] = formatFileSize($photo['file_size']);
            
            // Format upload date
            $photo['upload_date_formatted'] = date('M j, Y g:i A', strtotime($photo['upload_date']));
            
            // Ensure photo path is web accessible
            if (!str_starts_with($photo['photo_path'], 'http')) {
                // Make sure the path starts from the web root
                $photo['photo_path'] = './' . ltrim($photo['photo_path'], './');
            }
            
            $processed_photos[] = $photo;
        } else {
            // Log missing file but don't include in results
            error_log("Photo file not found: " . $photo['photo_path']);
        }
    }
    
    // Return the photos as JSON
    echo json_encode($processed_photos);
    
} catch (PDOException $e) {
    error_log("Database error in get_asset_photos.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database error occurred']);
} catch (Exception $e) {
    error_log("General error in get_asset_photos.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'An error occurred']);
}

/**
 * Format file size in human readable format
 */
function formatFileSize($bytes) {
    if ($bytes === null || $bytes === 0) {
        return '0 B';
    }
    
    $units = ['B', 'KB', 'MB', 'GB'];
    $factor = floor(log($bytes, 1024));
    
    return sprintf("%.1f %s", $bytes / pow(1024, $factor), $units[$factor]);
}

/**
 * Get photo type display name
 */
function getPhotoTypeDisplayName($type) {
    $types = [
        'main' => 'Main Photo',
        'detail' => 'Detail Shot',
        'serial' => 'Serial Number',
        'condition' => 'Condition Photo'
    ];
    
    return $types[$type] ?? ucfirst($type);
}

/**
 * Compatibility function for older PHP versions
 */
if (!function_exists('str_starts_with')) {
    function str_starts_with($haystack, $needle) {
        return substr($haystack, 0, strlen($needle)) === $needle;
    }
}
?>