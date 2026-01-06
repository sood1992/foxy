<?php
// checkin.php - Public Check-in Form
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
    $condition = $_POST['condition'];
    $notes = $_POST['notes'];
    
    if ($asset->checkIn($asset_id, $condition, $notes)) {
        $success = "Equipment checked in successfully!";
    } else {
        $error = "Failed to check in equipment.";
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
</head>
<body class="bg-light">
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h4><i class="fas fa-sign-in-alt"></i> Check In Equipment</h4>
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
                            <strong>Current Borrower:</strong> <?php echo htmlspecialchars($asset_info['current_borrower'] ?? 'None'); ?>
                        </div>

                        <?php if ($asset_info['status'] == 'checked_out'): ?>
                        <form method="POST">
                            <input type="hidden" name="asset_id" value="<?php echo $asset_id; ?>">
                            
                            <div class="mb-3">
                                <label for="condition" class="form-label">Equipment Condition *</label>
                                <select class="form-control" id="condition" name="condition" required>
                                    <option value="">Select condition...</option>
                                    <option value="excellent">Excellent</option>
                                    <option value="good">Good</option>
                                    <option value="needs_repair">Needs Repair</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="notes" class="form-label">Return Notes</label>
                                <textarea class="form-control" id="notes" name="notes" rows="3" 
                                    placeholder="Any issues, damage, or observations..."></textarea>
                            </div>
                            
                            <button type="submit" class="btn btn-success w-100">
                                <i class="fas fa-check"></i> Check In Equipment
                            </button>
                        </form>
                        <?php else: ?>
                        <div class="alert alert-warning">
                            This equipment is not currently checked out.
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