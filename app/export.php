<?php
// export.php - Data Export Functionality
session_start();
require_once 'config/database.php';
require_once 'classes/Asset.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();
$asset = new Asset($db);

if (isset($_GET['export']) && $_GET['export'] == 'csv') {
    $assets = $asset->getAll();
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="neofox_assets_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // CSV Headers
    fputcsv($output, [
        'Asset ID', 'Asset Name', 'Category', 'Description', 
        'Serial Number', 'Status', 'Current Borrower', 
        'Checkout Date', 'Expected Return', 'Condition', 'Notes'
    ]);
    
    // CSV Data
    foreach ($assets as $item) {
        fputcsv($output, [
            $item['asset_id'],
            $item['asset_name'],
            $item['category'],
            $item['description'],
            $item['serial_number'],
            $item['status'],
            $item['current_borrower'],
            $item['checkout_date'],
            $item['expected_return_date'],
            $item['condition_status'],
            $item['notes']
        ]);
    }
    
    fclose($output);
    exit();
}

// If not exporting, show export page
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Export Data - Neofox Gear Control</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h4><i class="fas fa-download"></i> Export Data</h4>
                    </div>
                    <div class="card-body text-center">
                        <p>Download your asset data in CSV format for backup or external analysis.</p>
                        
                        <a href="export.php?export=csv" class="btn btn-success btn-lg">
                            <i class="fas fa-file-csv"></i> Download CSV
                        </a>
                        
                        <div class="mt-3">
                            <a href="index.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> Back to Dashboard
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>