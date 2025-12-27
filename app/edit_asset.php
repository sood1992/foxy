<?php
// edit_asset.php - Clean Minimal Edit Asset Page
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Fix session path issue
if (!session_id()) {
    ini_set('session.save_path', sys_get_temp_dir());
    session_start();
}

require_once 'config/database.php';
require_once 'classes/Asset.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();
$asset = new Asset($db);

// Get asset ID from URL
$asset_id = $_GET['id'] ?? null;
if (!$asset_id) {
    header("Location: assets.php");
    exit();
}

// Get asset data
$asset_data = $asset->getById($asset_id);
if (!$asset_data) {
    $_SESSION['error'] = "Asset not found.";
    header("Location: assets.php");
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $update_data = [
        'asset_name' => $_POST['asset_name'],
        'category' => $_POST['category'],
        'description' => $_POST['description'],
        'serial_number' => $_POST['serial_number'],
        'condition_status' => $_POST['condition_status'],
        'notes' => $_POST['notes']
    ];
    
    if ($asset->update($asset_id, $update_data)) {
        $_SESSION['success'] = "Asset updated successfully!";
        header("Location: assets.php");
        exit();
    } else {
        $errors = $asset->getErrors();
        $error = !empty($errors) ? implode(', ', $errors) : "Failed to update asset.";
    }
}

// Get asset history for the sidebar
$asset_history = $asset->getAssetHistory($asset_data['asset_id']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Asset - <?php echo htmlspecialchars($asset_data['asset_name']); ?> - Neofox Gear Control</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
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

        .nav-link:hover {
            background: var(--gray-100);
            color: var(--black-primary);
        }

        /* Main Container */
        .main-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }

        /* Breadcrumbs */
        .breadcrumbs {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 2rem;
            font-size: 0.9rem;
            color: var(--gray-500);
        }

        .breadcrumb-link {
            color: var(--gray-600);
            text-decoration: none;
        }

        .breadcrumb-link:hover {
            color: var(--black-primary);
        }

        .breadcrumb-separator {
            color: var(--gray-400);
        }

        /* Page Header */
        .page-header {
            margin-bottom: 2rem;
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

        /* Layout Grid */
        .edit-layout {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
        }

        /* Cards */
        .card {
            background: var(--white-primary);
            border: 1px solid var(--gray-200);
            border-radius: 12px;
            overflow: hidden;
            margin-bottom: 1.5rem;
        }

        .card-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--gray-200);
            background: var(--gray-50);
        }

        .card-title {
            font-weight: 600;
            color: var(--black-primary);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin: 0;
        }

        .card-body {
            padding: 1.5rem;
        }

        /* Form Styling */
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .form-label {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--gray-700);
            margin-bottom: 0.5rem;
        }

        .form-label.required::after {
            content: " *";
            color: var(--red-500);
        }

        .form-control, .form-select, .form-textarea {
            padding: 0.75rem;
            border: 1px solid var(--gray-300);
            border-radius: 8px;
            font-size: 0.9rem;
            transition: all 0.2s ease;
            background: var(--white-primary);
            font-family: inherit;
        }

        .form-textarea {
            resize: vertical;
            min-height: 100px;
        }

        .form-control:focus, .form-select:focus, .form-textarea:focus {
            outline: none;
            border-color: var(--neofox-yellow);
            box-shadow: 0 0 0 3px rgba(255, 215, 0, 0.1);
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
            font-family: inherit;
        }

        .btn-primary {
            background: var(--black-primary);
            color: var(--white-primary);
        }

        .btn-primary:hover {
            background: var(--black-light);
        }

        .btn-secondary {
            background: var(--white-primary);
            color: var(--gray-700);
            border: 1px solid var(--gray-300);
        }

        .btn-secondary:hover {
            background: var(--gray-50);
            border-color: var(--gray-400);
        }

        .btn-danger {
            background: var(--red-500);
            color: var(--white-primary);
        }

        .btn-danger:hover {
            background: #dc2626;
        }

        .button-group {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
        }

        /* Alerts */
        .alert {
            padding: 1rem 1.5rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            color: var(--green-500);
            border: 1px solid rgba(16, 185, 129, 0.2);
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            color: var(--red-500);
            border: 1px solid rgba(239, 68, 68, 0.2);
        }

        /* Asset Info Sidebar */
        .asset-info {
            background: var(--gray-50);
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
        }

        .asset-detail {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.75rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid var(--gray-200);
        }

        .asset-detail:last-child {
            margin-bottom: 0;
            padding-bottom: 0;
            border-bottom: none;
        }

        .detail-label {
            font-weight: 500;
            color: var(--gray-600);
            font-size: 0.85rem;
        }

        .detail-value {
            color: var(--gray-700);
            font-size: 0.85rem;
            text-align: right;
        }

        /* Status Badges */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 500;
            text-transform: capitalize;
        }

        .status-available {
            background: rgba(16, 185, 129, 0.1);
            color: var(--green-500);
        }

        .status-checked_out {
            background: rgba(245, 158, 11, 0.1);
            color: var(--orange-500);
        }

        .status-maintenance {
            background: rgba(59, 130, 246, 0.1);
            color: var(--blue-500);
        }

        .condition-excellent {
            background: rgba(16, 185, 129, 0.1);
            color: var(--green-500);
        }

        .condition-good {
            background: rgba(245, 158, 11, 0.1);
            color: var(--orange-500);
        }

        .condition-needs_repair {
            background: rgba(239, 68, 68, 0.1);
            color: var(--red-500);
        }

        .status-dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: currentColor;
        }

        /* History Timeline */
        .history-item {
            padding: 1rem;
            border-left: 3px solid var(--gray-200);
            margin-bottom: 1rem;
            position: relative;
        }

        .history-item::before {
            content: '';
            position: absolute;
            left: -6px;
            top: 1rem;
            width: 9px;
            height: 9px;
            border-radius: 50%;
            background: var(--gray-400);
        }

        .history-item.checkout::before {
            background: var(--green-500);
        }

        .history-item.checkin::before {
            background: var(--orange-500);
        }

        .history-date {
            font-size: 0.8rem;
            color: var(--gray-500);
            font-weight: 500;
        }

        .history-action {
            font-weight: 600;
            color: var(--gray-700);
            margin: 0.25rem 0;
        }

        .history-details {
            font-size: 0.85rem;
            color: var(--gray-600);
        }

        /* QR Code Display */
        .qr-display {
            text-align: center;
            padding: 1rem;
        }

        .qr-display img {
            max-width: 150px;
            border: 1px solid var(--gray-200);
            border-radius: 8px;
        }

        /* Responsive Design */
        @media (max-width: 1024px) {
            .edit-layout {
                grid-template-columns: 1fr;
                gap: 1.5rem;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .main-container {
                padding: 1rem;
            }

            .navbar-container {
                padding: 1rem;
                flex-direction: column;
                gap: 1rem;
            }

            .navbar-nav {
                flex-wrap: wrap;
                justify-content: center;
            }

            .button-group {
                flex-direction: column;
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
                <a class="nav-link" href="dashboard_advanced.php">Analytics</a>
                <a class="nav-link" href="assets.php">Assets</a>
                <a class="nav-link" href="scanner.php">Scanner</a>
                <a class="nav-link" href="bulk_scanner_v2.php">QR Scanner</a>
                <a class="nav-link" href="reports_dashboard.php">Reports</a>
                <a class="nav-link" href="requests.php">Requests</a>
                <a class="nav-link" href="logout.php">Logout</a>
                <a class="nav-link" href="maintenance.php">Maintenance</a>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="main-container">
        <!-- Breadcrumbs -->
        <div class="breadcrumbs">
            <a href="index.php" class="breadcrumb-link">Dashboard</a>
            <span class="breadcrumb-separator"><i class="fas fa-chevron-right"></i></span>
            <a href="assets.php" class="breadcrumb-link">Assets</a>
            <span class="breadcrumb-separator"><i class="fas fa-chevron-right"></i></span>
            <span>Edit Asset</span>
        </div>

        <!-- Page Header -->
        <div class="page-header">
            <h1 class="page-title">Edit Asset</h1>
            <p class="page-subtitle">Update asset information and track changes</p>
        </div>

        <!-- Alerts -->
        <?php if (isset($error)): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo htmlspecialchars($error); ?>
        </div>
        <?php endif; ?>

        <!-- Main Layout -->
        <div class="edit-layout">
            <!-- Edit Form -->
            <div>
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-edit"></i>
                            Asset Information
                        </h3>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="form-grid">
                                <div class="form-group">
                                    <label class="form-label required">Asset Name</label>
                                    <input type="text" name="asset_name" class="form-control" 
                                           value="<?php echo htmlspecialchars($asset_data['asset_name']); ?>" required>
                                </div>

                                <div class="form-group">
                                    <label class="form-label required">Category</label>
                                    <select name="category" class="form-select" required>
                                        <option value="">Select category...</option>
                                        <?php
                                        $categories = ['Camera', 'Audio', 'Lighting', 'Drone', 'Tripod', 'Lens', 'Monitor', 'Storage', 'Cables', 'Other'];
                                        foreach ($categories as $cat):
                                        ?>
                                        <option value="<?php echo $cat; ?>" <?php echo $asset_data['category'] == $cat ? 'selected' : ''; ?>>
                                            <?php echo $cat; ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label class="form-label">Serial Number</label>
                                    <input type="text" name="serial_number" class="form-control" 
                                           value="<?php echo htmlspecialchars($asset_data['serial_number'] ?? ''); ?>">
                                </div>

                                <div class="form-group">
                                    <label class="form-label required">Condition</label>
                                    <select name="condition_status" class="form-select" required>
                                        <option value="excellent" <?php echo $asset_data['condition_status'] == 'excellent' ? 'selected' : ''; ?>>Excellent</option>
                                        <option value="good" <?php echo $asset_data['condition_status'] == 'good' ? 'selected' : ''; ?>>Good</option>
                                        <option value="needs_repair" <?php echo $asset_data['condition_status'] == 'needs_repair' ? 'selected' : ''; ?>>Needs Repair</option>
                                    </select>
                                </div>

                                <div class="form-group full-width">
                                    <label class="form-label">Description</label>
                                    <textarea name="description" class="form-textarea" rows="3"><?php echo htmlspecialchars($asset_data['description'] ?? ''); ?></textarea>
                                </div>

                                <div class="form-group full-width">
                                    <label class="form-label">Notes</label>
                                    <textarea name="notes" class="form-textarea" rows="2"><?php echo htmlspecialchars($asset_data['notes'] ?? ''); ?></textarea>
                                </div>
                            </div>

                            <div class="button-group">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i>
                                    Save Changes
                                </button>
                                <a href="assets.php" class="btn btn-secondary">
                                    <i class="fas fa-times"></i>
                                    Cancel
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div>
                <!-- Asset Details -->
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">
                            <i class="fas fa-info-circle"></i>
                            Asset Details
                        </h4>
                    </div>
                    <div class="card-body">
                        <div class="asset-info">
                            <div class="asset-detail">
                                <span class="detail-label">Asset ID</span>
                                <span class="detail-value" style="font-family: Monaco, monospace;">
                                    <?php echo htmlspecialchars($asset_data['asset_id']); ?>
                                </span>
                            </div>
                            <div class="asset-detail">
                                <span class="detail-label">Current Status</span>
                                <span class="detail-value">
                                    <span class="status-badge status-<?php echo $asset_data['status']; ?>">
                                        <span class="status-dot"></span>
                                        <?php echo ucfirst(str_replace('_', ' ', $asset_data['status'])); ?>
                                    </span>
                                </span>
                            </div>
                            <?php if ($asset_data['current_borrower']): ?>
                            <div class="asset-detail">
                                <span class="detail-label">Current Borrower</span>
                                <span class="detail-value">
                                    <?php echo htmlspecialchars($asset_data['current_borrower']); ?>
                                </span>
                            </div>
                            <?php endif; ?>
                            <div class="asset-detail">
                                <span class="detail-label">Created</span>
                                <span class="detail-value">
                                    <?php echo date('M j, Y', strtotime($asset_data['created_at'])); ?>
                                </span>
                            </div>
                            <?php if ($asset_data['updated_at'] != $asset_data['created_at']): ?>
                            <div class="asset-detail">
                                <span class="detail-label">Last Updated</span>
                                <span class="detail-value">
                                    <?php echo date('M j, Y', strtotime($asset_data['updated_at'])); ?>
                                </span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- QR Code -->
                <?php if ($asset_data['qr_code']): ?>
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">
                            <i class="fas fa-qrcode"></i>
                            QR Code
                        </h4>
                    </div>
                    <div class="card-body">
                        <div class="qr-display">
                            <img src="<?php echo htmlspecialchars($asset_data['qr_code']); ?>" alt="Asset QR Code">
                            <div style="margin-top: 0.5rem;">
                                <a href="<?php echo htmlspecialchars($asset_data['qr_code']); ?>" target="_blank" class="btn btn-secondary btn-sm">
                                    <i class="fas fa-external-link-alt"></i>
                                    View Full Size
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Transaction History -->
                <?php if (!empty($asset_history)): ?>
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">
                            <i class="fas fa-history"></i>
                            Recent Activity
                        </h4>
                    </div>
                    <div class="card-body">
                        <?php foreach (array_slice($asset_history, 0, 5) as $history): ?>
                        <div class="history-item <?php echo $history['transaction_type']; ?>">
                            <div class="history-date">
                                <?php echo date('M j, Y g:i A', strtotime($history['transaction_date'])); ?>
                            </div>
                            <div class="history-action">
                                <?php echo ucfirst($history['transaction_type']); ?>
                            </div>
                            <div class="history-details">
                                <?php if ($history['transaction_type'] == 'checkout'): ?>
                                    Checked out to <?php echo htmlspecialchars($history['borrower_name']); ?>
                                    <?php if ($history['purpose']): ?>
                                        <br><em><?php echo htmlspecialchars($history['purpose']); ?></em>
                                    <?php endif; ?>
                                <?php else: ?>
                                    Returned by <?php echo htmlspecialchars($history['borrower_name']); ?>
                                    <?php if ($history['condition_on_return']): ?>
                                        <br>Condition: <?php echo ucfirst(str_replace('_', ' ', $history['condition_on_return'])); ?>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        
                        <?php if (count($asset_history) > 5): ?>
                        <div style="text-align: center; margin-top: 1rem;">
                            <a href="reports_dashboard.php?asset_filter=<?php echo urlencode($asset_data['asset_id']); ?>" class="btn btn-secondary btn-sm">
                                View Full History
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Danger Zone -->
                <?php if ($asset_data['status'] == 'available'): ?>
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title" style="color: var(--red-500);">
                            <i class="fas fa-exclamation-triangle"></i>
                            Danger Zone
                        </h4>
                    </div>
                    <div class="card-body">
                        <p style="font-size: 0.9rem; color: var(--gray-600); margin-bottom: 1rem;">
                            Permanently delete this asset. This action cannot be undone.
                        </p>
                        <button type="button" class="btn btn-danger" onclick="confirmDelete()">
                            <i class="fas fa-trash"></i>
                            Delete Asset
                        </button>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        function confirmDelete() {
            if (confirm('Are you sure you want to delete this asset? This action cannot be undone.')) {
                window.location.href = 'delete_asset.php?id=<?php echo $asset_id; ?>';
            }
        }
    </script>
</body>
</html>