<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Fix session path issue
if (!session_id()) {
    ini_set('session.save_path', sys_get_temp_dir());
    session_start();
}

require_once 'config/database.php';
require_once 'classes/Asset.php';
require_once 'classes/GearRequest.php';
require_once 'classes/EmailNotification.php';

$database = new Database();
$db = $database->getConnection();
$gear_request = new GearRequest($db);
$asset = new Asset($db);

// Get all registered users for the dropdown
$users_query = "SELECT username, email FROM users WHERE role IN ('admin', 'team_member') ORDER BY username";
$users_stmt = $db->prepare($users_query);
$users_stmt->execute();
$registered_users = $users_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all available assets for the dropdown (only show available ones for public)
$available_assets_query = "SELECT * FROM assets WHERE status = 'available' ORDER BY asset_name";
$available_assets_stmt = $db->prepare($available_assets_query);
$available_assets_stmt->execute();
$available_assets = $available_assets_stmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get selected user's details
    $selected_username = $_POST['requester_name'];
    $selected_user = null;
    
    foreach ($registered_users as $user) {
        if ($user['username'] === $selected_username) {
            $selected_user = $user;
            break;
        }
    }
    
    if (!$selected_user) {
        $error = "Invalid user selected. Please select a registered user.";
    } else {
        // Handle multiple selected assets
        $selected_assets = isset($_POST['required_items']) ? $_POST['required_items'] : [];
        $assets_text = implode(', ', $selected_assets);
        
        $data = [
            'requester_name' => $selected_user['username'],
            'requester_email' => $selected_user['email'],
            'required_items' => $assets_text,
            'request_dates' => $_POST['request_dates'],
            'purpose' => $_POST['purpose']
        ];
        
        if ($gear_request->create($data)) {
            $success = "Gear request submitted successfully for " . htmlspecialchars($selected_user['username']) . "! We'll get back to you soon.";
            // Send notification to admin
            // EmailNotification::sendEmail('rishabh@neofoxmedia.com', 'New Gear Request - ' . $data['requester_name'], "A new gear request has been submitted");
        } else {
            $error = "Failed to submit gear request. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gear Request - Neofox Media</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-yellow: #FFD60A;
            --dark-yellow: #FFC300;
            --black: #000000;
            --dark-gray: #1a1a1a;
            --medium-gray: #333333;
            --light-gray: #666666;
            --white: #ffffff;
            --shadow: rgba(0, 0, 0, 0.1);
            --border-radius: 16px;
            --border-radius-sm: 8px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, var(--primary-yellow) 0%, var(--dark-yellow) 100%);
            color: var(--black);
            line-height: 1.6;
            min-height: 100vh;
            font-weight: 400;
        }

        /* Navigation */
        .navbar {
            background: rgba(0, 0, 0, 0.9);
            backdrop-filter: blur(10px);
            padding: 1rem 0;
            margin-bottom: 2rem;
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
            color: var(--primary-yellow);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .brand-icon {
            width: 32px;
            height: 32px;
            background: var(--primary-yellow);
            color: var(--black);
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
            color: var(--white);
            text-decoration: none;
            font-weight: 500;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            transition: all 0.2s ease;
            font-size: 0.9rem;
        }

        .nav-link:hover {
            background: var(--primary-yellow);
            color: var(--black);
        }

        .admin-access {
            background: var(--primary-yellow);
            color: var(--black) !important;
            font-weight: 600;
        }

        .admin-access:hover {
            background: var(--dark-yellow);
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 1rem 2rem;
        }

        .header {
            text-align: center;
            margin-bottom: 3rem;
        }

        .header h1 {
            font-size: 3.5rem;
            font-weight: 900;
            color: var(--black);
            text-transform: uppercase;
            letter-spacing: -0.02em;
            margin-bottom: 0.5rem;
        }

        .header p {
            font-size: 1.2rem;
            color: var(--medium-gray);
            font-weight: 500;
        }

        .main-card {
            background: var(--white);
            border-radius: var(--border-radius);
            box-shadow: 0 20px 40px var(--shadow);
            overflow: hidden;
            backdrop-filter: blur(10px);
        }

        .card-header {
            background: var(--black);
            color: var(--white);
            padding: 2rem;
            text-align: center;
        }

        .card-header h2 {
            font-size: 1.8rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: -0.01em;
            margin: 0;
        }

        .card-subtitle {
            margin-top: 0.5rem;
            opacity: 0.8;
            font-size: 1rem;
        }

        .card-body {
            padding: 3rem;
        }

        .alert {
            padding: 1rem 1.5rem;
            border-radius: var(--border-radius-sm);
            margin-bottom: 2rem;
            font-weight: 500;
        }

        .alert-success {
            background: rgba(34, 197, 94, 0.1);
            color: #166534;
            border: 1px solid rgba(34, 197, 94, 0.2);
        }

        .alert-danger {
            background: rgba(239, 68, 68, 0.1);
            color: #991b1b;
            border: 1px solid rgba(239, 68, 68, 0.2);
        }

        .form-section {
            margin-bottom: 2.5rem;
        }

        .section-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--black);
            margin-bottom: 1rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            font-weight: 600;
            color: var(--black);
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .form-control {
            width: 100%;
            padding: 1rem 1.5rem;
            border: 2px solid rgba(0, 0, 0, 0.1);
            border-radius: var(--border-radius-sm);
            font-size: 1rem;
            font-family: inherit;
            background: var(--white);
            color: var(--black);
            transition: all 0.3s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--black);
            box-shadow: 0 0 0 3px rgba(0, 0, 0, 0.1);
        }

        .equipment-search-container {
            margin-bottom: 1.5rem;
        }

        .search-wrapper {
            position: relative;
        }

        .search-input {
            padding-left: 3.5rem;
            font-size: 1.1rem;
            height: 60px;
        }

        .search-icon {
            position: absolute;
            left: 1.25rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--light-gray);
            font-size: 1.2rem;
        }

        .equipment-controls {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            margin-bottom: 1.5rem;
            align-items: center;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: var(--border-radius-sm);
            font-weight: 600;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-primary {
            background: var(--black);
            color: var(--white);
        }

        .btn-primary:hover {
            background: var(--medium-gray);
            transform: translateY(-2px);
        }

        .btn-outline {
            background: transparent;
            color: var(--black);
            border: 2px solid var(--black);
        }

        .btn-outline:hover {
            background: var(--black);
            color: var(--white);
            transform: translateY(-2px);
        }

        .btn-lg {
            padding: 1.25rem 2rem;
            font-size: 1rem;
            height: 60px;
        }

        .selected-count {
            background: rgba(0, 0, 0, 0.05);
            color: var(--black);
            padding: 0.75rem 1.5rem;
            border-radius: var(--border-radius-sm);
            font-weight: 600;
            border: 2px solid rgba(0, 0, 0, 0.1);
        }

        .equipment-grid {
            max-height: 400px;
            overflow-y: auto;
            border: 2px solid rgba(0, 0, 0, 0.1);
            border-radius: var(--border-radius);
            background: rgba(255, 255, 255, 0.5);
            backdrop-filter: blur(10px);
        }

        .equipment-item {
            display: flex;
            align-items: center;
            padding: 1.5rem;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .equipment-item:hover {
            background: rgba(255, 214, 10, 0.1);
            transform: translateX(5px);
        }

        .equipment-item:last-child {
            border-bottom: none;
        }

        .equipment-item.hidden {
            display: none;
        }

        .equipment-checkbox {
            width: 20px;
            height: 20px;
            margin-right: 1rem;
            accent-color: var(--black);
            cursor: pointer;
        }

        .equipment-label {
            flex: 1;
            cursor: pointer;
        }

        .equipment-name {
            font-weight: 700;
            color: var(--black);
            font-size: 1.1rem;
            margin-bottom: 0.25rem;
        }

        .equipment-meta {
            color: var(--light-gray);
            font-size: 0.9rem;
            font-weight: 500;
        }

        .equipment-status {
            margin-left: auto;
        }

        .status-badge {
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .status-available {
            background: rgba(34, 197, 94, 0.1);
            color: #166534;
        }

        .status-checked-out {
            background: rgba(249, 115, 22, 0.1);
            color: #ea580c;
        }

        .status-maintenance {
            background: rgba(59, 130, 246, 0.1);
            color: #1d4ed8;
        }

        .status-lost {
            background: rgba(239, 68, 68, 0.1);
            color: #991b1b;
        }

        .no-results {
            text-align: center;
            padding: 3rem;
            color: var(--light-gray);
            display: none;
        }

        .no-results i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        .selection-summary {
            background: rgba(255, 214, 10, 0.1);
            border: 2px solid rgba(255, 214, 10, 0.3);
            border-radius: var(--border-radius);
            padding: 1.5rem;
            margin-top: 1.5rem;
            display: none;
        }

        .selection-summary h4 {
            font-weight: 700;
            color: var(--black);
            margin-bottom: 1rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .selected-items {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .selected-item {
            background: var(--black);
            color: var(--white);
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .form-text {
            color: var(--light-gray);
            font-size: 0.85rem;
            margin-top: 0.5rem;
            font-weight: 500;
        }

        /* Selected User Info */
        .selected-user-info {
            margin-top: 1rem;
            padding: 1.5rem;
            background: rgba(255, 214, 10, 0.1);
            border: 2px solid rgba(255, 214, 10, 0.3);
            border-radius: var(--border-radius);
        }

        .user-card {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .user-avatar {
            width: 50px;
            height: 50px;
            background: var(--black);
            color: var(--primary-yellow);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1.2rem;
        }

        .user-details {
            flex: 1;
        }

        .user-name {
            font-weight: 700;
            color: var(--black);
            font-size: 1.1rem;
            margin-bottom: 0.25rem;
        }

        .user-email {
            color: var(--light-gray);
            font-size: 0.9rem;
        }

        .form-control select option {
            padding: 0.5rem;
        }

        .action-buttons {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-top: 2rem;
        }

        @media (max-width: 768px) {
            .navbar-container {
                padding: 0 1rem;
                flex-direction: column;
                gap: 1rem;
            }

            .card-header {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }

            .container {
                padding: 0 1rem 2rem;
            }

            .header h1 {
                font-size: 2.5rem;
            }

            .card-body {
                padding: 2rem;
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .equipment-controls {
                flex-direction: column;
                align-items: stretch;
            }

            .action-buttons {
                grid-template-columns: 1fr;
            }
        }

        /* Custom scrollbar */
        .equipment-grid::-webkit-scrollbar {
            width: 8px;
        }

        .equipment-grid::-webkit-scrollbar-track {
            background: rgba(0, 0, 0, 0.05);
        }

        .equipment-grid::-webkit-scrollbar-thumb {
            background: rgba(0, 0, 0, 0.2);
            border-radius: 4px;
        }

        .equipment-grid::-webkit-scrollbar-thumb:hover {
            background: rgba(0, 0, 0, 0.3);
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="navbar-container">
            <a href="#" class="navbar-brand">
                <div class="brand-icon">
                    <i class="fas fa-cube"></i>
                </div>
                Neofox Gear
            </a>
            <div class="navbar-nav">
                <a class="nav-link" href="#equipment">Equipment</a>
                <a class="nav-link" href="#about">About</a>
                <a class="nav-link" href="#contact">Contact</a>
                <a class="nav-link admin-access" href="login.php">
                    <i class="fas fa-sign-in-alt"></i> Admin Access
                </a>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="header">
            <h1>Gear Request</h1>
            <p>Request equipment for your project</p>
        </div>

        <div class="main-card">
            <div class="card-header">
                <h2>Equipment Request Form</h2>
                <div class="card-subtitle">Request professional equipment for your project</div>
            </div>
            
            <div class="card-body">
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

                <form method="POST">
                    <!-- User Selection -->
                    <div class="form-section">
                        <h3 class="section-title">Select Team Member</h3>
                        <div class="form-group">
                            <label for="requester_name" class="form-label">Team Member *</label>
                            <select class="form-control" id="requester_name" name="requester_name" required onchange="updateUserEmail()">
                                <option value="">Select a team member...</option>
                                <?php foreach ($registered_users as $user): ?>
                                <option value="<?php echo htmlspecialchars($user['username']); ?>" 
                                        data-email="<?php echo htmlspecialchars($user['email']); ?>"
                                        <?php echo (isset($_POST['requester_name']) && $_POST['requester_name'] === $user['username']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($user['username']); ?>
                                    <?php if ($user['email']): ?>
                                        (<?php echo htmlspecialchars($user['email']); ?>)
                                    <?php endif; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">Select the team member who will be using this equipment</div>
                        </div>
                        
                        <!-- Selected User Info Display -->
                        <div class="selected-user-info" id="selected-user-info" style="display: none;">
                            <div class="user-card">
                                <div class="user-avatar" id="user-avatar"></div>
                                <div class="user-details">
                                    <div class="user-name" id="display-user-name"></div>
                                    <div class="user-email" id="display-user-email"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Equipment Selection -->
                    <div class="form-section">
                        <h3 class="section-title">Equipment Selection</h3>
                        
                        <div class="equipment-search-container">
                            <div class="search-wrapper">
                                <i class="fas fa-search search-icon"></i>
                                <input type="text" class="form-control search-input" id="equipment-search" 
                                       placeholder="Search equipment by name, category, or ID...">
                            </div>
                        </div>
                        
                        <div class="equipment-controls">
                            <button type="button" class="btn btn-outline" id="select-all-visible">
                                <i class="fas fa-check-double"></i> Select All Visible
                            </button>
                            <button type="button" class="btn btn-outline" id="clear-all">
                                <i class="fas fa-times"></i> Clear All
                            </button>
                            <div class="selected-count" id="selected-count">
                                <i class="fas fa-list"></i> 0 Selected
                            </div>
                        </div>
                        
                        <div class="equipment-grid" id="equipment-grid">
                            <?php foreach ($available_assets as $asset_item): ?>
                            <div class="equipment-item" data-search-text="<?php echo strtolower(htmlspecialchars($asset_item['asset_name'] . ' ' . $asset_item['category'] . ' ' . $asset_item['asset_id'])); ?>">
                                <input type="checkbox" name="required_items[]" 
                                       value="<?php echo htmlspecialchars($asset_item['asset_name'] . ' (' . $asset_item['asset_id'] . ')'); ?>" 
                                       id="asset_<?php echo $asset_item['id']; ?>"
                                       class="equipment-checkbox">
                                <label for="asset_<?php echo $asset_item['id']; ?>" class="equipment-label">
                                    <div class="equipment-name"><?php echo htmlspecialchars($asset_item['asset_name']); ?></div>
                                    <div class="equipment-meta"><?php echo htmlspecialchars($asset_item['category'] . ' • ' . $asset_item['asset_id']); ?></div>
                                </label>
                                <div class="equipment-status">
                                    <span class="status-badge status-available">
                                        Available
                                    </span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            
                            <div class="no-results" id="no-results">
                                <i class="fas fa-search"></i>
                                <div>No equipment found matching your search</div>
                            </div>
                        </div>
                        
                        <div class="selection-summary" id="selection-summary">
                            <h4>Selected Equipment</h4>
                            <div class="selected-items" id="selected-items-display"></div>
                        </div>
                        
                        <div class="form-text">Browse available equipment and select what you need for your project. Only currently available items are shown.</div>
                    </div>
                    
                    <!-- Dates -->
                    <div class="form-section">
                        <h3 class="section-title">Request Dates</h3>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="start_date" class="form-label">Start Date *</label>
                                <input type="date" class="form-control" id="start_date" name="start_date" required>
                            </div>
                            <div class="form-group">
                                <label for="end_date" class="form-label">End Date *</label>
                                <input type="date" class="form-control" id="end_date" name="end_date" required>
                            </div>
                        </div>
                        <input type="hidden" id="request_dates" name="request_dates">
                    </div>
                    
                    <!-- Purpose -->
                    <div class="form-section">
                        <h3 class="section-title">Project Details</h3>
                        <div class="form-group">
                            <label for="purpose" class="form-label">Purpose / Project Description</label>
                            <textarea class="form-control" id="purpose" name="purpose" rows="4" 
                                placeholder="Brief description of your project and how the equipment will be used"></textarea>
                        </div>
                    </div>
                    
                    <!-- Action Buttons -->
                    <div class="action-buttons">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="fas fa-paper-plane"></i> Submit Request
                        </button>
                        <button type="button" class="btn btn-outline btn-lg" onclick="window.location.reload()">
                            <i class="fas fa-refresh"></i> Clear Form
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Equipment search and selection functionality
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('equipment-search');
            const equipmentItems = document.querySelectorAll('.equipment-item[data-search-text]');
            const noResults = document.getElementById('no-results');
            const selectAllBtn = document.getElementById('select-all-visible');
            const clearAllBtn = document.getElementById('clear-all');
            const selectedCount = document.getElementById('selected-count');
            const selectionSummary = document.getElementById('selection-summary');
            const selectedItemsDisplay = document.getElementById('selected-items-display');
            const checkboxes = document.querySelectorAll('.equipment-checkbox');
            
            // Search functionality
            searchInput.addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase().trim();
                let visibleCount = 0;
                
                equipmentItems.forEach(function(item) {
                    const searchText = item.getAttribute('data-search-text');
                    if (searchText.includes(searchTerm)) {
                        item.classList.remove('hidden');
                        visibleCount++;
                    } else {
                        item.classList.add('hidden');
                    }
                });
                
                // Show/hide no results message
                noResults.style.display = visibleCount === 0 ? 'block' : 'none';
            });
            
            // Select all visible items
            selectAllBtn.addEventListener('click', function() {
                equipmentItems.forEach(function(item) {
                    if (!item.classList.contains('hidden')) {
                        const checkbox = item.querySelector('.equipment-checkbox');
                        checkbox.checked = true;
                    }
                });
                updateSelectionDisplay();
            });
            
            // Clear all selections
            clearAllBtn.addEventListener('click', function() {
                checkboxes.forEach(function(checkbox) {
                    checkbox.checked = false;
                });
                updateSelectionDisplay();
            });
            
            // Update selection display
            function updateSelectionDisplay() {
                const selectedItems = Array.from(checkboxes).filter(cb => cb.checked);
                const count = selectedItems.length;
                
                selectedCount.innerHTML = `<i class="fas fa-list"></i> ${count} Selected`;
                
                if (count > 0) {
                    selectionSummary.style.display = 'block';
                    selectedItemsDisplay.innerHTML = selectedItems.map(function(checkbox) {
                        const label = document.querySelector(`label[for="${checkbox.id}"]`);
                        const itemName = label.querySelector('.equipment-name').textContent;
                        return `<span class="selected-item">${itemName}</span>`;
                    }).join('');
                } else {
                    selectionSummary.style.display = 'none';
                }
            }
            
            // Listen for checkbox changes
            checkboxes.forEach(function(checkbox) {
                checkbox.addEventListener('change', updateSelectionDisplay);
            });
            
            // Initialize selection display
            updateSelectionDisplay();
        });

        // User selection functionality
        function updateUserEmail() {
            const select = document.getElementById('requester_name');
            const selectedOption = select.options[select.selectedIndex];
            const userInfo = document.getElementById('selected-user-info');
            const userAvatar = document.getElementById('user-avatar');
            const displayName = document.getElementById('display-user-name');
            const displayEmail = document.getElementById('display-user-email');
            
            if (selectedOption.value && selectedOption.value !== '') {
                const userName = selectedOption.value;
                const userEmail = selectedOption.getAttribute('data-email') || 'No email on file';
                
                // Show user info card
                userInfo.style.display = 'block';
                
                // Update user card
                userAvatar.textContent = userName.charAt(0).toUpperCase();
                displayName.textContent = userName;
                displayEmail.textContent = userEmail;
            } else {
                userInfo.style.display = 'none';
            }
        }
        
        // Combine start and end dates into request_dates field
        document.getElementById('start_date').addEventListener('change', updateRequestDates);
        document.getElementById('end_date').addEventListener('change', updateRequestDates);
        
        function updateRequestDates() {
            const startDate = document.getElementById('start_date').value;
            const endDate = document.getElementById('end_date').value;
            
            if (startDate && endDate) {
                const startFormatted = new Date(startDate).toLocaleDateString();
                const endFormatted = new Date(endDate).toLocaleDateString();
                document.getElementById('request_dates').value = startFormatted + ' to ' + endFormatted;
            }
        }
    </script>
</body>
</html>