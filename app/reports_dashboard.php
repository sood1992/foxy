<?php
// reports_dashboard.php - Comprehensive Equipment Reports Dashboard
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

// Get filter parameters
$date_range = $_GET['date_range'] ?? '30';
$asset_filter = $_GET['asset_filter'] ?? '';
$borrower_filter = $_GET['borrower_filter'] ?? '';
$category_filter = $_GET['category_filter'] ?? '';
$transaction_type = $_GET['transaction_type'] ?? '';

// Date range calculations
$end_date = date('Y-m-d');
$start_date = date('Y-m-d', strtotime("-{$date_range} days"));

// Build WHERE conditions
$where_conditions = [];
$params = [];

if (!empty($date_range) && $date_range !== 'all') {
    $where_conditions[] = "t.transaction_date >= :start_date AND t.transaction_date <= :end_date";
    $params[':start_date'] = $start_date . ' 00:00:00';
    $params[':end_date'] = $end_date . ' 23:59:59';
}

if (!empty($asset_filter)) {
    $where_conditions[] = "(a.asset_name LIKE :asset_filter OR a.asset_id LIKE :asset_filter)";
    $params[':asset_filter'] = '%' . $asset_filter . '%';
}

if (!empty($borrower_filter)) {
    $where_conditions[] = "t.borrower_name LIKE :borrower_filter";
    $params[':borrower_filter'] = '%' . $borrower_filter . '%';
}

if (!empty($category_filter)) {
    $where_conditions[] = "a.category = :category_filter";
    $params[':category_filter'] = $category_filter;
}

if (!empty($transaction_type)) {
    $where_conditions[] = "t.transaction_type = :transaction_type";
    $params[':transaction_type'] = $transaction_type;
}

$where_clause = empty($where_conditions) ? '' : 'WHERE ' . implode(' AND ', $where_conditions);

// Get transaction history with asset details
$history_query = "
    SELECT 
        t.*,
        a.asset_name,
        a.category,
        a.asset_id,
        a.serial_number
    FROM transactions t
    LEFT JOIN assets a ON t.asset_id = a.asset_id
    {$where_clause}
    ORDER BY t.transaction_date DESC
    LIMIT 100
";

$stmt = $db->prepare($history_query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$transaction_history = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get equipment photos with enhanced filtering
$photos_where_conditions = [];
$photos_params = [];

if (!empty($date_range) && $date_range !== 'all') {
    $photos_where_conditions[] = "ep.upload_date >= :photo_start_date AND ep.upload_date <= :photo_end_date";
    $photos_params[':photo_start_date'] = $start_date . ' 00:00:00';
    $photos_params[':photo_end_date'] = $end_date . ' 23:59:59';
}

if (!empty($asset_filter)) {
    $photos_where_conditions[] = "(a.asset_name LIKE :photo_asset_filter OR ep.asset_id LIKE :photo_asset_filter)";
    $photos_params[':photo_asset_filter'] = '%' . $asset_filter . '%';
}

if (!empty($borrower_filter)) {
    $photos_where_conditions[] = "ep.borrower_name LIKE :photo_borrower_filter";
    $photos_params[':photo_borrower_filter'] = '%' . $borrower_filter . '%';
}

$photos_where_clause = empty($photos_where_conditions) ? '' : 'WHERE ' . implode(' AND ', $photos_where_conditions);

$photos_query = "
    SELECT 
        ep.*,
        a.asset_name,
        a.category
    FROM equipment_photos ep
    LEFT JOIN assets a ON ep.asset_id = a.asset_id
    {$photos_where_clause}
    ORDER BY ep.upload_date DESC
    LIMIT 100
";

$photos_stmt = $db->prepare($photos_query);
foreach ($photos_params as $key => $value) {
    $photos_stmt->bindValue($key, $value);
}
$photos_stmt->execute();
$equipment_photos = $photos_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get summary statistics
$stats_query = "
    SELECT 
        COUNT(*) as total_transactions,
        COUNT(CASE WHEN transaction_type = 'checkout' THEN 1 END) as total_checkouts,
        COUNT(CASE WHEN transaction_type = 'checkin' THEN 1 END) as total_checkins,
        COUNT(DISTINCT asset_id) as unique_assets_used,
        COUNT(DISTINCT borrower_name) as unique_borrowers
    FROM transactions t
    {$where_clause}
";

$stats_stmt = $db->prepare($stats_query);
foreach ($params as $key => $value) {
    $stats_stmt->bindValue($key, $value);
}
$stats_stmt->execute();
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

// Get top borrowers
$top_borrowers_query = "
    SELECT 
        borrower_name,
        COUNT(*) as transaction_count,
        COUNT(CASE WHEN transaction_type = 'checkout' THEN 1 END) as checkouts,
        COUNT(CASE WHEN transaction_type = 'checkin' THEN 1 END) as checkins
    FROM transactions t
    {$where_clause}
    GROUP BY borrower_name
    ORDER BY transaction_count DESC
    LIMIT 10
";

$top_borrowers_stmt = $db->prepare($top_borrowers_query);
foreach ($params as $key => $value) {
    $top_borrowers_stmt->bindValue($key, $value);
}
$top_borrowers_stmt->execute();
$top_borrowers = $top_borrowers_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get most used equipment
$top_equipment_query = "
    SELECT 
        t.asset_id,
        a.asset_name,
        a.category,
        COUNT(*) as usage_count,
        COUNT(CASE WHEN transaction_type = 'checkout' THEN 1 END) as checkout_count
    FROM transactions t
    LEFT JOIN assets a ON t.asset_id = a.asset_id
    {$where_clause}
    GROUP BY t.asset_id
    ORDER BY usage_count DESC
    LIMIT 10
";

$top_equipment_stmt = $db->prepare($top_equipment_query);
foreach ($params as $key => $value) {
    $top_equipment_stmt->bindValue($key, $value);
}
$top_equipment_stmt->execute();
$top_equipment = $top_equipment_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get categories
$categories_query = "SELECT DISTINCT category FROM assets ORDER BY category";
$categories_stmt = $db->prepare($categories_query);
$categories_stmt->execute();
$categories = $categories_stmt->fetchAll(PDO::FETCH_COLUMN);

// Get all borrowers
$borrowers_query = "SELECT DISTINCT borrower_name FROM transactions ORDER BY borrower_name";
$borrowers_stmt = $db->prepare($borrowers_query);
$borrowers_stmt->execute();
$borrowers = $borrowers_stmt->fetchAll(PDO::FETCH_COLUMN);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Equipment Reports - Neofox Gear Control</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        :root {
            --neofox-yellow: #FFD700;
            --bright-yellow: #FFEB3B;
            --yellow-light: #FFF9C4;
            --yellow-dark: #F57F17;
            --black-primary: #000000;
            --black-secondary: #1A1A1A;
            --white-primary: #FFFFFF;
            --gray-light: #F5F5F5;
            --gray-medium: #9E9E9E;
            --shadow-soft: 0 2px 10px rgba(0,0,0,0.1);
            --shadow-hover: 0 4px 20px rgba(0,0,0,0.15);
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, var(--neofox-yellow) 0%, var(--yellow-light) 100%);
            min-height: 100vh;
        }

        .navbar {
            background: var(--black-primary) !important;
            border-bottom: 3px solid var(--neofox-yellow);
            box-shadow: var(--shadow-soft);
        }

        .navbar-brand, .nav-link {
            color: var(--neofox-yellow) !important;
            font-weight: 600;
        }

        .nav-link:hover {
            color: white !important;
            transform: translateY(-1px);
        }

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
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
            letter-spacing: -1px;
        }

        .page-subtitle {
            color: var(--black-secondary);
            font-size: 1.1rem;
            font-weight: 500;
        }

        /* Filter Cards */
        .filter-card {
            background: white;
            border-radius: 15px;
            box-shadow: var(--shadow-soft);
            border: 3px solid var(--black-primary);
            margin-bottom: 2rem;
            overflow: hidden;
        }

        .filter-header {
            background: var(--black-primary);
            color: var(--neofox-yellow);
            padding: 1rem 1.5rem;
            font-weight: 700;
            font-size: 1.1rem;
        }

        .filter-body {
            padding: 1.5rem;
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            text-align: center;
            border: 3px solid var(--black-primary);
            box-shadow: 6px 6px 0px var(--black-primary);
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-4px) translateX(-2px);
            box-shadow: 10px 10px 0px var(--black-primary);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            background: var(--neofox-yellow);
            color: var(--black-primary);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 1.5rem;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 800;
            color: var(--black-primary);
            margin-bottom: 0.25rem;
        }

        .stat-label {
            color: var(--black-secondary);
            font-weight: 600;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Report Sections */
        .report-card {
            background: white;
            border-radius: 15px;
            box-shadow: var(--shadow-soft);
            border: 3px solid var(--black-primary);
            margin-bottom: 2rem;
            overflow: hidden;
        }

        .report-header {
            background: var(--black-primary);
            color: var(--neofox-yellow);
            padding: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .report-title {
            font-size: 1.3rem;
            font-weight: 700;
            margin: 0;
        }

        .report-body {
            padding: 0;
        }

        /* Tables */
        .modern-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
        }

        .modern-table th {
            background: var(--gray-light);
            color: var(--black-primary);
            font-weight: 700;
            padding: 1rem 0.75rem;
            text-align: left;
            border-bottom: 2px solid var(--black-primary);
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .modern-table td {
            padding: 1rem 0.75rem;
            border-bottom: 1px solid #e9ecef;
            color: var(--black-primary);
            vertical-align: middle;
        }

        .modern-table tr:hover {
            background: var(--yellow-light);
        }

        /* Badges */
        .badge {
            padding: 0.5rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .badge-checkout {
            background: rgba(40, 167, 69, 0.1);
            color: #28a745;
            border: 2px solid #28a745;
        }

        .badge-checkin {
            background: rgba(255, 193, 7, 0.1);
            color: #856404;
            border: 2px solid #ffc107;
        }

        .badge-excellent {
            background: rgba(40, 167, 69, 0.1);
            color: #28a745;
        }

        .badge-good {
            background: rgba(255, 193, 7, 0.1);
            color: #856404;
        }

        .badge-needs-repair {
            background: rgba(220, 53, 69, 0.1);
            color: #dc3545;
        }

        /* Photo Gallery */
        .photo-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 1rem;
            padding: 1.5rem;
        }

        .photo-item {
            background: var(--gray-light);
            border-radius: 10px;
            overflow: hidden;
            border: 2px solid var(--black-primary);
            transition: all 0.3s ease;
        }

        .photo-item:hover {
            transform: translateY(-2px);
            box-shadow: 4px 4px 0px var(--black-primary);
        }

        .photo-thumbnail {
            width: 100%;
            height: 120px;
            object-fit: cover;
            cursor: pointer;
        }

        .photo-info {
            padding: 0.75rem;
        }

        .photo-asset {
            font-weight: 600;
            font-size: 0.8rem;
            margin-bottom: 0.25rem;
            color: var(--black-primary);
        }

        .photo-meta {
            font-size: 0.7rem;
            color: var(--black-secondary);
        }

        /* Buttons */
        .btn-neofox {
            background: var(--neofox-yellow);
            border: 2px solid var(--black-primary);
            color: var(--black-primary);
            font-weight: 600;
            border-radius: 8px;
            padding: 0.5rem 1rem;
            transition: all 0.3s ease;
        }

        .btn-neofox:hover {
            background: var(--yellow-dark);
            color: white;
            transform: translateY(-1px);
        }

        /* Form Controls */
        .form-control, .form-select {
            border: 2px solid #e9ecef;
            border-radius: 8px;
            padding: 0.6rem 0.75rem;
            font-weight: 500;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--neofox-yellow);
            box-shadow: 0 0 0 0.2rem rgba(255, 215, 0, 0.25);
        }

        /* Top Lists */
        .top-list {
            padding: 1.5rem;
        }

        .top-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            margin-bottom: 0.75rem;
            background: var(--gray-light);
            border-radius: 8px;
            border: 2px solid transparent;
            transition: all 0.3s ease;
        }

        .top-item:hover {
            border-color: var(--neofox-yellow);
            background: var(--yellow-light);
        }

        .top-item:last-child {
            margin-bottom: 0;
        }

        .top-item-info {
            flex: 1;
        }

        .top-item-name {
            font-weight: 700;
            color: var(--black-primary);
            margin-bottom: 0.25rem;
        }

        .top-item-meta {
            font-size: 0.85rem;
            color: var(--black-secondary);
        }

        .top-item-count {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--black-primary);
            background: var(--neofox-yellow);
            padding: 0.5rem 1rem;
            border-radius: 20px;
            border: 2px solid var(--black-primary);
        }

        /* Purpose styling */
        .purpose-text {
            font-style: italic;
            color: var(--black-secondary);
            font-size: 0.85rem;
        }

        .purpose-empty {
            color: var(--gray-medium);
            font-style: italic;
            font-size: 0.8rem;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .main-container {
                padding: 1rem 0.5rem;
            }

            .page-title {
                font-size: 2rem;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .photo-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .modern-table {
                font-size: 0.8rem;
            }

            .modern-table th,
            .modern-table td {
                padding: 0.5rem 0.25rem;
            }

            /* Hide some columns on mobile */
            .mobile-hide {
                display: none;
            }
        }

        /* Empty states */
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: var(--gray-medium);
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        .empty-state h5 {
            color: var(--black-secondary);
            margin-bottom: 0.5rem;
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
                <a class="nav-link" href="bulk_scanner_v2.php">QR Scanner</a>
                <a class="nav-link active" href="reports_dashboard.php">Reports</a>
                <a class="nav-link" href="requests.php">Requests</a>
                <a class="nav-link" href="logout.php">Logout</a>
                <a class="nav-link" href="maintenance.php">Maintenance</a>
            </div>
        </div>
    </nav>

    <div class="main-container">
        <!-- Page Header -->
        <div class="page-header">
            <h1 class="page-title">
                <i class="fas fa-chart-line"></i> Equipment Reports
            </h1>
            <p class="page-subtitle">Comprehensive analytics and transaction history</p>
        </div>

        <!-- Filters -->
        <div class="filter-card">
            <div class="filter-header">
                <i class="fas fa-filter"></i> Filter Reports
            </div>
            <div class="filter-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-2">
                        <label class="form-label">Date Range</label>
                        <select name="date_range" class="form-select">
                            <option value="7" <?= $date_range == '7' ? 'selected' : '' ?>>Last 7 days</option>
                            <option value="30" <?= $date_range == '30' ? 'selected' : '' ?>>Last 30 days</option>
                            <option value="90" <?= $date_range == '90' ? 'selected' : '' ?>>Last 90 days</option>
                            <option value="365" <?= $date_range == '365' ? 'selected' : '' ?>>Last year</option>
                            <option value="all" <?= $date_range == 'all' ? 'selected' : '' ?>>All time</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Asset</label>
                        <input type="text" name="asset_filter" class="form-control" placeholder="Asset name/ID" value="<?= htmlspecialchars($asset_filter) ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Borrower</label>
                        <select name="borrower_filter" class="form-select">
                            <option value="">All Borrowers</option>
                            <?php foreach ($borrowers as $borrower): ?>
                            <option value="<?= htmlspecialchars($borrower) ?>" <?= $borrower_filter == $borrower ? 'selected' : '' ?>>
                                <?= htmlspecialchars($borrower) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Category</label>
                        <select name="category_filter" class="form-select">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $category): ?>
                            <option value="<?= htmlspecialchars($category) ?>" <?= $category_filter == $category ? 'selected' : '' ?>>
                                <?= htmlspecialchars($category) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Transaction</label>
                        <select name="transaction_type" class="form-select">
                            <option value="">All Types</option>
                            <option value="checkout" <?= $transaction_type == 'checkout' ? 'selected' : '' ?>>Checkout</option>
                            <option value="checkin" <?= $transaction_type == 'checkin' ? 'selected' : '' ?>>Check-in</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">&nbsp;</label>
                        <button type="submit" class="btn btn-neofox w-100">
                            <i class="fas fa-search"></i> Apply Filters
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Statistics Summary -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-exchange-alt"></i>
                </div>
                <div class="stat-number"><?= number_format($stats['total_transactions']) ?></div>
                <div class="stat-label">Total Transactions</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-sign-out-alt"></i>
                </div>
                <div class="stat-number"><?= number_format($stats['total_checkouts']) ?></div>
                <div class="stat-label">Checkouts</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-sign-in-alt"></i>
                </div>
                <div class="stat-number"><?= number_format($stats['total_checkins']) ?></div>
                <div class="stat-label">Check-ins</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-boxes"></i>
                </div>
                <div class="stat-number"><?= number_format($stats['unique_assets_used']) ?></div>
                <div class="stat-label">Assets Used</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-number"><?= number_format($stats['unique_borrowers']) ?></div>
                <div class="stat-label">Active Borrowers</div>
            </div>
        </div>

        <div class="row">
            <!-- Transaction History -->
            <div class="col-lg-8">
                <div class="report-card">
                    <div class="report-header">
                        <h3 class="report-title">
                            <i class="fas fa-history"></i> Transaction History
                        </h3>
                        <span class="badge bg-light text-dark"><?= count($transaction_history) ?> records</span>
                    </div>
                    <div class="report-body">
                        <?php if (empty($transaction_history)): ?>
                        <div class="empty-state">
                            <i class="fas fa-inbox"></i>
                            <h5>No Transactions Found</h5>
                            <p>No transactions match your current filters.</p>
                        </div>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="modern-table" id="historyTable">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Asset</th>
                                        <th>Borrower</th>
                                        <th>Type</th>
                                        <th class="mobile-hide">Purpose</th>
                                        <th class="mobile-hide">Condition</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($transaction_history as $transaction): ?>
                                    <tr>
                                        <td>
                                            <strong><?= date('M j, Y', strtotime($transaction['transaction_date'])) ?></strong><br>
                                            <small class="text-muted"><?= date('g:i A', strtotime($transaction['transaction_date'])) ?></small>
                                        </td>
                                        <td>
                                            <strong><?= htmlspecialchars($transaction['asset_name'] ?? 'Unknown') ?></strong><br>
                                            <small class="text-muted"><?= htmlspecialchars($transaction['asset_id']) ?></small>
                                        </td>
                                        <td>
                                            <strong><?= htmlspecialchars($transaction['borrower_name']) ?></strong>
                                        </td>
                                        <td>
                                            <span class="badge badge-<?= $transaction['transaction_type'] ?>">
                                                <?= ucfirst($transaction['transaction_type']) ?>
                                            </span>
                                        </td>
                                        <td class="mobile-hide">
                                            <?php if (!empty($transaction['purpose'])): ?>
                                                <span class="purpose-text"><?= htmlspecialchars($transaction['purpose']) ?></span>
                                            <?php else: ?>
                                                <span class="purpose-empty">No purpose specified</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="mobile-hide">
                                            <?php if ($transaction['condition_on_return']): ?>
                                                <span class="badge badge-<?= $transaction['condition_on_return'] ?>">
                                                    <?= ucfirst(str_replace('_', ' ', $transaction['condition_on_return'])) ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Top Lists -->
            <div class="col-lg-4">
                <!-- Top Borrowers -->
                <div class="report-card">
                    <div class="report-header">
                        <h4 class="report-title">
                            <i class="fas fa-user-crown"></i> Top Borrowers
                        </h4>
                    </div>
                    <div class="top-list">
                        <?php if (empty($top_borrowers)): ?>
                        <div class="empty-state">
                            <i class="fas fa-users"></i>
                            <p>No borrower data available</p>
                        </div>
                        <?php else: ?>
                        <?php foreach ($top_borrowers as $borrower): ?>
                        <div class="top-item">
                            <div class="top-item-info">
                                <div class="top-item-name"><?= htmlspecialchars($borrower['borrower_name']) ?></div>
                                <div class="top-item-meta">
                                    <?= $borrower['checkouts'] ?> checkouts • <?= $borrower['checkins'] ?> returns
                                </div>
                            </div>
                            <div class="top-item-count"><?= $borrower['transaction_count'] ?></div>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Most Used Equipment -->
                <div class="report-card">
                    <div class="report-header">
                        <h4 class="report-title">
                            <i class="fas fa-trophy"></i> Popular Equipment
                        </h4>
                    </div>
                    <div class="top-list">
                        <?php if (empty($top_equipment)): ?>
                        <div class="empty-state">
                            <i class="fas fa-box"></i>
                            <p>No equipment data available</p>
                        </div>
                        <?php else: ?>
                        <?php foreach ($top_equipment as $equipment): ?>
                        <div class="top-item">
                            <div class="top-item-info">
                                <div class="top-item-name"><?= htmlspecialchars($equipment['asset_name'] ?? 'Unknown') ?></div>
                                <div class="top-item-meta">
                                    <?= htmlspecialchars($equipment['category']) ?> • <?= $equipment['checkout_count'] ?> checkouts
                                </div>
                            </div>
                            <div class="top-item-count"><?= $equipment['usage_count'] ?></div>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Equipment Photos with Enhanced Features -->
        <div class="report-card">
            <div class="report-header">
                <h3 class="report-title">
                    <i class="fas fa-camera"></i> Equipment Photo Documentation
                </h3>
                <div class="d-flex gap-2">
                    <span class="badge bg-light text-dark"><?= count($equipment_photos) ?> photos</span>
                    <button class="btn btn-sm btn-neofox" onclick="downloadPhotoReport()">
                        <i class="fas fa-download"></i> Export
                    </button>
                </div>
            </div>
            <div class="report-body">
                <!-- Photo Statistics -->
                <div class="photo-stats mb-4" style="padding: 1.5rem;">
                    <div class="row text-center">
                        <div class="col-md-3">
                            <div class="stat-icon mb-2" style="width: 50px; height: 50px; font-size: 1.2rem;">
                                <i class="fas fa-sign-out-alt text-success"></i>
                            </div>
                            <div class="stat-number" style="font-size: 1.5rem;">
                                <?= count(array_filter($equipment_photos, function($p) { return $p['photo_type'] === 'checkout'; })) ?>
                            </div>
                            <div class="stat-label" style="font-size: 0.8rem;">Checkout Photos</div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-icon mb-2" style="width: 50px; height: 50px; font-size: 1.2rem;">
                                <i class="fas fa-sign-in-alt text-warning"></i>
                            </div>
                            <div class="stat-number" style="font-size: 1.5rem;">
                                <?= count(array_filter($equipment_photos, function($p) { return $p['photo_type'] === 'checkin'; })) ?>
                            </div>
                            <div class="stat-label" style="font-size: 0.8rem;">Check-in Photos</div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-icon mb-2" style="width: 50px; height: 50px; font-size: 1.2rem;">
                                <i class="fas fa-tools text-info"></i>
                            </div>
                            <div class="stat-number" style="font-size: 1.5rem;">
                                <?= count(array_filter($equipment_photos, function($p) { return $p['photo_type'] === 'maintenance'; })) ?>
                            </div>
                            <div class="stat-label" style="font-size: 0.8rem;">Maintenance Photos</div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-icon mb-2" style="width: 50px; height: 50px; font-size: 1.2rem;">
                                <i class="fas fa-database text-secondary"></i>
                            </div>
                            <div class="stat-number" style="font-size: 1.5rem;">
                                <?= array_sum(array_column($equipment_photos, 'file_size')) > 0 ? number_format(array_sum(array_column($equipment_photos, 'file_size')) / (1024*1024), 1) : '0' ?>MB
                            </div>
                            <div class="stat-label" style="font-size: 0.8rem;">Total Storage</div>
                        </div>
                    </div>
                </div>

                <?php if (empty($equipment_photos)): ?>
                <div class="empty-state">
                    <i class="fas fa-camera"></i>
                    <h5>No Photos Found</h5>
                    <p>No equipment photos match your current filters.<br>Photos are automatically captured during bulk checkout/checkin operations.</p>
                </div>
                <?php else: ?>
                
                <!-- Photo Type Filter Tabs -->
                <div class="photo-type-tabs mb-3" style="padding: 0 1.5rem;">
                    <button class="btn btn-sm btn-outline-secondary active" onclick="filterPhotosByType('all')">
                        All Photos
                    </button>
                    <button class="btn btn-sm btn-outline-success" onclick="filterPhotosByType('checkout')">
                        Checkout
                    </button>
                    <button class="btn btn-sm btn-outline-warning" onclick="filterPhotosByType('checkin')">
                        Check-in
                    </button>
                    <button class="btn btn-sm btn-outline-info" onclick="filterPhotosByType('maintenance')">
                        Maintenance
                    </button>
                </div>

                <div class="photo-grid" id="photoGrid">
                    <?php foreach ($equipment_photos as $photo): ?>
                    <div class="photo-item" data-type="<?= htmlspecialchars($photo['photo_type']) ?>">
                        <img src="<?= htmlspecialchars($photo['photo_path']) ?>" 
                             class="photo-thumbnail" 
                             onclick="openPhotoModal('<?= htmlspecialchars($photo['photo_path']) ?>', '<?= htmlspecialchars($photo['asset_name'] ?? 'Unknown Asset') ?>', '<?= htmlspecialchars($photo['borrower_name'] ?? '') ?>', '<?= date('M j, Y g:i A', strtotime($photo['upload_date'])) ?>', '<?= htmlspecialchars($photo['photo_type']) ?>')"
                             loading="lazy"
                             alt="Equipment photo">
                        <div class="photo-info">
                            <div class="photo-asset"><?= htmlspecialchars($photo['asset_name'] ?? 'Unknown Asset') ?></div>
                            <div class="photo-meta">
                                <?= date('M j, Y', strtotime($photo['upload_date'])) ?><br>
                                <span class="badge badge-<?= $photo['photo_type'] ?>" style="font-size: 0.6rem;">
                                    <?= ucfirst($photo['photo_type']) ?>
                                </span>
                                <?php if ($photo['borrower_name']): ?>
                                <br><small>by <?= htmlspecialchars($photo['borrower_name']) ?></small>
                                <?php endif; ?>
                                <?php if ($photo['file_size']): ?>
                                <br><small class="text-muted"><?= number_format($photo['file_size'] / 1024, 1) ?>KB</small>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Enhanced Photo Modal -->
    <div class="modal fade" id="photoModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="photoModalTitle">Equipment Photo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-8 text-center">
                            <img id="photoModalImage" src="" class="img-fluid" alt="Equipment photo" style="max-height: 70vh; border-radius: 8px;">
                        </div>
                        <div class="col-md-4">
                            <div class="photo-details">
                                <h6><i class="fas fa-info-circle"></i> Photo Details</h6>
                                <div class="detail-item">
                                    <strong>Asset:</strong> <span id="photoAssetName"></span>
                                </div>
                                <div class="detail-item">
                                    <strong>Type:</strong> <span id="photoType"></span>
                                </div>
                                <div class="detail-item">
                                    <strong>Date:</strong> <span id="photoDate"></span>
                                </div>
                                <div class="detail-item">
                                    <strong>Borrower:</strong> <span id="photoBorrower"></span>
                                </div>
                                <hr>
                                <div class="d-grid gap-2">
                                    <button class="btn btn-primary" onclick="downloadPhoto()">
                                        <i class="fas fa-download"></i> Download Photo
                                    </button>
                                    <button class="btn btn-outline-secondary" onclick="copyPhotoLink()">
                                        <i class="fas fa-link"></i> Copy Link
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function() {
            // Initialize DataTable for transaction history
            $('#historyTable').DataTable({
                pageLength: 25,
                order: [[0, 'desc']],
                responsive: true,
                columnDefs: [
                    { targets: [4, 5], className: 'mobile-hide' }
                ],
                dom: 'Bfrtip',
                buttons: [
                    {
                        extend: 'excel',
                        text: '<i class="fas fa-file-excel"></i> Export Excel',
                        className: 'btn btn-success btn-sm'
                    },
                    {
                        extend: 'csv',
                        text: '<i class="fas fa-file-csv"></i> Export CSV',
                        className: 'btn btn-info btn-sm'
                    }
                ]
            });
        });

        let currentPhotoSrc = '';

        function openPhotoModal(imageSrc, assetName, borrower, date, type) {
            currentPhotoSrc = imageSrc;
            document.getElementById('photoModalImage').src = imageSrc;
            document.getElementById('photoModalTitle').textContent = assetName + ' - Equipment Photo';
            document.getElementById('photoAssetName').textContent = assetName;
            document.getElementById('photoType').innerHTML = `<span class="badge badge-${type}">${type.charAt(0).toUpperCase() + type.slice(1)}</span>`;
            document.getElementById('photoDate').textContent = date;
            document.getElementById('photoBorrower').textContent = borrower || 'Not specified';
            
            const modal = new bootstrap.Modal(document.getElementById('photoModal'));
            modal.show();
        }

        function filterPhotosByType(type) {
            const photoItems = document.querySelectorAll('.photo-item');
            const buttons = document.querySelectorAll('.photo-type-tabs button');
            
            // Update active button
            buttons.forEach(btn => btn.classList.remove('active'));
            event.target.classList.add('active');
            
            // Filter photos
            photoItems.forEach(item => {
                if (type === 'all' || item.dataset.type === type) {
                    item.style.display = 'block';
                } else {
                    item.style.display = 'none';
                }
            });
        }

        function downloadPhoto() {
            if (currentPhotoSrc) {
                const link = document.createElement('a');
                link.href = currentPhotoSrc;
                link.download = currentPhotoSrc.split('/').pop();
                link.click();
            }
        }

        function copyPhotoLink() {
            if (currentPhotoSrc) {
                const fullUrl = window.location.origin + '/' + currentPhotoSrc;
                navigator.clipboard.writeText(fullUrl).then(() => {
                    showToast('success', 'Photo link copied to clipboard');
                });
            }
        }

        function downloadPhotoReport() {
            // Create CSV content for photo report
            let csvContent = "data:text/csv;charset=utf-8,";
            csvContent += "Asset Name,Asset ID,Photo Type,Borrower,Upload Date,File Size,File Path\n";
            
            // Add photo data (you'd need to pass this from PHP)
            <?php if (!empty($equipment_photos)): ?>
            const photoData = <?= json_encode($equipment_photos) ?>;
            photoData.forEach(photo => {
                csvContent += `"${photo.asset_name || 'Unknown'}","${photo.asset_id}","${photo.photo_type}","${photo.borrower_name || ''}","${photo.upload_date}","${photo.file_size || 0}","${photo.photo_path}"\n`;
            });
            <?php endif; ?>
            
            const encodedUri = encodeURI(csvContent);
            const link = document.createElement("a");
            link.setAttribute("href", encodedUri);
            link.setAttribute("download", `equipment_photos_report_${new Date().toISOString().split('T')[0]}.csv`);
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
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

        // Auto-refresh every 5 minutes
        setTimeout(function() {
            location.reload();
        }, 300000);
    </script>
</body>
</html>