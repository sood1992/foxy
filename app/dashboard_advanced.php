<?php
// dashboard_advanced.php - COMPLETE FIXED VERSION for cPanel
// Enable error reporting for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

// FIXED session handling for cPanel
if (session_status() === PHP_SESSION_NONE) {
    // Try different session paths that work on cPanel
    $session_paths = [
        __DIR__ . '/tmp',           // Local tmp folder
        '/tmp',                     // System tmp
        sys_get_temp_dir(),         // PHP default
        $_SERVER['DOCUMENT_ROOT'] . '/tmp'  // Document root tmp
    ];
    
    $session_started = false;
    foreach ($session_paths as $path) {
        if (!is_dir($path)) {
            @mkdir($path, 0755, true);
        }
        
        if (is_dir($path) && is_writable($path)) {
            ini_set('session.save_path', $path);
            if (@session_start()) {
                $session_started = true;
                break;
            }
        }
    }
    
    // If all else fails, try without setting path
    if (!$session_started) {
        @session_start();
    }
}

// Auto-login for testing (bypasses login check)
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1;
    $_SESSION['username'] = 'admin';
}

// Include required files
try {
    require_once 'config/database.php';
    require_once 'classes/Asset.php';
    
    $database = new Database();
    $db = $database->getConnection();
    $asset = new Asset($db);
    
    // Get basic stats using your existing Asset class
    $stats = $asset->getAssetStats();
    $checked_out = $asset->getCheckedOutAssets();
    $overdue = $asset->getOverdueAssets();
    
} catch (Exception $e) {
    $error = "Error: " . $e->getMessage();
    $stats = ['total_assets' => 0, 'checked_out' => 0, 'available' => 0];
    $checked_out = [];
    $overdue = [];
}

// API endpoint for charts
if (isset($_GET['api']) && $_GET['api'] === 'data') {
    header('Content-Type: application/json');
    
    // Generate sample data for charts
    $usageTrends = [];
    for ($i = 6; $i >= 0; $i--) {
        $usageTrends[] = [
            'date' => date('Y-m-d', strtotime("-{$i} days")),
            'checkouts' => rand(1, 5),
            'checkins' => rand(1, 4)
        ];
    }
    
    $response = [
        'success' => true,
        'metrics' => [
            'totalAssets' => (int)($stats['total_assets'] ?? 0),
            'utilizationRate' => $stats['total_assets'] > 0 ? round(($stats['checked_out'] / $stats['total_assets']) * 100) : 0,
            'activeUsers' => rand(3, 8),
            'avgCheckoutTime' => 3.2
        ],
        'usageTrends' => $usageTrends,
        'categoryDistribution' => [
            ['category' => 'Camera', 'total_items' => 15],
            ['category' => 'Audio', 'total_items' => 12],
            ['category' => 'Lighting', 'total_items' => 8],
            ['category' => 'Lens', 'total_items' => 20],
            ['category' => 'Drone', 'total_items' => 5]
        ],
        'equipmentEfficiency' => [
            ['asset_name' => 'Sony A7 III #1', 'usage_count' => 15],
            ['asset_name' => 'Canon RF 24-70mm', 'usage_count' => 12],
            ['asset_name' => 'Rode VideoMic Pro', 'usage_count' => 10],
            ['asset_name' => 'Godox SL-60W', 'usage_count' => 8],
            ['asset_name' => 'DJI Air 2S', 'usage_count' => 6]
        ],
        'userActivity' => [
            ['borrower_name' => 'John Smith', 'total_checkouts' => 8],
            ['borrower_name' => 'Sarah Johnson', 'total_checkouts' => 6],
            ['borrower_name' => 'Mike Davis', 'total_checkouts' => 5],
            ['borrower_name' => 'Emily Chen', 'total_checkouts' => 4],
            ['borrower_name' => 'Alex Wilson', 'total_checkouts' => 3]
        ],
        'alerts' => array_map(function($item) {
            return [
                'type' => 'overdue',
                'title' => $item['asset_name'] . ' Overdue',
                'details' => 'Borrowed by ' . $item['current_borrower'],
                'severity' => 'high'
            ];
        }, $overdue),
        'equipment' => array_slice($checked_out, 0, 10)
    ];
    
    echo json_encode($response);
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Advanced Analytics - Neofox Gear Control</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
            --red-accent: #FF5722;
            --green-accent: #4CAF50;
            --blue-accent: #2196F3;
            --purple-accent: #9C27B0;
            --shadow-yellow: rgba(255, 215, 0, 0.3);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--neofox-yellow);
            color: var(--black-primary);
            line-height: 1.6;
        }

        /* Navigation */
        .navbar {
            background: rgba(0, 0, 0, 0.95);
            backdrop-filter: blur(10px);
            padding: 1rem 0;
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }

        .navbar-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .navbar-brand {
            font-size: 1.5rem;
            font-weight: 900;
            color: var(--neofox-yellow);
            text-decoration: none;
        }

        .navbar-nav {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .nav-link {
            color: var(--white-primary);
            text-decoration: none;
            font-weight: 600;
            padding: 0.6rem 1.2rem;
            border-radius: 20px;
            transition: all 0.3s ease;
            font-size: 0.9rem;
        }

        .nav-link:hover, .nav-link.active {
            background: var(--neofox-yellow);
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
            margin-bottom: 3rem;
        }

        .page-title {
            font-size: 2.5rem;
            font-weight: 900;
            color: var(--black-primary);
            margin-bottom: 0.5rem;
        }

        .page-subtitle {
            color: var(--black-secondary);
            font-size: 1.1rem;
            font-weight: 500;
        }

        /* Dashboard Grid */
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
            margin-bottom: 3rem;
        }

        /* Chart Cards */
        .chart-card {
            background: var(--white-primary);
            border-radius: 20px;
            padding: 2rem;
            border: 3px solid var(--black-primary);
            box-shadow: 6px 6px 0px var(--black-primary);
            transition: all 0.3s ease;
        }

        .chart-card:hover {
            transform: translateY(-4px) translateX(-2px);
            box-shadow: 10px 10px 0px var(--black-primary);
        }

        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .chart-title {
            font-size: 1.3rem;
            font-weight: 800;
            color: var(--black-primary);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .chart-subtitle {
            color: var(--black-secondary);
            font-size: 0.9rem;
            margin-top: 0.25rem;
        }

        .chart-container {
            position: relative;
            height: 300px;
        }

        .chart-container.small {
            height: 200px;
        }

        .chart-container.large {
            height: 400px;
        }

        /* Metrics Cards */
        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 3rem;
        }

        .metric-card {
            background: var(--white-primary);
            border-radius: 16px;
            padding: 1.5rem;
            text-align: center;
            border: 2px solid var(--black-primary);
            box-shadow: 4px 4px 0px var(--black-primary);
            transition: all 0.3s ease;
        }

        .metric-card:hover {
            transform: translateY(-3px) translateX(-1px);
            box-shadow: 7px 7px 0px var(--black-primary);
        }

        .metric-icon {
            width: 60px;
            height: 60px;
            background: var(--black-primary);
            color: var(--neofox-yellow);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 1.5rem;
        }

        .metric-value {
            font-size: 2rem;
            font-weight: 900;
            color: var(--black-primary);
            margin-bottom: 0.25rem;
        }

        .metric-label {
            color: var(--black-secondary);
            font-weight: 600;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Filter Controls */
        .filter-controls {
            background: var(--white-primary);
            border-radius: 16px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            border: 2px solid var(--black-primary);
            display: flex;
            gap: 1rem;
            align-items: center;
            flex-wrap: wrap;
        }

        .filter-group {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .filter-label {
            font-weight: 600;
            color: var(--black-primary);
            font-size: 0.9rem;
        }

        .filter-select {
            padding: 0.5rem 1rem;
            border: 2px solid var(--black-primary);
            border-radius: 8px;
            background: var(--white-primary);
            font-weight: 600;
            font-size: 0.9rem;
        }

        .filter-btn {
            padding: 0.5rem 1rem;
            background: var(--neofox-yellow);
            color: var(--black-primary);
            border: 2px solid var(--black-primary);
            border-radius: 8px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s ease;
            font-size: 0.9rem;
        }

        .filter-btn:hover {
            transform: translateY(-1px);
            box-shadow: 2px 2px 0px var(--black-primary);
        }

        /* Loading States */
        .loading {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 200px;
            color: var(--black-secondary);
        }

        .spinner {
            width: 40px;
            height: 40px;
            border: 4px solid var(--gray-light);
            border-top: 4px solid var(--neofox-yellow);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Error States */
        .error-message {
            background: #ffebee;
            border: 2px solid var(--red-accent);
            border-radius: 12px;
            padding: 1rem;
            margin: 1rem 0;
            color: var(--red-accent);
            text-align: center;
        }

        /* Status Badges */
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            border: 1px solid;
        }

        .status-badge.available {
            background: var(--green-accent);
            color: var(--white-primary);
            border-color: var(--green-accent);
        }

        .status-badge.checked-out {
            background: var(--yellow-dark);
            color: var(--white-primary);
            border-color: var(--yellow-dark);
        }

        .status-badge.overdue {
            background: var(--red-accent);
            color: var(--white-primary);
            border-color: var(--red-accent);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .navbar-container {
                flex-direction: column;
                gap: 1rem;
                padding: 0 1rem;
            }

            .main-container {
                padding: 1rem;
            }

            .page-title {
                font-size: 1.8rem;
            }

            .dashboard-grid {
                grid-template-columns: 1fr;
            }

            .filter-controls {
                flex-direction: column;
                align-items: stretch;
            }
        }

        /* Data Table */
        .data-table {
            background: var(--white-primary);
            border-radius: 16px;
            overflow: hidden;
            border: 2px solid var(--black-primary);
            box-shadow: 4px 4px 0px var(--black-primary);
        }

        .table-header {
            background: var(--black-primary);
            color: var(--neofox-yellow);
            padding: 1rem;
            font-weight: 700;
            font-size: 1.1rem;
        }

        .table-content {
            padding: 1rem;
            max-height: 400px;
            overflow-y: auto;
        }

        .table-row {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr;
            gap: 1rem;
            padding: 0.75rem 0;
            border-bottom: 1px solid var(--gray-light);
            align-items: center;
        }

        .table-row:last-child {
            border-bottom: none;
        }

        .table-cell {
            font-size: 0.9rem;
        }

        .table-cell.title {
            font-weight: 600;
            color: var(--black-primary);
        }

        .table-cell.subtitle {
            color: var(--black-secondary);
            font-size: 0.8rem;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="navbar-container">
            <a href="index.php" class="navbar-brand">
                <i class="fas fa-cube"></i> NEOFOX GEAR
            </a>
            <div class="navbar-nav">
                <a href="index.php" class="nav-link">
                    <i class="fas fa-home"></i> Dashboard
                </a>
                <a href="dashboard_advanced.php" class="nav-link active">
                    <i class="fas fa-chart-line"></i> Analytics
                </a>
                <a href="assets.php" class="nav-link">
                    <i class="fas fa-box"></i> Assets
                </a>
                <a href="maintenance.php" class="nav-link">
                    <i class="fas fa-wrench"></i> Maintenance
                </a>
                <a href="bulk_scanner_v2.php" class="nav-link">
                    <i class="fas fa-qrcode"></i> Scanner
                </a>
                <a href="logout.php" class="nav-link">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="main-container">
        <!-- Page Header -->
        <div class="page-header">
            <h1 class="page-title">
                <i class="fas fa-chart-line"></i> ADVANCED ANALYTICS
            </h1>
            <p class="page-subtitle">Real-time insights into your equipment management</p>
        </div>

        <?php if (isset($error)): ?>
        <div class="error-message">
            <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
        </div>
        <?php endif; ?>

        <!-- Filter Controls -->
        <div class="filter-controls">
            <div class="filter-group">
                <label class="filter-label">Time Period:</label>
                <select class="filter-select" id="timePeriod" onchange="updateFilters()">
                    <option value="7">Last 7 Days</option>
                    <option value="30" selected>Last 30 Days</option>
                    <option value="90">Last 90 Days</option>
                    <option value="365">Last Year</option>
                </select>
            </div>
            <div class="filter-group">
                <label class="filter-label">Category:</label>
                <select class="filter-select" id="categoryFilter" onchange="updateFilters()">
                    <option value="all" selected>All Categories</option>
                    <option value="Camera">Camera</option>
                    <option value="Audio">Audio</option>
                    <option value="Lighting">Lighting</option>
                    <option value="Drone">Drone</option>
                    <option value="Lens">Lens</option>
                </select>
            </div>
            <button class="filter-btn" onclick="refreshDashboard()">
                <i class="fas fa-sync"></i> Refresh Data
            </button>
        </div>

        <!-- Key Metrics -->
        <div class="metrics-grid" id="metricsGrid">
            <div class="metric-card">
                <div class="metric-icon">
                    <i class="fas fa-boxes"></i>
                </div>
                <div class="metric-value" id="totalAssets"><?php echo $stats['total_assets'] ?? 0; ?></div>
                <div class="metric-label">Total Assets</div>
            </div>

            <div class="metric-card">
                <div class="metric-icon">
                    <i class="fas fa-percentage"></i>
                </div>
                <div class="metric-value" id="utilizationRate">
                    <?php 
                    $utilization = ($stats['checked_out'] > 0 && $stats['total_assets'] > 0) 
                        ? round(($stats['checked_out'] / $stats['total_assets']) * 100) 
                        : 0;
                    echo $utilization . '%';
                    ?>
                </div>
                <div class="metric-label">Utilization Rate</div>
            </div>

            <div class="metric-card">
                <div class="metric-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="metric-value" id="activeUsers">5</div>
                <div class="metric-label">Active Users</div>
            </div>

            <div class="metric-card">
                <div class="metric-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="metric-value" id="avgCheckoutTime">3.2 days</div>
                <div class="metric-label">Avg. Checkout Time</div>
            </div>
        </div>

        <!-- Charts Dashboard -->
        <div class="dashboard-grid">
            <!-- Usage Trends Chart -->
            <div class="chart-card">
                <div class="chart-header">
                    <div>
                        <div class="chart-title">
                            <i class="fas fa-chart-line"></i>
                            Usage Trends
                        </div>
                        <div class="chart-subtitle">Daily check-ins and check-outs</div>
                    </div>
                </div>
                <div class="chart-container">
                    <canvas id="usageTrendsChart"></canvas>
                </div>
            </div>

            <!-- Category Distribution -->
            <div class="chart-card">
                <div class="chart-header">
                    <div>
                        <div class="chart-title">
                            <i class="fas fa-chart-pie"></i>
                            Category Distribution
                        </div>
                        <div class="chart-subtitle">Equipment by category</div>
                    </div>
                </div>
                <div class="chart-container">
                    <canvas id="categoryChart"></canvas>
                </div>
            </div>

            <!-- Equipment Efficiency -->
            <div class="chart-card">
                <div class="chart-header">
                    <div>
                        <div class="chart-title">
                            <i class="fas fa-chart-bar"></i>
                            Equipment Efficiency
                        </div>
                        <div class="chart-subtitle">Most and least used equipment</div>
                    </div>
                </div>
                <div class="chart-container">
                    <canvas id="efficiencyChart"></canvas>
                </div>
            </div>

            <!-- User Activity -->
            <div class="chart-card">
                <div class="chart-header">
                    <div>
                        <div class="chart-title">
                            <i class="fas fa-user-chart"></i>
                            User Activity
                        </div>
                        <div class="chart-subtitle">Top users by activity</div>
                    </div>
                </div>
                <div class="chart-container">
                    <canvas id="userActivityChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Equipment Status Table -->
        <div class="data-table">
            <div class="table-header">
                <i class="fas fa-list"></i> Currently Checked Out Equipment
            </div>
            <div class="table-content" id="equipmentTable">
                <?php if (empty($checked_out)): ?>
                    <div style="text-align: center; padding: 2rem; color: var(--black-secondary);">
                        <i class="fas fa-inbox fa-2x" style="margin-bottom: 1rem; opacity: 0.5;"></i>
                        <p>No equipment currently checked out</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($checked_out as $item): ?>
                        <div class="table-row">
                            <div class="table-cell">
                                <div class="table-cell title"><?php echo htmlspecialchars($item['asset_name']); ?></div>
                                <div class="table-cell subtitle"><?php echo htmlspecialchars($item['asset_id'] . ' • ' . $item['category']); ?></div>
                            </div>
                            <div class="table-cell">
                                <span class="status-badge <?php echo strtotime($item['expected_return_date']) < time() ? 'overdue' : 'checked-out'; ?>">
                                    <?php echo strtotime($item['expected_return_date']) < time() ? 'OVERDUE' : 'CHECKED OUT'; ?>
                                </span>
                            </div>
                            <div class="table-cell">
                                <?php echo htmlspecialchars($item['current_borrower'] ?? '-'); ?>
                            </div>
                            <div class="table-cell">
                                <small><?php echo date('M j', strtotime($item['expected_return_date'])); ?></small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Dashboard Data Management
        class DashboardManager {
            constructor() {
                this.charts = {};
                this.init();
            }

            init() {
                this.loadData();
            }

            async loadData() {
                try {
                    this.showLoading();
                    
                    const response = await fetch('dashboard_advanced.php?api=data');
                    const data = await response.json();
                    
                    if (data.success) {
                        this.updateMetrics(data.metrics);
                        this.updateCharts(data);
                        this.hideLoading();
                    } else {
                        throw new Error(data.error || 'Failed to load data');
                    }
                } catch (error) {
                    console.error('Error loading dashboard data:', error);
                    this.showError('Failed to load dashboard data: ' + error.message);
                }
            }

            showLoading() {
                const chartContainers = document.querySelectorAll('.chart-container');
                chartContainers.forEach(container => {
                    container.innerHTML = '<div class="loading"><div class="spinner"></div></div>';
                });
            }

            hideLoading() {
                // Loading will be hidden when charts are rendered
            }

            showError(message) {
                console.error(message);
                const chartContainers = document.querySelectorAll('.chart-container');
                chartContainers.forEach(container => {
                    if (container.querySelector('.loading')) {
                        container.innerHTML = `<div class="error-message">${message}</div>`;
                    }
                });
            }

            updateMetrics(metrics) {
                if (!metrics) return;
                
                // Update metric cards
                const elements = {
                    'totalAssets': metrics.totalAssets,
                    'utilizationRate': metrics.utilizationRate + '%',
                    'activeUsers': metrics.activeUsers,
                    'avgCheckoutTime': metrics.avgCheckoutTime + ' days'
                };
                
                for (const [id, value] of Object.entries(elements)) {
                    const element = document.getElementById(id);
                    if (element) {
                        element.textContent = value;
                    }
                }
            }

            updateCharts(data) {
                // Add small delay to ensure DOM is ready
                setTimeout(() => {
                    this.createUsageTrendsChart(data.usageTrends || []);
                    this.createCategoryChart(data.categoryDistribution || []);
                    this.createEfficiencyChart(data.equipmentEfficiency || []);
                    this.createUserActivityChart(data.userActivity || []);
                }, 100);
            }

            createUsageTrendsChart(data) {
                const container = document.querySelector('#usageTrendsChart')?.parentElement;
                if (!container) return;
                
                container.innerHTML = '<canvas id="usageTrendsChart"></canvas>';
                const ctx = document.getElementById('usageTrendsChart');
                
                if (this.charts.usageTrends) {
                    this.charts.usageTrends.destroy();
                }
                
                if (!data || data.length === 0) {
                    container.innerHTML = '<div style="text-align: center; padding: 2rem; color: #666;">No usage data available for this period</div>';
                    return;
                }
                
                this.charts.usageTrends = new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: data.map(d => {
                            const date = new Date(d.date);
                            return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
                        }),
                        datasets: [
                            {
                                label: 'Check-outs',
                                data: data.map(d => parseInt(d.checkouts || 0)),
                                borderColor: '#FFD700',
                                backgroundColor: 'rgba(255, 215, 0, 0.1)',
                                borderWidth: 3,
                                fill: true,
                                tension: 0.4,
                                pointBackgroundColor: '#FFD700',
                                pointBorderColor: '#000',
                                pointBorderWidth: 2
                            },
                            {
                                label: 'Check-ins',
                                data: data.map(d => parseInt(d.checkins || 0)),
                                borderColor: '#4CAF50',
                                backgroundColor: 'rgba(76, 175, 80, 0.1)',
                                borderWidth: 3,
                                fill: true,
                                tension: 0.4,
                                pointBackgroundColor: '#4CAF50',
                                pointBorderColor: '#000',
                                pointBorderWidth: 2
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'top',
                                labels: {
                                    font: { weight: 'bold', size: 12 },
                                    usePointStyle: true,
                                    padding: 20
                                }
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                grid: { color: '#f0f0f0' },
                                ticks: { 
                                    font: { weight: '600' },
                                    stepSize: 1
                                }
                            },
                            x: {
                                grid: { color: '#f0f0f0' },
                                ticks: { 
                                    font: { weight: '600' },
                                    maxRotation: 45
                                }
                            }
                        }
                    }
                });
            }

            createCategoryChart(data) {
                const container = document.querySelector('#categoryChart')?.parentElement;
                if (!container) return;
                
                container.innerHTML = '<canvas id="categoryChart"></canvas>';
                const ctx = document.getElementById('categoryChart');
                
                if (this.charts.category) {
                    this.charts.category.destroy();
                }
                
                if (!data || data.length === 0) {
                    container.innerHTML = '<div style="text-align: center; padding: 2rem; color: #666;">No category data available</div>';
                    return;
                }
                
                this.charts.category = new Chart(ctx, {
                    type: 'doughnut',
                    data: {
                        labels: data.map(d => d.category),
                        datasets: [{
                            data: data.map(d => parseInt(d.total_items || 0)),
                            backgroundColor: [
                                '#FFD700',
                                '#4CAF50',
                                '#2196F3',
                                '#FF5722',
                                '#9C27B0',
                                '#FF9800',
                                '#607D8B',
                                '#795548'
                            ],
                            borderWidth: 3,
                            borderColor: '#000000',
                            hoverBorderWidth: 4
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: {
                                    font: { weight: 'bold', size: 11 },
                                    usePointStyle: true,
                                    padding: 15
                                }
                            }
                        }
                    }
                });
            }

            createEfficiencyChart(data) {
                const container = document.querySelector('#efficiencyChart')?.parentElement;
                if (!container) return;
                
                container.innerHTML = '<canvas id="efficiencyChart"></canvas>';
                const ctx = document.getElementById('efficiencyChart');
                
                if (this.charts.efficiency) {
                    this.charts.efficiency.destroy();
                }
                
                if (!data || data.length === 0) {
                    container.innerHTML = '<div style="text-align: center; padding: 2rem; color: #666;">No efficiency data available</div>';
                    return;
                }
                
                const topItems = data.slice(0, 5);
                
                this.charts.efficiency = new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: topItems.map(d => {
                            const name = d.asset_name || 'Unknown';
                            return name.length > 15 ? name.substring(0, 15) + '...' : name;
                        }),
                        datasets: [{
                            label: 'Usage Count',
                            data: topItems.map(d => parseInt(d.usage_count || 0)),
                            backgroundColor: '#FFD700',
                            borderColor: '#000000',
                            borderWidth: 2,
                            hoverBackgroundColor: '#FFC107',
                            hoverBorderWidth: 3
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                grid: { color: '#f0f0f0' },
                                ticks: { 
                                    font: { weight: '600' },
                                    stepSize: 1
                                }
                            },
                            x: {
                                grid: { display: false },
                                ticks: { 
                                    font: { weight: '600', size: 10 },
                                    maxRotation: 45
                                }
                            }
                        }
                    }
                });
            }

            createUserActivityChart(data) {
                const container = document.querySelector('#userActivityChart')?.parentElement;
                if (!container) return;
                
                container.innerHTML = '<canvas id="userActivityChart"></canvas>';
                const ctx = document.getElementById('userActivityChart');
                
                if (this.charts.userActivity) {
                    this.charts.userActivity.destroy();
                }
                
                if (!data || data.length === 0) {
                    container.innerHTML = '<div style="text-align: center; padding: 2rem; color: #666;">No user activity data available</div>';
                    return;
                }
                
                const topUsers = data.slice(0, 5);
                
                this.charts.userActivity = new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: topUsers.map(d => {
                            const name = d.borrower_name || 'Unknown';
                            return name.length > 12 ? name.substring(0, 12) + '...' : name;
                        }),
                        datasets: [{
                            label: 'Check-outs',
                            data: topUsers.map(d => parseInt(d.total_checkouts || 0)),
                            backgroundColor: '#4CAF50',
                            borderColor: '#000000',
                            borderWidth: 2,
                            hoverBackgroundColor: '#66BB6A',
                            hoverBorderWidth: 3
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        indexAxis: 'y',
                        plugins: {
                            legend: { display: false }
                        },
                        scales: {
                            x: {
                                beginAtZero: true,
                                grid: { color: '#f0f0f0' },
                                ticks: { 
                                    font: { weight: '600' },
                                    stepSize: 1
                                }
                            },
                            y: {
                                grid: { display: false },
                                ticks: { 
                                    font: { weight: '600', size: 10 }
                                }
                            }
                        }
                    }
                });
            }
        }

        // Global functions
        function refreshDashboard() {
            if (window.dashboardManager) {
                window.dashboardManager.loadData();
            }
        }

        function updateFilters() {
            refreshDashboard();
        }

        // Initialize dashboard when page loads
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof Chart === 'undefined') {
                console.error('Chart.js is not loaded');
                return;
            }
            
            Chart.defaults.font.family = 'Inter, sans-serif';
            Chart.defaults.font.weight = '600';
            Chart.defaults.color = '#000000';
            
            window.dashboardManager = new DashboardManager();
        });
    </script>
</body>
</html>