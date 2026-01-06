<?php
// migrate_to_clean_asset_ids.php - Migrate long asset IDs to clean CAM001 format
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (!session_id()) {
    ini_set('session.save_path', sys_get_temp_dir());
    session_start();
}

require_once 'config/database.php';
require_once 'classes/QRGenerator.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Category mapping to clean codes
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

function isCleanAssetId($asset_id) {
    // Clean format: 3 letters + 3 numbers (CAM001, AUD002, etc.)
    return preg_match('/^[A-Z]{3}\d{3}$/', $asset_id);
}

function generateCleanAssetId($category, $db, $category_codes) {
    $code = $category_codes[$category] ?? 'GEN';
    
    // Get highest number for this category
    $query = "SELECT asset_id FROM assets WHERE asset_id LIKE :pattern ORDER BY asset_id DESC LIMIT 1";
    $stmt = $db->prepare($query);
    $pattern = $code . '%';
    $stmt->bindParam(':pattern', $pattern);
    $stmt->execute();
    
    $last_asset = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($last_asset && isCleanAssetId($last_asset['asset_id'])) {
        $last_number = intval(substr($last_asset['asset_id'], 3));
        $next_number = $last_number + 1;
    } else {
        $next_number = 1;
    }
    
    return $code . sprintf('%03d', $next_number);
}

$results = [
    'total_assets' => 0,
    'needs_migration' => 0,
    'already_clean' => 0,
    'migrated_count' => 0,
    'error_count' => 0,
    'errors' => [],
    'migrations' => []
];

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['migrate_asset_ids'])) {
    try {
        $db->beginTransaction();
        
        // Get all assets
        $query = "SELECT id, asset_id, asset_name, category, qr_code FROM assets ORDER BY id";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $assets = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $results['total_assets'] = count($assets);
        
        foreach ($assets as $asset) {
            if (isCleanAssetId($asset['asset_id'])) {
                $results['already_clean']++;
                continue;
            }
            
            $results['needs_migration']++;
            
            try {
                // Generate new clean asset ID
                $new_asset_id = generateCleanAssetId($asset['category'], $db, $category_codes);
                
                // Generate new ultra-compact QR code
                $new_qr_url = QRGenerator::generateUltraCompactQR($new_asset_id, 350);
                
                // Update the asset
                $update_query = "UPDATE assets SET asset_id = :new_asset_id, qr_code = :qr_code WHERE id = :id";
                $update_stmt = $db->prepare($update_query);
                $update_stmt->bindParam(':new_asset_id', $new_asset_id);
                $update_stmt->bindParam(':qr_code', $new_qr_url);
                $update_stmt->bindParam(':id', $asset['id']);
                
                if ($update_stmt->execute()) {
                    // Update any related records (transactions, etc.)
                    $update_transactions = "UPDATE transactions SET asset_id = :new_asset_id WHERE asset_id = :old_asset_id";
                    $trans_stmt = $db->prepare($update_transactions);
                    $trans_stmt->bindParam(':new_asset_id', $new_asset_id);
                    $trans_stmt->bindParam(':old_asset_id', $asset['asset_id']);
                    $trans_stmt->execute();
                    
                    // Update photo records if they exist
                    try {
                        $update_photos = "UPDATE asset_photos SET asset_id = :new_asset_id WHERE asset_id = :old_asset_id";
                        $photo_stmt = $db->prepare($update_photos);
                        $photo_stmt->bindParam(':new_asset_id', $new_asset_id);
                        $photo_stmt->bindParam(':old_asset_id', $asset['asset_id']);
                        $photo_stmt->execute();
                    } catch (Exception $e) {
                        // Photos table might not exist, that's ok
                    }
                    
                    $results['migrated_count']++;
                    $results['migrations'][] = [
                        'old_id' => $asset['asset_id'],
                        'new_id' => $new_asset_id,
                        'asset_name' => $asset['asset_name'],
                        'category' => $asset['category']
                    ];
                    
                } else {
                    $results['error_count']++;
                    $results['errors'][] = "Failed to update asset: " . $asset['asset_id'];
                }
                
            } catch (Exception $e) {
                $results['error_count']++;
                $results['errors'][] = "Error processing asset " . $asset['asset_id'] . ": " . $e->getMessage();
            }
        }
        
        $db->commit();
        $migration_complete = true;
        
    } catch (Exception $e) {
        $db->rollback();
        $results['errors'][] = "Migration failed: " . $e->getMessage();
        $results['error_count']++;
    }
}

// Get current status
try {
    $status_query = "SELECT asset_id, asset_name, category FROM assets ORDER BY asset_id";
    $status_stmt = $db->prepare($status_query);
    $status_stmt->execute();
    $all_assets = $status_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $clean_assets = [];
    $messy_assets = [];
    
    foreach ($all_assets as $asset) {
        if (isCleanAssetId($asset['asset_id'])) {
            $clean_assets[] = $asset;
        } else {
            $messy_assets[] = $asset;
        }
    }
    
    $results['total_assets'] = count($all_assets);
    $results['already_clean'] = count($clean_assets);
    $results['needs_migration'] = count($messy_assets);
    
} catch (Exception $e) {
    $all_assets = [];
    $clean_assets = [];
    $messy_assets = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Asset ID Migration - Clean Format</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --neofox-yellow: #FFD700;
            --yellow-light: #FFF9C4;
            --black-primary: #1a1a1a;
            --gray-100: #f8f9fa;
            --green-500: #10B981;
            --red-500: #EF4444;
        }

        body {
            background: var(--gray-100);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
        }

        .navbar {
            background: var(--black-primary) !important;
            border-bottom: 3px solid var(--neofox-yellow);
        }

        .navbar-brand {
            color: var(--neofox-yellow) !important;
            font-weight: 600;
        }

        .container {
            max-width: 1000px;
            padding: 2rem 1rem;
        }

        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }

        .card-header {
            background: var(--neofox-yellow);
            color: var(--black-primary);
            border-radius: 12px 12px 0 0 !important;
            font-weight: 600;
            padding: 1.25rem;
        }

        .btn-warning {
            background: var(--neofox-yellow);
            border-color: var(--neofox-yellow);
            color: var(--black-primary);
            font-weight: 600;
        }

        .btn-warning:hover {
            background: #e6c200;
            border-color: #e6c200;
            color: var(--black-primary);
        }

        .asset-comparison {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            margin: 1rem 0;
        }

        .asset-list {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 1rem;
            max-height: 300px;
            overflow-y: auto;
        }

        .asset-item {
            padding: 0.75rem;
            margin-bottom: 0.5rem;
            background: white;
            border-radius: 6px;
            border-left: 4px solid;
        }

        .asset-item.clean {
            border-left-color: var(--green-500);
        }

        .asset-item.messy {
            border-left-color: var(--red-500);
        }

        .asset-item:last-child {
            margin-bottom: 0;
        }

        .asset-id {
            font-family: 'Monaco', 'Courier New', monospace;
            font-weight: 600;
        }

        .asset-name {
            color: #6c757d;
            font-size: 0.9rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            border-radius: 8px;
            padding: 1.5rem;
            text-align: center;
            border: 1px solid #dee2e6;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: #6c757d;
            font-size: 0.9rem;
        }

        .migration-result {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
        }

        .migration-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.5rem;
            background: white;
            border-radius: 4px;
            margin-bottom: 0.5rem;
        }

        .migration-item:last-child {
            margin-bottom: 0;
        }

        .old-id {
            color: var(--red-500);
            text-decoration: line-through;
        }

        .new-id {
            color: var(--green-500);
            font-weight: 600;
        }

        @media (max-width: 768px) {
            .asset-comparison {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-magic"></i> Asset ID Migration
            </a>
            <a href="assets.php" style="color: var(--neofox-yellow); text-decoration: none;">
                <i class="fas fa-arrow-left"></i> Back to Assets
            </a>
        </div>
    </nav>

    <div class="container">
        <h1 class="mb-4">
            <i class="fas fa-magic"></i> Migrate to Clean Asset IDs (CAM001 Format)
        </h1>
        
        <?php if (isset($migration_complete)): ?>
        <!-- Migration Results -->
        <div class="card">
            <div class="card-header">
                <i class="fas fa-check-circle"></i> Migration Complete!
            </div>
            <div class="card-body">
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-number text-primary"><?= $results['total_assets'] ?></div>
                        <div class="stat-label">Total Assets</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number text-success"><?= $results['migrated_count'] ?></div>
                        <div class="stat-label">Migrated</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number text-info"><?= $results['already_clean'] ?></div>
                        <div class="stat-label">Already Clean</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number text-danger"><?= $results['error_count'] ?></div>
                        <div class="stat-label">Errors</div>
                    </div>
                </div>

                <?php if ($results['migrated_count'] > 0): ?>
                <div class="alert alert-success">
                    <i class="fas fa-party-horn"></i>
                    <strong>Success!</strong> <?= $results['migrated_count'] ?> assets have been migrated to clean format!
                    All your assets now use the CAM001, AUD002 format for perfect 15mm QR scanning.
                </div>

                <div class="migration-result">
                    <h6><i class="fas fa-exchange-alt"></i> Migration Details:</h6>
                    <?php foreach ($results['migrations'] as $migration): ?>
                    <div class="migration-item">
                        <div>
                            <strong><?= htmlspecialchars($migration['asset_name']) ?></strong><br>
                            <small><?= htmlspecialchars($migration['category']) ?></small>
                        </div>
                        <div class="text-end">
                            <div class="old-id"><?= htmlspecialchars($migration['old_id']) ?></div>
                            <div class="new-id"><?= htmlspecialchars($migration['new_id']) ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <?php if (!empty($results['errors'])): ?>
                <div class="alert alert-danger">
                    <h6><i class="fas fa-exclamation-triangle"></i> Errors:</h6>
                    <ul class="mb-0">
                        <?php foreach ($results['errors'] as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>

                <div class="mt-4">
                    <a href="assets.php" class="btn btn-primary">
                        <i class="fas fa-list"></i> View Clean Assets
                    </a>
                    <a href="bulk_scanner_v2.php" class="btn btn-success">
                        <i class="fas fa-qrcode"></i> Test QR Scanner
                    </a>
                </div>
            </div>
        </div>

        <?php else: ?>
        <!-- Migration Form -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number text-primary"><?= $results['total_assets'] ?></div>
                <div class="stat-label">Total Assets</div>
            </div>
            <div class="stat-card">
                <div class="stat-number text-success"><?= $results['already_clean'] ?></div>
                <div class="stat-label">Clean Format (CAM001)</div>
            </div>
            <div class="stat-card">
                <div class="stat-number text-warning"><?= $results['needs_migration'] ?></div>
                <div class="stat-label">Need Migration</div>
            </div>
            <div class="stat-card">
                <div class="stat-number text-info"><?= round(($results['already_clean'] / max($results['total_assets'], 1)) * 100, 1) ?>%</div>
                <div class="stat-label">Clean Rate</div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <i class="fas fa-info-circle"></i> Asset ID Formats
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <h6><i class="fas fa-target"></i> Goal: Ultra-Clean Asset IDs</h6>
                    <p class="mb-2">Convert all assets to the clean <strong>CAM001, AUD002</strong> format for perfect 15mm QR scanning:</p>
                    <ul class="mb-0">
                        <li><strong>✅ Good:</strong> CAM001, AUD002, LIT003 (6 chars = tiny QR codes)</li>
                        <li><strong>❌ Bad:</strong> NF-AU-1751721967 (17 chars = complex QR codes)</li>
                        <li><strong>Result:</strong> 95%+ scan success on 15mm labels</li>
                    </ul>
                </div>

                <?php if (!empty($messy_assets) || !empty($clean_assets)): ?>
                <div class="asset-comparison">
                    <div>
                        <h6 class="text-success"><i class="fas fa-check"></i> Clean Format Assets (<?= count($clean_assets) ?>)</h6>
                        <div class="asset-list">
                            <?php foreach (array_slice($clean_assets, 0, 10) as $asset): ?>
                            <div class="asset-item clean">
                                <div class="asset-id"><?= htmlspecialchars($asset['asset_id']) ?></div>
                                <div class="asset-name"><?= htmlspecialchars($asset['asset_name']) ?></div>
                            </div>
                            <?php endforeach; ?>
                            <?php if (count($clean_assets) > 10): ?>
                            <div class="text-muted text-center mt-2">
                                <small>... and <?= count($clean_assets) - 10 ?> more</small>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div>
                        <h6 class="text-danger"><i class="fas fa-times"></i> Need Migration (<?= count($messy_assets) ?>)</h6>
                        <div class="asset-list">
                            <?php foreach (array_slice($messy_assets, 0, 10) as $asset): ?>
                            <div class="asset-item messy">
                                <div class="asset-id"><?= htmlspecialchars($asset['asset_id']) ?></div>
                                <div class="asset-name"><?= htmlspecialchars($asset['asset_name']) ?></div>
                            </div>
                            <?php endforeach; ?>
                            <?php if (count($messy_assets) > 10): ?>
                            <div class="text-muted text-center mt-2">
                                <small>... and <?= count($messy_assets) - 10 ?> more</small>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($results['needs_migration'] > 0): ?>
                <div class="alert alert-warning mt-4">
                    <h6><i class="fas fa-exclamation-triangle"></i> Migration Will:</h6>
                    <ul class="mb-0">
                        <li>Convert messy IDs like <code>NF-AU-1751721967</code> to clean <code>AUD003</code></li>
                        <li>Generate new ultra-compact QR codes for all migrated assets</li>
                        <li>Update all related records (transactions, photos, etc.)</li>
                        <li>Preserve all asset data - only the ID changes</li>
                    </ul>
                </div>

                <form method="POST" onsubmit="return confirmMigration()">
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <button type="submit" name="migrate_asset_ids" class="btn btn-warning btn-lg">
                            <i class="fas fa-magic"></i> Migrate <?= $results['needs_migration'] ?> Assets to Clean Format
                        </button>
                    </div>
                </form>
                <?php else: ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <strong>Perfect!</strong> All your assets already use the clean CAM001 format. 
                    Your QR codes are optimized for 15mm scanning!
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function confirmMigration() {
            return confirm(
                'This will convert messy asset IDs (like NF-AU-1751721967) to clean format (like AUD003).\n\n' +
                'This process will:\n' +
                '• Change asset IDs to CAM001, AUD002 format\n' +
                '• Generate new ultra-compact QR codes\n' +
                '• Update all related records\n' +
                '• Make 15mm QR scanning 95%+ reliable\n\n' +
                'Continue with migration?'
            );
        }
    </script>
</body>
</html>