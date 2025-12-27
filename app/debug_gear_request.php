<?php
// debug_gear_request.php - Debug version of gear request form
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Fix session path issue
if (!session_id()) {
    ini_set('session.save_path', sys_get_temp_dir());
    session_start();
}

echo "<h1>🔍 Debug Gear Request Form</h1>";
echo "<style>body{font-family:Arial,sans-serif;margin:40px;} .success{color:green;} .error{color:red;} .info{color:blue;} .debug{background:#f0f0f0;padding:15px;margin:10px 0;border-radius:5px;}</style>";

require_once 'config/database.php';
require_once 'classes/Asset.php';
require_once 'classes/GearRequest.php';
require_once 'classes/EmailNotification.php';

echo "<div class='info'>📋 Required classes loaded successfully</div>";

$database = new Database();
$db = $database->getConnection();
$gear_request = new GearRequest($db);
$asset = new Asset($db);

echo "<div class='info'>🔌 Database connection established</div>";

// Get all registered users for the dropdown
$users_query = "SELECT username, email FROM users WHERE role IN ('admin', 'team_member') ORDER BY username";
$users_stmt = $db->prepare($users_query);
$users_stmt->execute();
$registered_users = $users_stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<div class='info'>👥 Found " . count($registered_users) . " registered users</div>";

// Get all available assets for the dropdown
$available_assets_query = "SELECT * FROM assets WHERE status = 'available' ORDER BY asset_name";
$available_assets_stmt = $db->prepare($available_assets_query);
$available_assets_stmt->execute();
$available_assets = $available_assets_stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<div class='info'>📦 Found " . count($available_assets) . " available assets</div>";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    echo "<div class='debug'>";
    echo "<h3>🔍 Processing Form Submission</h3>";
    echo "<strong>POST Data:</strong><br>";
    echo "<pre>" . htmlspecialchars(print_r($_POST, true)) . "</pre>";
    echo "</div>";
    
    // Get selected user's details
    $selected_username = $_POST['requester_name'];
    $selected_user = null;
    
    echo "<div class='debug'>🔍 Looking for user: {$selected_username}</div>";
    
    foreach ($registered_users as $user) {
        if ($user['username'] === $selected_username) {
            $selected_user = $user;
            break;
        }
    }
    
    if (!$selected_user) {
        $error = "Invalid user selected. Please select a registered user.";
        echo "<div class='error'>❌ {$error}</div>";
    } else {
        echo "<div class='debug'>✅ Found user: {$selected_user['username']} ({$selected_user['email']})</div>";
        
        // Handle multiple selected assets
        $selected_assets = isset($_POST['required_items']) ? $_POST['required_items'] : [];
        $assets_text = implode(', ', $selected_assets);
        
        echo "<div class='debug'>📦 Selected assets: {$assets_text}</div>";
        
        $data = [
            'requester_name' => $selected_user['username'],
            'requester_email' => $selected_user['email'],
            'required_items' => $assets_text,
            'request_dates' => $_POST['request_dates'],
            'purpose' => $_POST['purpose']
        ];
        
        echo "<div class='debug'>";
        echo "<h3>📋 Final Request Data:</h3>";
        echo "<pre>" . htmlspecialchars(print_r($data, true)) . "</pre>";
        echo "</div>";
        
        echo "<div class='debug'>💾 Attempting to create gear request...</div>";
        
        // Clear error log for this test
        error_log("=== DEBUG GEAR REQUEST START ===");
        
        if ($gear_request->create($data)) {
            $success = "Gear request submitted successfully for " . htmlspecialchars($selected_user['username']) . "!";
            echo "<div class='success'>✅ {$success}</div>";
            
            echo "<div class='debug'>🎉 Request created successfully in database</div>";
            echo "<div class='debug'>📧 Check your server error logs for email sending details</div>";
            echo "<div class='debug'>🔍 Look for entries containing 'admin notification', 'SMTP', or 'EmailNotification'</div>";
            
        } else {
            $errors = $gear_request->getErrors();
            $error = "Failed to submit gear request. Errors: " . implode(", ", $errors);
            echo "<div class='error'>❌ {$error}</div>";
        }
        
        error_log("=== DEBUG GEAR REQUEST END ===");
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Debug Gear Request</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; }
        .form-container { background: #f9f9f9; padding: 20px; border-radius: 10px; margin-top: 20px; }
        .form-group { margin-bottom: 15px; }
        .form-label { display: block; font-weight: bold; margin-bottom: 5px; }
        .form-control { width: 100%; max-width: 400px; padding: 8px; border: 1px solid #ccc; border-radius: 4px; }
        .btn { padding: 10px 20px; background: #007cba; color: white; border: none; border-radius: 5px; cursor: pointer; }
        .btn:hover { background: #005a8b; }
    </style>
</head>
<body>

<div class="form-container">
    <h2>🧪 Debug Gear Request Form</h2>
    <p>This form will show detailed debug information about the request submission process.</p>
    
    <form method="POST">
        <div class="form-group">
            <label for="requester_name" class="form-label">Team Member *</label>
            <select class="form-control" id="requester_name" name="requester_name" required>
                <option value="">Select a team member...</option>
                <?php foreach ($registered_users as $user): ?>
                <option value="<?php echo htmlspecialchars($user['username']); ?>">
                    <?php echo htmlspecialchars($user['username']); ?>
                    <?php if ($user['email']): ?>
                        (<?php echo htmlspecialchars($user['email']); ?>)
                    <?php endif; ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="form-group">
            <label class="form-label">Equipment Selection *</label>
            <?php foreach ($available_assets as $asset_item): ?>
            <div style="margin: 5px 0;">
                <input type="checkbox" name="required_items[]" 
                       value="<?php echo htmlspecialchars($asset_item['asset_name'] . ' (' . $asset_item['asset_id'] . ')'); ?>" 
                       id="asset_<?php echo $asset_item['id']; ?>">
                <label for="asset_<?php echo $asset_item['id']; ?>">
                    <?php echo htmlspecialchars($asset_item['asset_name'] . ' (' . $asset_item['asset_id'] . ')'); ?>
                </label>
            </div>
            <?php endforeach; ?>
        </div>
        
        <div class="form-group">
            <label for="request_dates" class="form-label">Request Dates *</label>
            <input type="text" class="form-control" id="request_dates" name="request_dates" 
                   value="<?php echo date('m/d/Y') . ' to ' . date('m/d/Y', strtotime('+3 days')); ?>" required>
        </div>
        
        <div class="form-group">
            <label for="purpose" class="form-label">Purpose</label>
            <textarea class="form-control" id="purpose" name="purpose" rows="3" 
                placeholder="Debug test for email system"></textarea>
        </div>
        
        <button type="submit" class="btn">🧪 Submit Debug Request</button>
    </form>
</div>

<div style="margin-top: 30px; padding: 20px; background: #e8f4f8; border-radius: 10px;">
    <h3>🔍 Debug Instructions</h3>
    <ol>
        <li>Fill out the form above and submit it</li>
        <li>Watch the debug output to see what happens</li>
        <li>Check your server error logs for detailed SMTP information</li>
        <li>Look for entries containing "admin notification" or "SMTP"</li>
        <li>If emails are sent successfully but not received, check spam folders</li>
    </ol>
    
    <h4>📋 What to look for in error logs:</h4>
    <ul>
        <li><code>✅ Gear request created successfully, attempting to send admin notification</code></li>
        <li><code>📧 Attempting to send admin notification for gear request</code></li>
        <li><code>📧 Sending admin notification to: team@neofoxmedia.com</code></li>
        <li><code>✅ SMTP email sent successfully</code></li>
    </ul>
</div>

</body>
</html>