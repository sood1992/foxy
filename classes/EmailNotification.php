<?php
// classes/EmailNotification.php - Complete SMTP Version
class EmailNotification {
    private static $from_email = 'your_email@example.com';
    private static $from_name = 'Neofox Gear Control';
    private static $admin_emails = ['admin@example.com'];

    // SMTP Configuration based on your hosting settings
    private static $smtp_host = 'your_smtp_host';
    private static $smtp_port = 465;  // SSL port
    private static $smtp_username = 'your_smtp_username';
    private static $smtp_password = 'your_smtp_password';
    private static $smtp_secure = 'ssl';  // Using SSL for port 465
    
    /**
     * Main email sending method - tries SMTP first, fallback to PHP mail()
     */
    public static function sendEmail($to_email, $subject, $html_message) {
        try {
            // Validate email
            if (!filter_var($to_email, FILTER_VALIDATE_EMAIL)) {
                error_log("❌ Invalid email address: {$to_email}");
                return false;
            }
            
            error_log("📧 Attempting to send email to: {$to_email} | Subject: {$subject}");
            
            // Try SMTP first
            $smtp_result = self::sendViaSMTP($to_email, $subject, $html_message);
            
            if ($smtp_result) {
                error_log("✅ SMTP email sent successfully to: {$to_email}");
                return true;
            }
            
            // Fallback to PHP mail()
            error_log("⚠️ SMTP failed, trying PHP mail() for: {$to_email}");
            return self::sendViaPHPMail($to_email, $subject, $html_message);
            
        } catch (Exception $e) {
            error_log("❌ Exception in sendEmail: " . $e->getMessage());
            return self::sendViaPHPMail($to_email, $subject, $html_message);
        }
    }
    
    /**
     * Send email via SMTP
     */
    private static function sendViaSMTP($to_email, $subject, $html_message) {
        try {
            error_log("🔗 Connecting to SMTP: " . self::$smtp_host . ":" . self::$smtp_port);
            
            // Create SSL context for secure connection
            $context = stream_context_create([
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                ]
            ]);
            
            // Connect to SMTP server
            if (self::$smtp_secure == 'ssl') {
                $socket = stream_socket_client(
                    "ssl://" . self::$smtp_host . ":" . self::$smtp_port,
                    $errno, $errstr, 30, STREAM_CLIENT_CONNECT, $context
                );
            } else {
                $socket = fsockopen(self::$smtp_host, self::$smtp_port, $errno, $errstr, 30);
            }
            
            if (!$socket) {
                error_log("❌ SMTP connection failed: {$errno} - {$errstr}");
                return false;
            }
            
            // Read initial response
            $response = fgets($socket, 515);
            error_log("📨 SMTP greeting: " . trim($response));
            
            if (substr($response, 0, 3) != '220') {
                error_log("❌ SMTP greeting failed: {$response}");
                fclose($socket);
                return false;
            }
            
            // EHLO command
            fputs($socket, "EHLO " . $_SERVER['SERVER_NAME'] . "\r\n");
            $response = fgets($socket, 515);
            error_log("📨 EHLO response: " . trim($response));
            
            // For TLS on port 587 (not needed for SSL on 465)
            if (self::$smtp_secure == 'tls') {
                fputs($socket, "STARTTLS\r\n");
                $response = fgets($socket, 515);
                error_log("📨 STARTTLS response: " . trim($response));
                
                if (substr($response, 0, 3) != '220') {
                    error_log("❌ STARTTLS failed: {$response}");
                    fclose($socket);
                    return false;
                }
                
                // Enable TLS encryption
                if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    error_log("❌ TLS encryption failed");
                    fclose($socket);
                    return false;
                }
                
                // EHLO again after TLS
                fputs($socket, "EHLO " . $_SERVER['SERVER_NAME'] . "\r\n");
                $response = fgets($socket, 515);
                error_log("📨 EHLO after TLS: " . trim($response));
            }
            
            // Authentication
            fputs($socket, "AUTH LOGIN\r\n");
            $response = fgets($socket, 515);
            error_log("📨 AUTH LOGIN response: " . trim($response));
            
            if (substr($response, 0, 3) != '334') {
                error_log("❌ AUTH LOGIN failed: {$response}");
                fclose($socket);
                return false;
            }
            
            // Send username
            fputs($socket, base64_encode(self::$smtp_username) . "\r\n");
            $response = fgets($socket, 515);
            error_log("📨 Username response: " . trim($response));
            
            if (substr($response, 0, 3) != '334') {
                error_log("❌ Username authentication failed: {$response}");
                fclose($socket);
                return false;
            }
            
            // Send password
            fputs($socket, base64_encode(self::$smtp_password) . "\r\n");
            $response = fgets($socket, 515);
            error_log("📨 Password response: " . trim($response));
            
            if (substr($response, 0, 3) != '235') {
                error_log("❌ Password authentication failed: {$response}");
                fclose($socket);
                return false;
            }
            
            error_log("✅ SMTP authentication successful");
            
            // MAIL FROM
            fputs($socket, "MAIL FROM: <" . self::$from_email . ">\r\n");
            $response = fgets($socket, 515);
            error_log("📨 MAIL FROM response: " . trim($response));
            
            if (substr($response, 0, 3) != '250') {
                error_log("❌ MAIL FROM failed: {$response}");
                fclose($socket);
                return false;
            }
            
            // RCPT TO
            fputs($socket, "RCPT TO: <{$to_email}>\r\n");
            $response = fgets($socket, 515);
            error_log("📨 RCPT TO response: " . trim($response));
            
            if (substr($response, 0, 3) != '250') {
                error_log("❌ RCPT TO failed: {$response}");
                fclose($socket);
                return false;
            }
            
            // DATA command
            fputs($socket, "DATA\r\n");
            $response = fgets($socket, 515);
            error_log("📨 DATA response: " . trim($response));
            
            if (substr($response, 0, 3) != '354') {
                error_log("❌ DATA command failed: {$response}");
                fclose($socket);
                return false;
            }
            
            // Build email content
            $email_data = "From: " . self::$from_name . " <" . self::$from_email . ">\r\n";
            $email_data .= "To: {$to_email}\r\n";
            $email_data .= "Subject: {$subject}\r\n";
            $email_data .= "MIME-Version: 1.0\r\n";
            $email_data .= "Content-Type: text/html; charset=UTF-8\r\n";
            $email_data .= "Content-Transfer-Encoding: 8bit\r\n";
            $email_data .= "Date: " . date('r') . "\r\n";
            $email_data .= "Message-ID: <" . uniqid() . "@neofoxmedia.com>\r\n";
            $email_data .= "\r\n";
            $email_data .= $html_message;
            $email_data .= "\r\n.\r\n";
            
            // Send email data
            fputs($socket, $email_data);
            $response = fgets($socket, 515);
            error_log("📨 Email data response: " . trim($response));
            
            if (substr($response, 0, 3) != '250') {
                error_log("❌ Email data failed: {$response}");
                fclose($socket);
                return false;
            }
            
            // QUIT
            fputs($socket, "QUIT\r\n");
            fclose($socket);
            
            error_log("✅ SMTP email sent successfully");
            return true;
            
        } catch (Exception $e) {
            error_log("❌ SMTP exception: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Fallback to PHP mail()
     */
    private static function sendViaPHPMail($to_email, $subject, $html_message) {
        try {
            $headers = [
                'MIME-Version: 1.0',
                'Content-type: text/html; charset=UTF-8',
                'From: ' . self::$from_name . ' <' . self::$from_email . '>',
                'Reply-To: ' . self::$from_email,
                'Return-Path: ' . self::$from_email,
                'X-Mailer: PHP/' . phpversion()
            ];
            
            $headers_string = implode("\r\n", $headers);
            $success = @mail($to_email, $subject, $html_message, $headers_string);
            
            if ($success) {
                error_log("✅ PHP mail() sent successfully to: {$to_email}");
            } else {
                error_log("❌ PHP mail() failed to: {$to_email}");
            }
            
            return $success;
        } catch (Exception $e) {
            error_log("❌ PHP mail() exception: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send checkout confirmation with current equipment list
     */
    public static function sendCheckoutConfirmation($borrower_email, $asset_name, $borrower_name, $expected_return, $current_equipment = []) {
        $subject = "📦 Equipment Checkout Confirmation - {$asset_name}";
        
        $message = self::buildEquipmentListEmail(
            $borrower_name,
            "You have successfully checked out: <strong>{$asset_name}</strong>",
            "Expected return date: " . date('M j, Y g:i A', strtotime($expected_return)),
            $current_equipment,
            'checkout'
        );
        
        $success = self::sendEmail($borrower_email, $subject, $message);
        self::notifyAdminsOfCheckout($asset_name, $borrower_name, $expected_return);
        return $success;
    }
    
    /**
     * Send checkin confirmation with updated equipment list
     */
    public static function sendCheckinConfirmation($borrower_email, $asset_name, $borrower_name, $condition, $current_equipment = []) {
        $subject = "✅ Equipment Check-in Confirmation - {$asset_name}";
        
        $message = self::buildEquipmentListEmail(
            $borrower_name,
            "You have successfully returned: <strong>{$asset_name}</strong>",
            "Returned in: <strong>{$condition}</strong> condition",
            $current_equipment,
            'checkin'
        );
        
        $success = self::sendEmail($borrower_email, $subject, $message);
        self::notifyAdminsOfCheckin($asset_name, $borrower_name, $condition);
        return $success;
    }
    
    /**
     * Send complete equipment list to borrower
     */
    public static function sendEquipmentList($borrower_email, $borrower_name, $equipment_list) {
        $subject = "📋 Your Current Equipment List - Neofox Gear Control";
        
        $message = self::buildEquipmentListEmail(
            $borrower_name,
            "Here is your current equipment list:",
            "",
            $equipment_list,
            'list'
        );
        
        return self::sendEmail($borrower_email, $subject, $message);
    }
    
    /**
     * Notify admins of equipment checkout
     */
    private static function notifyAdminsOfCheckout($asset_name, $borrower_name, $expected_return) {
        $subject = "🔔 Equipment Checked Out - {$asset_name}";
        $message = self::buildAdminCheckoutNotification($asset_name, $borrower_name, $expected_return);
        
        foreach (self::$admin_emails as $admin_email) {
            self::sendEmail($admin_email, $subject, $message);
        }
    }
    
    /**
     * Notify admins of equipment checkin
     */
    private static function notifyAdminsOfCheckin($asset_name, $borrower_name, $condition) {
        $subject = "🔔 Equipment Returned - {$asset_name}";
        $message = self::buildAdminCheckinNotification($asset_name, $borrower_name, $condition);
        
        foreach (self::$admin_emails as $admin_email) {
            self::sendEmail($admin_email, $subject, $message);
        }
    }
    
    /**
     * Build admin checkout notification
     */
    private static function buildAdminCheckoutNotification($asset_name, $borrower_name, $expected_return) {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background-color: #f5f5f5; }
                .container { max-width: 500px; margin: 0 auto; background: white; border-radius: 10px; padding: 20px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
                .header { background: #28a745; color: white; padding: 20px; border-radius: 8px; text-align: center; margin-bottom: 20px; }
                .info { background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 15px; }
                .label { font-weight: bold; color: #333; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2 style='margin: 0;'>📦 Equipment Checked Out</h2>
                </div>
                <div class='info'>
                    <p><span class='label'>Equipment:</span> {$asset_name}</p>
                    <p><span class='label'>Borrower:</span> {$borrower_name}</p>
                    <p><span class='label'>Expected Return:</span> " . date('M j, Y g:i A', strtotime($expected_return)) . "</p>
                    <p><span class='label'>Checked Out:</span> " . date('M j, Y g:i A') . "</p>
                </div>
                <p style='text-align: center; color: #666; font-size: 14px;'>
                    Neofox Gear Control System
                </p>
            </div>
        </body>
        </html>";
    }
    
    /**
     * Build admin checkin notification
     */
    private static function buildAdminCheckinNotification($asset_name, $borrower_name, $condition) {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background-color: #f5f5f5; }
                .container { max-width: 500px; margin: 0 auto; background: white; border-radius: 10px; padding: 20px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
                .header { background: #ffc107; color: #000; padding: 20px; border-radius: 8px; text-align: center; margin-bottom: 20px; }
                .info { background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 15px; }
                .label { font-weight: bold; color: #333; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2 style='margin: 0;'>✅ Equipment Returned</h2>
                </div>
                <div class='info'>
                    <p><span class='label'>Equipment:</span> {$asset_name}</p>
                    <p><span class='label'>Returned by:</span> {$borrower_name}</p>
                    <p><span class='label'>Condition:</span> {$condition}</p>
                    <p><span class='label'>Returned:</span> " . date('M j, Y g:i A') . "</p>
                </div>
                <p style='text-align: center; color: #666; font-size: 14px;'>
                    Neofox Gear Control System
                </p>
            </div>
        </body>
        </html>";
    }
    
    /**
     * Build the HTML email template
     */
    private static function buildEquipmentListEmail($borrower_name, $main_message, $sub_message, $equipment_list, $type = 'checkout') {
        $equipment_count = count($equipment_list);
        $plural = $equipment_count !== 1 ? 's' : '';
        
        // Color scheme based on action
        $colors = [
            'checkout' => ['primary' => '#28a745', 'secondary' => '#e8f5e9'],
            'checkin' => ['primary' => '#ffc107', 'secondary' => '#fffdf7'],
            'list' => ['primary' => '#007bff', 'secondary' => '#e3f2fd']
        ];
        
        $color = $colors[$type] ?? $colors['list'];
        
        $html = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Neofox Gear Control</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background-color: #f5f5f5; }
                .container { max-width: 600px; margin: 0 auto; background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
                .header { background: {$color['primary']}; color: white; padding: 30px 20px; text-align: center; }
                .header h1 { margin: 0; font-size: 24px; }
                .content { padding: 30px 20px; }
                .message { background: {$color['secondary']}; padding: 20px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid {$color['primary']}; }
                .equipment-section { margin-top: 25px; }
                .equipment-section h3 { color: #333; margin-bottom: 15px; font-size: 18px; }
                .equipment-list { background: #f8f9fa; border-radius: 8px; padding: 15px; }
                .equipment-item { display: flex; justify-content: space-between; align-items: center; padding: 12px 0; border-bottom: 1px solid #e9ecef; }
                .equipment-item:last-child { border-bottom: none; }
                .equipment-name { font-weight: bold; color: #333; }
                .equipment-details { font-size: 14px; color: #666; }
                .equipment-status { padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: bold; }
                .status-checked-out { background: #fff3cd; color: #856404; }
                .status-overdue { background: #f8d7da; color: #721c24; }
                .no-equipment { text-align: center; color: #666; font-style: italic; padding: 20px; }
                .footer { background: #f8f9fa; padding: 20px; text-align: center; font-size: 14px; color: #666; }
                .summary-box { background: linear-gradient(135deg, {$color['primary']}, {$color['primary']}dd); color: white; padding: 15px; border-radius: 8px; margin-bottom: 20px; text-align: center; }
                .return-reminder { background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 8px; padding: 15px; margin-top: 20px; }
                .return-reminder h4 { margin: 0 0 10px 0; color: #856404; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>🎬 Neofox Gear Control</h1>
                    <p style='margin: 10px 0 0 0; opacity: 0.9;'>Equipment Management System</p>
                </div>
                
                <div class='content'>
                    <div class='message'>
                        <p style='margin: 0; font-size: 16px;'><strong>Hi {$borrower_name},</strong></p>
                        <p style='margin: 10px 0 0 0;'>{$main_message}</p>";
        
        if ($sub_message) {
            $html .= "<p style='margin: 10px 0 0 0;'>{$sub_message}</p>";
        }
        
        $html .= "</div>";
        
        // Equipment summary box
        if ($equipment_count > 0) {
            $html .= "
                    <div class='summary-box'>
                        <h3 style='margin: 0; font-size: 18px;'>📦 You currently have {$equipment_count} item{$plural} checked out</h3>
                    </div>";
        }
        
        // Equipment list section
        $html .= "
                    <div class='equipment-section'>
                        <h3>🎥 Your Current Equipment:</h3>
                        <div class='equipment-list'>";
        
        if (empty($equipment_list)) {
            $html .= "<div class='no-equipment'>✅ You currently have no equipment checked out.</div>";
        } else {
            foreach ($equipment_list as $item) {
                $checkout_date = isset($item['checkout_date']) ? date('M j', strtotime($item['checkout_date'])) : '';
                $return_date = isset($item['expected_return_date']) ? date('M j', strtotime($item['expected_return_date'])) : '';
                $is_overdue = isset($item['expected_return_date']) && strtotime($item['expected_return_date']) < time();
                
                $status_class = $is_overdue ? 'status-overdue' : 'status-checked-out';
                $status_text = $is_overdue ? 'OVERDUE' : 'Checked Out';
                
                $html .= "
                        <div class='equipment-item'>
                            <div>
                                <div class='equipment-name'>{$item['asset_name']}</div>
                                <div class='equipment-details'>{$item['category']} - {$item['asset_id']}</div>";
                
                if ($checkout_date && $return_date) {
                    $html .= "<div class='equipment-details'>Out: {$checkout_date} → Due: {$return_date}</div>";
                }
                
                $html .= "
                            </div>
                            <div class='equipment-status {$status_class}'>{$status_text}</div>
                        </div>";
            }
        }
        
        $html .= "</div></div>";
        
        // Return reminder for overdue items
        $overdue_items = array_filter($equipment_list, function($item) {
            return isset($item['expected_return_date']) && strtotime($item['expected_return_date']) < time();
        });
        
        if (!empty($overdue_items)) {
            $overdue_count = count($overdue_items);
            $overdue_plural = $overdue_count !== 1 ? 's' : '';
            $html .= "
                    <div class='return-reminder'>
                        <h4>⚠️ Return Reminder</h4>
                        <p style='margin: 0;'>You have {$overdue_count} overdue item{$overdue_plural}. Please return them as soon as possible.</p>
                    </div>";
        }
        
        $html .= "
                    <div style='margin-top: 30px; text-align: center;'>
                        <p style='color: #666; font-size: 14px;'>
                            Questions? Contact us at <a href='mailto:" . self::$from_email . "'>" . self::$from_email . "</a>
                        </p>
                    </div>
                </div>
                
                <div class='footer'>
                    <p>This is an automated message from Neofox Gear Control System.<br>
                    Please do not reply to this email.</p>
                    <p style='margin-top: 10px;'>
                        <strong>Neofox Media</strong> | Equipment Management
                    </p>
                </div>
            </div>
        </body>
        </html>";
        
        return $html;
    }
    
    /**
     * Get user email from username
     */
    public static function getUserEmail($username, $db_connection) {
        try {
            $query = "SELECT email FROM users WHERE username = :username";
            $stmt = $db_connection->prepare($query);
            $stmt->bindParam(":username", $username);
            $stmt->execute();
            
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            return $user ? $user['email'] : null;
        } catch (PDOException $e) {
            error_log("Error getting user email: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Send overdue equipment reminders
     */
    public static function sendOverdueReminders($db_connection) {
        try {
            // Get all overdue equipment grouped by borrower
            $query = "SELECT current_borrower, asset_id, asset_name, category, checkout_date, expected_return_date, DATEDIFF(NOW(), expected_return_date) as days_overdue
                      FROM assets 
                      WHERE status = 'checked_out' AND expected_return_date < NOW()
                      ORDER BY current_borrower, days_overdue DESC";
            
            $stmt = $db_connection->prepare($query);
            $stmt->execute();
            $overdue_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Group by borrower
            $borrower_equipment = [];
            foreach ($overdue_items as $item) {
                $borrower_equipment[$item['current_borrower']][] = $item;
            }
            
            // Send reminders
            foreach ($borrower_equipment as $borrower => $equipment) {
                $email = self::getUserEmail($borrower, $db_connection);
                if ($email) {
                    $count = count($equipment);
                    $plural = $count !== 1 ? 's' : '';
                    $subject = "⚠️ Overdue Equipment Reminder - {$count} Item{$plural}";
                    
                    $message = self::buildEquipmentListEmail(
                        $borrower,
                        "You have {$count} overdue equipment item{$plural}. Please return them as soon as possible.",
                        "Total overdue items: {$count}",
                        $equipment,
                        'checkin'
                    );
                    
                    self::sendEmail($email, $subject, $message);
                }
            }
            
            return count($borrower_equipment);
        } catch (Exception $e) {
            error_log("Error sending overdue reminders: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Test email functionality
     */
    public static function testEmail($test_email = null) {
        $test_email = $test_email ?: self::$admin_emails[0];
        
        $subject = "🧪 SMTP Email Test - Neofox Gear Control";
        $message = "
        <!DOCTYPE html>
        <html>
        <head><meta charset='UTF-8'></head>
        <body style='font-family: Arial, sans-serif; padding: 20px;'>
            <div style='max-width: 500px; margin: 0 auto; background: #f8f9fa; padding: 20px; border-radius: 10px;'>
                <h2 style='color: #28a745; text-align: center;'>✅ SMTP Email Test Successful!</h2>
                <p>This is an SMTP test email from the Neofox Gear Control System.</p>
                <p><strong>Sent at:</strong> " . date('Y-m-d H:i:s') . "</p>
                <p><strong>Server:</strong> " . ($_SERVER['SERVER_NAME'] ?? 'Unknown') . "</p>
                <p><strong>SMTP Host:</strong> " . self::$smtp_host . "</p>
                <p><strong>SMTP Port:</strong> " . self::$smtp_port . "</p>
                <p><strong>From Email:</strong> " . self::$from_email . "</p>
                <p style='text-align: center; margin-top: 30px; color: #666;'>
                    If you received this email, the SMTP email system is working correctly!
                </p>
            </div>
        </body>
        </html>";
        
        $result = self::sendEmail($test_email, $subject, $message);
        
        if ($result) {
            error_log("✅ SMTP email test successful to: {$test_email}");
        } else {
            error_log("❌ SMTP email test failed to: {$test_email}");
        }
        
        return $result;
    }
}
?>