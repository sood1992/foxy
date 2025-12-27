<?php
// enhanced_checkout.php - Updated checkout with email notifications
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
    $borrower = $_POST['borrower_name'];
    $borrower_email = $_POST['borrower_email']; // New field
    $expected_return = $_POST['expected_return_date'];
    $purpose = $_POST['purpose'];
    
    if ($asset->checkOut($asset_id, $borrower, $expected_return, $purpose)) {
        // Send email notifications
        $notification_sent = $notifications->sendCheckoutNotification(
            $asset_id, 
            $borrower, 
            $borrower_email, 
            $expected_return, 
            $purpose
        );
        
        $success = "Equipment checked out successfully!";
        if ($notification_sent) {
            $success .= " Confirmation email sent.";
        } else {
            $success .= " (Note: Email notification failed to send)";
        }
    } else {
        $error = "Failed to check out equipment.";
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

                <?php if ($asset_info['status'] == 'available'): ?>
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
                <?php else: ?>
                <div class="status-unavailable">
                    <i class="fas fa-exclamation-circle fa-2x mb-3"></i>
                    <h5>Equipment Not Available</h5>
                    <p>This equipment is currently <strong><?php echo $asset_info['status']; ?></strong> and cannot be checked out.</p>
                    <?php if ($asset_info['current_borrower']): ?>
                    <p><strong>Current Borrower:</strong> <?php echo htmlspecialchars($asset_info['current_borrower']); ?></p>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                
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