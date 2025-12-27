<?php
// Enhanced assets.php with bulk delete functionality and photo viewing - COMPLETE WITH SMALL QR FUNCTIONALITY
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

// Handle bulk delete
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['bulk_delete'])) {
    if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
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

        /* Photo Lightbox */
        .photo-lightbox {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.95);
            z-index: 10000;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            pointer-events: auto !important;
        }

        .photo-lightbox.show {
            display: flex;
        }

        .photo-lightbox-content {
            background: var(--white-primary);
            border-radius: 16px;
            max-width: 90vw;
            max-height: 90vh;
            overflow: hidden;
            box-shadow: var(--shadow-lg);
            position: relative;
            pointer-events: auto !important;
        }

        .photo-lightbox-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .photo-lightbox-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--black-primary);
            margin: 0;
        }

        .photo-lightbox-subtitle {
            font-size: 0.9rem;
            color: var(--gray-500);
            margin-top: 0.25rem;
        }

        .photo-close-btn {
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

        .photo-close-btn:hover {
            background: var(--gray-50);
            border-color: var(--gray-400);
        }

        .photo-gallery-container {
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

        /* Photo preview modal */
        .photo-preview-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.95);
            z-index: 11000;
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
            position: relative;
        }

        .photo-preview-content img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            border-radius: 8px;
        }

        .photo-preview-close {
            position: absolute;
            top: -3rem;
            right: 0;
            background: rgba(255, 255, 255, 0.9);
            border: none;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            color: var(--black-primary);
        }

        /* QR Lightbox Styles */
        .qr-lightbox {
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
            pointer-events: auto !important;
        }

        .qr-lightbox.show {
            display: flex;
        }

        .qr-lightbox-content {
            background: var(--white-primary);
            border-radius: 16px;
            max-width: 500px;
            width: 100%;
            box-shadow: var(--shadow-lg);
            position: relative;
            pointer-events: auto !important;
        }

        .qr-lightbox-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }

        .qr-lightbox-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--black-primary);
            margin: 0;
        }

        .qr-lightbox-subtitle {
            font-size: 0.9rem;
            color: var(--gray-500);
            margin-top: 0.25rem;
        }

        .qr-close-btn {
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

        .qr-close-btn:hover {
            background: var(--gray-50);
            border-color: var(--gray-400);
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

        /* Modal Styling */
        .modal-content {
            border-radius: 12px;
            border: none;
            box-shadow: var(--shadow-lg);
        }

        .modal-header {
            border-bottom: 1px solid var(--gray-200);
            padding: 1.5rem;
        }

        .modal-body {
            padding: 1.5rem;
        }

        .modal-footer {
            border-top: 1px solid var(--gray-200);
            padding: 1.5rem;
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
                    <button class="btn btn-secondary dropdown-toggle" type="button" id="qrDownloadDropdown" onclick="toggleDropdown(event)">
                        <i class="fas fa-download"></i>
                        Download QR Codes
                    </button>
                    <ul class="dropdown-menu" id="qrDropdownMenu">
                        <li>
                            <a class="dropdown-item" href="#" onclick="event.preventDefault(); bulkDownloadQR();">
                                <i class="fas fa-download"></i> All Assets
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="#" onclick="event.preventDefault(); bulkDownloadSelectedQR();">
                                <i class="fas fa-check-square"></i> Selected Assets Only
                            </a>
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
                                    <button class="action-btn photos" title="View Photos (<?php echo count($photos); ?>)" 
                                            onclick="event.stopPropagation(); event.preventDefault(); showAssetPhotos('<?php echo htmlspecialchars($item['asset_id']); ?>', '<?php echo htmlspecialchars($item['asset_name']); ?>');">
                                        <i class="fas fa-images"></i>
                                        <span style="font-size: 0.7rem; margin-left: 2px;"><?php echo count($photos); ?></span>
                                    </button>
                                    <?php else: ?>
                                    <span style="color: var(--gray-400); font-size: 0.8rem;">No photos</span>
                                    <?php endif; ?>
                                </td>
                                <td class="mobile-hide">
                                    <button class="action-btn qr" title="View QR Code" 
                                            onclick="event.stopPropagation(); event.preventDefault(); showQRLightbox('<?= htmlspecialchars($item['qr_code']) ?>', '<?= htmlspecialchars($item['asset_name']) ?>', '<?= htmlspecialchars($item['asset_id']) ?>', '<?= htmlspecialchars($item['category']) ?>');">
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

    <!-- Bulk Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-exclamation-triangle text-danger"></i>
                        Confirm Bulk Delete
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-3">Are you sure you want to delete <strong id="delete-count">0</strong> selected asset(s)?</p>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>Warning:</strong> This action cannot be undone. Equipment that is currently checked out cannot be deleted.
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

    <!-- Photo Lightbox -->
    <div class="photo-lightbox" id="photoLightbox" onclick="closePhotoLightbox(event)">
        <div class="photo-lightbox-content" onclick="event.stopPropagation()">
            <div class="photo-lightbox-header">
                <div>
                    <h3 class="photo-lightbox-title" id="photoAssetName"></h3>
                    <div class="photo-lightbox-subtitle" id="photoAssetDetails"></div>
                </div>
                <button class="photo-close-btn" onclick="closePhotoLightbox()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div class="photo-gallery-container" id="photoGalleryContainer">
                <div class="photo-grid" id="photoGrid">
                    <!-- Photos will be loaded here -->
                </div>
            </div>
        </div>
    </div>

    <!-- Photo Preview Modal -->
    <div class="photo-preview-modal" id="photoPreviewModal" onclick="closePhotoPreview(event)">
        <div class="photo-preview-content" onclick="event.stopPropagation()">
            <button class="photo-preview-close" onclick="closePhotoPreview()">
                <i class="fas fa-times"></i>
            </button>
            <img id="photoPreviewImage" src="" alt="Photo Preview">
        </div>
    </div>

    <!-- QR Lightbox -->
    <div class="qr-lightbox" id="qrLightbox" onclick="closeQRLightbox(event)">
        <div class="qr-lightbox-content" onclick="event.stopPropagation()">
            <div class="qr-lightbox-header">
                <div>
                    <h3 class="qr-lightbox-title" id="qrAssetName"></h3>
                    <div class="qr-lightbox-subtitle" id="qrAssetDetails"></div>
                </div>
                <button class="qr-close-btn" onclick="closeQRLightbox()">
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
                    Print
                </button>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Global variables
        let currentQRData = {};

        // Dropdown functionality
        function toggleDropdown(event) {
            event.stopPropagation();
            const dropdown = document.getElementById('qrDropdownMenu');
            dropdown.classList.toggle('show');
        }

        // Close dropdown when clicking outside
        document.addEventListener('click', function() {
            const dropdown = document.getElementById('qrDropdownMenu');
            dropdown.classList.remove('show');
        });

        // UPDATED: Bulk QR download functions with SMALL QR codes (1x1cm size) and descriptions
        function bulkDownloadQR() {
            console.log('Starting bulk QR download for all assets');
            
            // Get all asset rows from the table (visible ones only)
            const table = $('#assetsTable').DataTable();
            const allData = table.rows({ search: 'applied' }).data();
            
            if (allData.length === 0) {
                alert('No assets found to download QR codes for.');
                return;
            }
            
            // Extract asset data from DataTable with all needed info
            const assetData = [];
            table.rows({ search: 'applied' }).every(function() {
                const row = this.node();
                const assetName = row.querySelector('.asset-name')?.textContent.trim();
                const assetId = row.querySelector('.asset-id')?.textContent.trim();
                const category = row.querySelector('td:nth-child(3) span')?.textContent.trim(); // Category column
                const status = row.querySelector('.status-badge')?.textContent.trim(); // Status
                const qrButton = row.querySelector('.action-btn.qr');
                
                if (assetName && assetId && qrButton) {
                    const onclickAttr = qrButton.getAttribute('onclick');
                    const qrUrlMatch = onclickAttr?.match(/showQRLightbox\('([^']+)'/);
                    
                    if (qrUrlMatch) {
                        assetData.push({
                            name: assetName,
                            id: assetId,
                            category: category || 'N/A',
                            status: status || 'N/A',
                            qrUrl: qrUrlMatch[1]
                        });
                    }
                }
            });
            
            if (assetData.length === 0) {
                alert('No valid assets found for QR generation.');
                return;
            }
            
            generateQRPDF(assetData, 'all-assets');
        }

        function bulkDownloadSelectedQR() {
            console.log('Starting bulk QR download for selected assets');
            
            const selectedCheckboxes = document.querySelectorAll('.asset-checkbox:checked');
            
            if (selectedCheckboxes.length === 0) {
                alert('Please select assets to download QR codes for.');
                return;
            }
            
            // Extract data from selected rows with all needed info
            const assetData = [];
            selectedCheckboxes.forEach(checkbox => {
                const row = checkbox.closest('tr');
                const assetName = row.querySelector('.asset-name')?.textContent.trim();
                const assetId = row.querySelector('.asset-id')?.textContent.trim();
                const category = row.querySelector('td:nth-child(3) span')?.textContent.trim(); // Category column
                const status = row.querySelector('.status-badge')?.textContent.trim(); // Status
                const qrButton = row.querySelector('.action-btn.qr');
                
                if (assetName && assetId && qrButton) {
                    const onclickAttr = qrButton.getAttribute('onclick');
                    const qrUrlMatch = onclickAttr?.match(/showQRLightbox\('([^']+)'/);
                    
                    if (qrUrlMatch) {
                        assetData.push({
                            name: assetName,
                            id: assetId,
                            category: category || 'N/A',
                            status: status || 'N/A',
                            qrUrl: qrUrlMatch[1]
                        });
                    }
                }
            });
            
            if (assetData.length === 0) {
                alert('No valid selected assets found for QR generation.');
                return;
            }
            
            generateQRPDF(assetData, 'selected-assets');
        }

        // UPDATED: Generate PDF with SMALL QR codes optimized for 1x1cm labels WITH DESCRIPTIONS
        function generateQRPDF(assetData, filename) {
            console.log(`Generating PDF for ${assetData.length} assets with small QR codes and descriptions`);
            
            // Show loading indicator
            const loadingDiv = document.createElement('div');
            loadingDiv.id = 'qr-loading';
            loadingDiv.style.cssText = `
                position: fixed;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                background: white;
                padding: 2rem;
                border-radius: 12px;
                box-shadow: 0 10px 25px rgba(0,0,0,0.2);
                z-index: 10001;
                text-align: center;
                min-width: 300px;
            `;
            loadingDiv.innerHTML = `
                <div style="margin-bottom: 1rem;">
                    <i class="fas fa-spinner fa-spin" style="font-size: 2rem; color: #FFD700;"></i>
                </div>
                <h3 style="margin: 0 0 0.5rem 0; color: #333;">Generating QR Labels with Descriptions</h3>
                <p style="margin: 0; color: #666;">Creating 1x1cm QR labels with item descriptions for ${assetData.length} assets...</p>
                <div style="margin-top: 1rem; background: #f0f0f0; border-radius: 4px; height: 8px; overflow: hidden;">
                    <div id="progress-bar" style="background: #FFD700; height: 100%; width: 0%; transition: width 0.3s ease;"></div>
                </div>
            `;
            document.body.appendChild(loadingDiv);
            
            // Create a new window for the PDF content with small QR codes and descriptions
            const printWindow = window.open('', '_blank');
            printWindow.document.write(`
                <!DOCTYPE html>
                <html>
                <head>
                    <title>QR Labels with Descriptions - ${filename}</title>
                    <style>
                        @media print {
                            @page {
                                margin: 0.3in;
                                size: A4;
                            }
                        }
                        body {
                            font-family: Arial, sans-serif;
                            margin: 0;
                            padding: 15px;
                            background: white;
                        }
                        .qr-grid {
                            display: grid;
                            grid-template-columns: repeat(6, 1fr);
                            gap: 10px;
                            max-width: 100%;
                        }
                        .qr-item {
                            border: 2px solid #333;
                            border-radius: 6px;
                            padding: 6px;
                            text-align: center;
                            background: white;
                            page-break-inside: avoid;
                            box-sizing: border-box;
                            width: 32mm;
                            min-height: 35mm;
                            display: flex;
                            flex-direction: column;
                            justify-content: flex-start;
                            align-items: center;
                        }
                        .qr-image {
                            width: 20mm;
                            height: 20mm;
                            margin: 0 auto 3px auto;
                            display: block;
                            border: 1px solid #ccc;
                        }
                        .asset-name {
                            font-weight: bold;
                            font-size: 7px;
                            margin-bottom: 2px;
                            word-wrap: break-word;
                            line-height: 1.1;
                            max-height: 2.2em;
                            overflow: hidden;
                            width: 100%;
                            text-align: center;
                        }
                        .asset-id {
                            font-family: 'Courier New', monospace;
                            font-size: 6px;
                            color: #666;
                            margin-bottom: 2px;
                            word-wrap: break-word;
                            width: 100%;
                            text-align: center;
                        }
                        .asset-category {
                            font-size: 5px;
                            color: #888;
                            margin-bottom: 2px;
                            text-transform: uppercase;
                            font-weight: 600;
                            width: 100%;
                            text-align: center;
                        }
                        .asset-status {
                            font-size: 5px;
                            padding: 1px 3px;
                            border-radius: 3px;
                            font-weight: 600;
                            text-transform: uppercase;
                            width: fit-content;
                            margin: 0 auto;
                        }
                        .status-available {
                            background: #d4edda;
                            color: #155724;
                        }
                        .status-checked-out {
                            background: #fff3cd;
                            color: #856404;
                        }
                        .status-maintenance {
                            background: #d1ecf1;
                            color: #0c5460;
                        }
                        .status-lost {
                            background: #f8d7da;
                            color: #721c24;
                        }
                        .header {
                            text-align: center;
                            margin-bottom: 20px;
                            border-bottom: 2px solid #333;
                            padding-bottom: 15px;
                        }
                        .header h1 {
                            color: #333;
                            margin: 0 0 8px 0;
                            font-size: 20px;
                        }
                        .header p {
                            color: #666;
                            margin: 0;
                            font-size: 12px;
                        }
                        .page-break {
                            page-break-before: always;
                        }
                        .instructions {
                            background: #f8f9fa;
                            border: 1px solid #dee2e6;
                            border-radius: 6px;
                            padding: 10px;
                            margin-bottom: 15px;
                            font-size: 11px;
                            color: #495057;
                        }
                        .instructions h3 {
                            margin: 0 0 5px 0;
                            font-size: 12px;
                            color: #333;
                        }
                        @media screen {
                            body {
                                background: #f5f5f5;
                                padding: 30px;
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
                                box-shadow: 0 4px 8px rgba(0,0,0,0.1);
                                z-index: 1000;
                            }
                            .print-button:hover {
                                background: #e6c200;
                            }
                        }
                    </style>
                </head>
                <body>
                    <button class="print-button" onclick="window.print()">
                        <i class="fas fa-print"></i> Print QR Labels
                    </button>
                    <div class="header">
                        <h1>Neofox Gear - Equipment QR Labels</h1>
                        <p>Generated on ${new Date().toLocaleDateString()} • ${assetData.length} Assets • 1x1cm QR Codes with Descriptions</p>
                    </div>
                    <div class="instructions">
                        <h3>📋 Label Application Instructions:</h3>
                        <p><strong>1.</strong> Cut along the grid lines • <strong>2.</strong> Match asset name/ID with your equipment • <strong>3.</strong> Clean equipment surface before applying • <strong>4.</strong> Apply QR label to a flat, visible area • <strong>5.</strong> Test scan after application</p>
                    </div>
                    <div class="qr-grid" id="qr-grid">
                        <!-- QR codes will be inserted here -->
                    </div>
                </body>
                </html>
            `);
            
            const qrGrid = printWindow.document.getElementById('qr-grid');
            let loadedCount = 0;
            
            // Function to update progress
            function updateProgress() {
                const progressBar = document.getElementById('progress-bar');
                if (progressBar) {
                    const progress = (loadedCount / assetData.length) * 100;
                    progressBar.style.width = progress + '%';
                }
            }
            
            // Add each SMALL QR code with descriptions to the PDF
            assetData.forEach((asset, index) => {
                const qrItem = printWindow.document.createElement('div');
                qrItem.className = 'qr-item';
                
                // Add page break every 36 items (6x6 grid per page)
                if (index > 0 && index % 36 === 0) {
                    qrItem.classList.add('page-break');
                }
                
                const qrImage = printWindow.document.createElement('img');
                qrImage.className = 'qr-image';
                // Use the EXACT same QR URL from the asset list
                qrImage.src = asset.qrUrl;
                qrImage.onload = function() {
                    loadedCount++;
                    updateProgress();
                    
                    if (loadedCount === assetData.length) {
                        // All images loaded, remove loading indicator
                        setTimeout(() => {
                            const loading = document.getElementById('qr-loading');
                            if (loading) {
                                loading.remove();
                            }
                            
                            // Focus the print window and show print dialog
                            printWindow.focus();
                            alert(`QR Labels with descriptions ready! Generated ${assetData.length} equipment labels with QR codes and item descriptions. Use the Print button to print and apply to your equipment.`);
                        }, 500);
                    }
                };
                qrImage.onerror = function() {
                    console.error(`Failed to load QR image for ${asset.name}`);
                    this.src = 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMzAiIGhlaWdodD0iMzAiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PHJlY3Qgd2lkdGg9IjEwMCUiIGhlaWdodD0iMTAwJSIgZmlsbD0iI2RkZCIvPjx0ZXh0IHg9IjUwJSIgeT0iNTAlIiBmb250LWZhbWlseT0iQXJpYWwiIGZvbnQtc2l6ZT0iOCIgZmlsbD0iIzk5OSIgdGV4dC1hbmNob3I9Im1pZGRsZSIgZHk9Ii4zZW0iPk5vIFFSPC90ZXh0Pjwvc3ZnPg==';
                    loadedCount++;
                    updateProgress();
                };
                
                // Asset name (truncated if too long)
                const assetName = printWindow.document.createElement('div');
                assetName.className = 'asset-name';
                assetName.textContent = asset.name.length > 20 ? asset.name.substring(0, 20) + '...' : asset.name;
                
                // Asset ID
                const assetId = printWindow.document.createElement('div');
                assetId.className = 'asset-id';
                assetId.textContent = asset.id;
                
                // Category
                const assetCategory = printWindow.document.createElement('div');
                assetCategory.className = 'asset-category';
                assetCategory.textContent = asset.category;
                
                // Status with color coding
                const assetStatus = printWindow.document.createElement('div');
                assetStatus.className = 'asset-status';
                const statusClass = asset.status.toLowerCase().replace(/\s+/g, '-');
                assetStatus.classList.add(`status-${statusClass}`);
                assetStatus.textContent = asset.status;
                
                // Assemble the QR item
                qrItem.appendChild(qrImage);
                qrItem.appendChild(assetName);
                qrItem.appendChild(assetId);
                qrItem.appendChild(assetCategory);
                qrItem.appendChild(assetStatus);
                qrGrid.appendChild(qrItem);
            });
            
            printWindow.document.close();
        }

        // FIXED: Bulk selection functions with proper validation
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
            
            // Update count
            selectionCount.textContent = checkboxes.length;
            
            // Show/hide bulk actions
            if (checkboxes.length > 0) {
                bulkActions.classList.add('show');
            } else {
                bulkActions.classList.remove('show');
            }
            
            // Update select all checkbox state
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
            // Remove existing event listeners to prevent duplicates
            const selectAllCheckbox = document.getElementById('select-all');
            const assetCheckboxes = document.querySelectorAll('.asset-checkbox');
            
            // Clone and replace select-all checkbox to remove all event listeners
            if (selectAllCheckbox) {
                const newSelectAll = selectAllCheckbox.cloneNode(true);
                selectAllCheckbox.parentNode.replaceChild(newSelectAll, selectAllCheckbox);
                
                // Add new event listener
                newSelectAll.addEventListener('change', toggleSelectAll);
            }
            
            // Add event listeners to asset checkboxes
            assetCheckboxes.forEach(checkbox => {
                // Clone and replace to remove existing listeners
                const newCheckbox = checkbox.cloneNode(true);
                checkbox.parentNode.replaceChild(newCheckbox, checkbox);
                
                // Add new event listener
                newCheckbox.addEventListener('change', updateBulkActions);
            });
        }

        // FIXED: Bulk delete confirmation with proper validation
        function confirmBulkDelete() {
            const checkboxes = document.querySelectorAll('.asset-checkbox:checked');
            
            if (checkboxes.length === 0) {
                alert('Please select assets to delete.');
                return;
            }
            
            // Update modal content
            document.getElementById('delete-count').textContent = checkboxes.length;
            
            // Build list of selected assets
            let assetsList = '<ul style="list-style: none; padding: 0; margin: 0;">';
            checkboxes.forEach(checkbox => {
                const row = checkbox.closest('tr');
                const assetName = row.querySelector('.asset-name')?.textContent || 'Unknown Asset';
                const assetId = row.querySelector('.asset-id')?.textContent || 'Unknown ID';
                assetsList += `<li style="padding: 0.25rem 0; border-bottom: 1px solid #eee;">• ${assetName} (${assetId})</li>`;
            });
            assetsList += '</ul>';
            
            document.getElementById('selected-assets-list').innerHTML = assetsList;
            
            // Show modal
            const modal = new bootstrap.Modal(document.getElementById('deleteModal'));
            modal.show();
        }

        function executeBulkDelete() {
            const checkboxes = document.querySelectorAll('.asset-checkbox:checked');
            
            if (checkboxes.length === 0) {
                alert('No assets selected for deletion.');
                return;
            }
            
            const form = document.getElementById('bulk-form');
            const deleteButton = document.querySelector('#deleteModal .btn-danger');
            
            // Add bulk delete flag
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'bulk_delete';
            input.value = '1';
            form.appendChild(input);
            
            // Show loading state
            deleteButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Deleting...';
            deleteButton.disabled = true;
            
            // Submit form
            form.submit();
        }

        // Photo viewing functions
        function showAssetPhotos(assetId, assetName) {
            if (event) {
                event.stopPropagation();
                event.preventDefault();
            }
            
            document.getElementById('photoAssetName').textContent = assetName;
            document.getElementById('photoAssetDetails').textContent = `Asset ID: ${assetId}`;
            
            const photoGrid = document.getElementById('photoGrid');
            photoGrid.innerHTML = '<div style="text-align: center; padding: 2rem; color: #666;"><i class="fas fa-spinner fa-spin" style="font-size: 2rem; margin-bottom: 1rem; display: block;"></i>Loading photos...</div>';
            
            setTimeout(() => {
                document.getElementById('photoLightbox').classList.add('show');
                document.body.style.overflow = 'hidden';
            }, 50);
            
            const url = `get_asset_photos.php?asset_id=${encodeURIComponent(assetId)}`;
            
            fetch(url)
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(photos => {
                    if (!Array.isArray(photos)) {
                        throw new Error('Invalid response format - expected array');
                    }
                    
                    if (photos.length === 0) {
                        photoGrid.innerHTML = '<div class="no-photos"><i class="fas fa-camera"></i><div>No photos available for this asset</div></div>';
                    } else {
                        photoGrid.innerHTML = '';
                        
                        photos.forEach((photo, index) => {
                            const photoItem = document.createElement('div');
                            photoItem.className = 'photo-item';
                            photoItem.onclick = (e) => {
                                e.stopPropagation();
                                previewPhoto(photo.photo_path);
                            };
                            
                            photoItem.innerHTML = `
                                <img src="${photo.photo_path}" 
                                     alt="${photo.description || photo.photo_type}" 
                                     loading="lazy"
                                     onerror="this.onerror=null; this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAwIiBoZWlnaHQ9IjIwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMTAwJSIgaGVpZ2h0PSIxMDAlIiBmaWxsPSIjZGRkIi8+PHRleHQgeD0iNTAlIiB5PSI1MCUiIGZvbnQtZmFtaWx5PSJBcmlhbCwgc2Fucy1zZXJpZiIgZm9udC1zaXplPSIxNCIgZmlsbD0iIzk5OSIgdGV4dC1hbmNob3I9Im1pZGRsZSIgZHk9Ii4zZW0iPkltYWdlIG5vdCBmb3VuZDwvdGV4dD48L3N2Zz4=';">
                                <div class="photo-type-badge">${photo.photo_type}</div>
                            `;
                            
                            photoGrid.appendChild(photoItem);
                        });
                    }
                })
                .catch(error => {
                    console.error('Error loading photos:', error);
                    photoGrid.innerHTML = `
                        <div class="no-photos">
                            <i class="fas fa-exclamation-triangle"></i>
                            <div>Error loading photos</div>
                            <div style="font-size: 0.8rem; margin-top: 0.5rem; color: #999;">
                                ${error.message}
                            </div>
                            <button onclick="showAssetPhotos('${assetId}', '${assetName}')" 
                                    style="margin-top: 1rem; padding: 0.5rem 1rem; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer;">
                                Try Again
                            </button>
                        </div>
                    `;
                });
        }

        function closePhotoLightbox(event) {
            if (event && event.target !== event.currentTarget) {
                return;
            }
            
            document.getElementById('photoLightbox').classList.remove('show');
            document.body.style.overflow = '';
        }

        function previewPhoto(photoPath) {
            document.getElementById('photoPreviewImage').src = photoPath;
            document.getElementById('photoPreviewModal').classList.add('show');
        }

        function closePhotoPreview(event) {
            if (event && event.target !== event.currentTarget) return;
            
            document.getElementById('photoPreviewModal').classList.remove('show');
        }

        // QR Code functions
        function showQRLightbox(qrUrl, assetName, assetId, category) {
            if (event) {
                event.stopPropagation();
                event.preventDefault();
            }
            
            currentQRData = { qrUrl, assetName, assetId, category };
            
            document.getElementById('qrAssetName').textContent = assetName;
            document.getElementById('qrAssetDetails').textContent = `${category} • ${assetId}`;
            document.getElementById('qrImage').src = qrUrl;
            
            const qrAssetInfo = document.getElementById('qrAssetInfo');
            qrAssetInfo.innerHTML = `
                <div class="qr-info-row">
                    <span class="qr-info-label">Asset Name:</span>
                    <span class="qr-info-value">${assetName}</span>
                </div>
                <div class="qr-info-row">
                    <span class="qr-info-label">Asset ID:</span>
                    <span class="qr-info-value">${assetId}</span>
                </div>
                <div class="qr-info-row">
                    <span class="qr-info-label">Category:</span>
                    <span class="qr-info-value">${category}</span>
                </div>
            `;
            
            setTimeout(() => {
                document.getElementById('qrLightbox').classList.add('show');
                document.body.style.overflow = 'hidden';
            }, 50);
        }

        function closeQRLightbox(event) {
            if (event && event.target !== event.currentTarget) {
                return;
            }
            
            document.getElementById('qrLightbox').classList.remove('show');
            document.body.style.overflow = '';
        }

        function downloadSingleQR() {
            if (!currentQRData.qrUrl) {
                console.error('No QR data available');
                return;
            }
            
            const link = document.createElement('a');
            link.href = currentQRData.qrUrl;
            link.download = `${currentQRData.assetId}_QR.png`;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }

        function printQR() {
            if (!currentQRData.qrUrl) {
                console.error('No QR data available');
                return;
            }
            
            const printWindow = window.open('', '_blank');
            printWindow.document.write(`
                <html>
                    <head>
                        <title>Small QR Code - ${currentQRData.assetName}</title>
                        <style>
                            body { 
                                text-align: center; 
                                font-family: Arial, sans-serif;
                                margin: 20px;
                            }
                            .qr-container {
                                margin: 20px auto;
                                max-width: 200px;
                                border: 2px solid #000;
                                padding: 10px;
                                border-radius: 8px;
                            }
                            .qr-image { 
                                width: 28mm;
                                height: 28mm;
                                border: 1px solid #000;
                                margin: 10px 0;
                            }
                            .asset-info {
                                margin: 10px 0;
                                padding: 10px;
                                background: #f5f5f5;
                                border-radius: 6px;
                            }
                            h2 { 
                                color: #333; 
                                margin-bottom: 8px;
                                font-size: 14px;
                            }
                            p { 
                                margin: 3px 0; 
                                color: #666;
                                font-size: 10px;
                            }
                        </style>
                    </head>
                    <body>
                        <div class="qr-container">
                            <h2>${currentQRData.assetName}</h2>
                            <img class="qr-image" src="${currentQRData.qrUrl}" alt="QR Code">
                            <div class="asset-info">
                                <p><strong>ID:</strong> ${currentQRData.assetId}</p>
                                <p><strong>Cat:</strong> ${currentQRData.category}</p>
                            </div>
                        </div>
                    </body>
                </html>
            `);
            printWindow.document.close();
            printWindow.print();
        }

        // Initialize DataTables with proper event handling
        $(document).ready(function() {
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
        });

        // Prevent event bubbling on action buttons
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.action-btn.qr').forEach(button => {
                button.addEventListener('click', function(e) {
                    e.stopPropagation();
                    e.preventDefault();
                });
            });
            
            document.querySelectorAll('.action-btn.photos').forEach(button => {
                button.addEventListener('click', function(e) {
                    e.stopPropagation();
                    e.preventDefault();
                });
            });
        });
    </script>
</body>
</html>