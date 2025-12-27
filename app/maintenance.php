<?php
// maintenance.php - Real Maintenance Dashboard with Database Connection
// Fix session path issue
if (!session_id()) {
    ini_set('session.save_path', sys_get_temp_dir());
    session_start();
}

require_once 'config/database.php';
require_once 'classes/Asset.php';
require_once 'classes/MaintenanceTracker.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();
$asset = new Asset($db);
$maintenance = new MaintenanceTracker($db);

// Handle API requests
if (isset($_GET['api'])) {
    header('Content-Type: application/json');
    
    try {
        switch ($_GET['api']) {
            case 'data':
                $stats = $maintenance->getMaintenanceStats();
                $upcoming = $maintenance->getUpcomingMaintenance();
                $overdue = $maintenance->getOverdueMaintenance();
                $issues = $maintenance->getOpenIssues();
                $assets = $asset->getAll();
                
                echo json_encode([
                    'success' => true,
                    'stats' => $stats,
                    'upcomingTasks' => $upcoming,
                    'openIssues' => $issues,
                    'assets' => $assets
                ]);
                break;
                
            case 'schedule':
                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                    $input = json_decode(file_get_contents('php://input'), true);
                    $result = $maintenance->scheduleMaintenance($input);
                    echo json_encode(['success' => $result !== false, 'id' => $result]);
                }
                break;
                
            case 'report_issue':
                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                    $input = json_decode(file_get_contents('php://input'), true);
                    $result = $maintenance->reportIssue($input);
                    echo json_encode(['success' => $result !== false, 'id' => $result]);
                }
                break;
                
            case 'complete_task':
                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                    $input = json_decode(file_get_contents('php://input'), true);
                    $task_id = $input['task_id'];
                    $completion_data = [
                        'actual_duration' => $input['actual_duration'] ?? null,
                        'actual_cost' => $input['actual_cost'] ?? null,
                        'completion_notes' => $input['completion_notes'] ?? '',
                        'completed_by' => $_SESSION['username'],
                        'resulting_condition' => $input['resulting_condition'] ?? 'good'
                    ];
                    $result = $maintenance->completeMaintenance($task_id, $completion_data);
                    echo json_encode(['success' => $result]);
                }
                break;
                
            case 'resolve_issue':
                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                    $input = json_decode(file_get_contents('php://input'), true);
                    $issue_id = $input['issue_id'];
                    $resolution_data = [
                        'resolved_by' => $_SESSION['username'],
                        'resolution_notes' => $input['resolution_notes'] ?? '',
                        'resolution_cost' => $input['resolution_cost'] ?? null,
                        'maintenance_completed' => $input['maintenance_completed'] ?? false,
                        'resulting_condition' => $input['resulting_condition'] ?? 'good'
                    ];
                    $result = $maintenance->resolveIssue($issue_id, $resolution_data);
                    echo json_encode(['success' => $result]);
                }
                break;
                
            default:
                echo json_encode(['success' => false, 'error' => 'Unknown API endpoint']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit();
}

// Get data for initial page load
try {
    $stats = $maintenance->getMaintenanceStats();
    $upcoming = $maintenance->getUpcomingMaintenance(30);
    $overdue = $maintenance->getOverdueMaintenance();
    $issues = $maintenance->getOpenIssues();
    $assets = $asset->getAll();
} catch (Exception $e) {
    $error = "Error loading maintenance data: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maintenance Dashboard - Neofox Gear Control</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
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
            --red-accent: #FF5722;
            --green-accent: #4CAF50;
            --blue-accent: #2196F3;
            --purple-accent: #9C27B0;
            --orange-accent: #FF9800;
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
            text-align: center;
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

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
            margin-bottom: 3rem;
        }

        .stat-card {
            background: var(--white-primary);
            border-radius: 20px;
            padding: 2rem;
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
            width: 70px;
            height: 70px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 1.8rem;
            color: var(--white-primary);
        }

        .stat-icon.pending { background: var(--orange-accent); }
        .stat-icon.overdue { background: var(--red-accent); }
        .stat-icon.completed { background: var(--green-accent); }
        .stat-icon.issues { background: var(--purple-accent); }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 900;
            color: var(--black-primary);
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: var(--black-secondary);
            font-weight: 700;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 1rem;
            margin-bottom: 3rem;
            flex-wrap: wrap;
        }

        .btn {
            padding: 0.8rem 1.5rem;
            border-radius: 12px;
            font-weight: 700;
            text-decoration: none;
            border: 2px solid var(--black-primary);
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
            background: none;
        }

        .btn-primary {
            background: var(--neofox-yellow);
            color: var(--black-primary);
            box-shadow: 3px 3px 0px var(--black-primary);
        }

        .btn-primary:hover {
            transform: translateY(-2px) translateX(-1px);
            box-shadow: 5px 5px 0px var(--black-primary);
            color: var(--black-primary);
        }

        .btn-success {
            background: var(--green-accent);
            color: var(--white-primary);
            box-shadow: 3px 3px 0px var(--black-primary);
        }

        .btn-danger {
            background: var(--red-accent);
            color: var(--white-primary);
            box-shadow: 3px 3px 0px var(--black-primary);
        }

        /* Section Cards */
        .section-card {
            background: var(--white-primary);
            border-radius: 20px;
            border: 3px solid var(--black-primary);
            box-shadow: 6px 6px 0px var(--black-primary);
            margin-bottom: 2rem;
            overflow: hidden;
        }

        .section-header {
            background: var(--black-primary);
            color: var(--neofox-yellow);
            padding: 1.5rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .section-title {
            font-size: 1.3rem;
            font-weight: 800;
            margin: 0;
        }

        .section-body {
            padding: 2rem;
        }

        /* Task Lists */
        .task-list {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .task-item {
            background: var(--gray-light);
            border: 2px solid var(--black-primary);
            border-radius: 12px;
            padding: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.3s ease;
        }

        .task-item:hover {
            transform: translateY(-2px);
            box-shadow: 3px 3px 0px var(--black-primary);
        }

        .task-item.overdue {
            border-color: var(--red-accent);
            background: #FFEBEE;
        }

        .task-item.due-soon {
            border-color: var(--orange-accent);
            background: #FFF3E0;
        }

        .task-info {
            flex: 1;
        }

        .task-title {
            font-weight: 700;
            color: var(--black-primary);
            margin-bottom: 0.5rem;
            font-size: 1.1rem;
        }

        .task-details {
            color: var(--black-secondary);
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
        }

        .task-meta {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .task-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            border: 1px solid;
        }

        .task-badge.priority-high {
            background: var(--red-accent);
            color: var(--white-primary);
            border-color: var(--red-accent);
        }

        .task-badge.priority-medium {
            background: var(--orange-accent);
            color: var(--white-primary);
            border-color: var(--orange-accent);
        }

        .task-badge.priority-low {
            background: var(--green-accent);
            color: var(--white-primary);
            border-color: var(--green-accent);
        }

        .task-badge.priority-critical {
            background: var(--red-accent);
            color: var(--white-primary);
            border-color: var(--red-accent);
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.7; }
            100% { opacity: 1; }
        }

        .task-badge.type {
            background: var(--blue-accent);
            color: var(--white-primary);
            border-color: var(--blue-accent);
        }

        .task-actions {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }

        .task-btn {
            padding: 0.5rem;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s ease;
            font-size: 0.9rem;
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .task-btn.complete {
            background: var(--green-accent);
            color: var(--white-primary);
        }

        .task-btn.edit {
            background: var(--blue-accent);
            color: var(--white-primary);
        }

        .task-btn.delete {
            background: var(--red-accent);
            color: var(--white-primary);
        }

        .task-btn:hover {
            transform: scale(1.1);
        }

        /* Issue Cards */
        .issue-item {
            background: var(--white-primary);
            border: 2px solid;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
        }

        .issue-item.severity-critical {
            border-color: var(--red-accent);
            background: #FFEBEE;
        }

        .issue-item.severity-high {
            border-color: var(--orange-accent);
            background: #FFF3E0;
        }

        .issue-item.severity-medium {
            border-color: var(--neofox-yellow);
            background: var(--yellow-light);
        }

        .issue-item.severity-low {
            border-color: var(--green-accent);
            background: #E8F5E8;
        }

        .issue-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }

        .issue-title {
            font-weight: 700;
            color: var(--black-primary);
            font-size: 1.1rem;
        }

        .issue-description {
            color: var(--black-secondary);
            margin: 0.5rem 0;
            line-height: 1.5;
        }

        .issue-meta {
            display: flex;
            gap: 1rem;
            align-items: center;
            font-size: 0.9rem;
            color: var(--black-secondary);
            flex-wrap: wrap;
        }

        /* Tabs */
        .tab-container {
            margin-bottom: 2rem;
        }

        .tab-nav {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }

        .tab-btn {
            padding: 0.8rem 1.5rem;
            background: var(--white-primary);
            border: 2px solid var(--black-primary);
            border-radius: 12px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            color: var(--black-primary);
        }

        .tab-btn.active {
            background: var(--neofox-yellow);
            color: var(--black-primary);
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        /* Modals */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 2000;
            align-items: center;
            justify-content: center;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: var(--white-primary);
            border: 3px solid var(--black-primary);
            border-radius: 20px;
            padding: 2rem;
            max-width: 600px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid var(--gray-light);
        }

        .modal-title {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--black-primary);
        }

        .close-btn {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--black-secondary);
        }

        /* Forms */
        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 700;
            color: var(--black-primary);
        }

        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid var(--black-primary);
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--neofox-yellow);
            box-shadow: 0 0 0 3px rgba(255, 215, 0, 0.2);
        }

        .form-select {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='m6 8 4 4 4-4'/%3e%3c/svg%3e");
            background-position: right 0.5rem center;
            background-repeat: no-repeat;
            background-size: 1.5em 1.5em;
            padding-right: 2.5rem;
        }

        /* Empty States */
        .empty-state {
            text-align: center;
            padding: 3rem 2rem;
            color: var(--black-secondary);
        }

        .empty-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        .empty-title {
            font-size: 1.3rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            color: var(--black-primary);
        }

        /* Error message */
        .error-message {
            background: #ffebee;
            border: 2px solid var(--red-accent);
            border-radius: 12px;
            padding: 1rem;
            margin: 1rem 0;
            color: var(--red-accent);
            text-align: center;
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

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .action-buttons {
                flex-direction: column;
            }

            .task-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }

            .task-actions {
                align-self: stretch;
                justify-content: center;
            }

            .tab-nav {
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
                <i class="fas fa-cube"></i> NEOFOX GEAR
            </a>
            <div class="navbar-nav">
                <a href="index.php" class="nav-link">
                    <i class="fas fa-home"></i> Dashboard
                </a>
                <a href="dashboard_advanced.php" class="nav-link">
                    <i class="fas fa-chart-line"></i> Analytics
                </a>
                <a href="assets.php" class="nav-link">
                    <i class="fas fa-box"></i> Assets
                </a>
                <a href="maintenance.php" class="nav-link active">
                    <i class="fas fa-wrench"></i> Maintenance
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
                <i class="fas fa-wrench"></i> MAINTENANCE CONTROL
            </h1>
            <p class="page-subtitle">Track, schedule, and manage equipment maintenance</p>
        </div>

        <?php if (isset($error)): ?>
        <div class="error-message">
            <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
        </div>
        <?php endif; ?>

        <!-- Stats Overview -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon pending">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-number" id="pendingCount"><?php echo $stats['pending_maintenance'] ?? 0; ?></div>
                <div class="stat-label">Pending Tasks</div>
            </div>

            <div class="stat-card">
                <div class="stat-icon overdue">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>