<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Fix session path issue
if (!session_id()) {
    ini_set('session.save_path', sys_get_temp_dir());
    session_start();
}
require_once 'config/database.php';
require_once 'classes/Asset.php';
require_once 'classes/QRGenerator.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();
$asset = new Asset($db);

// Create asset_photos table if it doesn't exist
try {
    $create_photos_table = "
    CREATE TABLE IF NOT EXISTS asset_photos (
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
    $db->exec($create_photos_table);
    
    // Create uploads directory if it doesn't exist
    $upload_dir = 'uploads/asset_photos';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
} catch (Exception $e) {
    error_log("Error creating photos table: " . $e->getMessage());
}

// Function to generate short asset ID
function generateShortAssetID($category, $db) {
    // Category mapping to short codes
    $category_codes = [
        'Camera' => 'CAM',
        'Audio' => 'AUD', 
        'Lighting' => 'LIT',
        'Drone' => 'DRN',
        'Tripod' => 'TRI',
        'Lens' => 'LEN',
        'Monitor' => 'MON',
        'Storage' => 'STO',
        'Cables' => 'CAB',
        'Other' => 'OTH'
    ];
    
    $code = $category_codes[$category] ?? 'GEN'; // General if not found
    
    // Get next sequential number for this category
    $query = "SELECT asset_id FROM assets WHERE asset_id LIKE :pattern ORDER BY asset_id DESC LIMIT 1";
    $stmt = $db->prepare($query);
    $pattern = $code . '%';
    $stmt->bindParam(':pattern', $pattern);
    $stmt->execute();
    
    $last_asset = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($last_asset) {
        // Extract number from existing asset ID (e.g., CAM001 -> 001)
        $last_number = intval(substr($last_asset['asset_id'], 3));
        $next_number = $last_number + 1;
    } else {
        $next_number = 1;
    }
    
    // Format as 3-digit number (001, 002, etc.)
    return $code . sprintf('%03d', $next_number);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Generate short asset ID if not provided
    if (empty($_POST['asset_id'])) {
        $asset_id = generateShortAssetID($_POST['category'], $db);
    } else {
        $asset_id = $_POST['asset_id'];
    }
    
    // Generate ultra-compact QR code using asset ID only (much simpler than full URL)
    $qr_url = QRGenerator::generateQRCode($asset_id, 350);
    
    $data = [
        'asset_id' => $asset_id,
        'asset_name' => $_POST['asset_name'],
        'category' => $_POST['category'],
        'description' => $_POST['description'],
        'serial_number' => $_POST['serial_number'],
        'qr_code' => $qr_url,
        'condition_status' => $_POST['condition_status'],
        'notes' => $_POST['notes']
    ];
    
    if ($asset->create($data)) {
        // Handle photo uploads
        $photo_success_count = 0;
        $photo_error_count = 0;
        
        if (isset($_POST['photo_data']) && !empty($_POST['photo_data'])) {
            $photos_data = json_decode($_POST['photo_data'], true);
            
            foreach ($photos_data as $photo_data) {
                if (saveBase64Photo($asset_id, $photo_data, $db)) {
                    $photo_success_count++;
                } else {
                    $photo_error_count++;
                }
            }
        }
        
        $success = "Asset added successfully with ID: <strong>{$asset_id}</strong>";
        if ($photo_success_count > 0) {
            $success .= " {$photo_success_count} photo(s) uploaded.";
        }
        if ($photo_error_count > 0) {
            $success .= " {$photo_error_count} photo(s) failed to upload.";
        }
        
        // Show QR code preview
        $qr_preview = true;
        $generated_qr_url = $qr_url;
        
    } else {
        $error = "Failed to add asset: " . implode(', ', $asset->getErrors());
    }
}

function saveBase64Photo($asset_id, $photo_data, $db) {
    try {
        $image_data = base64_decode($photo_data['data']);
        $file_size = strlen($image_data);
        
        // Generate unique filename
        $filename = $asset_id . '_' . $photo_data['type'] . '_' . time() . '_' . uniqid() . '.jpg';
        $file_path = 'uploads/asset_photos/' . $filename;
        
        // Save the file
        if (file_put_contents($file_path, $image_data)) {
            // Save to database - use variables for bindParam (required for reference passing)
            $query = "INSERT INTO asset_photos (asset_id, photo_path, photo_type, file_size, mime_type, description) 
                     VALUES (:asset_id, :photo_path, :photo_type, :file_size, :mime_type, :description)";
            
            $stmt = $db->prepare($query);
            
            // Extract values to variables for bindParam (required for reference passing)
            $photo_type = $photo_data['type'];
            $mime_type = 'image/jpeg';
            $description = isset($photo_data['description']) ? $photo_data['description'] : '';
            
            $stmt->bindParam(':asset_id', $asset_id);
            $stmt->bindParam(':photo_path', $file_path);
            $stmt->bindParam(':photo_type', $photo_type);
            $stmt->bindParam(':file_size', $file_size);
            $stmt->bindParam(':mime_type', $mime_type);
            $stmt->bindParam(':description', $description);
            
            return $stmt->execute();
        }
        
        return false;
    } catch (Exception $e) {
        error_log("Error saving photo: " . $e->getMessage());
        return false;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title>Add Asset - Neofox Gear</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --neofox-yellow: #FFD700;
            --yellow-light: #FFF9C4;
            --yellow-dark: #F57F17;
            --black-primary: #000000;
            --black-light: #2d2d2d;
            --white-primary: #FFFFFF;
            --gray-50: #FAFAFA;
            --gray-100: #F5F5F5;
            --gray-200: #E5E5E5;
            --gray-300: #D4D4D4;
            --gray-400: #A3A3A3;
            --gray-500: #737373;
            --gray-600: #525252;
            --gray-700: #404040;
            --green-500: #10B981;
            --red-500: #EF4444;
            --shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 25px rgba(0, 0, 0, 0.15);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            -webkit-tap-highlight-color: transparent;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--gray-50);
            color: var(--gray-700);
            line-height: 1.5;
            overflow-x: hidden;
            -webkit-font-smoothing: antialiased;
        }

        /* Mobile-first navigation */
        .mobile-nav {
            background: var(--black-primary);
            color: var(--neofox-yellow);
            padding: 1rem;
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: var(--shadow);
        }

        .nav-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .nav-title {
            font-size: 1.1rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .back-btn {
            background: none;
            border: none;
            color: var(--neofox-yellow);
            font-size: 1.2rem;
            padding: 0.5rem;
            cursor: pointer;
            border-radius: 6px;
            transition: background 0.2s;
        }

        .back-btn:hover {
            background: rgba(255, 215, 0, 0.1);
        }

        /* Container */
        .container {
            padding: 1rem;
            max-width: 600px;
            margin: 0 auto;
        }

        /* Form styling */
        .form-card {
            background: var(--white-primary);
            border-radius: 16px;
            box-shadow: var(--shadow);
            overflow: hidden;
            margin-bottom: 2rem;
        }

        .form-section {
            padding: 1.5rem;
            border-bottom: 1px solid var(--gray-200);
        }

        .form-section:last-child {
            border-bottom: none;
        }

        .section-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--black-primary);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .section-icon {
            width: 32px;
            height: 32px;
            background: var(--neofox-yellow);
            color: var(--black-primary);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
        }

        /* Form elements */
        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            font-weight: 600;
            color: var(--gray-700);
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }

        .required::after {
            content: ' *';
            color: var(--red-500);
        }

        .form-control, .form-select {
            width: 100%;
            padding: 1rem;
            border: 2px solid var(--gray-300);
            border-radius: 12px;
            font-size: 1rem;
            font-family: inherit;
            background: var(--white-primary);
            transition: all 0.3s ease;
            -webkit-appearance: none;
        }

        .form-control:focus, .form-select:focus {
            outline: none;
            border-color: var(--neofox-yellow);
            box-shadow: 0 0 0 3px rgba(255, 215, 0, 0.1);
        }

        .form-select {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='m6 8 4 4 4-4'/%3e%3c/svg%3e");
            background-position: right 0.75rem center;
            background-repeat: no-repeat;
            background-size: 1.5em 1.5em;
        }

        textarea.form-control {
            resize: vertical;
            min-height: 80px;
        }

        /* Photo capture section */
        .photo-section {
            background: var(--gray-50);
        }

        .camera-controls {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .photo-type-btn {
            background: var(--white-primary);
            border: 2px solid var(--gray-300);
            border-radius: 12px;
            padding: 1rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.5rem;
        }

        .photo-type-btn:hover, .photo-type-btn.active {
            border-color: var(--neofox-yellow);
            background: var(--yellow-light);
        }

        .photo-type-btn .icon {
            font-size: 1.5rem;
            color: var(--gray-600);
        }

        .photo-type-btn.active .icon {
            color: var(--black-primary);
        }

        .photo-type-btn .label {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--gray-700);
        }

        .camera-capture {
            position: relative;
            margin-bottom: 1.5rem;
        }

        .camera-viewport {
            width: 100%;
            height: 250px;
            background: var(--gray-200);
            border-radius: 12px;
            overflow: hidden;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .camera-video {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .camera-placeholder {
            text-align: center;
            color: var(--gray-500);
            padding: 2rem;
        }

        .camera-placeholder i {
            font-size: 2rem;
            margin-bottom: 0.5rem;
            display: block;
        }

        .capture-controls {
            position: absolute;
            bottom: 1rem;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .capture-btn {
            width: 60px;
            height: 60px;
            background: var(--neofox-yellow);
            border: 4px solid var(--white-primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: var(--black-primary);
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: var(--shadow);
        }

        .capture-btn:hover {
            transform: scale(1.1);
        }

        .capture-btn:disabled {
            background: var(--gray-400);
            cursor: not-allowed;
            transform: none;
        }

        .toggle-camera-btn {
            width: 40px;
            height: 40px;
            background: rgba(0, 0, 0, 0.7);
            border: none;
            border-radius: 50%;
            color: white;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* Photo gallery */
        .photo-gallery {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
            gap: 1rem;
        }

        .photo-item {
            position: relative;
            aspect-ratio: 1;
            border-radius: 12px;
            overflow: hidden;
            background: var(--gray-200);
            border: 2px solid var(--gray-300);
        }

        .photo-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .photo-item .remove-btn {
            position: absolute;
            top: 0.5rem;
            right: 0.5rem;
            width: 24px;
            height: 24px;
            background: var(--red-500);
            color: white;
            border: none;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
            cursor: pointer;
        }

        .photo-item .type-badge {
            position: absolute;
            bottom: 0.5rem;
            left: 0.5rem;
            background: rgba(0, 0, 0, 0.7);
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.7rem;
            font-weight: 600;
        }

        /* QR Code Preview */
        .qr-preview {
            background: var(--white-primary);
            border-radius: 16px;
            box-shadow: var(--shadow);
            padding: 1.5rem;
            margin-bottom: 2rem;
            text-align: center;
        }

        .qr-preview h3 {
            color: var(--black-primary);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .qr-preview img {
            max-width: 150px;
            width: 100%;
            height: auto;
            border: 1px solid var(--gray-300);
            border-radius: 8px;
            margin-bottom: 1rem;
        }

        .qr-info {
            background: var(--gray-50);
            border-radius: 8px;
            padding: 1rem;
            font-size: 0.9rem;
            color: var(--gray-600);
        }

        .asset-id-preview {
            background: var(--yellow-light);
            border: 2px solid var(--neofox-yellow);
            border-radius: 8px;
            padding: 0.75rem;
            margin-bottom: 1rem;
            text-align: center;
        }

        .asset-id-preview .label {
            font-size: 0.8rem;
            color: var(--gray-600);
            margin-bottom: 0.25rem;
        }

        .asset-id-preview .value {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--black-primary);
            font-family: 'Monaco', 'Courier New', monospace;
        }

        /* Action buttons */
        .action-buttons {
            position: sticky;
            bottom: 0;
            background: var(--white-primary);
            padding: 1rem;
            border-top: 1px solid var(--gray-200);
            box-shadow: 0 -4px 6px rgba(0, 0, 0, 0.1);
        }

        .btn {
            width: 100%;
            padding: 1rem;
            border-radius: 12px;
            font-weight: 700;
            font-size: 1rem;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            margin-bottom: 0.75rem;
        }

        .btn:last-child {
            margin-bottom: 0;
        }

        .btn-primary {
            background: var(--black-primary);
            color: var(--white-primary);
        }

        .btn-primary:hover {
            background: var(--black-light);
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: var(--gray-200);
            color: var(--gray-700);
        }

        .btn-secondary:hover {
            background: var(--gray-300);
        }

        .btn:disabled {
            background: var(--gray-400);
            cursor: not-allowed;
            transform: none;
        }

        /* Alerts */
        .alert {
            padding: 1rem;
            border-radius: 12px;
            margin-bottom: 1rem;
            font-weight: 500;
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            color: #065f46;
            border: 1px solid rgba(16, 185, 129, 0.3);
        }

        .alert-danger {
            background: rgba(239, 68, 68, 0.1);
            color: #991b1b;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }

        /* Loading states */
        .loading {
            opacity: 0.6;
            pointer-events: none;
        }

        .spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid var(--gray-300);
            border-top: 3px solid var(--neofox-yellow);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Canvas for photo compression */
        .hidden {
            display: none !important;
        }

        /* Photo preview modal */
        .photo-preview-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.9);
            z-index: 10000;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }

        .photo-preview-modal.show {
            display: flex;
        }

        .photo-preview-content {
            max-width: 90%;
            max-height: 90%;
            border-radius: 12px;
            overflow: hidden;
        }

        .photo-preview-content img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .close-preview {
            position: absolute;
            top: 2rem;
            right: 2rem;
            background: rgba(255, 255, 255, 0.9);
            border: none;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
        }

        /* Responsive adjustments */
        @media (max-width: 480px) {
            .container {
                padding: 0.5rem;
            }

            .form-section {
                padding: 1rem;
            }

            .camera-controls {
                grid-template-columns: 1fr;
            }

            .camera-viewport {
                height: 200px;
            }
        }

        /* iOS specific fixes */
        @supports (-webkit-touch-callout: none) {
            .form-control, .form-select {
                font-size: 16px; /* Prevents zoom on iOS */
            }
        }
    </style>
</head>
<body>
    <!-- Mobile Navigation -->
    <div class="mobile-nav">
        <div class="nav-header">
            <button class="back-btn" onclick="goBack()">
                <i class="fas fa-arrow-left"></i>
            </button>
            <div class="nav-title">
                <i class="fas fa-plus"></i>
                Add New Asset
            </div>
            <div style="width: 40px;"></div> <!-- Spacer for centering -->
        </div>
    </div>

    <div class="container">
        <!-- Alerts -->
        <?php if (isset($success)): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> <?php echo $success; ?>
        </div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
        </div>
        <?php endif; ?>

        <!-- QR Code Preview (show after successful creation) -->
        <?php if (isset($qr_preview) && $qr_preview): ?>
        <div class="qr-preview">
            <h3><i class="fas fa-qrcode"></i> Ultra-Compact QR Code Generated!</h3>
            <img src="<?php echo $generated_qr_url; ?>" alt="Asset QR Code">
            <div class="qr-info">
                <strong>15mm Label Ready!</strong><br>
                This ultra-compact QR contains just "<strong><?php echo $asset_id; ?></strong>" for maximum scannability.
            </div>
        </div>
        <?php endif; ?>

        <form id="assetForm" method="POST">
            <!-- Basic Information -->
            <div class="form-card">
                <div class="form-section">
                    <h3 class="section-title">
                        <div class="section-icon">
                            <i class="fas fa-info"></i>
                        </div>
                        Basic Information
                    </h3>
                    
                    <div class="form-group">
                        <label for="asset_name" class="form-label required">Asset Name</label>
                        <input type="text" class="form-control" id="asset_name" name="asset_name" 
                               placeholder="e.g., Canon EOS R5" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="category" class="form-label required">Category</label>
                        <select class="form-select" id="category" name="category" required onchange="updateAssetIdPreview()">
                            <option value="">Select category...</option>
                            <option value="Camera">📷 Camera (CAM)</option>
                            <option value="Audio">🎤 Audio (AUD)</option>
                            <option value="Lighting">💡 Lighting (LIT)</option>
                            <option value="Drone">🚁 Drone (DRN)</option>
                            <option value="Tripod">📐 Tripod (TRI)</option>
                            <option value="Lens">🔍 Lens (LEN)</option>
                            <option value="Monitor">🖥️ Monitor (MON)</option>
                            <option value="Storage">💾 Storage (STO)</option>
                            <option value="Cables">🔌 Cables (CAB)</option>
                            <option value="Other">📦 Other (OTH)</option>
                        </select>
                    </div>
                    
                    <!-- Asset ID Preview -->
                    <div class="asset-id-preview" id="assetIdPreview" style="display: none;">
                        <div class="label">Generated Asset ID:</div>
                        <div class="value" id="previewAssetId">CAM001</div>
                        <div style="font-size: 0.8rem; color: var(--gray-600); margin-top: 0.5rem;">
                            Ultra-short ID for 15mm QR labels
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="asset_id" class="form-label">Custom Asset ID (Optional)</label>
                        <input type="text" class="form-control" id="asset_id" name="asset_id" 
                               placeholder="Leave blank for auto-generation" maxlength="10">
                        <div style="font-size: 0.8rem; color: var(--gray-500); margin-top: 0.5rem;">
                            Keep it short (6 characters max) for best QR scanning
                        </div>
                    </div>
                </div>
            </div>

            <!-- Photos Section -->
            <div class="form-card">
                <div class="form-section photo-section">
                    <h3 class="section-title">
                        <div class="section-icon">
                            <i class="fas fa-camera"></i>
                        </div>
                        Equipment Photos
                    </h3>
                    
                    <!-- Photo Type Selection -->
                    <div class="camera-controls">
                        <div class="photo-type-btn active" data-type="main">
                            <div class="icon"><i class="fas fa-camera"></i></div>
                            <div class="label">Main Photo</div>
                        </div>
                        <div class="photo-type-btn" data-type="detail">
                            <div class="icon"><i class="fas fa-search-plus"></i></div>
                            <div class="label">Detail Shot</div>
                        </div>
                        <div class="photo-type-btn" data-type="serial">
                            <div class="icon"><i class="fas fa-hashtag"></i></div>
                            <div class="label">Serial Number</div>
                        </div>
                        <div class="photo-type-btn" data-type="condition">
                            <div class="icon"><i class="fas fa-shield-alt"></i></div>
                            <div class="label">Condition</div>
                        </div>
                    </div>
                    
                    <!-- Camera Interface -->
                    <div class="camera-capture">
                        <div class="camera-viewport" id="cameraViewport">
                            <div class="camera-placeholder" id="cameraPlaceholder">
                                <i class="fas fa-camera"></i>
                                <div>Tap to start camera</div>
                            </div>
                            <video id="cameraVideo" class="camera-video hidden" playsinline></video>
                        </div>
                        
                        <div class="capture-controls hidden" id="captureControls">
                            <button type="button" class="toggle-camera-btn" id="toggleCameraBtn" title="Switch Camera">
                                <i class="fas fa-sync-alt"></i>
                            </button>
                            <button type="button" class="capture-btn" id="captureBtn">
                                <i class="fas fa-camera"></i>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Photo Gallery -->
                    <div class="photo-gallery" id="photoGallery">
                        <!-- Photos will be added here -->
                    </div>
                </div>
            </div>

            <!-- Additional Details -->
            <div class="form-card">
                <div class="form-section">
                    <h3 class="section-title">
                        <div class="section-icon">
                            <i class="fas fa-clipboard"></i>
                        </div>
                        Additional Details
                    </h3>
                    
                    <div class="form-group">
                        <label for="serial_number" class="form-label">Serial Number</label>
                        <input type="text" class="form-control" id="serial_number" name="serial_number" 
                               placeholder="Equipment serial number">
                    </div>
                    
                    <div class="form-group">
                        <label for="condition_status" class="form-label required">Condition</label>
                        <select class="form-select" id="condition_status" name="condition_status" required>
                            <option value="excellent">✨ Excellent</option>
                            <option value="good">👍 Good</option>
                            <option value="needs_repair">🔧 Needs Repair</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" 
                                  placeholder="Detailed description of the equipment"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="notes" class="form-label">Notes</label>
                        <textarea class="form-control" id="notes" name="notes" 
                                  placeholder="Additional notes or comments"></textarea>
                    </div>
                </div>
            </div>

            <!-- Hidden input for photo data -->
            <input type="hidden" id="photoData" name="photo_data">
        </form>
    </div>

    <!-- Action Buttons -->
    <div class="action-buttons">
        <button type="submit" form="assetForm" class="btn btn-primary" id="submitBtn">
            <i class="fas fa-save"></i>
            Add Asset
        </button>
        <button type="button" class="btn btn-secondary" onclick="goBack()">
            <i class="fas fa-times"></i>
            Cancel
        </button>
    </div>

    <!-- Photo Preview Modal -->
    <div class="photo-preview-modal" id="photoPreviewModal">
        <button class="close-preview" onclick="closePhotoPreview()">
            <i class="fas fa-times"></i>
        </button>
        <div class="photo-preview-content">
            <img id="previewImage" src="" alt="Photo Preview">
        </div>
    </div>

    <!-- Hidden canvas for compression -->
    <canvas id="compressionCanvas" class="hidden"></canvas>

    <script>
        // Global variables
        let currentStream = null;
        let currentPhotoType = 'main';
        let facingMode = 'environment'; // Start with back camera
        let capturedPhotos = [];
        let isCapturing = false;

        // Category to code mapping
        const categoryCodes = {
            'Camera': 'CAM',
            'Audio': 'AUD', 
            'Lighting': 'LIT',
            'Drone': 'DRN',
            'Tripod': 'TRI',
            'Lens': 'LEN',
            'Monitor': 'MON',
            'Storage': 'STO',
            'Cables': 'CAB',
            'Other': 'OTH'
        };

        // Initialize the app
        document.addEventListener('DOMContentLoaded', function() {
            initializePhotoCapture();
            setupEventListeners();
        });

        function updateAssetIdPreview() {
            const category = document.getElementById('category').value;
            const preview = document.getElementById('assetIdPreview');
            const previewId = document.getElementById('previewAssetId');
            
            if (category && categoryCodes[category]) {
                const code = categoryCodes[category];
                previewId.textContent = code + '001'; // Example ID
                preview.style.display = 'block';
            } else {
                preview.style.display = 'none';
            }
        }

        function setupEventListeners() {
            // Photo type selection
            document.querySelectorAll('.photo-type-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    document.querySelectorAll('.photo-type-btn').forEach(b => b.classList.remove('active'));
                    this.classList.add('active');
                    currentPhotoType = this.dataset.type;
                });
            });

            // Camera controls
            document.getElementById('cameraPlaceholder').addEventListener('click', startCamera);
            document.getElementById('captureBtn').addEventListener('click', capturePhoto);
            document.getElementById('toggleCameraBtn').addEventListener('click', toggleCamera);

            // Form submission
            document.getElementById('assetForm').addEventListener('submit', function(e) {
                e.preventDefault();
                submitForm();
            });
        }

        async function initializePhotoCapture() {
            // Check if camera is available
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                showFallbackUpload();
                return;
            }

            // Start camera on placeholder click
            document.getElementById('cameraPlaceholder').addEventListener('click', startCamera);
        }

        async function startCamera() {
            try {
                if (currentStream) {
                    currentStream.getTracks().forEach(track => track.stop());
                }

                const constraints = {
                    video: {
                        facingMode: facingMode,
                        width: { ideal: 1280 },
                        height: { ideal: 720 }
                    }
                };

                currentStream = await navigator.mediaDevices.getUserMedia(constraints);
                
                const video = document.getElementById('cameraVideo');
                video.srcObject = currentStream;
                await video.play();

                // Show camera interface
                document.getElementById('cameraPlaceholder').classList.add('hidden');
                document.getElementById('cameraVideo').classList.remove('hidden');
                document.getElementById('captureControls').classList.remove('hidden');

            } catch (error) {
                console.error('Error accessing camera:', error);
                showFallbackUpload();
            }
        }

        async function toggleCamera() {
            facingMode = facingMode === 'environment' ? 'user' : 'environment';
            await startCamera();
        }

        function capturePhoto() {
            if (isCapturing) return;
            isCapturing = true;

            const video = document.getElementById('cameraVideo');
            const canvas = document.getElementById('compressionCanvas');
            const ctx = canvas.getContext('2d');

            // Set canvas size
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;

            // Draw video frame to canvas
            ctx.drawImage(video, 0, 0);

            // Compress and convert to base64
            const compressedDataUrl = canvas.toDataURL('image/jpeg', 0.8);
            const base64Data = compressedDataUrl.split(',')[1];

            // Create photo object
            const photo = {
                id: Date.now() + Math.random(),
                type: currentPhotoType,
                data: base64Data,
                dataUrl: compressedDataUrl,
                description: getPhotoTypeLabel(currentPhotoType)
            };

            // Add to photos array
            capturedPhotos.push(photo);

            // Update gallery
            updatePhotoGallery();

            // Visual feedback
            const captureBtn = document.getElementById('captureBtn');
            captureBtn.innerHTML = '<i class="fas fa-check"></i>';
            setTimeout(() => {
                captureBtn.innerHTML = '<i class="fas fa-camera"></i>';
                isCapturing = false;
            }, 1000);
        }

        function updatePhotoGallery() {
            const gallery = document.getElementById('photoGallery');
            gallery.innerHTML = '';

            capturedPhotos.forEach(photo => {
                const photoElement = document.createElement('div');
                photoElement.className = 'photo-item';
                photoElement.innerHTML = `
                    <img src="${photo.dataUrl}" alt="${photo.description}" onclick="previewPhoto('${photo.dataUrl}')">
                    <button type="button" class="remove-btn" onclick="removePhoto(${photo.id})">
                        <i class="fas fa-times"></i>
                    </button>
                    <div class="type-badge">${photo.description}</div>
                `;
                gallery.appendChild(photoElement);
            });
        }

        function removePhoto(photoId) {
            capturedPhotos = capturedPhotos.filter(photo => photo.id !== photoId);
            updatePhotoGallery();
        }

        function previewPhoto(dataUrl) {
            document.getElementById('previewImage').src = dataUrl;
            document.getElementById('photoPreviewModal').classList.add('show');
        }

        function closePhotoPreview() {
            document.getElementById('photoPreviewModal').classList.remove('show');
        }

        function getPhotoTypeLabel(type) {
            const labels = {
                'main': 'Main',
                'detail': 'Detail',
                'serial': 'Serial',
                'condition': 'Condition'
            };
            return labels[type] || 'Photo';
        }

        function showFallbackUpload() {
            // Show file input as fallback
            const cameraViewport = document.getElementById('cameraViewport');
            cameraViewport.innerHTML = `
                <div class="camera-placeholder">
                    <i class="fas fa-upload"></i>
                    <div>Camera not available</div>
                    <input type="file" accept="image/*" multiple style="margin-top: 1rem;" onchange="handleFileUpload(this)">
                </div>
            `;
        }

        function handleFileUpload(input) {
            Array.from(input.files).forEach(file => {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const img = new Image();
                    img.onload = function() {
                        const canvas = document.getElementById('compressionCanvas');
                        const ctx = canvas.getContext('2d');
                        
                        // Resize and compress
                        const maxWidth = 1280;
                        const maxHeight = 720;
                        let { width, height } = img;
                        
                        if (width > height) {
                            if (width > maxWidth) {
                                height = (height * maxWidth) / width;
                                width = maxWidth;
                            }
                        } else {
                            if (height > maxHeight) {
                                width = (width * maxHeight) / height;
                                height = maxHeight;
                            }
                        }
                        
                        canvas.width = width;
                        canvas.height = height;
                        ctx.drawImage(img, 0, 0, width, height);
                        
                        const compressedDataUrl = canvas.toDataURL('image/jpeg', 0.8);
                        const base64Data = compressedDataUrl.split(',')[1];
                        
                        const photo = {
                            id: Date.now() + Math.random(),
                            type: currentPhotoType,
                            data: base64Data,
                            dataUrl: compressedDataUrl,
                            description: getPhotoTypeLabel(currentPhotoType)
                        };
                        
                        capturedPhotos.push(photo);
                        updatePhotoGallery();
                    };
                    img.src = e.target.result;
                };
                reader.readAsDataURL(file);
            });
        }

        function submitForm() {
            const submitBtn = document.getElementById('submitBtn');
            const form = document.getElementById('assetForm');
            
            // Prepare photo data
            const photoData = capturedPhotos.map(photo => ({
                type: photo.type,
                data: photo.data,
                description: photo.description
            }));
            
            document.getElementById('photoData').value = JSON.stringify(photoData);
            
            // Show loading state
            submitBtn.innerHTML = '<div class="spinner"></div> Adding Asset...';
            submitBtn.disabled = true;
            
            // Submit form
            form.submit();
        }

        function goBack() {
            if (currentStream) {
                currentStream.getTracks().forEach(track => track.stop());
            }
            window.history.back();
        }

        // Handle page unload
        window.addEventListener('beforeunload', function() {
            if (currentStream) {
                currentStream.getTracks().forEach(track => track.stop());
            }
        });

        // Handle orientation change
        window.addEventListener('orientationchange', function() {
            setTimeout(() => {
                if (currentStream) {
                    startCamera();
                }
            }, 500);
        });
    </script>
</body>
</html>