<?php
// smtp_test.php - Test SMTP Email System
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h1>🚀 SMTP Email Test</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 40px; }
    .success { color: green; font-weight: bold; }
    .error { color: red; font-weight: bold; }
    .info { color: blue; }
    .warning { color: orange; }
    .log { background: #f5f5f5; padding: 15px; border-radius: 5px; margin: 10px 0; font-family: monospace; white-space: pre-wrap; }
    .button { background: #007cba; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block; margin: 10px 5px; }
</style>";

// Test 1: Load the SMTP EmailNotification class
echo "<h2>1. 📧 Loading SMTP EmailNotification Class</h2>";
try {
    require_once 'classes/EmailNotification.php';
    echo "<div class='success'>✅ SMTP EmailNotification class loaded successfully</div>";
    
    if (method_exists('EmailNotification', 'sendEmail')) {
        echo "<div class='success'>✅ sendEmail method exists</div>";
    } else {
        echo "<div class='error'>❌ sendEmail method missing</div>";
    }
    
    if (method_exists('EmailNotification', 'testEmail')) {
        echo "<div class='success'>✅ testEmail method exists</div>";
    } else {
        echo "<div class='error'>❌ testEmail method missing</div>";
    }
    
} catch (Exception $e) {
    echo "<div class='error'>❌ Error loading EmailNotification: " . htmlspecialchars($e->getMessage()) . "</div>";
    exit;
}

// Test 2: SMTP Connection Test
echo "<h2>2. 🔗 SMTP Connection Test</h2>";
echo "<div class='info'>Testing connection to: mail.neofoxmedia.com:465 (SSL)</div>";

$smtp_host = 'mail.neofoxmedia.com';
$smtp_port = 465;

// Test SSL connection
$context = stream_context_create([
    'ssl' => [
        'verify_peer' => false,
        'verify_peer_name' => false,
        'allow_self_signed' => true
    ]
]);

$socket = @stream_socket_client(
    "ssl://{$smtp_host}:{$smtp_port}",
    $errno, $errstr, 10, STREAM_CLIENT_CONNECT, $context
);

if ($socket) {
    echo "<div class='success'>✅ SMTP connection successful</div>";
    $response = fgets($socket, 515);
    echo "<div class='info'>SMTP greeting: " . htmlspecialchars(trim($response)) . "</div>";
    fclose($socket);
} else {
    echo "<div class='error'>❌ SMTP connection failed: {$errno} - {$errstr}</div>";
    echo "<div class='warning'>⚠️ This might indicate firewall issues or incorrect SMTP settings</div>";
}

// Test 3: Send test emails
echo "<h2>3. 📨 Send Test Emails</h2>";

if (isset($_GET['test']) && $_GET['test'] == 'smtp') {
    echo "<h3>Sending SMTP test emails...</h3>";
    
    // Clear previous logs for this test
    error_log("=== SMTP TEST START ===");
    
    $test_emails = ['team@neofoxmedia.com', 'sood1992@gmail.com'];
    $success_count = 0;
    
    foreach ($test_emails as $test_email) {
        echo "<h4>Testing: {$test_email}</h4>";
        
        try {
            $result = EmailNotification::testEmail($test_email);
            
            if ($result) {
                echo "<div class='success'>✅ SMTP test email sent successfully to {$test_email}</div>";
                $success_count++;
            } else {
                echo "<div class='error'>❌ SMTP test email failed to {$test_email}</div>";
            }
            
        } catch (Exception $e) {
            echo "<div class='error'>❌ Exception sending to {$test_email}: " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    }
    
    echo "<div class='info'>📊 Summary: {$success_count}/" . count($test_emails) . " emails sent successfully</div>";
    
    error_log("=== SMTP TEST END ===");
    
} else {
    echo "<a href='?test=smtp' class='button'>🧪 Run SMTP Email Test</a>";
}

// Test 4: Test Gear Request Email
echo "<h2>4. 🎬 Test Gear Request Admin Email</h2>";

if (isset($_GET['test']) && $_GET['test'] == 'request') {
    echo "<h3>Sending gear request test emails...</h3>";
    
    try {
        require_once 'config/database.php';
        require_once 'classes/GearRequest.php';
        
        $database = new Database();
        $db = $database->getConnection();
        
        // Fake request data
        $fake_data = [
            'requester_name' => 'Test User (SMTP)',
            'requester_email' => 'team@neofoxmedia.com',
            'required_items' => 'Sony FX3 Camera (NF001), 50mm Lens (NF002)',
            'request_dates' => date('m/d/Y') . ' to ' . date('m/d/Y', strtotime('+5 days')),
            'purpose' => 'SMTP email system testing'
        ];
        
        // Build admin notification email
        $subject = "🔔 SMTP TEST - New Gear Request - " . $fake_data['requester_name'];
        
        $message = "
        <!DOCTYPE html>
        <html>
        <head><meta charset='UTF-8'></head>
        <body style='font-family: Arial, sans-serif; padding: 20px;'>
            <div style='max-width: 600px; margin: 0 auto; background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.1);'>
                <div style='background: #FFD700; color: #000; padding: 30px 20px; text-align: center;'>
                    <h1 style='margin: 0; font-size: 24px;'>🎬 SMTP TEST - Gear Request</h1>
                    <p style='margin: 10px 0 0 0; opacity: 0.8;'>Neofox Gear Control System</p>
                </div>
                
                <div style='padding: 30px 20px;'>
                    <p><strong>This is an SMTP test for gear request admin notifications.</strong></p>
                    
                    <div style='background: #f8f9fa; border-radius: 8px; padding: 20px; margin-bottom: 20px;'>
                        <div style='margin-bottom: 15px;'>
                            <span style='font-weight: bold; color: #333;'>Requester:</span>
                            <span style='color: #666;'>{$fake_data['requester_name']} ({$fake_data['requester_email']})</span>
                        </div>
                        <div style='margin-bottom: 15px;'>
                            <span style='font-weight: bold; color: #333;'>Dates:</span>
                            <span style='color: #666;'>{$fake_data['request_dates']}</span>
                        </div>
                        <div style='margin-bottom: 15px;'>
                            <span style='font-weight: bold; color: #333;'>Purpose:</span>
                            <span style='color: #666;'>{$fake_data['purpose']}</span>
                        </div>
                    </div>
                    
                    <h3>📦 Requested Equipment:</h3>
                    <div style='background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 8px; padding: 15px; font-family: monospace;'>{$fake_data['required_items']}</div>
                    
                    <div style='text-align: center; margin-top: 30px;'>
                        <p><strong>Sent via SMTP at:</strong> " . date('Y-m-d H:i:s') . "</p>
                    </div>
                </div>
            </div>
        </body>
        </html>";
        
        // Send to admin emails
        $admin_emails = ['team@neofoxmedia.com', 'sood1992@gmail.com'];
        $success_count = 0;
        
        foreach ($admin_emails as $admin_email) {
            if (EmailNotification::sendEmail($admin_email, $subject, $message)) {
                echo "<div class='success'>✅ SMTP gear request email sent to {$admin_email}</div>";
                $success_count++;
            } else {
                echo "<div class='error'>❌ SMTP gear request email failed to {$admin_email}</div>";
            }
        }
        
        echo "<div class='info'>📊 Admin notification summary: {$success_count}/" . count($admin_emails) . " emails sent</div>";
        
    } catch (Exception $e) {
        echo "<div class='error'>❌ Error testing gear request: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
    
} else {
    echo "<a href='?test=request' class='button'>🎬 Test Gear Request Email</a>";
}

// Test 5: Show recent error logs
echo "<h2>5. 📋 Recent Error Logs</h2>";
echo "<div class='info'>Check your server error logs for detailed SMTP communication. Look for entries containing 'SMTP', '✅', or '❌'.</div>";

if (function_exists('error_get_last')) {
    $last_error = error_get_last();
    if ($last_error) {
        echo "<div class='warning'>Last PHP error: " . htmlspecialchars($last_error['message']) . "</div>";
    }
}

// Test 6: Configuration summary
echo "<h2>6. ⚙️ SMTP Configuration Summary</h2>";
echo "<div class='info'>";
echo "<strong>SMTP Host:</strong> mail.neofoxmedia.com<br>";
echo "<strong>SMTP Port:</strong> 465 (SSL)<br>";
echo "<strong>Username:</strong> foxy@neofoxmedia.com<br>";
echo "<strong>From Email:</strong> foxy@neofoxmedia.com<br>";
echo "<strong>Admin Emails:</strong> team@neofoxmedia.com, sood1992@gmail.com<br>";
echo "</div>";

echo "<h2>7. 🎯 Next Steps</h2>";
echo "<div class='info'>";
echo "<ol>";
echo "<li><strong>If SMTP connection fails:</strong> Check with your hosting provider about port 465 access</li>";
echo "<li><strong>If authentication fails:</strong> Verify the email password is correct</li>";
echo "<li><strong>If emails don't arrive:</strong> Check spam folders and email server logs</li>";
echo "<li><strong>If tests succeed:</strong> Replace your current EmailNotification.php with the SMTP version</li>";
echo "</ol>";
echo "</div>";

echo "<div style='margin-top: 40px; padding: 20px; background: #f0f0f0; border-radius: 10px;'>";
echo "<h3>📞 Support Information</h3>";
echo "<p><strong>Current time:</strong> " . date('Y-m-d H:i:s') . "</p>";
echo "<p><strong>Server:</strong> " . $_SERVER['SERVER_NAME'] . "</p>";
echo "<p><strong>PHP Version:</strong> " . phpversion() . "</p>";
echo "</div>";
?>