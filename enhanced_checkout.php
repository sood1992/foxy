<?php
// enhanced_checkout.php - Updated checkout with email notifications and multi-item support
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'config/database.php';
require_once 'classes/Asset.php';
require_once 'classes/NotificationSystem.php';

$database = new Database();
$db = $database->getConnection();
$asset = new Asset($db);
$notifications = new NotificationSystem($db);

// Get single asset if specified in URL
$single_asset_mode = false;
if (isset($_GET['asset_id'])) {
    $asset_id = $_GET['asset_id'];
    $asset_info = $asset->getByAssetId($asset_id);
    $single_asset_mode = true;
}

// Get all available assets for multi-select mode
$available_assets = $asset->search('', null, 'available');

// Get categories for filtering
$categories_query = "SELECT DISTINCT category FROM assets ORDER BY category";
$categories_stmt = $db->prepare($categories_query);
$categories_stmt->execute();
$categories = $categories_stmt->fetchAll(PDO::FETCH_COLUMN);

$success = '';
$error = '';
$checkout_results = [];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $borrower = $_POST['borrower_name'];
    $borrower_email = $_POST['borrower_email'];
    $expected_return = $_POST['expected_return_date'];
    $purpose = $_POST['purpose'];

    // Handle multiple asset IDs
    $asset_ids = isset($_POST['asset_ids']) ? $_POST['asset_ids'] : [];

    // Also handle single asset_id for backwards compatibility
    if (isset($_POST['asset_id']) && !empty($_POST['asset_id'])) {
        $asset_ids[] = $_POST['asset_id'];
    }

    $asset_ids = array_unique(array_filter($asset_ids));

    if (empty($asset_ids)) {
        $error = "Please select at least one equipment item to check out.";
    } else {
        $success_count = 0;
        $fail_count = 0;
        $checked_out_items = [];

        try {
            $db->beginTransaction();

            foreach ($asset_ids as $current_asset_id) {
                if ($asset->checkOut($current_asset_id, $borrower, $expected_return, $purpose, false)) {
                    $asset_info = $asset->getByAssetId($current_asset_id);
                    $checked_out_items[] = [
                        'asset_id' => $current_asset_id,
                        'asset_name' => $asset_info['asset_name'],
                        'category' => $asset_info['category'],
                        'success' => true
                    ];
                    $success_count++;
                } else {
                    $checkout_results[] = [
                        'asset_id' => $current_asset_id,
                        'success' => false,
                        'message' => 'Checkout failed'
                    ];
                    $fail_count++;
                }
            }

            if ($success_count > 0) {
                $db->commit();

                // Send email notification for all items
                if (!empty($borrower_email)) {
                    $notification_sent = $notifications->sendCheckoutNotification(
                        implode(', ', array_column($checked_out_items, 'asset_id')),
                        $borrower,
                        $borrower_email,
                        $expected_return,
                        $purpose
                    );
                }

                $success = "Successfully checked out {$success_count} item(s)!";
                if ($fail_count > 0) {
                    $success .= " ({$fail_count} item(s) failed)";
                }
                if (isset($notification_sent) && $notification_sent) {
                    $success .= " Confirmation email sent.";
                }

                $checkout_results = $checked_out_items;
            } else {
                $db->rollBack();
                $error = "Failed to check out any equipment.";
            }

        } catch (Exception $e) {
            $db->rollBack();
            $error = "Error processing checkout: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Check Out Equipment - Neofox Gear Control</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --neofox-yellow: #FFD700;
            --black-primary: #000000;
            --white-primary: #FFFFFF;
            --green-accent: #4CAF50;
            --red-accent: #FF5722;
        }

        body {
            background: var(--neofox-yellow);
            font-family: 'Inter', sans-serif;
        }

        .checkout-container {
            max-width: 600px;
            margin: 2rem auto;
            padding: 2rem;
        }

        .card {
            border: 3px solid var(--black-primary);
            border-radius: 20px;
            box-shadow: 8px 8px 0px var(--black-primary);
            overflow: hidden;
        }

        .card-header {
            background: var(--black-primary);
            color: var(--neofox-yellow);
            padding: 2rem;
            text-align: center;
        }

        .card-header h4 {
            margin: 0;
            font-weight: 900;
            font-size: 1.5rem;
        }

        .card-body {
            background: var(--white-primary);
            padding: 2rem;
        }

        .asset-info-card {
            background: #FFF9C4;
            border: 2px solid var(--black-primary);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .asset-title {
            font-size: 1.3rem;
            font-weight: 800;
            color: var(--black-primary);
            margin-bottom: 1rem;
        }

        .asset-detail {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem 0;
            border-bottom: 1px solid #ddd;
        }

        .asset-detail:last-child {
            border-bottom: none;
        }

        .detail-label {
            font-weight: 600;
            color: #333;
        }

        .detail-value {
            color: var(--black-primary);
            font-weight: 500;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            font-weight: 700;
            color: var(--black-primary);
            margin-bottom: 0.5rem;
            display: block;
        }

        .form-control {
            border: 2px solid var(--black-primary);
            border-radius: 8px;
            padding: 0.75rem;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: var(--neofox-yellow);
            box-shadow: 0 0 0 3px rgba(255, 215, 0, 0.2);
            outline: none;
        }

        .btn-checkout {
            background: var(--green-accent);
            color: var(--white-primary);
            border: 3px solid var(--black-primary);
            border-radius: 12px;
            padding: 1rem 2rem;
            font-weight: 700;
            font-size: 1.1rem;
            width: 100%;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .btn-checkout:hover {
            transform: translateY(-2px);
            box-shadow: 4px 4px 0px var(--black-primary);
            color: var(--white-primary);
        }

        .alert {
            border: 2px solid;
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 1.5rem;
            font-weight: 600;
        }

        .alert-success {
            background: #E8F5E8;
            border-color: var(--green-accent);
            color: #2E7D32;
        }

        .alert-danger {
            background: #FFEBEE;
            border-color: var(--red-accent);
            color: #C62828;
        }

        .alert-warning {
            background: #FFF3E0;
            border-color: #FF9800;
            color: #E65100;
        }

        .status-unavailable {
            background: #FFEBEE;
            border-color: var(--red-accent);
            color: #C62828;
            text-align: center;
            padding: 2rem;
            border-radius: 12px;
        }

        .email-info {
            background: #E3F2FD;
            border: 1px solid #2196F3;
            border-radius: 8px;
            padding: 1rem;
            margin-top: 0.5rem;
            font-size: 0.9rem;
            color: #1976D2;
        }

        .required-field {
            color: var(--red-accent);
        }
    </style>
</head>
<body>
    <div class="checkout-container">
        <div class="card">
            <div class="card-header">
                <h4><i class="fas fa-sign-out-alt"></i> Check Out Equipment</h4>
            </div>
            <div class="card-body">
                <?php if (!empty($success)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($error)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
                </div>
                <?php endif; ?>

                <?php if (isset($asset_info) && $asset_info): ?>
                <div class="asset-info-card">
                    <div class="asset-title">
                        <i class="fas fa-box"></i> <?php echo htmlspecialchars($asset_info['asset_name']); ?>
                    </div>
                    <div class="asset-detail">
                        <span class="detail-label">Asset ID:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($asset_info['asset_id']); ?></span>
                    </div>
                    <div class="asset-detail">
                        <span class="detail-label">Category:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($asset_info['category']); ?></span>
                    </div>
                    <div class="asset-detail">
                        <span class="detail-label">Status:</span>
                        <span class="detail-value">
                            <?php
                            $status_colors = [
                                'available' => 'success',
                                'checked_out' => 'warning',
                                'maintenance' => 'info',
                                'lost' => 'danger'
                            ];
                            $status_color = $status_colors[$asset_info['status']] ?? 'secondary';
                            ?>
                            <span class="badge bg-<?php echo $status_color; ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $asset_info['status'])); ?>
                            </span>
                        </span>
                    </div>
                    <div class="asset-detail">
                        <span class="detail-label">Condition:</span>
                        <span class="detail-value"><?php echo ucfirst(str_replace('_', ' ', $asset_info['condition_status'])); ?></span>
                    </div>
                </div>

                <?php if ($single_asset_mode && $asset_info['status'] == 'available'): ?>
                <!-- Single Asset Mode -->
                <form method="POST" id="checkoutForm">
                    <input type="hidden" name="asset_id" value="<?php echo htmlspecialchars($asset_id); ?>">

                    <div class="form-group">
                        <label for="borrower_name" class="form-label">
                            Your Name <span class="required-field">*</span>
                        </label>
                        <input type="text" class="form-control" id="borrower_name" name="borrower_name" required>
                    </div>

                    <div class="form-group">
                        <label for="borrower_email" class="form-label">
                            Your Email Address <span class="required-field">*</span>
                        </label>
                        <input type="email" class="form-control" id="borrower_email" name="borrower_email" required>
                        <div class="email-info">
                            <i class="fas fa-info-circle"></i>
                            We'll send you checkout confirmation and return reminders at this email address.
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="expected_return_date" class="form-label">
                            Expected Return Date & Time <span class="required-field">*</span>
                        </label>
                        <input type="datetime-local" class="form-control" id="expected_return_date" name="expected_return_date" required>
                    </div>

                    <div class="form-group">
                        <label for="purpose" class="form-label">Purpose / Project Details</label>
                        <textarea class="form-control" id="purpose" name="purpose" rows="3"
                            placeholder="Brief description of how you'll use this equipment..."></textarea>
                    </div>

                    <button type="submit" class="btn-checkout">
                        <i class="fas fa-check"></i> Check Out Equipment
                    </button>
                </form>
                <?php elseif ($single_asset_mode): ?>
                <div class="status-unavailable">
                    <i class="fas fa-exclamation-circle fa-2x mb-3"></i>
                    <h5>Equipment Not Available</h5>
                    <p>This equipment is currently <strong><?php echo $asset_info['status']; ?></strong> and cannot be checked out.</p>
                    <?php if ($asset_info['current_borrower']): ?>
                    <p><strong>Current Borrower:</strong> <?php echo htmlspecialchars($asset_info['current_borrower']); ?></p>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <?php elseif (!$single_asset_mode): ?>
                <!-- Multi-Asset Selection Mode -->
                <?php if (!empty($checkout_results)): ?>
                <div class="checkout-results" style="margin-bottom: 2rem;">
                    <h5 style="font-weight: 700; margin-bottom: 1rem;">Checked Out Items:</h5>
                    <div class="equipment-list" style="background: #E8F5E8; border: 2px solid #4CAF50; border-radius: 12px; padding: 1rem;">
                        <?php foreach ($checkout_results as $item): ?>
                        <div style="padding: 0.5rem 0; border-bottom: 1px solid #ddd;">
                            <i class="fas fa-check-circle" style="color: #4CAF50;"></i>
                            <strong><?php echo htmlspecialchars($item['asset_name']); ?></strong>
                            <span style="color: #666;">(<?php echo htmlspecialchars($item['asset_id']); ?>)</span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <form method="POST" id="checkoutForm">
                    <div class="form-group">
                        <label for="borrower_name" class="form-label">
                            Your Name <span class="required-field">*</span>
                        </label>
                        <input type="text" class="form-control" id="borrower_name" name="borrower_name" required>
                    </div>

                    <div class="form-group">
                        <label for="borrower_email" class="form-label">
                            Your Email Address <span class="required-field">*</span>
                        </label>
                        <input type="email" class="form-control" id="borrower_email" name="borrower_email" required>
                        <div class="email-info">
                            <i class="fas fa-info-circle"></i>
                            We'll send you checkout confirmation and return reminders at this email address.
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="expected_return_date" class="form-label">
                            Expected Return Date & Time <span class="required-field">*</span>
                        </label>
                        <input type="datetime-local" class="form-control" id="expected_return_date" name="expected_return_date" required>
                    </div>

                    <div class="form-group">
                        <label for="purpose" class="form-label">Purpose / Project Details</label>
                        <textarea class="form-control" id="purpose" name="purpose" rows="3"
                            placeholder="Brief description of how you'll use this equipment..."></textarea>
                    </div>

                    <!-- Equipment Selection Section -->
                    <div class="equipment-selection" style="margin-top: 2rem; padding-top: 1rem; border-top: 2px solid #ddd;">
                        <label class="form-label" style="font-size: 1.1rem; margin-bottom: 1rem;">
                            <i class="fas fa-boxes"></i> Select Equipment <span class="required-field">*</span>
                        </label>

                        <div class="search-filter" style="margin-bottom: 1rem;">
                            <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                                <input type="text" class="form-control" id="equipmentSearch" placeholder="Search equipment..." style="flex: 1; min-width: 200px;">
                                <select class="form-control" id="categoryFilter" style="width: auto; min-width: 150px;">
                                    <option value="">All Categories</option>
                                    <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo htmlspecialchars($category); ?>"><?php echo htmlspecialchars($category); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="selection-controls" style="margin-bottom: 1rem; display: flex; gap: 1rem; align-items: center;">
                            <button type="button" class="btn btn-sm" onclick="selectAllVisible()" style="background: #f0f0f0; border: 1px solid #ddd; padding: 0.5rem 1rem; border-radius: 8px;">
                                <i class="fas fa-check-double"></i> Select All
                            </button>
                            <button type="button" class="btn btn-sm" onclick="clearSelection()" style="background: #f0f0f0; border: 1px solid #ddd; padding: 0.5rem 1rem; border-radius: 8px;">
                                <i class="fas fa-times"></i> Clear
                            </button>
                            <span id="selectedCount" style="font-weight: 600; color: #333;">0 items selected</span>
                        </div>

                        <div class="equipment-list-container" style="max-height: 400px; overflow-y: auto; border: 2px solid #ddd; border-radius: 12px; background: #fafafa;">
                            <?php if (empty($available_assets)): ?>
                            <div style="padding: 2rem; text-align: center; color: #666;">
                                <i class="fas fa-inbox" style="font-size: 2rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                                <p>No available equipment at the moment.</p>
                            </div>
                            <?php else: ?>
                            <?php foreach ($available_assets as $equip): ?>
                            <label class="equipment-item" data-name="<?php echo strtolower($equip['asset_name']); ?>" data-category="<?php echo htmlspecialchars($equip['category']); ?>" data-id="<?php echo strtolower($equip['asset_id']); ?>" style="display: flex; align-items: center; padding: 1rem; border-bottom: 1px solid #eee; cursor: pointer; transition: background 0.2s;">
                                <input type="checkbox" name="asset_ids[]" value="<?php echo htmlspecialchars($equip['asset_id']); ?>" class="equipment-checkbox" style="width: 20px; height: 20px; margin-right: 1rem;">
                                <div style="flex: 1;">
                                    <div style="font-weight: 600; color: #000;"><?php echo htmlspecialchars($equip['asset_name']); ?></div>
                                    <div style="font-size: 0.85rem; color: #666;"><?php echo htmlspecialchars($equip['category']); ?> • <?php echo htmlspecialchars($equip['asset_id']); ?></div>
                                </div>
                                <span style="background: #E8F5E8; color: #4CAF50; padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.75rem; font-weight: 600;">Available</span>
                            </label>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </div>

                        <!-- Selected Items Summary -->
                        <div id="selectedSummary" style="margin-top: 1rem; padding: 1rem; background: #FFF9C4; border: 2px solid #FFD700; border-radius: 12px; display: none;">
                            <strong>Selected Equipment:</strong>
                            <div id="selectedItemsList" style="margin-top: 0.5rem;"></div>
                        </div>
                    </div>

                    <button type="submit" class="btn-checkout" style="margin-top: 2rem;">
                        <i class="fas fa-check"></i> Check Out Selected Equipment
                    </button>
                </form>

                <script>
                    // Equipment search and filter
                    document.getElementById('equipmentSearch').addEventListener('input', filterEquipment);
                    document.getElementById('categoryFilter').addEventListener('change', filterEquipment);

                    function filterEquipment() {
                        const searchTerm = document.getElementById('equipmentSearch').value.toLowerCase();
                        const category = document.getElementById('categoryFilter').value;
                        const items = document.querySelectorAll('.equipment-item');

                        items.forEach(item => {
                            const name = item.dataset.name;
                            const itemCategory = item.dataset.category;
                            const id = item.dataset.id;

                            const matchesSearch = name.includes(searchTerm) || id.includes(searchTerm);
                            const matchesCategory = !category || itemCategory === category;

                            item.style.display = (matchesSearch && matchesCategory) ? 'flex' : 'none';
                        });
                    }

                    // Selection management
                    document.querySelectorAll('.equipment-checkbox').forEach(cb => {
                        cb.addEventListener('change', updateSelectionSummary);
                    });

                    function updateSelectionSummary() {
                        const checked = document.querySelectorAll('.equipment-checkbox:checked');
                        const count = checked.length;
                        document.getElementById('selectedCount').textContent = count + ' item(s) selected';

                        const summary = document.getElementById('selectedSummary');
                        const list = document.getElementById('selectedItemsList');

                        if (count > 0) {
                            summary.style.display = 'block';
                            list.innerHTML = Array.from(checked).map(cb => {
                                const label = cb.closest('.equipment-item');
                                const name = label.querySelector('div > div:first-child').textContent;
                                return '<span style="display: inline-block; background: #000; color: #FFD700; padding: 0.25rem 0.75rem; border-radius: 20px; margin: 0.25rem; font-size: 0.85rem;">' + name + '</span>';
                            }).join('');
                        } else {
                            summary.style.display = 'none';
                        }
                    }

                    function selectAllVisible() {
                        document.querySelectorAll('.equipment-item').forEach(item => {
                            if (item.style.display !== 'none') {
                                item.querySelector('.equipment-checkbox').checked = true;
                            }
                        });
                        updateSelectionSummary();
                    }

                    function clearSelection() {
                        document.querySelectorAll('.equipment-checkbox').forEach(cb => cb.checked = false);
                        updateSelectionSummary();
                    }

                    // Hover effect
                    document.querySelectorAll('.equipment-item').forEach(item => {
                        item.addEventListener('mouseenter', () => item.style.background = '#FFF9C4');
                        item.addEventListener('mouseleave', () => item.style.background = '');
                    });
                </script>
                <?php else: ?>
                <div class="alert alert-warning">
                    <i class="fas fa-search"></i> Asset not found. Please scan a valid QR code or check the asset ID.
                </div>
                <?php endif; ?>

                <div class="text-center mt-3">
                    <a href="index.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Set default return date to tomorrow at 5 PM
        document.addEventListener('DOMContentLoaded', function() {
            const returnDateInput = document.getElementById('expected_return_date');
            if (returnDateInput && !returnDateInput.value) {
                const tomorrow = new Date();
                tomorrow.setDate(tomorrow.getDate() + 1);
                tomorrow.setHours(17, 0, 0, 0);
                returnDateInput.value = tomorrow.toISOString().slice(0, 16);
            }
        });

        // Form validation
        document.getElementById('checkoutForm')?.addEventListener('submit', function(e) {
            const returnDate = new Date(document.getElementById('expected_return_date').value);
            const now = new Date();
            
            if (returnDate <= now) {
                e.preventDefault();
                alert('Expected return date must be in the future.');
                return false;
            }
            
            // Show loading state
            const submitBtn = e.target.querySelector('button[type="submit"]');
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
            submitBtn.disabled = true;
        });
    </script>
</body>
</html>

<?php
// enhanced_checkin.php - Updated checkin with email notifications
/*
<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'config/database.php';
require_once 'classes/Asset.php';
require_once 'classes/NotificationSystem.php';

$database = new Database();
$db = $database->getConnection();
$asset = new Asset($db);
$notifications = new NotificationSystem($db);

if (isset($_GET['asset_id'])) {
    $asset_id = $_GET['asset_id'];
    $asset_info = $asset->getByAssetId($asset_id);
}

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $asset_id = $_POST['asset_id'];
    $condition = $_POST['condition'];
    $notes = $_POST['notes'];
    $returner_email = $_POST['returner_email']; // New field
    
    // Get borrower info before check-in
    $current_borrower = $asset_info['current_borrower'];
    
    if ($asset->checkIn($asset_id, $condition, $notes)) {
        // Send email notifications
        $notification_sent = $notifications->sendCheckinNotification(
            $asset_id, 
            $current_borrower, 
            $returner_email, 
            $condition, 
            $notes
        );
        
        $success = "Equipment checked in successfully!";
        if ($notification_sent) {
            $success .= " Confirmation email sent.";
        } else {
            $success .= " (Note: Email notification failed to send)";
        }
    } else {
        $error = "Failed to check in equipment.";
        $error_details = $asset->getErrors();
        if (!empty($error_details)) {
            $error .= " " . implode(", ", $error_details);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Check In Equipment - Neofox Gear Control</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --neofox-yellow: #FFD700;
            --black-primary: #000000;
            --white-primary: #FFFFFF;
            --green-accent: #4CAF50;
            --red-accent: #FF5722;
        }

        body {
            background: var(--green-accent);
            font-family: 'Inter', sans-serif;
        }

        .checkin-container {
            max-width: 600px;
            margin: 2rem auto;
            padding: 2rem;
        }

        .card {
            border: 3px solid var(--black-primary);
            border-radius: 20px;
            box-shadow: 8px 8px 0px var(--black-primary);
            overflow: hidden;
        }

        .card-header {
            background: var(--black-primary);
            color: var(--green-accent);
            padding: 2rem;
            text-align: center;
        }

        .btn-checkin {
            background: var(--green-accent);
            color: var(--white-primary);
            border: 3px solid var(--black-primary);
            border-radius: 12px;
            padding: 1rem 2rem;
            font-weight: 700;
            font-size: 1.1rem;
            width: 100%;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Rest of the styles similar to checkout but with green theme */
    </style>
</head>
<body>
    <!-- Similar structure to checkout but for check-in -->
    <!-- Include email field for return confirmation -->
</body>
</html>
?>
*/

// cron_notifications.php - Scheduled notification sender
/*
<?php
// This file should be run via cron job daily
require_once 'config/database.php';
require_once 'classes/NotificationSystem.php';
require_once 'classes/MaintenanceTracker.php';

$database = new Database();
$db = $database->getConnection();
$notifications = new NotificationSystem($db);
$maintenance = new MaintenanceTracker($db);

echo "Starting daily notification check...\n";

// Send overdue notifications
$overdue_sent = $notifications->sendOverdueNotifications();
echo "Sent {$overdue_sent} overdue notifications\n";

// Send maintenance reminders
$maintenance_sent = $notifications->sendMaintenanceNotifications();
echo "Sent {$maintenance_sent} maintenance reminders\n";

echo "Daily notification check completed.\n";

// Log the cron run
$log_entry = date('Y-m-d H:i:s') . " - Sent {$overdue_sent} overdue, {$maintenance_sent} maintenance notifications\n";
file_put_contents('logs/notification_cron.log', $log_entry, FILE_APPEND | LOCK_EX);
?>

# Add this to your crontab to run daily at 9 AM:
# 0 9 * * * php /path/to/your/project/cron_notifications.php

*/
?>