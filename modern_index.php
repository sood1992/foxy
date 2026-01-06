<?php
// neofox_dashboard.php - Bright Yellow Theme to Match Webflow Design
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

$stats = $asset->getAssetStats();
$checked_out = $asset->getCheckedOutAssets();
$overdue = $asset->getOverdueAssets();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Neofox Gear Control</title>
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
            overflow-x: hidden;
        }

        /* Fun floating elements like in the design */
        .floating-elements {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 1;
        }

        .floating-icon {
            position: absolute;
            font-size: 2rem;
            opacity: 0.1;
            animation: float 6s ease-in-out infinite;
        }

        .floating-icon:nth-child(1) { top: 10%; left: 10%; animation-delay: 0s; }
        .floating-icon:nth-child(2) { top: 20%; right: 15%; animation-delay: 1s; }
        .floating-icon:nth-child(3) { top: 60%; left: 5%; animation-delay: 2s; }
        .floating-icon:nth-child(4) { bottom: 20%; right: 10%; animation-delay: 3s; }
        .floating-icon:nth-child(5) { bottom: 40%; left: 20%; animation-delay: 4s; }

        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(5deg); }
        }

        /* Navigation */
        .navbar {
            background: rgba(0, 0, 0, 0.9);
            backdrop-filter: blur(10px);
            padding: 1.2rem 0;
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
            font-size: 1.8rem;
            font-weight: 900;
            color: var(--neofox-yellow);
            text-decoration: none;
            letter-spacing: -1px;
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
            padding: 0.8rem 1.5rem;
            border-radius: 25px;
            transition: all 0.3s ease;
            position: relative;
            font-size: 0.95rem;
        }

        .nav-link:hover {
            background: var(--neofox-yellow);
            color: var(--black-primary);
            transform: translateY(-2px);
        }

        .nav-link.active {
            background: var(--neofox-yellow);
            color: var(--black-primary);
            font-weight: 700;
        }

        /* Main Container */
        .main-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 3rem 2rem;
            position: relative;
            z-index: 10;
        }

        /* Page Header */
        .page-header {
            text-align: center;
            margin-bottom: 4rem;
        }

        .page-title {
            font-size: 4rem;
            font-weight: 900;
            color: var(--black-primary);
            margin-bottom: 1rem;
            letter-spacing: -2px;
            line-height: 0.9;
        }

        .page-subtitle {
            font-size: 1.4rem;
            color: var(--black-secondary);
            font-weight: 500;
            max-width: 600px;
            margin: 0 auto;
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
            margin-bottom: 4rem;
        }

        .stat-card {
            background: var(--white-primary);
            border-radius: 24px;
            padding: 2.5rem;
            text-align: center;
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
            border: 3px solid var(--black-primary);
            box-shadow: 8px 8px 0px var(--black-primary);
        }

        .stat-card:hover {
            transform: translateY(-8px) translateX(-4px);
            box-shadow: 12px 12px 0px var(--black-primary);
        }

        .stat-icon {
            width: 80px;
            height: 80px;
            background: var(--black-primary);
            color: var(--neofox-yellow);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            font-size: 2rem;
        }

        .stat-number {
            font-size: 3.5rem;
            font-weight: 900;
            color: var(--black-primary);
            margin-bottom: 0.5rem;
            line-height: 1;
        }

        .stat-label {
            color: var(--black-secondary);
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-size: 1rem;
        }

        /* Cards */
        .modern-card {
            background: var(--white-primary);
            border-radius: 24px;
            overflow: hidden;
            margin-bottom: 3rem;
            border: 3px solid var(--black-primary);
            box-shadow: 8px 8px 0px var(--black-primary);
            transition: all 0.3s ease;
        }

        .modern-card:hover {
            transform: translateY(-4px) translateX(-2px);
            box-shadow: 12px 12px 0px var(--black-primary);
        }

        .card-header {
            background: var(--black-primary);
            color: var(--neofox-yellow);
            padding: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .card-title {
            font-size: 1.5rem;
            font-weight: 800;
            margin: 0;
        }

        .card-body {
            padding: 2.5rem;
        }

        /* Buttons */
        .btn {
            padding: 1rem 2rem;
            border-radius: 50px;
            font-weight: 700;
            text-decoration: none;
            border: 3px solid var(--black-primary);
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 1rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .btn-primary {
            background: var(--neofox-yellow);
            color: var(--black-primary);
            box-shadow: 4px 4px 0px var(--black-primary);
        }

        .btn-primary:hover {
            transform: translateY(-2px) translateX(-2px);
            box-shadow: 6px 6px 0px var(--black-primary);
            color: var(--black-primary);
        }

        .btn-success {
            background: var(--green-accent);
            color: var(--white-primary);
            box-shadow: 4px 4px 0px var(--black-primary);
        }

        .btn-warning {
            background: var(--yellow-dark);
            color: var(--white-primary);
            box-shadow: 4px 4px 0px var(--black-primary);
        }

        /* Quick Actions Grid */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 2rem;
            margin-bottom: 4rem;
        }

        .action-card {
            background: var(--white-primary);
            border-radius: 24px;
            padding: 3rem 2rem;
            text-align: center;
            transition: all 0.3s ease;
            text-decoration: none;
            color: var(--black-primary);
            border: 3px solid var(--black-primary);
            box-shadow: 8px 8px 0px var(--black-primary);
            position: relative;
            overflow: hidden;
        }

        .action-card:hover {
            transform: translateY(-8px) translateX(-4px);
            box-shadow: 12px 12px 0px var(--black-primary);
            color: var(--black-primary);
        }

        .action-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 6px;
            background: var(--neofox-yellow);
        }

        .action-icon {
            width: 100px;
            height: 100px;
            background: var(--black-primary);
            color: var(--neofox-yellow);
            border-radius: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 2rem;
            font-size: 2.5rem;
        }

        .action-title {
            font-size: 1.3rem;
            font-weight: 800;
            margin-bottom: 1rem;
            color: var(--black-primary);
        }

        .action-desc {
            color: var(--black-secondary);
            font-weight: 500;
            font-size: 1rem;
        }

        /* Table */
        .modern-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.95rem;
        }

        .modern-table th {
            background: var(--black-primary);
            color: var(--neofox-yellow);
            font-weight: 700;
            padding: 1.5rem 1rem;
            text-align: left;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-size: 0.9rem;
        }

        .modern-table td {
            padding: 1.5rem 1rem;
            border-bottom: 2px solid var(--gray-light);
            color: var(--black-primary);
            font-weight: 500;
        }

        .modern-table tr:hover {
            background: var(--yellow-light);
        }

        /* Status Badges */
        .badge {
            padding: 0.6rem 1.2rem;
            border-radius: 25px;
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border: 2px solid var(--black-primary);
        }

        .badge-success {
            background: var(--green-accent);
            color: var(--white-primary);
        }

        .badge-warning {
            background: var(--yellow-dark);
            color: var(--white-primary);
        }

        .badge-danger {
            background: var(--red-accent);
            color: var(--white-primary);
        }

        /* Alert Cards */
        .alert-card {
            background: var(--white-primary);
            border: 3px solid var(--red-accent);
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 1.5rem;
            box-shadow: 6px 6px 0px var(--red-accent);
        }

        .alert-icon {
            width: 60px;
            height: 60px;
            background: var(--red-accent);
            color: var(--white-primary);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            flex-shrink: 0;
        }

        .alert-text {
            font-weight: 600;
            font-size: 1.1rem;
        }

        /* Empty States */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: var(--black-secondary);
        }

        .empty-icon {
            font-size: 5rem;
            margin-bottom: 2rem;
            opacity: 0.3;
        }

        .empty-title {
            font-size: 2rem;
            font-weight: 800;
            margin-bottom: 1rem;
            color: var(--black-primary);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .navbar-container {
                padding: 0 1rem;
                flex-direction: column;
                gap: 1rem;
            }

            .main-container {
                padding: 2rem 1rem;
            }

            .page-title {
                font-size: 2.5rem;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .quick-actions {
                grid-template-columns: 1fr;
            }

            .navbar-nav {
                gap: 0.5rem;
                flex-wrap: wrap;
                justify-content: center;
            }

            .nav-link {
                padding: 0.6rem 1rem;
                font-size: 0.9rem;
            }
        }
    </style>
</head>
<body>
    <!-- Floating Elements for Fun -->
    <div class="floating-elements">
        <div class="floating-icon"><i class="fas fa-camera"></i></div>
        <div class="floating-icon"><i class="fas fa-microphone"></i></div>
        <div class="floating-icon"><i class="fas fa-video"></i></div>
        <div class="floating-icon"><i class="fas fa-lightbulb"></i></div>
        <div class="floating-icon"><i class="fas fa-headphones"></i></div>
    </div>

    <!-- Navigation -->
    <nav class="navbar">
        <div class="navbar-container">
            <a href="index.php" class="navbar-brand">
                <i class="fas fa-cube"></i> NEOFOX GEAR
            </a>
            <div class="navbar-nav">
                <a href="index.php" class="nav-link active">
                    <i class="fas fa-home"></i> Dashboard
                </a>
                <a href="assets.php" class="nav-link">
                    <i class="fas fa-box"></i> Assets
                </a>
                <a href="bulk_scanner_v2.php" class="nav-link">
                    <i class="fas fa-qrcode"></i> Scanner
                </a>
                <a href="requests.php" class="nav-link">
                    <i class="fas fa-calendar-check"></i> Requests
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
            <h1 class="page-title">GEAR CONTROL<br>SYSTEM</h1>
            <p class="page-subtitle">Not a studio. Not agency. A crew. A lab. A system.</p>
        </div>

        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-boxes"></i>
                </div>
                <div class="stat-number"><?php echo $stats['total_assets']; ?></div>
                <div class="stat-label">Total Assets</div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-number"><?php echo $stats['available']; ?></div>
                <div class="stat-label">Available</div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-arrow-right"></i>
                </div>
                <div class="stat-number"><?php echo $stats['checked_out']; ?></div>
                <div class="stat-label">Checked Out</div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="stat-number"><?php echo count($overdue); ?></div>
                <div class="stat-label">Overdue</div>
            </div>
        </div>

        <!-- Overdue Alerts -->
        <?php if (!empty($overdue)): ?>
        <div class="modern-card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-exclamation-triangle"></i> OVERDUE EQUIPMENT
                </h3>
            </div>
            <div class="card-body">
                <?php foreach ($overdue as $item): ?>
                <div class="alert-card">
                    <div class="alert-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="alert-text">
                        <strong><?php echo htmlspecialchars($item['asset_name']); ?></strong><br>
                        Borrowed by: <?php echo htmlspecialchars($item['current_borrower']); ?> • 
                        Due: <?php echo date('M j, Y', strtotime($item['expected_return_date'])); ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Quick Actions -->
        <div class="quick-actions">
            <a href="add_asset.php" class="action-card">
                <div class="action-icon">
                    <i class="fas fa-plus"></i>
                </div>
                <div class="action-title">ADD ASSET</div>
                <div class="action-desc">Register new equipment in the system</div>
            </a>

            <a href="bulk_scanner_v2.php" class="action-card">
                <div class="action-icon">
                    <i class="fas fa-qrcode"></i>
                </div>
                <div class="action-title">BULK SCANNER</div>
                <div class="action-desc">Scan multiple QR codes at once</div>
            </a>

            <a href="gear_request.php" class="action-card">
                <div class="action-icon">
                    <i class="fas fa-calendar-plus"></i>
                </div>
                <div class="action-title">REQUEST GEAR</div>
                <div class="action-desc">Book equipment for upcoming shoots</div>
            </a>

            <a href="export.php" class="action-card">
                <div class="action-icon">
                    <i class="fas fa-download"></i>
                </div>
                <div class="action-title">EXPORT DATA</div>
                <div class="action-desc">Download reports and analytics</div>
            </a>
        </div>

        <!-- Currently Checked Out -->
        <div class="modern-card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-arrow-right"></i> CURRENTLY CHECKED OUT
                </h3>
                <span class="badge badge-warning"><?php echo count($checked_out); ?> ITEMS</span>
            </div>
            <div class="card-body">
                <?php if (empty($checked_out)): ?>
                <div class="empty-state">
                    <div class="empty-icon">
                        <i class="fas fa-inbox"></i>
                    </div>
                    <h4 class="empty-title">ALL CLEAR!</h4>
                    <p>No equipment is currently checked out. Everything is ready for action!</p>
                </div>
                <?php else: ?>
                <div style="overflow-x: auto;">
                    <table class="modern-table">
                        <thead>
                            <tr>
                                <th>ASSET</th>
                                <th>BORROWER</th>
                                <th>CHECKED OUT</th>
                                <th>DUE BACK</th>
                                <th>STATUS</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($checked_out as $item): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($item['asset_name']); ?></strong><br>
                                    <?php echo htmlspecialchars($item['category']); ?>
                                </td>
                                <td><strong><?php echo htmlspecialchars($item['current_borrower']); ?></strong></td>
                                <td><?php echo date('M j, Y', strtotime($item['checkout_date'])); ?></td>
                                <td><?php echo date('M j, Y', strtotime($item['expected_return_date'])); ?></td>
                                <td>
                                    <?php if (strtotime($item['expected_return_date']) < time()): ?>
                                    <span class="badge badge-danger">OVERDUE</span>
                                    <?php else: ?>
                                    <span class="badge badge-success">ON TIME</span>
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
</body>
</html>