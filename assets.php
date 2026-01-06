<?php
// Enhanced assets.php with bulk delete functionality and photo viewing - COMPLETE WITH 15MM QR FUNCTIONALITY
// Fix session path issue
if (!session_id()) {
    ini_set('session.save_path', sys_get_temp_dir());
    session_start();
}
require_once 'config/database.php';
require_once 'classes/Asset.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();
$asset = new Asset($db);

// Handle bulk delete with password protection
$admin_delete_password = getenv('DELETE_PASSWORD') ?: 'change_this_password';
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['bulk_delete'])) {
    // Check password first
    $delete_password = $_POST['delete_password'] ?? '';
    if ($delete_password !== $admin_delete_password) {
        $error = "Invalid password. Access denied for delete operation.";
    } elseif (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
        $error = "Only administrators can delete assets.";
    } else {
        $selected_assets = $_POST['selected_assets'] ?? [];
        
        if (empty($selected_assets)) {
            $error = "No assets selected for deletion.";
        } else {
            $deleted_count = 0;
            $failed_count = 0;
            $failed_assets = [];
            
            foreach ($selected_assets as $asset_id) {
                $asset_info = $asset->getById($asset_id);
                if ($asset_info && $asset_info['status'] == 'checked_out') {
                    $failed_count++;
                    $failed_assets[] = $asset_info['asset_name'] . ' (Currently checked out)';
                } else {
                    if ($asset->delete($asset_id)) {
                        $deleted_count++;
                    } else {
                        $failed_count++;
                        $failed_assets[] = $asset_info['asset_name'] ?? "Asset ID: $asset_id";
                    }
                }
            }
            
            if ($deleted_count > 0) {
                $success = "Successfully deleted $deleted_count asset(s).";
            }
            
            if ($failed_count > 0) {
                $error = "Failed to delete $failed_count asset(s): " . implode(', ', $failed_assets);
            }
        }
    }
}

$assets = $asset->getAll();
$stats = $asset->getAssetStats();

// Function to get asset photos
function getAssetPhotos($asset_id, $db) {
    try {
        // Check if asset_photos table exists
        $check_table = "SHOW TABLES LIKE 'asset_photos'";
        $stmt = $db->prepare($check_table);
        $stmt->execute();
        
        if ($stmt->rowCount() == 0) {
            return []; // Table doesn't exist yet
        }
        
        $query = "SELECT * FROM asset_photos WHERE asset_id = :asset_id ORDER BY photo_type, upload_date";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':asset_id', $asset_id);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error getting photos for asset $asset_id: " . $e->getMessage());
        return [];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assets - Neofox Gear Control</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
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
            position: relative;
            z-index: 1000;
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

        .header-actions {
            display: flex;
            gap: 1rem;
            align-items: center;
            margin-top: 1.5rem;
            flex-wrap: wrap;
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--white-primary);
            border: 1px solid var(--gray-200);
            border-radius: 12px;
            padding: 1.5rem;
            transition: all 0.2s ease;
        }

        .stat-card:hover {
            box-shadow: var(--shadow);
            border-color: var(--gray-300);
        }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 0.5rem;
        }

        .stat-icon {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
        }

        .stat-icon.available { background: rgba(16, 185, 129, 0.1); color: var(--green-500); }
        .stat-icon.checked-out { background: rgba(245, 158, 11, 0.1); color: var(--orange-500); }
        .stat-icon.maintenance { background: rgba(59, 130, 246, 0.1); color: var(--blue-500); }
        .stat-icon.total { background: rgba(0, 0, 0, 0.1); color: var(--black-primary); }

        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--black-primary);
            line-height: 1;
            margin-bottom: 0.25rem;
        }

        .stat-label {
            color: var(--gray-500);
            font-size: 0.9rem;
            font-weight: 500;
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

        .btn-danger {
            background: var(--red-500);
            color: var(--white-primary);
        }

        .btn-danger:hover {
            background: #dc2626;
            color: var(--white-primary);
        }

        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.8rem;
        }

        /* Dropdown */
        .dropdown {
            position: relative;
            display: inline-block;
        }

        .dropdown-toggle::after {
            content: '';
            border-top: 0.3em solid;
            border-right: 0.3em solid transparent;
            border-left: 0.3em solid transparent;
            margin-left: 0.5em;
        }

        .dropdown-menu {
            position: absolute;
            top: 100%;
            left: 0;
            z-index: 1000;
            display: none;
            min-width: 200px;
            padding: 0.5rem 0;
            margin: 0.125rem 0 0;
            font-size: 0.875rem;
            color: var(--gray-700);
            text-align: left;
            background-color: var(--white-primary);
            border: 1px solid var(--gray-200);
            border-radius: 8px;
            box-shadow: var(--shadow-lg);
        }

        .dropdown-menu.show {
            display: block;
        }

        .dropdown-item {
            display: block;
            width: 100%;
            padding: 0.5rem 1rem;
            clear: both;
            font-weight: 400;
            color: var(--gray-700);
            text-decoration: none;
            white-space: nowrap;
            background-color: transparent;
            border: 0;
            cursor: pointer;
        }

        .dropdown-item:hover {
            background-color: var(--gray-100);
            color: var(--black-primary);
        }

        /* Bulk Actions */
        .bulk-actions {
            background: var(--white-primary);
            border: 1px solid var(--gray-200);
            border-radius: 12px;
            padding: 1rem 1.5rem;
            margin-bottom: 1.5rem;
            display: none;
            align-items: center;
            gap: 1rem;
        }

        .bulk-actions.show {
            display: flex;
        }

        .bulk-selection-info {
            flex: 1;
            font-weight: 500;
            color: var(--gray-700);
        }

        /* Assets Table */
        .assets-card {
            background: var(--white-primary);
            border: 1px solid var(--gray-200);
            border-radius: 12px;
            overflow: hidden;
        }

        .assets-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .assets-title {
            font-weight: 600;
            color: var(--black-primary);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .assets-count {
            background: var(--gray-100);
            color: var(--gray-600);
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        /* Clean Table Styling */
        .table-container {
            overflow-x: auto;
        }

        .assets-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
        }

        .assets-table th {
            background: var(--gray-50);
            color: var(--gray-600);
            font-weight: 600;
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid var(--gray-200);
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .assets-table td {
            padding: 1rem;
            border-bottom: 1px solid var(--gray-100);
            vertical-align: middle;
        }

        .assets-table tr:hover {
            background: var(--gray-50);
        }

        .asset-info {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .asset-name {
            font-weight: 600;
            color: var(--black-primary);
        }

        .asset-id {
            font-size: 0.8rem;
            color: var(--gray-500);
            font-family: 'Monaco', 'Menlo', monospace;
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

        .status-lost {
            background: rgba(239, 68, 68, 0.1);
            color: var(--red-500);
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

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 0.5rem;
        }

        .action-btn {
            width: 32px;
            height: 32px;
            border-radius: 6px;
            border: 1px solid var(--gray-300);
            background: var(--white-primary);
            color: var(--gray-600);
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            transition: all 0.2s ease;
            font-size: 0.8rem;
            cursor: pointer;
        }

        .action-btn:hover {
            background: var(--gray-50);
            border-color: var(--gray-400);
            color: var(--gray-700);
        }

        .action-btn.edit { color: var(--blue-500); }
        .action-btn.checkout { color: var(--green-500); }
        .action-btn.checkin { color: var(--orange-500); }
        .action-btn.qr { color: var(--gray-600); position: relative; z-index: 1; }
        .action-btn.photos { color: #8B5CF6; }
        .action-btn.delete { color: var(--red-500); }

        /* Checkbox styling */
        .select-checkbox {
            width: 18px;
            height: 18px;
            accent-color: var(--neofox-yellow);
            cursor: pointer;
        }

        /* Alerts */
        .alert {
            padding: 1rem 1.5rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            border: 1px solid;
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            border-color: rgba(16, 185, 129, 0.3);
            color: #065f46;
        }

        .alert-danger {
            background: rgba(239, 68, 68, 0.1);
            border-color: rgba(239, 68, 68, 0.3);
            color: #991b1b;
        }

        /* Custom Lightboxes */
        .custom-lightbox {
            position: fixed !important;
            top: 0 !important;
            left: 0 !important;
            width: 100% !important;
            height: 100% !important;
            background: rgba(0, 0, 0, 0.9) !important;
            z-index: 9999 !important;
            display: none !important;
            align-items: center !important;
            justify-content: center !important;
            padding: 2rem !important;
        }

        .lightbox-content {
            background: var(--white-primary);
            border-radius: 16px;
            max-width: 90vw;
            max-height: 90vh;
            overflow: hidden;
            box-shadow: var(--shadow-lg);
            position: relative;
        }

        .lightbox-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .lightbox-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--black-primary);
            margin: 0;
        }

        .lightbox-subtitle {
            font-size: 0.9rem;
            color: var(--gray-500);
            margin-top: 0.25rem;
        }

        .lightbox-close {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            border: 1px solid var(--gray-300);
            background: var(--white-primary);
            color: var(--gray-600);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .lightbox-close:hover {
            background: var(--gray-50);
            border-color: var(--gray-400);
        }

        .lightbox-body {
            padding: 1.5rem;
            max-height: 60vh;
            overflow-y: auto;
        }

        .photo-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 1rem;
        }

        .photo-item {
            position: relative;
            aspect-ratio: 1;
            border-radius: 12px;
            overflow: hidden;
            background: var(--gray-100);
            border: 1px solid var(--gray-200);
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .photo-item:hover {
            transform: scale(1.02);
            box-shadow: var(--shadow);
        }

        .photo-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .photo-type-badge {
            position: absolute;
            top: 0.5rem;
            left: 0.5rem;
            background: rgba(0, 0, 0, 0.8);
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 6px;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: capitalize;
        }

        .no-photos {
            text-align: center;
            color: var(--gray-500);
            padding: 3rem 2rem;
            font-style: italic;
        }

        .no-photos i {
            font-size: 3rem;
            margin-bottom: 1rem;
            display: block;
            opacity: 0.5;
        }

        .qr-image-container {
            text-align: center;
            padding: 2rem;
        }

        .qr-image {
            max-width: 150px;
            width: 100%;
            height: auto;
            border-radius: 8px;
            border: 1px solid var(--gray-200);
        }

        .qr-asset-info {
            padding: 1.5rem;
            border-top: 1px solid var(--gray-200);
            background: var(--gray-50);
        }

        .qr-info-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.5rem 0;
            border-bottom: 1px solid var(--gray-200);
        }

        .qr-info-row:last-child {
            border-bottom: none;
        }

        .qr-info-label {
            font-weight: 600;
            color: var(--gray-700);
        }

        .qr-info-value {
            color: var(--gray-600);
            font-family: 'Monaco', 'Menlo', monospace;
            font-size: 0.9rem;
        }

        .qr-actions {
            padding: 1.5rem;
            display: flex;
            gap: 1rem;
        }

        .qr-btn {
            flex: 1;
            padding: 0.75rem 1rem;
            border-radius: 8px;
            font-weight: 500;
            border: none;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .qr-btn-primary {
            background: var(--black-primary);
            color: var(--white-primary);
        }

        .qr-btn-primary:hover {
            background: var(--black-light);
        }

        .qr-btn-secondary {
            background: var(--gray-200);
            color: var(--gray-700);
        }

        .qr-btn-secondary:hover {
            background: var(--gray-300);
        }

        /* Password Field Styling */
        .password-field {
            margin-bottom: 1rem;
        }

        .password-field label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--gray-700);
        }

        .password-field input {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid var(--gray-300);
            border-radius: 8px;
            font-size: 0.9rem;
            transition: border-color 0.2s ease;
        }

        .password-field input:focus {
            outline: none;
            border-color: var(--neofox-yellow);
            box-shadow: 0 0 0 0.2rem rgba(255, 215, 0, 0.25);
        }

        /* Form Elements */
        .form-control, .form-select {
            border-radius: 8px;
            border: 2px solid #e9ecef;
            padding: 0.75rem;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--neofox-yellow);
            box-shadow: 0 0 0 0.2rem rgba(255, 215, 0, 0.25);
        }

        /* Responsive Design */
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

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .header-actions {
                flex-direction: column;
                align-items: stretch;
            }

            .bulk-actions {
                flex-direction: column;
                align-items: stretch;
                gap: 0.75rem;
            }

            /* Hide less important columns on mobile */
            .mobile-hide {
                display: none;
            }

            .photo-grid {
                grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            }
        }

        /* DataTables Override */
        .dataTables_wrapper {
            font-family: inherit;
        }

        .dataTables_filter {
            margin-bottom: 1rem;
        }

        .dataTables_filter input {
            border: 1px solid var(--gray-300);
            border-radius: 6px;
            padding: 0.5rem;
            margin-left: 0.5rem;
        }

        .dataTables_length select {
            border: 1px solid var(--gray-300);
            border-radius: 6px;
            padding: 0.5rem;
            margin: 0 0.5rem;
        }

        .dataTables_info, .dataTables_paginate {
            margin-top: 1rem;
        }

        .paginate_button {
            padding: 0.5rem 0.75rem !important;
            margin: 0 0.25rem !important;
            border-radius: 6px !important;
            border: 1px solid var(--gray-300) !important;
            background: var(--white-primary) !important;
            color: var(--gray-600) !important;
        }

        .paginate_button:hover {
            background: var(--gray-50) !important;
            border-color: var(--gray-400) !important;
        }

        .paginate_button.current {
            background: var(--black-primary) !important;
            border-color: var(--black-primary) !important;
            color: var(--white-primary) !important;
        }

        /* Loading state */
        .loading {
            opacity: 0.6;
            pointer-events: none;
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
                <a class="nav-link active" href="assets.php">Assets</a>
                <a class="nav-link" href="bulk_scanner_v2.php">QR Scanner</a>
                <a class="nav-link" href="crew_checkout.php">Crew Checkout</a>
                <a class="nav-link" href="reports_dashboard.php">Reports</a>
                <a class="nav-link" href="requests.php">Requests</a>
                <a class="nav-link" href="logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="main-container">
        <!-- Page Header -->
        <div class="page-header">
            <h1 class="page-title">Assets</h1>
            <p class="page-subtitle">Manage and track all your equipment inventory</p>
            
            <div class="header-actions">
                <a href="add_asset.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i>
                    Add New Asset
                </a>
                <a href="bulk_scanner_v2.php" class="btn btn-secondary">
                    <i class="fas fa-qrcode"></i>
                    Bulk Scanner
                </a>
                <a href="crew_checkout.php" class="btn btn-secondary">
                    <i class="fas fa-users"></i>
                    Crew Checkout
                </a>
                <a href="reports_dashboard.php" class="btn btn-secondary">
                    <i class="fas fa-chart-line"></i>
                    View Reports
                </a>
                <div class="dropdown">
                    <button type="button" class="btn btn-secondary dropdown-toggle" id="qrDownloadDropdown">
                        <i class="fas fa-download"></i>
                        Download Clean 15mm QR Codes
                    </button>
                    <ul class="dropdown-menu" id="qrDropdownMenu">
                        <li>
                            <button type="button" class="dropdown-item" onclick="bulkDownloadQR();">
                                <i class="fas fa-download"></i> All Assets
                            </button>
                        </li>
                        <li>
                            <button type="button" class="dropdown-item" onclick="bulkDownloadSelectedQR();">
                                <i class="fas fa-check-square"></i> Selected Assets Only
                            </button>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

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

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-header">
                    <div>
                        <div class="stat-number"><?php echo $stats['total_assets']; ?></div>
                        <div class="stat-label">Total Assets</div>
                    </div>
                    <div class="stat-icon total">
                        <i class="fas fa-boxes"></i>
                    </div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-header">
                    <div>
                        <div class="stat-number"><?php echo $stats['available']; ?></div>
                        <div class="stat-label">Available</div>
                    </div>
                    <div class="stat-icon available">
                        <i class="fas fa-check-circle"></i>
                    </div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-header">
                    <div>
                        <div class="stat-number"><?php echo $stats['checked_out']; ?></div>
                        <div class="stat-label">Checked Out</div>
                    </div>
                    <div class="stat-icon checked-out">
                        <i class="fas fa-arrow-right"></i>
                    </div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-header">
                    <div>
                        <div class="stat-number"><?php echo $stats['maintenance'] ?? 0; ?></div>
                        <div class="stat-label">Maintenance</div>
                    </div>
                    <div class="stat-icon maintenance">
                        <i class="fas fa-tools"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Bulk Actions Bar -->
        <form method="POST" id="bulk-form">
            <div class="bulk-actions" id="bulk-actions">
                <div class="bulk-selection-info">
                    <i class="fas fa-check-square"></i>
                    <span id="selection-count">0</span> asset(s) selected
                </div>
                <div style="display: flex; gap: 0.5rem;">
                    <button type="button" class="btn btn-secondary btn-sm" onclick="selectAll()">
                        <i class="fas fa-check-double"></i> Select All
                    </button>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="clearSelection()">
                        <i class="fas fa-times"></i> Clear Selection
                    </button>
                    <?php if (isset($_SESSION['role']) && $_SESSION['role'] == 'admin'): ?>
                    <button type="button" class="btn btn-danger btn-sm" onclick="confirmBulkDelete()">
                        <i class="fas fa-trash"></i> Delete Selected
                    </button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Assets Table -->
            <div class="assets-card">
                <div class="assets-header">
                    <div class="assets-title">
                        <i class="fas fa-list"></i>
                        All Assets
                    </div>
                    <div class="assets-count">
                        <?php echo count($assets); ?> items
                    </div>
                </div>
                
                <div class="table-container">
                    <table class="assets-table" id="assetsTable">
                        <thead>
                            <tr>
                                <th>
                                    <input type="checkbox" id="select-all" class="select-checkbox">
                                </th>
                                <th>Asset</th>
                                <th>Category</th>
                                <th>Status</th>
                                <th>Condition</th>
                                <th class="mobile-hide">Current Borrower</th>
                                <th>Photos</th>
                                <th class="mobile-hide">QR Code</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($assets as $item): ?>
                            <?php $photos = getAssetPhotos($item['asset_id'], $db); ?>
                            <tr>
                                <td>
                                    <input type="checkbox" name="selected_assets[]" value="<?php echo $item['id']; ?>" 
                                           class="select-checkbox asset-checkbox">
                                </td>
                                <td>
                                    <div class="asset-info">
                                        <div class="asset-name"><?php echo htmlspecialchars($item['asset_name']); ?></div>
                                        <div class="asset-id"><?php echo htmlspecialchars($item['asset_id']); ?></div>
                                    </div>
                                </td>
                                <td>
                                    <span style="color: var(--gray-600); font-weight: 500;">
                                        <?php echo htmlspecialchars($item['category']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo $item['status']; ?>">
                                        <span class="status-dot"></span>
                                        <?php echo ucfirst(str_replace('_', ' ', $item['status'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="status-badge condition-<?php echo $item['condition_status']; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $item['condition_status'])); ?>
                                    </span>
                                </td>
                                <td class="mobile-hide">
                                    <?php if ($item['current_borrower']): ?>
                                        <span style="color: var(--gray-700); font-weight: 500;">
                                            <?php echo htmlspecialchars($item['current_borrower']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="color: var(--gray-400);">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($photos)): ?>
                                    <button type="button" class="action-btn photos" title="View Photos (<?php echo count($photos); ?>)" 
                                            onclick="showAssetPhotos('<?php echo htmlspecialchars($item['asset_id']); ?>', '<?php echo htmlspecialchars($item['asset_name']); ?>')">
                                        <i class="fas fa-images"></i>
                                        <span style="font-size: 0.7rem; margin-left: 2px;"><?php echo count($photos); ?></span>
                                    </button>
                                    <?php else: ?>
                                    <span style="color: var(--gray-400); font-size: 0.8rem;">No photos</span>
                                    <?php endif; ?>
                                </td>
                                <td class="mobile-hide">
                                    <button type="button" class="action-btn qr" title="View QR Code" 
                                            onclick="showQRLightbox('<?= htmlspecialchars($item['qr_code']) ?>', '<?= htmlspecialchars($item['asset_name']) ?>', '<?= htmlspecialchars($item['asset_id']) ?>', '<?= htmlspecialchars($item['category']) ?>')">
                                        <i class="fas fa-qrcode"></i>
                                    </button>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="edit_asset.php?id=<?php echo $item['id']; ?>" class="action-btn edit" title="Edit Asset">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <?php if ($item['status'] == 'available'): ?>
                                        <a href="checkout.php?asset_id=<?php echo $item['asset_id']; ?>" class="action-btn checkout" title="Check Out">
                                            <i class="fas fa-sign-out-alt"></i>
                                        </a>
                                        <?php elseif ($item['status'] == 'checked_out'): ?>
                                        <a href="checkin.php?asset_id=<?php echo $item['asset_id']; ?>" class="action-btn checkin" title="Check In">
                                            <i class="fas fa-sign-in-alt"></i>
                                        </a>
                                        <?php endif; ?>
                                        <?php if (isset($_SESSION['role']) && $_SESSION['role'] == 'admin'): ?>
                                        <button type="button" class="action-btn delete" title="Delete Asset" 
                                                onclick="confirmSingleDelete(<?php echo $item['id']; ?>, '<?php echo htmlspecialchars($item['asset_name'], ENT_QUOTES); ?>')">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </form>
    </div>

    <!-- Custom Photo Lightbox -->
    <div class="custom-lightbox" id="photoLightbox">
        <div class="lightbox-content">
            <div class="lightbox-header">
                <div>
                    <h3 class="lightbox-title" id="photoAssetName"></h3>
                    <div class="lightbox-subtitle" id="photoAssetDetails"></div>
                </div>
                <button class="lightbox-close" onclick="closePhotoLightbox()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="lightbox-body" id="photoGalleryContainer">
                <div class="photo-grid" id="photoGrid">
                    <!-- Photos will be loaded here -->
                </div>
            </div>
        </div>
    </div>

    <!-- Custom QR Lightbox -->
    <div class="custom-lightbox" id="qrLightbox">
        <div class="lightbox-content">
            <div class="lightbox-header">
                <div>
                    <h3 class="lightbox-title" id="qrAssetName"></h3>
                    <div class="lightbox-subtitle" id="qrAssetDetails"></div>
                </div>
                <button class="lightbox-close" onclick="closeQRLightbox()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="qr-image-container">
                <img id="qrImage" class="qr-image" src="" alt="QR Code">
            </div>
            <div class="qr-asset-info" id="qrAssetInfo">
                <!-- Asset info will be populated here -->
            </div>
            <div class="qr-actions">
                <button class="qr-btn qr-btn-primary" onclick="downloadSingleQR()">
                    <i class="fas fa-download"></i>
                    Download QR
                </button>
                <button class="qr-btn qr-btn-secondary" onclick="printQR()">
                    <i class="fas fa-print"></i>
                    Print Clean 15mm Label
                </button>
            </div>
        </div>
    </div>

    <!-- Bootstrap Modals for Delete Confirmation -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteModalLabel">
                        <i class="fas fa-exclamation-triangle text-danger"></i>
                        Confirm Bulk Delete
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-3">Are you sure you want to delete <strong id="delete-count">0</strong> selected asset(s)?</p>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>Warning:</strong> This action cannot be undone. Equipment that is currently checked out cannot be deleted.
                    </div>
                    
                    <!-- Password Field -->
                    <div class="password-field">
                        <label for="bulk-delete-password">Enter Delete Password:</label>
                        <input type="password" id="bulk-delete-password" class="form-control" placeholder="Enter password to proceed" required>
                    </div>
                    
                    <div id="selected-assets-list" class="small text-muted">
                        <!-- Selected assets will be listed here -->
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" onclick="executeBulkDelete()">
                        <i class="fas fa-trash"></i> Delete Assets
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Single Delete Confirmation Modal -->
    <div class="modal fade" id="singleDeleteModal" tabindex="-1" aria-labelledby="singleDeleteModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="singleDeleteModalLabel">
                        <i class="fas fa-exclamation-triangle text-danger"></i>
                        Confirm Delete
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-3">Are you sure you want to delete <strong id="single-asset-name">this asset</strong>?</p>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>Warning:</strong> This action cannot be undone.
                    </div>
                    
                    <!-- Password Field -->
                    <div class="password-field">
                        <label for="single-delete-password">Enter Delete Password:</label>
                        <input type="password" id="single-delete-password" class="form-control" placeholder="Enter password to proceed" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" onclick="executeSingleDelete()">
                        <i class="fas fa-trash"></i> Delete Asset
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Global variables
        let currentQRData = {};
        let currentSingleDeleteId = null;

        // Get current domain for short URLs
        function getCurrentDomain() {
            return window.location.hostname;
        }

        // Generate ultra-compact QR codes using shortest possible URLs
        function generateUltraCompactQR(assetId, strategy = 'minimal') {
            const baseUrl = "https://quickchart.io/qr";
            
            let text;
            switch (strategy) {
                case 'asset_only':
                    // Strategy 1: Just asset ID (shortest possible)
                    text = assetId;
                    break;
                case 'minimal':
                default:
                    // Strategy 2: Minimal URL (domain + asset ID)
                    text = `https://${getCurrentDomain()}/${assetId}`;
                    break;
            }
            
            // Optimized parameters for 15mm labels
            const params = new URLSearchParams({
                text: text,
                size: 350,        // High resolution for small print
                format: 'png',
                ecc: 'M',         // Medium error correction (optimal for short URLs)
                margin: 1,        // Minimal margin
                qzone: 0         // No quiet zone padding
            });
            return `${baseUrl}?${params.toString()}`;
        }

        // Extract asset ID from existing table data
        function getAssetIdFromRow(row) {
            const assetIdElement = row.querySelector('.asset-id');
            return assetIdElement ? assetIdElement.textContent.trim() : null;
        }

        // Show strategy selection modal
        function showQRStrategyModal(callback) {
            const modal = document.createElement('div');
            modal.style.cssText = `
                position: fixed; top: 0; left: 0; width: 100%; height: 100%;
                background: rgba(0,0,0,0.8); z-index: 10000;
                display: flex; align-items: center; justify-content: center;
            `;
            
            modal.innerHTML = `
                <div style="background: white; border-radius: 12px; padding: 2rem; max-width: 500px; width: 90%;">
                    <h3 style="margin: 0 0 1rem 0; color: #333;">📱 Choose QR Strategy for Clean 15mm Labels</h3>
                    <p style="color: #666; margin-bottom: 1.5rem;">Select the simplest QR code type for best scanning:</p>
                    
                    <div style="display: grid; gap: 1rem; margin-bottom: 2rem;">
                        <button class="strategy-option" data-strategy="asset_only" style="
                            padding: 1rem; border: 2px solid #FFD700; border-radius: 8px;
                            background: #FFF9C4; cursor: pointer; text-align: left;
                        ">
                            <div style="font-weight: bold; color: #333;">🎯 Asset ID Only (Simplest)</div>
                            <div style="font-size: 0.9rem; color: #666;">QR contains just: CAM-001</div>
                            <div style="font-size: 0.8rem; color: #4CAF50; margin-top: 4px;">✅ Highest scan success rate</div>
                        </button>
                        
                        <button class="strategy-option" data-strategy="minimal" style="
                            padding: 1rem; border: 2px solid #ddd; border-radius: 8px;
                            background: white; cursor: pointer; text-align: left;
                        ">
                            <div style="font-weight: bold; color: #333;">🔗 Minimal URL</div>
                            <div style="font-size: 0.9rem; color: #666;">QR contains: ${getCurrentDomain()}/CAM-001</div>
                            <div style="font-size: 0.8rem; color: #2196F3; margin-top: 4px;">✅ Works with any QR scanner</div>
                        </button>
                    </div>
                    
                    <div style="background: #e8f5e8; padding: 1rem; border-radius: 6px; margin-bottom: 1rem;">
                        <div style="font-size: 0.9rem; color: #2e7d32;">
                            <strong>💡 Recommendation:</strong> Start with "Asset ID Only" for maximum scannability.
                        </div>
                    </div>
                    
                    <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                        <button onclick="this.closest('div').parentElement.remove()" style="
                            padding: 0.75rem 1.5rem; border: 1px solid #ddd;
                            border-radius: 6px; background: white; cursor: pointer;
                        ">Cancel</button>
                    </div>
                </div>
            `;
            
            // Add click handlers
            modal.querySelectorAll('.strategy-option').forEach(button => {
                button.addEventListener('mouseenter', () => {
                    if (!button.style.background.includes('#FFF9C4')) {
                        button.style.background = '#f8f9fa';
                        button.style.borderColor = '#ccc';
                    }
                });
                
                button.addEventListener('mouseleave', () => {
                    if (!button.style.background.includes('#FFF9C4')) {
                        button.style.background = 'white';
                        button.style.borderColor = '#ddd';
                    }
                });
                
                button.addEventListener('click', () => {
                    const strategy = button.dataset.strategy;
                    modal.remove();
                    callback(strategy);
                });
            });
            
            modal.addEventListener('click', (e) => {
                if (e.target === modal) modal.remove();
            });
            
            document.body.appendChild(modal);
        }

        // Generate optimized 15mm PDF layout with clean design (NO BORDERS)
        // Updated generate15mmQRPDF function for 50x25mm vertical labels with 2 QR codes each

// Updated generate15mmQRPDF function for 50x25mm horizontal labels with 2 QR codes side by side

// Complete generate15mmQRPDF function with 10% larger QR codes (16.5mm)

// Revised generate15mmQRPDF function with 18mm QR codes (20% larger than original)

// Complete generate15mmQRPDF function with 18.9mm QR codes (+5% from 18mm) and 10% larger asset names

// Complete generate15mmQRPDF function with 18.9mm QR codes (+5% from 18mm) and 10% larger asset names

// Complete generate15mmQRPDF function with 18mm QR codes and 10% larger text

// Complete generate15mmQRPDF function with 18mm QR codes and 10% larger text

function generate15mmQRPDF(assetData, filename) {
    const strategy = assetData[0]?.strategy || 'minimal';
    
    console.log(`Generating 50x25mm horizontal labels (18mm QR codes) for ${assetData.length} assets`);
    
    // Show loading
    const loadingDiv = document.createElement('div');
    loadingDiv.id = 'qr-loading';
    loadingDiv.style.cssText = `
        position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%);
        background: white; padding: 2rem; border-radius: 12px;
        box-shadow: 0 10px 25px rgba(0,0,0,0.2); z-index: 10001;
        text-align: center; min-width: 350px;
    `;
    loadingDiv.innerHTML = `
        <div style="margin-bottom: 1rem;">
            <i class="fas fa-spinner fa-spin" style="font-size: 2rem; color: #FFD700;"></i>
        </div>
        <h3 style="margin: 0 0 0.5rem 0; color: #333;">Generating 50x25mm Premium Labels</h3>
        <p style="margin: 0; color: #666;">Creating 2 enhanced QR codes (18mm) with larger 6px text...</p>
        <div style="margin-top: 1rem; background: #f0f0f0; border-radius: 4px; height: 8px; overflow: hidden;">
            <div id="progress-bar" style="background: #FFD700; height: 100%; width: 0%; transition: width 0.3s ease;"></div>
        </div>
    `;
    document.body.appendChild(loadingDiv);
    
    // Create print window with 50x25mm horizontal layout
    const printWindow = window.open('', '_blank', 'width=900,height=700');
    
    printWindow.document.open();
    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>50x25mm Premium Labels - ${filename}</title>
            <meta charset="UTF-8">
            <style>
                @media print {
                    @page { margin: 0.1in; size: A4; }
                    .print-button { display: none !important; }
                }
                body {
                    font-family: Arial, sans-serif;
                    margin: 0;
                    padding: 8px;
                    background: white;
                }
                .print-button {
                    position: fixed;
                    top: 10px;
                    right: 10px;
                    background: #FFD700;
                    color: #333;
                    border: none;
                    padding: 8px 16px;
                    border-radius: 6px;
                    font-weight: bold;
                    cursor: pointer;
                    z-index: 1000;
                    font-size: 12.1px;
                }
                .header {
                    text-align: center;
                    margin-bottom: 12px;
                    border-bottom: 2px solid #333;
                    padding-bottom: 8px;
                }
                .header h1 {
                    color: #333;
                    margin: 0 0 4px 0;
                    font-size: 15.4px;
                }
                .header p {
                    color: #666;
                    margin: 0;
                    font-size: 9.9px;
                }
                .instructions {
                    background: #e8f5e8;
                    border: 1px solid #4caf50;
                    border-radius: 4px;
                    padding: 8px;
                    margin-bottom: 12px;
                    font-size: 8.8px;
                    color: #2e7d32;
                }
                .labels-grid {
                    display: grid;
                    grid-template-columns: repeat(4, 1fr);
                    gap: 2mm;
                    max-width: 100%;
                }
                .label-50x25 {
                    width: 50mm;
                    height: 25mm;
                    border: 1px dashed #ccc;
                    display: flex;
                    flex-direction: row;
                    justify-content: space-between;
                    align-items: center;
                    page-break-inside: avoid;
                    box-sizing: border-box;
                    padding: 1mm;
                    background: white;
                    position: relative;
                    gap: 1mm;
                }
                .qr-section {
                    width: 24mm;
                    height: 23mm;
                    display: flex;
                    flex-direction: column;
                    align-items: center;
                    justify-content: center;
                    text-align: center;
                }
                .qr-image-horizontal {
                    width: 18mm;
                    height: 18mm;
                    display: block;
                    margin: 0 auto 1mm auto;
                    flex-shrink: 0;
                }
                .asset-name-horizontal {
                    font-weight: bold;
                    font-size: 6px;
                    line-height: 1.0;
                    text-align: center;
                    overflow-wrap: break-word;
                    hyphens: auto;
                    display: -webkit-box;
                    -webkit-line-clamp: 2;
                    -webkit-box-orient: vertical;
                    overflow: hidden;
                    max-height: 2.4em;
                    color: #333;
                    width: 100%;
                }
                .divider-vertical {
                    width: 0.5mm;
                    height: 85%;
                    background: #ddd;
                    align-self: center;
                }
                .page-break { 
                    page-break-before: always; 
                }
                .strategy-info {
                    background: #fff3cd;
                    border: 1px solid #ffeaa7;
                    border-radius: 4px;
                    padding: 6px;
                    margin-bottom: 10px;
                    font-size: 8.8px;
                    color: #856404;
                }
                .empty-section {
                    width: 24mm;
                    height: 23mm;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    color: #ccc;
                    font-size: 6.6px;
                    border: 1px dashed #eee;
                    border-radius: 2px;
                }
                .performance-badge {
                    position: absolute;
                    top: -2px;
                    right: -2px;
                    background: #10B981;
                    color: white;
                    font-size: 3.3px;
                    padding: 1px 2px;
                    border-radius: 2px;
                    font-weight: bold;
                }
            </style>
        </head>
        <body>
            <button class="print-button" onclick="window.print()">
                🖨️ Print Premium 50x25mm Labels
            </button>
            <div class="header">
                <h1>📱 50x25mm Premium QR Labels</h1>
                <p>2 enhanced QR codes (18mm) with 6px text • ${assetData.length} assets • ${Math.ceil(assetData.length / 2)} labels</p>
            </div>
            <div class="strategy-info">
                <strong>QR Strategy:</strong> ${strategy === 'asset_only' ? 'Asset ID Only' : 'Minimal URL'} • 
                <strong>Label Size:</strong> 50x25mm with 2 premium QR codes (18mm each) • 
                <strong>Performance:</strong> 20% larger QR codes + 6px text for optimal readability
            </div>
            <div class="instructions">
                <strong>📋 Premium Label Instructions:</strong> Each label contains 2 enhanced QR codes side by side • 
                Cut along dashed lines • Apply to equipment •                 Each 18mm QR code with 6px text provides superior scanning and readability • 
                Optimized for professional equipment labeling with maximum visibility
            </div>
            <div class="labels-grid" id="labels-grid">
                <!-- 50x25mm premium labels will be inserted here -->
            </div>
        </body>
        </html>
    `);
    printWindow.document.close();
    
    const labelsGrid = printWindow.document.getElementById('labels-grid');
    let loadedCount = 0;
    
    function updateProgress() {
        const progressBar = document.getElementById('progress-bar');
        if (progressBar) {
            const progress = (loadedCount / assetData.length) * 100;
            progressBar.style.width = progress + '%';
        }
    }
    
    // 4 labels per row × ~25 rows = ~100 labels per page (200 QR codes)
    const labelsPerPage = 100;
    
    // Process assets in pairs for each 50x25mm label
    for (let i = 0; i < assetData.length; i += 2) {
        const asset1 = assetData[i];
        const asset2 = assetData[i + 1]; // May be undefined for odd numbers
        
        const labelDiv = printWindow.document.createElement('div');
        labelDiv.className = 'label-50x25';
        
        // Page break every labelsPerPage labels
        if (i > 0 && Math.floor(i / 2) % labelsPerPage === 0) {
            labelDiv.classList.add('page-break');
        }
        
        // Add performance badge to first label
        if (i === 0) {
            const badge = printWindow.document.createElement('div');
            badge.className = 'performance-badge';
            badge.textContent = '18MM';
            labelDiv.appendChild(badge);
        }
        
        // First QR code (left side)
        const qrSection1 = printWindow.document.createElement('div');
        qrSection1.className = 'qr-section';
        
        const qrImage1 = printWindow.document.createElement('img');
        qrImage1.className = 'qr-image-horizontal';
        qrImage1.src = asset1.qrUrl;
        
        qrImage1.onload = function() {
            loadedCount++;
            updateProgress();
            
            if (loadedCount === assetData.length) {
                setTimeout(() => {
                    const loading = document.getElementById('qr-loading');
                    if (loading) loading.remove();
                    printWindow.focus();
                    console.log('50x25mm premium labels with 18mm QR codes and 6px text ready!');
                }, 500);
            }
        };
        
        qrImage1.onerror = function() {
            console.error(`Failed to load QR for ${asset1.name}`);
            loadedCount++;
            updateProgress();
        };
        
        const assetName1 = printWindow.document.createElement('div');
        assetName1.className = 'asset-name-horizontal';
        assetName1.textContent = asset1.name;
        
        qrSection1.appendChild(qrImage1);
        qrSection1.appendChild(assetName1);
        
        // Vertical divider
        const divider = printWindow.document.createElement('div');
        divider.className = 'divider-vertical';
        
        // Second QR code (right side) or empty section
        const qrSection2 = printWindow.document.createElement('div');
        qrSection2.className = 'qr-section';
        
        if (asset2) {
            const qrImage2 = printWindow.document.createElement('img');
            qrImage2.className = 'qr-image-horizontal';
            qrImage2.src = asset2.qrUrl;
            
            qrImage2.onload = function() {
                loadedCount++;
                updateProgress();
                
                if (loadedCount === assetData.length) {
                    setTimeout(() => {
                        const loading = document.getElementById('qr-loading');
                        if (loading) loading.remove();
                        printWindow.focus();
                        console.log('50x25mm premium labels with 18mm QR codes and enhanced text ready!');
                    }, 500);
                }
            };
            
            qrImage2.onerror = function() {
                console.error(`Failed to load QR for ${asset2.name}`);
                loadedCount++;
                updateProgress();
            };
            
            const assetName2 = printWindow.document.createElement('div');
            assetName2.className = 'asset-name-horizontal';
            assetName2.textContent = asset2.name;
            
            qrSection2.appendChild(qrImage2);
            qrSection2.appendChild(assetName2);
        } else {
            // Empty section for odd number of assets
            const emptyDiv = printWindow.document.createElement('div');
            emptyDiv.className = 'empty-section';
            emptyDiv.textContent = 'Empty';
            qrSection2.appendChild(emptyDiv);
            
            // Still need to update progress for the missing asset
            if (loadedCount === assetData.length) {
                setTimeout(() => {
                    const loading = document.getElementById('qr-loading');
                    if (loading) loading.remove();
                    printWindow.focus();
                    console.log('50x25mm premium labels with 18mm QR codes and enhanced text ready!');
                }, 500);
            }
        }
        
        // Assemble the label (left QR | divider | right QR)
        labelDiv.appendChild(qrSection1);
        labelDiv.appendChild(divider);
        labelDiv.appendChild(qrSection2);
        
        labelsGrid.appendChild(labelDiv);
    }
}
        // Dropdown functionality
        document.getElementById('qrDownloadDropdown').addEventListener('click', function(e) {
            e.stopPropagation();
            const dropdown = document.getElementById('qrDropdownMenu');
            dropdown.classList.toggle('show');
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', function() {
            const dropdown = document.getElementById('qrDropdownMenu');
            dropdown.classList.remove('show');
        });

        // Updated bulk QR download functions
        function bulkDownloadQR() {
            console.log('Starting clean 15mm QR download');
            
            const table = $('#assetsTable').DataTable();
            
            if (table.rows({ search: 'applied' }).count() === 0) {
                alert('No assets found to download QR codes for.');
                return;
            }
            
            showQRStrategyModal((strategy) => {
                const assetData = [];
                table.rows({ search: 'applied' }).every(function() {
                    const row = this.node();
                    const assetName = row.querySelector('.asset-name')?.textContent.trim();
                    const assetId = getAssetIdFromRow(row);
                    const category = row.querySelector('td:nth-child(3) span')?.textContent.trim();
                    
                    if (assetName && assetId) {
                        assetData.push({
                            name: assetName,
                            id: assetId,
                            category: category || 'N/A',
                            qrUrl: generateUltraCompactQR(assetId, strategy),
                            strategy: strategy
                        });
                    }
                });
                
                if (assetData.length === 0) {
                    alert('No valid assets found for QR generation.');
                    return;
                }
                
                generate15mmQRPDF(assetData, `clean-15mm-${strategy}-labels`);
            });
        }

        function bulkDownloadSelectedQR() {
            console.log('Starting selected clean 15mm QR download');
            
            const selectedCheckboxes = document.querySelectorAll('.asset-checkbox:checked');
            
            if (selectedCheckboxes.length === 0) {
                alert('Please select assets to download QR codes for.');
                return;
            }
            
            showQRStrategyModal((strategy) => {
                const assetData = [];
                selectedCheckboxes.forEach(checkbox => {
                    const row = checkbox.closest('tr');
                    const assetName = row.querySelector('.asset-name')?.textContent.trim();
                    const assetId = getAssetIdFromRow(row);
                    const category = row.querySelector('td:nth-child(3) span')?.textContent.trim();
                    
                    if (assetName && assetId) {
                        assetData.push({
                            name: assetName,
                            id: assetId,
                            category: category || 'N/A',
                            qrUrl: generateUltraCompactQR(assetId, strategy),
                            strategy: strategy
                        });
                    }
                });
                
                if (assetData.length === 0) {
                    alert('No valid selected assets found for QR generation.');
                    return;
                }
                
                generate15mmQRPDF(assetData, `clean-15mm-${strategy}-selected`);
            });
        }

        // Selection functions
        function toggleSelectAll() {
            const selectAll = document.getElementById('select-all');
            const checkboxes = document.querySelectorAll('.asset-checkbox');
            
            checkboxes.forEach(checkbox => {
                checkbox.checked = selectAll.checked;
            });
            
            updateBulkActions();
        }

        function selectAll() {
            const selectAll = document.getElementById('select-all');
            const checkboxes = document.querySelectorAll('.asset-checkbox');
            
            selectAll.checked = true;
            checkboxes.forEach(checkbox => {
                checkbox.checked = true;
            });
            
            updateBulkActions();
        }

        function clearSelection() {
            const selectAll = document.getElementById('select-all');
            const checkboxes = document.querySelectorAll('.asset-checkbox');
            
            selectAll.checked = false;
            selectAll.indeterminate = false;
            checkboxes.forEach(checkbox => {
                checkbox.checked = false;
            });
            
            updateBulkActions();
        }

        function updateBulkActions() {
            const checkboxes = document.querySelectorAll('.asset-checkbox:checked');
            const totalCheckboxes = document.querySelectorAll('.asset-checkbox');
            const bulkActions = document.getElementById('bulk-actions');
            const selectionCount = document.getElementById('selection-count');
            const selectAll = document.getElementById('select-all');
            
            selectionCount.textContent = checkboxes.length;
            
            if (checkboxes.length > 0) {
                bulkActions.classList.add('show');
            } else {
                bulkActions.classList.remove('show');
            }
            
            if (checkboxes.length === totalCheckboxes.length && totalCheckboxes.length > 0) {
                selectAll.checked = true;
                selectAll.indeterminate = false;
            } else if (checkboxes.length > 0) {
                selectAll.checked = false;
                selectAll.indeterminate = true;
            } else {
                selectAll.checked = false;
                selectAll.indeterminate = false;
            }
        }

        function initializeCheckboxHandlers() {
            const selectAllCheckbox = document.getElementById('select-all');
            const assetCheckboxes = document.querySelectorAll('.asset-checkbox');
            
            if (selectAllCheckbox) {
                const newSelectAll = selectAllCheckbox.cloneNode(true);
                selectAllCheckbox.parentNode.replaceChild(newSelectAll, selectAllCheckbox);
                newSelectAll.addEventListener('change', toggleSelectAll);
            }
            
            assetCheckboxes.forEach(checkbox => {
                const newCheckbox = checkbox.cloneNode(true);
                checkbox.parentNode.replaceChild(newCheckbox, checkbox);
                newCheckbox.addEventListener('change', updateBulkActions);
            });
        }

        // Delete functions
        function confirmBulkDelete() {
            const checkboxes = document.querySelectorAll('.asset-checkbox:checked');
            
            if (checkboxes.length === 0) {
                alert('Please select assets to delete.');
                return;
            }
            
            document.getElementById('delete-count').textContent = checkboxes.length;
            document.getElementById('bulk-delete-password').value = '';
            
            let assetsList = '<ul style="list-style: none; padding: 0; margin: 0;">';
            checkboxes.forEach(checkbox => {
                const row = checkbox.closest('tr');
                const assetName = row.querySelector('.asset-name')?.textContent || 'Unknown Asset';
                const assetId = row.querySelector('.asset-id')?.textContent || 'Unknown ID';
                assetsList += `<li style="padding: 0.25rem 0; border-bottom: 1px solid #eee;">• ${assetName} (${assetId})</li>`;
            });
            assetsList += '</ul>';
            
            document.getElementById('selected-assets-list').innerHTML = assetsList;
            
            const modal = new bootstrap.Modal(document.getElementById('deleteModal'));
            modal.show();
        }

        function executeBulkDelete() {
            const password = document.getElementById('bulk-delete-password').value;
            
            if (!password) {
                alert('Invalid password. Access denied.');
                return;
            }
            
            const checkboxes = document.querySelectorAll('.asset-checkbox:checked');
            
            if (checkboxes.length === 0) {
                alert('No assets selected for deletion.');
                return;
            }
            
            const form = document.getElementById('bulk-form');
            const deleteButton = document.querySelector('#deleteModal .btn-danger');
            
            const passwordInput = document.createElement('input');
            passwordInput.type = 'hidden';
            passwordInput.name = 'delete_password';
            passwordInput.value = password;
            form.appendChild(passwordInput);
            
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'bulk_delete';
            input.value = '1';
            form.appendChild(input);
            
            deleteButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Deleting...';
            deleteButton.disabled = true;
            
            form.submit();
        }

        function confirmSingleDelete(assetId, assetName) {
            console.log('DELETE FUNCTION CALLED:', assetId, assetName);
            
            currentSingleDeleteId = assetId;
            
            try {
                document.getElementById('single-asset-name').textContent = assetName;
                document.getElementById('single-delete-password').value = '';
                
                const modal = new bootstrap.Modal(document.getElementById('singleDeleteModal'));
                modal.show();
                console.log('Bootstrap modal shown');
                
            } catch (error) {
                console.log('Bootstrap modal failed, using simple confirm');
                if (confirm(`Delete "${assetName}"? This cannot be undone!`)) {
                    const password = prompt('Enter delete password:');
                    if (password) {
                        executeSingleDeleteDirect(assetId, password);
                    } else {
                        alert('Password required!');
                    }
                }
            }
        }

        function executeSingleDelete() {
            console.log('Executing single delete');
            
            const password = document.getElementById('single-delete-password').value;
            
            if (!password) {
                alert('Invalid password. Access denied.');
                return;
            }
            
            if (!currentSingleDeleteId) {
                alert('No asset selected for deletion.');
                return;
            }
            
            executeSingleDeleteDirect(currentSingleDeleteId, password);
        }

        function executeSingleDeleteDirect(assetId, password) {
            console.log('Executing direct single delete for asset:', assetId);
            
            const form = document.createElement('form');
            form.method = 'POST';
            form.style.display = 'none';

            const passwordInput = document.createElement('input');
            passwordInput.type = 'hidden';
            passwordInput.name = 'delete_password';
            passwordInput.value = password;
            form.appendChild(passwordInput);
            
            const assetInput = document.createElement('input');
            assetInput.type = 'hidden';
            assetInput.name = 'selected_assets[]';
            assetInput.value = assetId;
            form.appendChild(assetInput);
            
            const bulkDeleteInput = document.createElement('input');
            bulkDeleteInput.type = 'hidden';
            bulkDeleteInput.name = 'bulk_delete';
            bulkDeleteInput.value = '1';
            form.appendChild(bulkDeleteInput);
            
            document.body.appendChild(form);
            
            try {
                const deleteButton = document.querySelector('#singleDeleteModal .btn-danger');
                if (deleteButton) {
                    deleteButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Deleting...';
                    deleteButton.disabled = true;
                }
            } catch (error) {
                console.log('Modal not available, submitting directly');
            }
            
            console.log('Submitting delete form');
            form.submit();
        }

        // Photo viewing functions 
        function showAssetPhotos(assetId, assetName) {
            console.log('PHOTO FUNCTION CALLED:', assetId, assetName);
            
            document.getElementById('photoAssetName').textContent = assetName;
            document.getElementById('photoAssetDetails').textContent = `Asset ID: ${assetId}`;
            
            const photoGrid = document.getElementById('photoGrid');
            photoGrid.innerHTML = '<div style="text-align: center; padding: 2rem;"><i class="fas fa-spinner fa-spin" style="font-size: 2rem;"></i><br>Loading photos...</div>';
            
            const lightbox = document.getElementById('photoLightbox');
            console.log('Lightbox element found:', !!lightbox);
            
            if (lightbox) {
                lightbox.style.cssText = `
                    position: fixed !important;
                    top: 0 !important;
                    left: 0 !important;
                    width: 100% !important;
                    height: 100% !important;
                    background: rgba(0,0,0,0.9) !important;
                    z-index: 99999 !important;
                    display: flex !important;
                    align-items: center !important;
                    justify-content: center !important;
                `;
                document.body.style.overflow = 'hidden';
                console.log('Lightbox styles applied');
            }
            
            fetch(`get_asset_photos.php?asset_id=${encodeURIComponent(assetId)}`)
                .then(response => response.json())
                .then(photos => {
                    console.log('Photos loaded:', photos.length);
                    if (photos.length === 0) {
                        photoGrid.innerHTML = '<div style="text-align: center; padding: 2rem; color: white;"><i class="fas fa-camera" style="font-size: 3rem; margin-bottom: 1rem; display: block;"></i>No photos available</div>';
                    } else {
                        photoGrid.innerHTML = '';
                        photos.forEach((photo) => {
                            const div = document.createElement('div');
                            div.className = 'photo-item';
                            div.innerHTML = `<img src="${photo.photo_path}" alt="${photo.photo_type}" style="width: 100%; height: 100%; object-fit: cover;">`;
                            div.onclick = () => window.open(photo.photo_path, '_blank');
                            photoGrid.appendChild(div);
                        });
                    }
                })
                .catch(error => {
                    console.error('Photo error:', error);
                    photoGrid.innerHTML = '<div style="text-align: center; padding: 2rem; color: white;">Error loading photos</div>';
                });
        }

        function closePhotoLightbox() {
            console.log('CLOSING PHOTO LIGHTBOX');
            const lightbox = document.getElementById('photoLightbox');
            if (lightbox) {
                lightbox.style.display = 'none';
                document.body.style.overflow = '';
            }
        }

        // Updated QR Code function - generates ultra-compact QR on the fly
        function showQRLightbox(oldQrUrl, assetName, assetId, category) {
            console.log('QR FUNCTION CALLED:', assetId, assetName);
            
            // Generate ultra-compact QR code using asset ID only (ignore the old complex URL)
            const ultraCompactQR = generateUltraCompactQR(assetId, 'asset_only');
            
            currentQRData = { 
                qrUrl: ultraCompactQR,  // Use new ultra-compact QR
                assetName, 
                assetId, 
                category 
            };
            
            document.getElementById('qrAssetName').textContent = assetName;
            document.getElementById('qrAssetDetails').textContent = `${category} • ${assetId}`;
            document.getElementById('qrImage').src = ultraCompactQR; // Show the simple QR
            
            // Show QR complexity comparison
            document.getElementById('qrAssetInfo').innerHTML = `
                <div style="padding: 1rem; background: #f5f5f5;">
                    <p><strong>Asset:</strong> ${assetName}</p>
                    <p><strong>ID:</strong> ${assetId}</p>
                    <p><strong>Category:</strong> ${category}</p>
                    <hr style="margin: 1rem 0;">
                    <div style="background: #e8f5e8; padding: 0.5rem; border-radius: 4px; margin-bottom: 0.5rem;">
                        <strong>✅ Ultra-Compact QR:</strong> Contains just "${assetId}"<br>
                        <small>Perfect for 15mm labels - Large squares, easy scanning</small>
                    </div>
                    <div style="background: #fff3cd; padding: 0.5rem; border-radius: 4px;">
                        <strong>📏 QR Size:</strong> ${assetId.length} characters = Ultra-simple pattern<br>
                        <small>Estimated 15mm scan success: 95%+</small>
                    </div>
                </div>
            `;
            
            const lightbox = document.getElementById('qrLightbox');
            
            if (lightbox) {
                lightbox.style.cssText = `
                    position: fixed !important;
                    top: 0 !important;
                    left: 0 !important;
                    width: 100% !important;
                    height: 100% !important;
                    background: rgba(0,0,0,0.9) !important;
                    z-index: 99999 !important;
                    display: flex !important;
                    align-items: center !important;
                    justify-content: center !important;
                `;
                document.body.style.overflow = 'hidden';
                console.log('Showing ultra-compact QR for:', assetId, '- Length:', assetId.length);
            }
        }

        function closeQRLightbox() {
            console.log('CLOSING QR LIGHTBOX');
            const lightbox = document.getElementById('qrLightbox');
            if (lightbox) {
                lightbox.style.display = 'none';
                document.body.style.overflow = '';
            }
        }

        function downloadSingleQR() {
            if (!currentQRData.qrUrl) {
                console.error('No QR data available');
                return;
            }
            
            const link = document.createElement('a');
            link.href = currentQRData.qrUrl;
            link.download = `${currentQRData.assetId}_Clean_QR.png`;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }

        function printQR() {
            if (!currentQRData.qrUrl) {
                console.error('No QR data available');
                return;
            }
            
            showQRStrategyModal((strategy) => {
                const assetId = currentQRData.assetId;
                const ultraCompactQRUrl = generateUltraCompactQR(assetId, strategy);
                
                const printWindow = window.open('', '_blank');
                printWindow.document.write(`
                    <html>
                        <head>
                            <title>Clean 15mm Label - ${currentQRData.assetName}</title>
                            <style>
                                @page { margin: 0.5in; }
                                @media print { .print-button { display: none !important; } }
                                body { 
                                    text-align: center; 
                                    font-family: Arial, sans-serif;
                                    margin: 20px;
                                    background: white;
                                }
                                .print-button {
                                    position: fixed;
                                    top: 20px;
                                    right: 20px;
                                    background: #FFD700;
                                    color: #333;
                                    border: none;
                                    padding: 12px 24px;
                                    border-radius: 8px;
                                    font-weight: bold;
                                    cursor: pointer;
                                    z-index: 1000;
                                }
                                .label-container {
                                    margin: 20px auto;
                                    max-width: 120px;
                                    /* REMOVED BORDER */
                                    padding: 6px;
                                    background: white;
                                }
                                .qr-image { 
                                    width: 15mm;
                                    height: 15mm;
                                    /* REMOVED BORDER */
                                    margin: 4px 0;
                                    display: block;
                                }
                                .asset-info {
                                    margin: 4px 0;
                                    padding: 4px;
                                    background: #f5f5f5;
                                    border-radius: 2px;
                                }
                                h3 { 
                                    color: #333; 
                                    margin-bottom: 3px;
                                    font-size: 10px;
                                    word-wrap: break-word;
                                }
                                p { 
                                    margin: 1px 0; 
                                    color: #666;
                                    font-size: 8px;
                                    font-weight: 600;
                                }
                                .size-info {
                                    margin-top: 8px;
                                    padding: 6px;
                                    background: #e3f2fd;
                                    border-radius: 3px;
                                    font-size: 8px;
                                    color: #1976d2;
                                }
                            </style>
                        </head>
                        <body>
                            <button class="print-button" onclick="window.print()">
                                🖨️ Print Clean 15mm Label
                            </button>
                            <div class="label-container">
                                <h3>${currentQRData.assetName}</h3>
                                <img class="qr-image" src="${ultraCompactQRUrl}" alt="Clean Ultra-Compact QR Code">
                                <div class="asset-info">
                                    <p><strong>ID:</strong> ${currentQRData.assetId}</p>
                                </div>
                                <div class="size-info">
                                    <strong>15MM CLEAN DESIGN</strong><br>
                                    ${strategy === 'asset_only' ? 'Asset ID Only' : 'Minimal URL'} Strategy
                                </div>
                            </div>
                        </body>
                    </html>
                `);
                printWindow.document.close();
                
                setTimeout(() => {
                    printWindow.focus();
                }, 500);
            });
        }

        // Initialize page functionality
        $(document).ready(function() {
            console.log('Page loaded, initializing...');
            
            const table = $('#assetsTable').DataTable({
                pageLength: 25,
                order: [[1, 'asc']],
                responsive: true,
                language: {
                    search: "Search assets:",
                    lengthMenu: "Show _MENU_ assets per page",
                    info: "Showing _START_ to _END_ of _TOTAL_ assets",
                    paginate: {
                        first: "First",
                        last: "Last",
                        next: "Next",
                        previous: "Previous"
                    }
                },
                columnDefs: [
                    { 
                        targets: [0],
                        orderable: false,
                        searchable: false
                    },
                    { 
                        targets: [5, 7],
                        className: 'mobile-hide',
                        responsivePriority: 10001 
                    }
                ],
                drawCallback: function() {
                    initializeCheckboxHandlers();
                }
            });
            
            initializeCheckboxHandlers();
            
            setTimeout(() => {
                const photoLightbox = document.getElementById('photoLightbox');
                const qrLightbox = document.getElementById('qrLightbox');
                
                if (photoLightbox) {
                    photoLightbox.addEventListener('click', function(e) {
                        if (e.target === photoLightbox) {
                            console.log('Photo background clicked');
                            closePhotoLightbox();
                        }
                    });
                }
                
                if (qrLightbox) {
                    qrLightbox.addEventListener('click', function(e) {
                        if (e.target === qrLightbox) {
                            console.log('QR background clicked');
                            closeQRLightbox();
                        }
                    });
                }
                
                console.log('Background click handlers added');
            }, 1000);
            
            console.log('Page initialization complete');
        });
    </script>
</body>
</html>