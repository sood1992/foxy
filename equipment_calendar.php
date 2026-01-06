<?php
// equipment_calendar.php - Equipment availability calendar with multi-select reservation
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Fix session path issue
if (!session_id()) {
    ini_set('session.save_path', sys_get_temp_dir());
    session_start();
}

require_once 'config/database.php';
require_once 'classes/Asset.php';
require_once 'classes/GearRequest.php';

$database = new Database();
$db = $database->getConnection();
$asset = new Asset($db);
$gear_request = new GearRequest($db);

// Get all users for dropdown
$users_query = "SELECT username, email FROM users WHERE role IN ('admin', 'team_member') ORDER BY username";
$users_stmt = $db->prepare($users_query);
$users_stmt->execute();
$users = $users_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all assets (both available and checked out for calendar view)
$all_assets_query = "SELECT * FROM assets ORDER BY category, asset_name";
$all_assets_stmt = $db->prepare($all_assets_query);
$all_assets_stmt->execute();
$all_assets = $all_assets_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get categories for filtering
$categories_query = "SELECT DISTINCT category FROM assets ORDER BY category";
$categories_stmt = $db->prepare($categories_query);
$categories_stmt->execute();
$categories = $categories_stmt->fetchAll(PDO::FETCH_COLUMN);

// Get pending and approved requests for calendar events
$requests_query = "SELECT * FROM gear_requests WHERE status IN ('pending', 'approved') ORDER BY created_at DESC";
$requests_stmt = $db->prepare($requests_query);
$requests_stmt->execute();
$reservations = $requests_stmt->fetchAll(PDO::FETCH_ASSOC);

$success = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $selected_username = trim($_POST['requester_name'] ?? '');
    $selected_assets = isset($_POST['selected_equipment']) ? $_POST['selected_equipment'] : [];
    $start_date = $_POST['start_date'] ?? '';
    $end_date = $_POST['end_date'] ?? '';
    $purpose = trim($_POST['purpose'] ?? '');

    // Get user email
    $user_email = '';
    foreach ($users as $user) {
        if ($user['username'] === $selected_username) {
            $user_email = $user['email'];
            break;
        }
    }

    // Validation
    if (empty($selected_username)) {
        $error = "Please select a team member.";
    } elseif (empty($selected_assets)) {
        $error = "Please select at least one equipment item.";
    } elseif (empty($start_date) || empty($end_date)) {
        $error = "Please select both start and end dates.";
    } elseif (strtotime($start_date) < strtotime(date('Y-m-d'))) {
        $error = "Start date cannot be in the past.";
    } elseif (strtotime($end_date) < strtotime($start_date)) {
        $error = "End date must be on or after the start date.";
    } else {
        // Format the equipment list
        $assets_text = implode(', ', $selected_assets);
        $request_dates = date('m/d/Y', strtotime($start_date)) . ' to ' . date('m/d/Y', strtotime($end_date));

        $data = [
            'requester_name' => $selected_username,
            'requester_email' => $user_email,
            'required_items' => $assets_text,
            'request_dates' => $request_dates,
            'purpose' => $purpose
        ];

        if ($gear_request->create($data)) {
            $success = "Reservation request submitted successfully for " . count($selected_assets) . " item(s)! An admin will review your request.";
        } else {
            $error = "Failed to submit reservation request. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Equipment Calendar - Neofox Gear Control</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
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
            --gray-500: #737373;
            --gray-600: #525252;
            --gray-700: #404040;
            --green-500: #10B981;
            --orange-500: #F59E0B;
            --red-500: #EF4444;
            --blue-500: #3B82F6;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, var(--neofox-yellow) 0%, var(--yellow-light) 100%);
            color: var(--gray-700);
            line-height: 1.6;
            min-height: 100vh;
        }

        /* Navigation */
        .navbar {
            background: rgba(0, 0, 0, 0.9);
            backdrop-filter: blur(10px);
            padding: 1rem 0;
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
            font-weight: 700;
            color: var(--neofox-yellow);
            text-decoration: none;
        }

        .navbar-nav {
            display: flex;
            gap: 0.5rem;
        }

        .nav-link {
            color: var(--white-primary);
            text-decoration: none;
            font-weight: 500;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            transition: all 0.2s ease;
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

        .page-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .page-title {
            font-size: 2.5rem;
            font-weight: 900;
            color: var(--black-primary);
            margin-bottom: 0.5rem;
        }

        .page-subtitle {
            color: var(--gray-600);
            font-size: 1.1rem;
        }

        /* Main Card */
        .main-card {
            background: var(--white-primary);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .card-header {
            background: var(--black-primary);
            color: var(--neofox-yellow);
            padding: 2rem;
            text-align: center;
        }

        .card-header h2 {
            font-size: 1.5rem;
            font-weight: 700;
            margin: 0;
        }

        .card-body {
            padding: 2rem;
        }

        /* Alert */
        .alert {
            padding: 1rem 1.5rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            font-weight: 500;
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            color: #065f46;
            border: 1px solid rgba(16, 185, 129, 0.2);
        }

        .alert-danger {
            background: rgba(239, 68, 68, 0.1);
            color: #991b1b;
            border: 1px solid rgba(239, 68, 68, 0.2);
        }

        /* Form Styles */
        .form-section {
            margin-bottom: 2rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid var(--gray-200);
        }

        .section-title {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--black-primary);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-label {
            font-weight: 600;
            color: var(--black-primary);
            margin-bottom: 0.5rem;
            display: block;
        }

        .form-control, .form-select {
            border: 2px solid var(--gray-300);
            border-radius: 10px;
            padding: 0.75rem 1rem;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--neofox-yellow);
            box-shadow: 0 0 0 3px rgba(255, 215, 0, 0.2);
            outline: none;
        }

        /* Equipment Grid */
        .equipment-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 1rem;
            max-height: 500px;
            overflow-y: auto;
            padding: 1rem;
            background: var(--gray-50);
            border-radius: 12px;
            border: 2px solid var(--gray-200);
        }

        .equipment-card {
            background: var(--white-primary);
            border: 2px solid var(--gray-200);
            border-radius: 12px;
            padding: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
        }

        .equipment-card:hover {
            border-color: var(--neofox-yellow);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .equipment-card.selected {
            border-color: var(--green-500);
            background: rgba(16, 185, 129, 0.05);
        }

        .equipment-card.selected::after {
            content: '\f00c';
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            position: absolute;
            top: 0.5rem;
            right: 0.5rem;
            background: var(--green-500);
            color: white;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
        }

        .equipment-card.unavailable {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .equipment-name {
            font-weight: 700;
            color: var(--black-primary);
            margin-bottom: 0.25rem;
        }

        .equipment-meta {
            font-size: 0.85rem;
            color: var(--gray-500);
            margin-bottom: 0.5rem;
        }

        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .status-available {
            background: rgba(16, 185, 129, 0.1);
            color: var(--green-500);
        }

        .status-checked-out {
            background: rgba(239, 68, 68, 0.1);
            color: var(--red-500);
        }

        .status-maintenance {
            background: rgba(59, 130, 246, 0.1);
            color: var(--blue-500);
        }

        /* Selection Controls */
        .selection-controls {
            display: flex;
            gap: 1rem;
            margin-bottom: 1rem;
            align-items: center;
            flex-wrap: wrap;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            border: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-primary {
            background: var(--black-primary);
            color: var(--white-primary);
        }

        .btn-primary:hover {
            background: var(--black-light);
            transform: translateY(-2px);
        }

        .btn-outline {
            background: transparent;
            border: 2px solid var(--gray-300);
            color: var(--gray-700);
        }

        .btn-outline:hover {
            border-color: var(--black-primary);
            background: var(--gray-50);
        }

        .btn-success {
            background: var(--green-500);
            color: white;
        }

        .btn-lg {
            padding: 1rem 2rem;
            font-size: 1.1rem;
        }

        /* Selection Summary */
        .selection-summary {
            background: var(--yellow-light);
            border: 2px solid var(--neofox-yellow);
            border-radius: 12px;
            padding: 1rem;
            margin-top: 1rem;
        }

        .selection-summary h4 {
            font-weight: 700;
            margin-bottom: 0.5rem;
            color: var(--black-primary);
        }

        .selected-items {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .selected-item {
            background: var(--black-primary);
            color: var(--neofox-yellow);
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        /* Calendar Legend */
        .calendar-legend {
            display: flex;
            gap: 1.5rem;
            margin-bottom: 1rem;
            flex-wrap: wrap;
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
        }

        .legend-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .main-container {
                padding: 1rem;
            }

            .page-title {
                font-size: 2rem;
            }

            .equipment-grid {
                grid-template-columns: 1fr;
            }

            .selection-controls {
                flex-direction: column;
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
                <i class="fas fa-cube"></i> Neofox Gear
            </a>
            <div class="navbar-nav">
                <a class="nav-link" href="index.php">Dashboard</a>
                <a class="nav-link" href="gear_request.php">Request Gear</a>
                <a class="nav-link active" href="equipment_calendar.php">Calendar</a>
                <?php if (isset($_SESSION['user_id'])): ?>
                <a class="nav-link" href="requests.php">Requests</a>
                <a class="nav-link" href="logout.php">Logout</a>
                <?php else: ?>
                <a class="nav-link" href="login.php">Login</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <div class="main-container">
        <div class="page-header">
            <h1 class="page-title"><i class="fas fa-calendar-alt"></i> Equipment Calendar</h1>
            <p class="page-subtitle">View equipment availability and create new reservations</p>
        </div>

        <?php if ($success): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
        </div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
        </div>
        <?php endif; ?>

        <div class="main-card">
            <div class="card-header">
                <h2><i class="fas fa-calendar-plus"></i> New Reservation - Select Multiple Equipment</h2>
            </div>

            <div class="card-body">
                <form method="POST" id="reservationForm">
                    <!-- Team Member Selection -->
                    <div class="form-section">
                        <h3 class="section-title"><i class="fas fa-user"></i> Team Member</h3>
                        <div class="row">
                            <div class="col-md-6">
                                <label class="form-label">Select Team Member *</label>
                                <select name="requester_name" class="form-select" required>
                                    <option value="">Choose team member...</option>
                                    <?php foreach ($users as $user): ?>
                                    <option value="<?php echo htmlspecialchars($user['username']); ?>">
                                        <?php echo htmlspecialchars($user['username']); ?>
                                        <?php if ($user['email']): ?>(<?php echo htmlspecialchars($user['email']); ?>)<?php endif; ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Date Selection -->
                    <div class="form-section">
                        <h3 class="section-title"><i class="fas fa-calendar"></i> Reservation Dates</h3>
                        <div class="row">
                            <div class="col-md-6">
                                <label class="form-label">Start Date *</label>
                                <input type="date" name="start_date" class="form-control" required min="<?php echo date('Y-m-d'); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">End Date *</label>
                                <input type="date" name="end_date" class="form-control" required min="<?php echo date('Y-m-d'); ?>">
                            </div>
                        </div>
                    </div>

                    <!-- Equipment Selection -->
                    <div class="form-section">
                        <h3 class="section-title"><i class="fas fa-boxes"></i> Select Equipment (Multiple Selection)</h3>

                        <div class="calendar-legend">
                            <div class="legend-item">
                                <div class="legend-dot" style="background: var(--green-500);"></div>
                                <span>Available</span>
                            </div>
                            <div class="legend-item">
                                <div class="legend-dot" style="background: var(--red-500);"></div>
                                <span>Checked Out</span>
                            </div>
                            <div class="legend-item">
                                <div class="legend-dot" style="background: var(--blue-500);"></div>
                                <span>Maintenance</span>
                            </div>
                        </div>

                        <div class="selection-controls">
                            <input type="text" id="searchEquipment" class="form-control" placeholder="Search equipment..." style="max-width: 300px;">
                            <select id="categoryFilter" class="form-select" style="max-width: 200px;">
                                <option value="">All Categories</option>
                                <?php foreach ($categories as $category): ?>
                                <option value="<?php echo htmlspecialchars($category); ?>"><?php echo htmlspecialchars($category); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="button" class="btn btn-outline" onclick="selectAllAvailable()">
                                <i class="fas fa-check-double"></i> Select All Available
                            </button>
                            <button type="button" class="btn btn-outline" onclick="clearSelection()">
                                <i class="fas fa-times"></i> Clear Selection
                            </button>
                            <span id="selectedCount" style="font-weight: 600;">0 item(s) selected</span>
                        </div>

                        <div class="equipment-grid" id="equipmentGrid">
                            <?php foreach ($all_assets as $item): ?>
                            <?php
                                $is_available = $item['status'] === 'available';
                                $status_class = 'status-' . str_replace('_', '-', $item['status']);
                            ?>
                            <div class="equipment-card <?php echo $is_available ? '' : 'unavailable'; ?>"
                                 data-asset-id="<?php echo htmlspecialchars($item['asset_id']); ?>"
                                 data-asset-name="<?php echo htmlspecialchars($item['asset_name']); ?>"
                                 data-category="<?php echo htmlspecialchars($item['category']); ?>"
                                 data-status="<?php echo $item['status']; ?>"
                                 onclick="<?php echo $is_available ? 'toggleEquipment(this)' : ''; ?>">
                                <div class="equipment-name"><?php echo htmlspecialchars($item['asset_name']); ?></div>
                                <div class="equipment-meta"><?php echo htmlspecialchars($item['category']); ?> • <?php echo htmlspecialchars($item['asset_id']); ?></div>
                                <span class="status-badge <?php echo $status_class; ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $item['status'])); ?>
                                </span>
                                <?php if (!$is_available && $item['current_borrower']): ?>
                                <div class="equipment-meta" style="margin-top: 0.5rem;">
                                    <i class="fas fa-user"></i> <?php echo htmlspecialchars($item['current_borrower']); ?>
                                </div>
                                <?php endif; ?>
                                <?php if ($is_available): ?>
                                <input type="checkbox" name="selected_equipment[]" value="<?php echo htmlspecialchars($item['asset_name'] . ' (' . $item['asset_id'] . ')'); ?>" style="display: none;">
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="selection-summary" id="selectionSummary" style="display: none;">
                            <h4><i class="fas fa-check-circle"></i> Selected Equipment</h4>
                            <div class="selected-items" id="selectedItems"></div>
                        </div>
                    </div>

                    <!-- Purpose -->
                    <div class="form-section" style="border-bottom: none;">
                        <h3 class="section-title"><i class="fas fa-info-circle"></i> Project Details</h3>
                        <label class="form-label">Purpose / Description</label>
                        <textarea name="purpose" class="form-control" rows="3" placeholder="Describe how the equipment will be used..."></textarea>
                    </div>

                    <!-- Submit -->
                    <div class="text-center">
                        <button type="submit" class="btn btn-success btn-lg">
                            <i class="fas fa-calendar-check"></i> Submit Reservation Request
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Equipment selection
        function toggleEquipment(card) {
            if (card.classList.contains('unavailable')) return;

            card.classList.toggle('selected');
            const checkbox = card.querySelector('input[type="checkbox"]');
            if (checkbox) {
                checkbox.checked = card.classList.contains('selected');
            }
            updateSelectionSummary();
        }

        function selectAllAvailable() {
            document.querySelectorAll('.equipment-card:not(.unavailable)').forEach(card => {
                if (card.style.display !== 'none') {
                    card.classList.add('selected');
                    const checkbox = card.querySelector('input[type="checkbox"]');
                    if (checkbox) checkbox.checked = true;
                }
            });
            updateSelectionSummary();
        }

        function clearSelection() {
            document.querySelectorAll('.equipment-card.selected').forEach(card => {
                card.classList.remove('selected');
                const checkbox = card.querySelector('input[type="checkbox"]');
                if (checkbox) checkbox.checked = false;
            });
            updateSelectionSummary();
        }

        function updateSelectionSummary() {
            const selected = document.querySelectorAll('.equipment-card.selected');
            const count = selected.length;
            document.getElementById('selectedCount').textContent = count + ' item(s) selected';

            const summary = document.getElementById('selectionSummary');
            const itemsContainer = document.getElementById('selectedItems');

            if (count > 0) {
                summary.style.display = 'block';
                itemsContainer.innerHTML = Array.from(selected).map(card => {
                    return `<span class="selected-item">${card.dataset.assetName}</span>`;
                }).join('');
            } else {
                summary.style.display = 'none';
            }
        }

        // Search and filter
        document.getElementById('searchEquipment').addEventListener('input', filterEquipment);
        document.getElementById('categoryFilter').addEventListener('change', filterEquipment);

        function filterEquipment() {
            const search = document.getElementById('searchEquipment').value.toLowerCase();
            const category = document.getElementById('categoryFilter').value;

            document.querySelectorAll('.equipment-card').forEach(card => {
                const name = card.dataset.assetName.toLowerCase();
                const id = card.dataset.assetId.toLowerCase();
                const cat = card.dataset.category;

                const matchesSearch = name.includes(search) || id.includes(search);
                const matchesCategory = !category || cat === category;

                card.style.display = (matchesSearch && matchesCategory) ? 'block' : 'none';
            });
        }

        // Form validation
        document.getElementById('reservationForm').addEventListener('submit', function(e) {
            // Check team member selection
            const teamMember = document.querySelector('select[name="requester_name"]').value;
            if (!teamMember) {
                e.preventDefault();
                alert('Please select a team member.');
                return false;
            }

            // Check start and end dates
            const startDate = document.querySelector('input[name="start_date"]').value;
            const endDate = document.querySelector('input[name="end_date"]').value;

            if (!startDate || !endDate) {
                e.preventDefault();
                alert('Please select both start and end dates.');
                return false;
            }

            if (new Date(endDate) < new Date(startDate)) {
                e.preventDefault();
                alert('End date must be on or after the start date.');
                return false;
            }

            // Check equipment selection
            const selected = document.querySelectorAll('.equipment-card.selected');
            if (selected.length === 0) {
                e.preventDefault();
                alert('Please select at least one equipment item.');
                return false;
            }

            // Show loading state
            const submitBtn = e.target.querySelector('button[type="submit"]');
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';
            submitBtn.disabled = true;
        });

        // Set minimum end date based on start date
        document.querySelector('input[name="start_date"]').addEventListener('change', function() {
            const endDateInput = document.querySelector('input[name="end_date"]');
            endDateInput.min = this.value;
            // If end date is before start date, reset it
            if (endDateInput.value && new Date(endDateInput.value) < new Date(this.value)) {
                endDateInput.value = this.value;
            }
        });
    </script>
</body>
</html>
