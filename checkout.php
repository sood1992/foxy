<?php
// Enhanced checkout.php with user authentication
/*
Add this at the top of checkout.php to require login:

// Check if user is logged in (for registered users only)
if (!session_id()) {
    ini_set('session.save_path', sys_get_temp_dir());
    session_start();
}

// Redirect to login if not authenticated
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php?redirect=" . urlencode($_SERVER['REQUEST_URI']));
    exit();
}

// Pre-fill the borrower name with logged-in user
$logged_in_user = $_SESSION['username'];
*/

// Enhanced login.php with redirect capability
/*
Add this to login.php after successful login:

// Check for redirect parameter
$redirect = isset($_GET['redirect']) ? $_GET['redirect'] : 'index.php';
header("Location: " . $redirect);
exit();
*/
?>
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'config/database.php';
require_once 'classes/Asset.php';
require_once 'classes/EmailNotification.php';

$database = new Database();
$db = $database->getConnection();
$asset = new Asset($db);

if (isset($_GET['asset_id'])) {
    $asset_id = $_GET['asset_id'];
    $asset_info = $asset->getByAssetId($asset_id);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $asset_id = $_POST['asset_id'];
    $borrower = $_POST['borrower_name'];
    $expected_return = $_POST['expected_return_date'];
    $purpose = $_POST['purpose'];
    
    if ($asset->checkOut($asset_id, $borrower, $expected_return, $purpose)) {
        $success = "Equipment checked out successfully!";
        // Send email notification
        EmailNotification::sendCheckoutConfirmation('rishabh@neofoxmedia.com', $asset_info['asset_name'], $borrower, $expected_return);
    } else {
        $error = "Failed to check out equipment.";
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
</head>
<body class="bg-light">
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h4><i class="fas fa-sign-out-alt"></i> Check Out Equipment</h4>
                    </div>
                    <div class="card-body">
                        <?php if (isset($success)): ?>
                        <div class="alert alert-success"><?php echo $success; ?></div>
                        <?php endif; ?>
                        
                        <?php if (isset($error)): ?>
                        <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>

                        <?php if (isset($asset_info) && $asset_info): ?>
                        <div class="alert alert-info">
                            <strong>Asset:</strong> <?php echo htmlspecialchars($asset_info['asset_name']); ?><br>
                            <strong>Category:</strong> <?php echo htmlspecialchars($asset_info['category']); ?><br>
                            <strong>Status:</strong> <?php echo ucfirst($asset_info['status']); ?>
                        </div>

                        <?php if ($asset_info['status'] == 'available'): ?>
                        <form method="POST">
                            <input type="hidden" name="asset_id" value="<?php echo $asset_id; ?>">
                            
                            <div class="mb-3">
                                <label for="borrower_name" class="form-label">Your Name *</label>
                                <input type="text" class="form-control" id="borrower_name" name="borrower_name" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="expected_return_date" class="form-label">Expected Return Date *</label>
                                <input type="datetime-local" class="form-control" id="expected_return_date" name="expected_return_date" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="purpose" class="form-label">Purpose / Notes</label>
                                <textarea class="form-control" id="purpose" name="purpose" rows="3"></textarea>
                            </div>
                            
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-check"></i> Check Out Equipment
                            </button>
                        </form>
                        <?php else: ?>
                        <div class="alert alert-warning">
                            This equipment is currently <?php echo $asset_info['status']; ?> and cannot be checked out.
                        </div>
                        <?php endif; ?>
                        
                        <?php else: ?>
                        <div class="alert alert-warning">
                            Asset not found. Please scan a valid QR code.
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>