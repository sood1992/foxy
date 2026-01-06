<?php
// requests.php - Enhanced Admin View of Gear Requests with Approve & Checkout
// Enable comprehensive error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
ini_set('log_errors', 1);

// Add output buffering to catch any output issues
ob_start();

// Fix session path issue
if (!session_id()) {
    ini_set('session.save_path', sys_get_temp_dir());
    session_start();
}

// Log the start of the script
error_log("=== REQUESTS.PHP START ===");
error_log("Request method: " . $_SERVER['REQUEST_METHOD']);
error_log("POST data: " . json_encode($_POST));

try {
    require_once 'config/database.php';
    error_log("Database config loaded");
    
    require_once 'classes/GearRequest.php';
    error_log("GearRequest class loaded");
    
    require_once 'classes/Asset.php';
    error_log("Asset class loaded");
    
    require_once 'classes/EmailNotification.php';
    error_log("EmailNotification class loaded");
} catch (Exception $e) {
    error_log("Error loading classes: " . $e->getMessage());
    die("Error loading required classes: " . $e->getMessage());
}

if (!isset($_SESSION['user_id'])) {
    error_log("User not logged in, redirecting to login");
    header("Location: login.php");
    exit();
}

error_log("User logged in: " . $_SESSION['user_id']);

try {
    $database = new Database();
    $db = $database->getConnection();
    error_log("Database connection established");
    
    $gear_request = new GearRequest($db);
    $asset = new Asset($db);
    error_log("Objects created successfully");
} catch (Exception $e) {
    error_log("Error creating objects: " . $e->getMessage());
    die("Database connection error: " . $e->getMessage());
}

// Handle status updates
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_status'])) {
    error_log("Processing status update for request ID: " . $_POST['request_id']);
    
    try {
        $request_id = $_POST['request_id'];
        $new_status = $_POST['status'];
        $admin_notes = $_POST['admin_notes'];
        
        if ($gear_request->updateStatus($request_id, $new_status, $admin_notes)) {
            $success = "Request status updated successfully!";
            error_log("Status update successful");
        } else {
            $error = "Failed to update request status.";
            $errors = $gear_request->getErrors();
            error_log("Status update failed: " . implode(", ", $errors));
        }
    } catch (Exception $e) {
        error_log("Exception in status update: " . $e->getMessage());
        $error = "Error updating status: " . $e->getMessage();
    }
}

// Handle approve and checkout
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['approve_and_checkout'])) {
    error_log("=== APPROVE AND CHECKOUT START ===");
    
    $transaction_started = false;
    
    try {
        $request_id = $_POST['request_id'];
        $admin_notes = $_POST['admin_notes'] ?? '';
        $expected_return = $_POST['expected_return'];
        $purpose = $_POST['purpose'] ?? '';
        
        error_log("Processing approve and checkout:");
        error_log("Request ID: " . $request_id);
        error_log("Expected return: " . $expected_return);
        error_log("Purpose: " . $purpose);
        error_log("Admin notes: " . $admin_notes);
        
        // Validate required fields
        if (empty($request_id)) {
            throw new Exception("Request ID is missing");
        }
        if (empty($expected_return)) {
            throw new Exception("Expected return date is missing");
        }
        
        // Get the request details BEFORE starting transaction
        $request = $gear_request->getById($request_id);
        if (!$request) {
            throw new Exception("Request not found for ID: " . $request_id);
        }
        
        error_log("Found request: " . json_encode($request));
        
        // Parse the required items to extract asset IDs
        $required_items = $request['required_items'];
        $asset_ids = [];
        $checkout_errors = [];
        $checkout_success = [];
        
        error_log("Required items string: " . $required_items);
        
        // Extract asset IDs from the required_items string
        // Format is usually "Asset Name (ASSET_ID), Asset Name 2 (ASSET_ID2)"
        preg_match_all('/\(([^)]+)\)/', $required_items, $matches);
        if (!empty($matches[1])) {
            $asset_ids = $matches[1];
        }
        
        error_log("Extracted asset IDs: " . json_encode($asset_ids));
        
        if (empty($asset_ids)) {
            // If regex didn't work, try alternative parsing methods
            error_log("No asset IDs found with regex, trying alternative methods");
            
            // Try to find patterns like NF001, NF002, etc.
            preg_match_all('/\b(NF\d+)\b/', $required_items, $nf_matches);
            if (!empty($nf_matches[1])) {
                $asset_ids = $nf_matches[1];
                error_log("Found NF pattern asset IDs: " . json_encode($asset_ids));
            } else {
                // Log the exact format for debugging
                error_log("Required items format: '" . $required_items . "'");
                throw new Exception("No valid asset IDs found in the request. Required items format: " . $required_items);
            }
        }
        
        // Validate that we have assets to check out
        if (empty($asset_ids)) {
            throw new Exception("No asset IDs could be extracted from: " . $required_items);
        }
        
        // Check if Asset class has required methods
        if (!method_exists($asset, 'getByAssetId')) {
            throw new Exception("Asset class missing getByAssetId method");
        }
        if (!method_exists($asset, 'checkOut')) {
            throw new Exception("Asset class missing checkOut method");
        }
        
        // NOW start the transaction - after all validation
        $db->beginTransaction();
        $transaction_started = true;
        error_log("Transaction started");
        
        // Check out each asset
        foreach ($asset_ids as $asset_id) {
            $asset_id = trim($asset_id);
            error_log("Processing asset ID: " . $asset_id);
            
            try {
                $asset_data = $asset->getByAssetId($asset_id);
                error_log("Asset data for {$asset_id}: " . json_encode($asset_data));
                
                if (!$asset_data) {
                    $checkout_errors[] = "Asset {$asset_id} not found in database";
                    error_log("Asset {$asset_id} not found");
                    continue;
                }
                
                if ($asset_data['status'] !== 'available') {
                    $checkout_errors[] = "Asset {$asset_id} ({$asset_data['asset_name']}) is not available (current status: {$asset_data['status']})";
                    error_log("Asset {$asset_id} not available, status: {$asset_data['status']}");
                    continue;
                }
                
                // Attempt checkout
                error_log("Attempting checkout for asset {$asset_id}");
                if ($asset->checkOut($asset_id, $request['requester_name'], $expected_return, $purpose, false)) {
                    $checkout_success[] = $asset_data['asset_name'] . " ({$asset_id})";
                    error_log("Successfully checked out: {$asset_data['asset_name']} ({$asset_id})");
                } else {
                    $asset_errors = $asset->getErrors();
                    $error_msg = "Failed to check out {$asset_data['asset_name']} ({$asset_id})";
                    if (!empty($asset_errors)) {
                        $error_msg .= ": " . implode(", ", $asset_errors);
                    }
                    $checkout_errors[] = $error_msg;
                    error_log($error_msg);
                }
            } catch (Exception $e) {
                $checkout_errors[] = "Error processing asset {$asset_id}: " . $e->getMessage();
                error_log("Exception processing asset {$asset_id}: " . $e->getMessage());
            }
        }
        
        // Update request status to approved
        $final_notes = $admin_notes;
        if (!empty($checkout_success)) {
            $final_notes .= "\n\nChecked out: " . implode(", ", $checkout_success);
        }
        if (!empty($checkout_errors)) {
            $final_notes .= "\n\nErrors: " . implode("; ", $checkout_errors);
        }
        
        error_log("Updating request status to approved with notes: " . $final_notes);
        
        if (!$gear_request->updateStatus($request_id, 'approved', $final_notes)) {
            $gear_errors = $gear_request->getErrors();
            throw new Exception("Failed to update request status: " . implode(", ", $gear_errors));
        }
        
        // Commit transaction BEFORE sending emails (to avoid nested transaction issues)
        $db->commit();
        $transaction_started = false; // Mark transaction as completed
        error_log("Transaction committed successfully");
        
        // Set success/warning messages
        if (!empty($checkout_success) && empty($checkout_errors)) {
            $success = "Request approved and all equipment checked out successfully! Items: " . implode(", ", $checkout_success);
            error_log("Full success: " . $success);
        } elseif (!empty($checkout_success)) {
            $warning = "Request approved. Some equipment checked out successfully (" . implode(", ", $checkout_success) . "), but there were errors with others. Check admin notes for details.";
            error_log("Partial success: " . $warning);
        } else {
            $error = "Request approved but failed to check out any equipment. Errors: " . implode("; ", $checkout_errors);
            error_log("Checkout failed: " . $error);
        }
        
    } catch (Exception $e) {
        // Only rollback if transaction was actually started
        if ($transaction_started) {
            try {
                $db->rollBack();
                error_log("Transaction rolled back");
            } catch (Exception $rollback_e) {
                error_log("Error rolling back transaction: " . $rollback_e->getMessage());
            }
        }
        
        $error = "Error processing request: " . $e->getMessage();
        error_log("Exception in approve_and_checkout: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
    }
    
    error_log("=== APPROVE AND CHECKOUT END ===");
}

// Load all requests
try {
    $requests = $gear_request->getAll();
    error_log("Loaded " . count($requests) . " requests");
} catch (Exception $e) {
    error_log("Error loading requests: " . $e->getMessage());
    $requests = [];
    $error = "Error loading requests: " . $e->getMessage();
}

error_log("=== REQUESTS.PHP PROCESSING COMPLETE ===");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gear Requests - Neofox Gear Control</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
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
            --orange-500: #F59E0B;
            --red-500: #EF4444;
            --blue-500: #3B82F6;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--gray-50);
            color: var(--gray-700);
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
        }

        /* Navigation */
        .navbar {
            background: var(--white-primary);
            border-bottom: 1px solid var(--gray-200);
            padding: 0;
            box-shadow: var(--shadow-sm);
        }

        .navbar-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .navbar-brand {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--black-primary);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .brand-icon {
            width: 32px;
            height: 32px;
            background: var(--neofox-yellow);
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
        }

        .navbar-nav {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }

        .nav-link {
            color: var(--gray-600);
            text-decoration: none;
            font-weight: 500;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            transition: all 0.2s ease;
            font-size: 0.9rem;
        }

        .nav-link:hover, .nav-link.active {
            background: var(--gray-100);
            color: var(--black-primary);
        }

        /* Main Container */
        .main-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }

        /* Page Header */
        .page-header {
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }

        .page-title {
            font-size: 2rem;
            font-weight: 700;
            color: var(--black-primary);
            margin-bottom: 0.5rem;
        }

        .page-subtitle {
            color: var(--gray-500);
            font-size: 1rem;
            font-weight: 400;
        }

        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 500;
            text-decoration: none;
            border: none;
            cursor: pointer;
            transition: all 0.2s ease;
            font-size: 0.9rem;
        }

        .btn-primary {
            background: var(--black-primary);
            color: var(--white-primary);
        }

        .btn-primary:hover {
            background: var(--black-light);
            color: var(--white-primary);
        }

        .btn-success {
            background: var(--green-500);
            color: var(--white-primary);
        }

        .btn-success:hover {
            background: #059669;
        }

        .btn-warning {
            background: var(--orange-500);
            color: var(--white-primary);
        }

        .btn-warning:hover {
            background: #D97706;
        }

        .btn-danger {
            background: var(--red-500);
            color: var(--white-primary);
        }

        .btn-danger:hover {
            background: #DC2626;
        }

        .btn-secondary {
            background: var(--white-primary);
            color: var(--gray-700);
            border: 1px solid var(--gray-300);
        }

        .btn-secondary:hover {
            background: var(--gray-50);
            border-color: var(--gray-400);
            color: var(--gray-700);
        }

        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.8rem;
        }

        /* Alerts */
        .alert {
            padding: 1rem 1.5rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-weight: 500;
            border: 1px solid;
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            color: #065f46;
            border-color: rgba(16, 185, 129, 0.2);
        }

        .alert-warning {
            background: rgba(245, 158, 11, 0.1);
            color: #92400e;
            border-color: rgba(245, 158, 11, 0.2);
        }

        .alert-danger {
            background: rgba(239, 68, 68, 0.1);
            color: #991b1b;
            border-color: rgba(239, 68, 68, 0.2);
        }

        /* Request Cards */
        .requests-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
            gap: 1.5rem;
        }

        .request-card {
            background: var(--white-primary);
            border: 1px solid var(--gray-200);
            border-radius: 12px;
            overflow: hidden;
            transition: all 0.2s ease;
        }

        .request-card:hover {
            box-shadow: var(--shadow);
        }

        .request-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--gray-100);
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }

        .request-user {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            background: var(--neofox-yellow);
            color: var(--black-primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1.1rem;
        }

        .user-info h6 {
            font-weight: 600;
            color: var(--black-primary);
            margin-bottom: 0.25rem;
        }

        .user-email {
            font-size: 0.85rem;
            color: var(--gray-500);
        }

        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-pending {
            background: rgba(245, 158, 11, 0.1);
            color: var(--orange-500);
            border: 1px solid rgba(245, 158, 11, 0.2);
        }

        .status-approved {
            background: rgba(16, 185, 129, 0.1);
            color: var(--green-500);
            border: 1px solid rgba(16, 185, 129, 0.2);
        }

        .status-rejected {
            background: rgba(239, 68, 68, 0.1);
            color: var(--red-500);
            border: 1px solid rgba(239, 68, 68, 0.2);
        }

        .request-body {
            padding: 1.5rem;
        }

        .request-details {
            margin-bottom: 1.5rem;
        }

        .detail-row {
            display: flex;
            margin-bottom: 1rem;
        }

        .detail-label {
            font-weight: 600;
            color: var(--gray-600);
            min-width: 80px;
            font-size: 0.9rem;
        }

        .detail-value {
            color: var(--gray-800);
            flex: 1;
            font-size: 0.9rem;
        }

        .equipment-list {
            background: var(--gray-50);
            border-radius: 6px;
            padding: 0.75rem;
            margin: 0.5rem 0;
            font-family: 'Monaco', 'Menlo', monospace;
            font-size: 0.85rem;
            line-height: 1.4;
        }

        .request-timestamp {
            font-size: 0.8rem;
            color: var(--gray-400);
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid var(--gray-100);
        }

        /* Admin Actions */
        .admin-actions {
            background: var(--gray-50);
            padding: 1.5rem;
            border-top: 1px solid var(--gray-100);
        }

        .action-form {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            align-items: end;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-label {
            font-size: 0.85rem;
            font-weight: 500;
            color: var(--gray-600);
            margin-bottom: 0.5rem;
        }

        .form-control, .form-select {
            padding: 0.75rem;
            border: 1px solid var(--gray-300);
            border-radius: 6px;
            font-size: 0.9rem;
            transition: all 0.2s ease;
            background: var(--white-primary);
        }

        .form-control:focus, .form-select:focus {
            outline: none;
            border-color: var(--neofox-yellow);
            box-shadow: 0 0 0 3px rgba(255, 215, 0, 0.1);
        }

        .form-control[readonly] {
            background: var(--gray-100);
            color: var(--gray-600);
        }

        .action-buttons {
            display: flex;
            gap: 1rem;
            margin-top: 1rem;
        }

        .action-buttons .btn {
            flex: 1;
        }

        .approve-checkout-section {
            background: rgba(16, 185, 129, 0.08);
            border: 2px solid rgba(16, 185, 129, 0.3);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1rem;
        }

        .approve-checkout-title {
            font-weight: 700;
            color: var(--green-500);
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 1.1rem;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: var(--gray-500);
        }

        .empty-icon {
            font-size: 4rem;
            margin-bottom: 1.5rem;
            opacity: 0.3;
        }

        .empty-title {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: var(--gray-600);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .navbar-container {
                padding: 1rem;
                flex-direction: column;
                gap: 1rem;
            }

            .main-container {
                padding: 1rem;
            }

            .page-header {
                flex-direction: column;
                gap: 1rem;
                align-items: stretch;
            }

            .requests-grid {
                grid-template-columns: 1fr;
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .action-buttons {
                flex-direction: column;
            }

            .request-header {
                flex-direction: column;
                gap: 1rem;
                align-items: stretch;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="navbar-container">
            <a href="index.php" class="navbar-brand">
                <div class="brand-icon">
                    <i class="fas fa-cube"></i>
                </div>
                Neofox Gear
            </a>
            <div class="navbar-nav">
                <a class="nav-link" href="index.php">Dashboard</a>
                <a class="nav-link" href="assets.php">Assets</a>
                <a class="nav-link" href="scanner.php">Scanner</a>
                <a class="nav-link" href="bulk_scanner_v2.php">QR Scanner</a>
                <a class="nav-link active" href="requests.php">Requests</a>
                <a class="nav-link" href="logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="main-container">
        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h1 class="page-title">Gear Requests</h1>
                <p class="page-subtitle">Review and manage equipment requests from team members</p>
            </div>
            <a href="gear_request.php" class="btn btn-primary">
                <i class="fas fa-plus"></i>
                New Request
            </a>
        </div>

        <!-- Alerts -->
        <?php if (isset($success)): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
        </div>
        <?php endif; ?>

        <?php if (isset($warning)): ?>
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($warning); ?>
        </div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
        </div>
        <?php endif; ?>

        <!-- Requests Grid -->
        <div class="requests-grid">
            <?php foreach ($requests as $request): ?>
            <div class="request-card">
                <div class="request-header">
                    <div class="request-user">
                        <div class="user-avatar">
                            <?php echo strtoupper(substr($request['requester_name'], 0, 1)); ?>
                        </div>
                        <div class="user-info">
                            <h6><?php echo htmlspecialchars($request['requester_name']); ?></h6>
                            <?php if ($request['requester_email']): ?>
                            <div class="user-email"><?php echo htmlspecialchars($request['requester_email']); ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <span class="status-badge status-<?php echo $request['status']; ?>">
                        <?php echo ucfirst($request['status']); ?>
                    </span>
                </div>
                
                <div class="request-body">
                    <div class="request-details">
                        <div class="detail-row">
                            <div class="detail-label">Dates:</div>
                            <div class="detail-value"><?php echo htmlspecialchars($request['request_dates']); ?></div>
                        </div>
                        
                        <div class="detail-row">
                            <div class="detail-label">Equipment:</div>
                            <div class="detail-value">
                                <div class="equipment-list"><?php echo nl2br(htmlspecialchars($request['required_items'])); ?></div>
                            </div>
                        </div>
                        
                        <?php if ($request['purpose']): ?>
                        <div class="detail-row">
                            <div class="detail-label">Purpose:</div>
                            <div class="detail-value"><?php echo nl2br(htmlspecialchars($request['purpose'])); ?></div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($request['admin_notes']) && trim($request['admin_notes']) !== ''): ?>
                        <div class="detail-row">
                            <div class="detail-label">Admin Notes:</div>
                            <div class="detail-value"><?php echo nl2br(htmlspecialchars($request['admin_notes'])); ?></div>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="request-timestamp">
                        <i class="fas fa-clock"></i>
                        Requested: <?php echo date('M j, Y g:i A', strtotime($request['created_at'])); ?>
                    </div>
                </div>
                
                <?php if ($_SESSION['role'] == 'admin'): ?>
                <div class="admin-actions">
                    <form method="POST" id="adminForm_<?php echo $request['id']; ?>">
                        <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                        <input type="hidden" name="purpose" value="<?php echo htmlspecialchars($request['purpose']); ?>">
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-select" required>
                                    <option value="pending" <?php echo $request['status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="approved" <?php echo $request['status'] == 'approved' ? 'selected' : ''; ?>>Approved</option>
                                    <option value="rejected" <?php echo $request['status'] == 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                </select>
                            </div>
                            <?php if ($request['status'] == 'pending'): ?>
                            <div class="form-group">
                                <label class="form-label">Expected Return Date</label>
                                <input type="date" name="expected_return" class="form-control" 
                                       min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>"
                                       value="<?php echo date('Y-m-d', strtotime('+7 days')); ?>">
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Admin Notes</label>
                            <textarea name="admin_notes" class="form-control" rows="2" 
                                placeholder="Add notes about this request..."><?php echo isset($request['admin_notes']) && $request['admin_notes'] ? htmlspecialchars($request['admin_notes']) : ''; ?></textarea>
                        </div>
                        
                        <div class="action-buttons">
                            <button type="submit" name="update_status" class="btn btn-secondary">
                                <i class="fas fa-save"></i> Update Status
                            </button>
                            <?php if ($request['status'] == 'pending'): ?>
                            <button type="submit" name="approve_and_checkout" class="btn btn-success" 
                                    onclick="return confirmApproveCheckout('<?php echo htmlspecialchars($request['requester_name']); ?>')">
                                <i class="fas fa-check-double"></i> Approve & Checkout
                            </button>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        
        <?php if (empty($requests)): ?>
        <div class="empty-state">
            <div class="empty-icon">
                <i class="fas fa-inbox"></i>
            </div>
            <h3 class="empty-title">No gear requests yet</h3>
            <p>Gear requests will appear here when submitted by team members.</p>
        </div>
        <?php endif; ?>
    </div>

    <script>
        // Confirm before approve & checkout
        function confirmApproveCheckout(borrowerName) {
            const form = event.target.form;
            const returnDate = form.querySelector('input[name="expected_return"]');
            
            if (!returnDate || !returnDate.value) {
                alert('Please select an expected return date before approving and checking out equipment.');
                if (returnDate) returnDate.focus();
                return false;
            }
            
            return confirm(`This will approve the request and check out all available equipment to ${borrowerName} until ${returnDate.value}. Continue?`);
        }

        // Set default return date to 7 days from now
        document.addEventListener('DOMContentLoaded', function() {
            const returnDateInputs = document.querySelectorAll('input[name="expected_return"]');
            const defaultDate = new Date();
            defaultDate.setDate(defaultDate.getDate() + 7);
            const defaultDateString = defaultDate.toISOString().split('T')[0];
            
            returnDateInputs.forEach(input => {
                if (!input.value) {
                    input.value = defaultDateString;
                }
            });
        });
    </script>
</body>
</html>