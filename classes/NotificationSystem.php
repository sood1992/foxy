<?php
// classes/NotificationSystem.php
class NotificationSystem {
    private $conn;
    private $email_config;
    
    public function __construct($db, $email_config = null) {
        $this->conn = $db;
        $this->email_config = $email_config ?: [
            'from_email' => 'noreply@neofoxmedia.com',
            'from_name' => 'Neofox Gear Control',
            'admin_email' => 'admin@neofoxmedia.com',
            'smtp_host' => null, // Use built-in mail() if null
            'smtp_username' => '',
            'smtp_password' => '',
            'smtp_port' => 587
        ];
    }
    
    /**
     * Send checkout confirmation to borrower and admin
     */
    public function sendCheckoutNotification($asset_id, $borrower_name, $borrower_email, $expected_return, $purpose = '') {
        // Get asset details
        $asset = $this->getAssetDetails($asset_id);
        if (!$asset) {
            return false;
        }
        
        $checkout_date = date('M j, Y g:i A');
        $return_date = date('M j, Y g:i A', strtotime($expected_return));
        
        // Email to borrower
        $borrower_subject = "Equipment Checked Out - {$asset['asset_name']}";
        $borrower_message = $this->getCheckoutEmailTemplate([
            'borrower_name' => $borrower_name,
            'asset_name' => $asset['asset_name'],
            'asset_id' => $asset['asset_id'],
            'category' => $asset['category'],
            'checkout_date' => $checkout_date,
            'expected_return' => $return_date,
            'purpose' => $purpose,
            'qr_code_url' => $asset['qr_code']
        ]);
        
        // Email to admin
        $admin_subject = "Equipment Checkout Alert - {$asset['asset_name']}";
        $admin_message = $this->getAdminCheckoutTemplate([
            'borrower_name' => $borrower_name,
            'borrower_email' => $borrower_email,
            'asset_name' => $asset['asset_name'],
            'asset_id' => $asset['asset_id'],
            'category' => $asset['category'],
            'checkout_date' => $checkout_date,
            'expected_return' => $return_date,
            'purpose' => $purpose
        ]);
        
        $borrower_sent = false;
        $admin_sent = false;
        
        // Send to borrower if email provided
        if (!empty($borrower_email)) {
            $borrower_sent = $this->sendEmail($borrower_email, $borrower_subject, $borrower_message);
        }
        
        // Always send to admin
        $admin_sent = $this->sendEmail($this->email_config['admin_email'], $admin_subject, $admin_message);
        
        // Log notification
        $this->logNotification([
            'type' => 'checkout',
            'asset_id' => $asset_id,
            'recipient_email' => $borrower_email,
            'borrower_name' => $borrower_name,
            'status' => ($borrower_sent || $admin_sent) ? 'sent' : 'failed',
            'details' => json_encode([
                'borrower_sent' => $borrower_sent,
                'admin_sent' => $admin_sent,
                'expected_return' => $expected_return
            ])
        ]);
        
        return $borrower_sent || $admin_sent;
    }
    
    /**
     * Send checkin confirmation to borrower and admin
     */
    public function sendCheckinNotification($asset_id, $borrower_name, $borrower_email, $condition, $notes = '') {
        $asset = $this->getAssetDetails($asset_id);
        if (!$asset) {
            return false;
        }
        
        $checkin_date = date('M j, Y g:i A');
        
        // Email to borrower
        $borrower_subject = "Equipment Returned - {$asset['asset_name']}";
        $borrower_message = $this->getCheckinEmailTemplate([
            'borrower_name' => $borrower_name,
            'asset_name' => $asset['asset_name'],
            'asset_id' => $asset['asset_id'],
            'category' => $asset['category'],
            'checkin_date' => $checkin_date,
            'condition' => $condition,
            'notes' => $notes
        ]);
        
        // Email to admin
        $admin_subject = "Equipment Return Alert - {$asset['asset_name']}";
        $admin_message = $this->getAdminCheckinTemplate([
            'borrower_name' => $borrower_name,
            'borrower_email' => $borrower_email,
            'asset_name' => $asset['asset_name'],
            'asset_id' => $asset['asset_id'],
            'category' => $asset['category'],
            'checkin_date' => $checkin_date,
            'condition' => $condition,
            'notes' => $notes
        ]);
        
        $borrower_sent = false;
        $admin_sent = false;
        
        // Send to borrower if email provided
        if (!empty($borrower_email)) {
            $borrower_sent = $this->sendEmail($borrower_email, $borrower_subject, $borrower_message);
        }
        
        // Always send to admin
        $admin_sent = $this->sendEmail($this->email_config['admin_email'], $admin_subject, $admin_message);
        
        // Log notification
        $this->logNotification([
            'type' => 'checkin',
            'asset_id' => $asset_id,
            'recipient_email' => $borrower_email,
            'borrower_name' => $borrower_name,
            'status' => ($borrower_sent || $admin_sent) ? 'sent' : 'failed',
            'details' => json_encode([
                'borrower_sent' => $borrower_sent,
                'admin_sent' => $admin_sent,
                'condition' => $condition
            ])
        ]);
        
        return $borrower_sent || $admin_sent;
    }
    
    /**
     * Send overdue notifications
     */
    public function sendOverdueNotifications() {
        $query = "SELECT a.*, u.email as borrower_email
                  FROM assets a
                  LEFT JOIN users u ON a.current_borrower = u.username
                  WHERE a.status = 'checked_out' 
                  AND a.expected_return_date < NOW()";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $overdue_assets = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $notifications_sent = 0;
        
        foreach ($overdue_assets as $asset) {
            $days_overdue = floor((time() - strtotime($asset['expected_return_date'])) / 86400);
            
            // Send reminder based on overdue severity
            $should_send = false;
            if ($days_overdue == 1 || $days_overdue == 3 || $days_overdue == 7) {
                $should_send = true;
            } elseif ($days_overdue > 7 && $days_overdue % 7 == 0) {
                $should_send = true; // Weekly reminders after first week
            }
            
            if ($should_send) {
                $severity = $this->getOverdueSeverity($days_overdue);
                
                $subject = "URGENT: Equipment Overdue - {$asset['asset_name']}";
                $message = $this->getOverdueEmailTemplate([
                    'borrower_name' => $asset['current_borrower'],
                    'asset_name' => $asset['asset_name'],
                    'asset_id' => $asset['asset_id'],
                    'days_overdue' => $days_overdue,
                    'expected_return' => date('M j, Y', strtotime($asset['expected_return_date'])),
                    'severity' => $severity,
                    'checkin_url' => $this->getCheckinUrl($asset['asset_id'])
                ]);
                
                // Send to borrower and admin
                $borrower_sent = false;
                if (!empty($asset['borrower_email'])) {
                    $borrower_sent = $this->sendEmail($asset['borrower_email'], $subject, $message);
                }
                
                $admin_sent = $this->sendEmail($this->email_config['admin_email'], $subject, $message);
                
                if ($borrower_sent || $admin_sent) {
                    $notifications_sent++;
                    
                    $this->logNotification([
                        'type' => 'overdue_reminder',
                        'asset_id' => $asset['asset_id'],
                        'recipient_email' => $asset['borrower_email'],
                        'borrower_name' => $asset['current_borrower'],
                        'status' => 'sent',
                        'details' => json_encode([
                            'days_overdue' => $days_overdue,
                            'severity' => $severity,
                            'borrower_sent' => $borrower_sent,
                            'admin_sent' => $admin_sent
                        ])
                    ]);
                }
            }
        }
        
        return $notifications_sent;
    }
    
    /**
     * Send maintenance reminders
     */
    public function sendMaintenanceNotifications() {
        $query = "SELECT * FROM maintenance_schedule ms
                  JOIN assets a ON ms.asset_id = a.id
                  WHERE ms.next_maintenance_date <= DATE_ADD(NOW(), INTERVAL 7 DAY)
                  AND ms.status = 'scheduled'";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $maintenance_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $notifications_sent = 0;
        
        foreach ($maintenance_items as $item) {
            $days_until = floor((strtotime($item['next_maintenance_date']) - time()) / 86400);
            
            $subject = "Maintenance Due - {$item['asset_name']}";
            $message = $this->getMaintenanceEmailTemplate([
                'asset_name' => $item['asset_name'],
                'asset_id' => $item['asset_id'],
                'maintenance_type' => $item['maintenance_type'],
                'due_date' => date('M j, Y', strtotime($item['next_maintenance_date'])),
                'days_until' => $days_until,
                'notes' => $item['notes']
            ]);
            
            if ($this->sendEmail($this->email_config['admin_email'], $subject, $message)) {
                $notifications_sent++;
                
                $this->logNotification([
                    'type' => 'maintenance_reminder',
                    'asset_id' => $item['asset_id'],
                    'recipient_email' => $this->email_config['admin_email'],
                    'borrower_name' => 'Admin',
                    'status' => 'sent',
                    'details' => json_encode([
                        'maintenance_type' => $item['maintenance_type'],
                        'days_until' => $days_until
                    ])
                ]);
            }
        }
        
        return $notifications_sent;
    }
    
    /**
     * Get asset details for notifications
     */
    private function getAssetDetails($asset_id) {
        $query = "SELECT * FROM assets WHERE asset_id = :asset_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':asset_id', $asset_id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get borrower email from username
     */
    private function getBorrowerEmail($username) {
        $query = "SELECT email FROM users WHERE username = :username";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':username', $username);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['email'] : null;
    }
    
    /**
     * Send email using configured method
     */
    private function sendEmail($to, $subject, $message) {
        $headers = [
            'MIME-Version: 1.0',
            'Content-type: text/html; charset=UTF-8',
            'From: ' . $this->email_config['from_name'] . ' <' . $this->email_config['from_email'] . '>',
            'Reply-To: ' . $this->email_config['from_email'],
            'X-Mailer: PHP/' . phpversion()
        ];
        
        if ($this->email_config['smtp_host']) {
            // Use SMTP if configured
            return $this->sendSMTPEmail($to, $subject, $message);
        } else {
            // Use built-in mail function
            return mail($to, $subject, $message, implode("\r\n", $headers));
        }
    }
    
    /**
     * Send email via SMTP (requires PHPMailer or similar)
     */
    private function sendSMTPEmail($to, $subject, $message) {
        // This would require PHPMailer or similar SMTP library
        // For now, fall back to mail()
        return mail($to, $subject, $message, 
            'From: ' . $this->email_config['from_email'] . "\r\n" .
            'Content-Type: text/html; charset=UTF-8'
        );
    }
    
    /**
     * Log notification for tracking
     */
    private function logNotification($data) {
        $query = "INSERT INTO notification_log 
                  (type, asset_id, recipient_email, borrower_name, status, details, created_at) 
                  VALUES (:type, :asset_id, :recipient_email, :borrower_name, :status, :details, NOW())";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':type', $data['type']);
        $stmt->bindParam(':asset_id', $data['asset_id']);
        $stmt->bindParam(':recipient_email', $data['recipient_email']);
        $stmt->bindParam(':borrower_name', $data['borrower_name']);
        $stmt->bindParam(':status', $data['status']);
        $stmt->bindParam(':details', $data['details']);
        
        try {
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Failed to log notification: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get overdue severity level
     */
    private function getOverdueSeverity($days_overdue) {
        if ($days_overdue <= 3) {
            return 'mild';
        } elseif ($days_overdue <= 7) {
            return 'moderate';
        } elseif ($days_overdue <= 14) {
            return 'severe';
        } else {
            return 'critical';
        }
    }
    
    /**
     * Get checkin URL for asset
     */
    private function getCheckinUrl($asset_id) {
        $base_url = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
        $base_url .= dirname($_SERVER['PHP_SELF']);
        return $base_url . "/checkin.php?asset_id=" . urlencode($asset_id);
    }
    
    // EMAIL TEMPLATES
    
    private function getCheckoutEmailTemplate($data) {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: 'Arial', sans-serif; margin: 0; padding: 0; background-color: #FFD700; }
                .container { max-width: 600px; margin: 0 auto; background: white; border: 3px solid #000; border-radius: 15px; overflow: hidden; }
                .header { background: #000; color: #FFD700; padding: 30px; text-align: center; }
                .header h1 { margin: 0; font-size: 24px; font-weight: 900; }
                .content { padding: 30px; }
                .asset-info { background: #FFF9C4; border: 2px solid #000; border-radius: 10px; padding: 20px; margin: 20px 0; }
                .detail-row { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #eee; }
                .detail-row:last-child { border-bottom: none; }
                .label { font-weight: bold; color: #333; }
                .value { color: #000; }
                .qr-section { text-align: center; margin: 25px 0; }
                .qr-section img { border: 2px solid #000; border-radius: 8px; }
                .important-note { background: #FF5722; color: white; padding: 15px; border-radius: 8px; margin: 20px 0; font-weight: bold; }
                .footer { background: #f8f8f8; padding: 20px; text-align: center; font-size: 12px; color: #666; }
                .btn { background: #FFD700; color: #000; padding: 12px 25px; border: 2px solid #000; border-radius: 25px; text-decoration: none; font-weight: bold; display: inline-block; margin: 15px 0; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>🎬 EQUIPMENT CHECKED OUT</h1>
                    <p>Neofox Gear Control System</p>
                </div>
                
                <div class='content'>
                    <h2>Hi {$data['borrower_name']}! 👋</h2>
                    <p>You have successfully checked out the following equipment:</p>
                    
                    <div class='asset-info'>
                        <h3 style='margin-top: 0; color: #000;'>{$data['asset_name']}</h3>
                        <div class='detail-row'>
                            <span class='label'>Asset ID:</span>
                            <span class='value'>{$data['asset_id']}</span>
                        </div>
                        <div class='detail-row'>
                            <span class='label'>Category:</span>
                            <span class='value'>{$data['category']}</span>
                        </div>
                        <div class='detail-row'>
                            <span class='label'>Checked Out:</span>
                            <span class='value'>{$data['checkout_date']}</span>
                        </div>
                        <div class='detail-row'>
                            <span class='label'>Due Back:</span>
                            <span class='value'>{$data['expected_return']}</span>
                        </div>
                        " . (!empty($data['purpose']) ? "
                        <div class='detail-row'>
                            <span class='label'>Purpose:</span>
                            <span class='value'>{$data['purpose']}</span>
                        </div>" : "") . "
                    </div>
                    
                    <div class='important-note'>
                        ⚠️ Please return this equipment on time and in the same condition you received it.
                    </div>
                    
                    <div class='qr-section'>
                        <p><strong>Quick Return QR Code:</strong></p>
                        <img src='{$data['qr_code_url']}' alt='Return QR Code' width='150' height='150'>
                        <p><em>Scan this QR code to quickly return the item</em></p>
                    </div>
                    
                    <p><strong>Need to return early or extend?</strong><br>
                    Contact the equipment manager or use the gear control system.</p>
                </div>
                
                <div class='footer'>
                    <p>Neofox Gear Control System<br>
                    This is an automated message. Please do not reply to this email.</p>
                </div>
            </div>
        </body>
        </html>";
    }
    
    private function getCheckinEmailTemplate($data) {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: 'Arial', sans-serif; margin: 0; padding: 0; background-color: #4CAF50; }
                .container { max-width: 600px; margin: 0 auto; background: white; border: 3px solid #000; border-radius: 15px; overflow: hidden; }
                .header { background: #000; color: #4CAF50; padding: 30px; text-align: center; }
                .header h1 { margin: 0; font-size: 24px; font-weight: 900; }
                .content { padding: 30px; }
                .asset-info { background: #E8F5E8; border: 2px solid #000; border-radius: 10px; padding: 20px; margin: 20px 0; }
                .detail-row { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #eee; }
                .detail-row:last-child { border-bottom: none; }
                .label { font-weight: bold; color: #333; }
                .value { color: #000; }
                .thank-you { background: #4CAF50; color: white; padding: 15px; border-radius: 8px; margin: 20px 0; font-weight: bold; text-align: center; }
                .footer { background: #f8f8f8; padding: 20px; text-align: center; font-size: 12px; color: #666; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>✅ EQUIPMENT RETURNED</h1>
                    <p>Neofox Gear Control System</p>
                </div>
                
                <div class='content'>
                    <h2>Thank you, {$data['borrower_name']}! 🙏</h2>
                    <p>You have successfully returned the following equipment:</p>
                    
                    <div class='asset-info'>
                        <h3 style='margin-top: 0; color: #000;'>{$data['asset_name']}</h3>
                        <div class='detail-row'>
                            <span class='label'>Asset ID:</span>
                            <span class='value'>{$data['asset_id']}</span>
                        </div>
                        <div class='detail-row'>
                            <span class='label'>Category:</span>
                            <span class='value'>{$data['category']}</span>
                        </div>
                        <div class='detail-row'>
                            <span class='label'>Returned:</span>
                            <span class='value'>{$data['checkin_date']}</span>
                        </div>
                        <div class='detail-row'>
                            <span class='label'>Condition:</span>
                            <span class='value'>" . ucfirst(str_replace('_', ' ', $data['condition'])) . "</span>
                        </div>
                        " . (!empty($data['notes']) ? "
                        <div class='detail-row'>
                            <span class='label'>Notes:</span>
                            <span class='value'>{$data['notes']}</span>
                        </div>" : "") . "
                    </div>
                    
                    <div class='thank-you'>
                        🎉 Thanks for taking great care of our equipment!
                    </div>
                    
                    <p>The equipment is now available for other team members to use.</p>
                </div>
                
                <div class='footer'>
                    <p>Neofox Gear Control System<br>
                    This is an automated message. Please do not reply to this email.</p>
                </div>
            </div>
        </body>
        </html>";
    }
    
    private function getOverdueEmailTemplate($data) {
        $severity_colors = [
            'mild' => '#FFC107',
            'moderate' => '#FF9800', 
            'severe' => '#FF5722',
            'critical' => '#D32F2F'
        ];
        
        $color = $severity_colors[$data['severity']];
        
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: 'Arial', sans-serif; margin: 0; padding: 0; background-color: {$color}; }
                .container { max-width: 600px; margin: 0 auto; background: white; border: 3px solid #000; border-radius: 15px; overflow: hidden; }
                .header { background: #000; color: {$color}; padding: 30px; text-align: center; }
                .header h1 { margin: 0; font-size: 24px; font-weight: 900; }
                .content { padding: 30px; }
                .asset-info { background: #ffebee; border: 2px solid #000; border-radius: 10px; padding: 20px; margin: 20px 0; }
                .urgent-notice { background: {$color}; color: white; padding: 20px; border-radius: 8px; margin: 20px 0; font-weight: bold; text-align: center; }
                .detail-row { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #eee; }
                .detail-row:last-child { border-bottom: none; }
                .label { font-weight: bold; color: #333; }
                .value { color: #000; }
                .return-btn { background: {$color}; color: white; padding: 15px 30px; border: 2px solid #000; border-radius: 25px; text-decoration: none; font-weight: bold; display: inline-block; margin: 20px 0; }
                .footer { background: #f8f8f8; padding: 20px; text-align: center; font-size: 12px; color: #666; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>🚨 EQUIPMENT OVERDUE</h1>
                    <p>Neofox Gear Control System</p>
                </div>
                
                <div class='content'>
                    <h2>Hi {$data['borrower_name']},</h2>
                    
                    <div class='urgent-notice'>
                        ⚠️ URGENT: Your equipment is {$data['days_overdue']} day(s) overdue!
                    </div>
                    
                    <p>The following equipment was due for return and needs to be returned immediately:</p>
                    
                    <div class='asset-info'>
                        <h3 style='margin-top: 0; color: #000;'>{$data['asset_name']}</h3>
                        <div class='detail-row'>
                            <span class='label'>Asset ID:</span>
                            <span class='value'>{$data['asset_id']}</span>
                        </div>
                        <div class='detail-row'>
                            <span class='label'>Was Due:</span>
                            <span class='value'>{$data['expected_return']}</span>
                        </div>
                        <div class='detail-row'>
                            <span class='label'>Days Overdue:</span>
                            <span class='value'>{$data['days_overdue']} days</span>
                        </div>
                        <div class='detail-row'>
                            <span class='label'>Severity:</span>
                            <span class='value'>" . ucfirst($data['severity']) . "</span>
                        </div>
                    </div>
                    
                    <div style='text-align: center;'>
                        <a href='{$data['checkin_url']}' class='return-btn'>
                            📱 RETURN EQUIPMENT NOW
                        </a>
                    </div>
                    
                    <p><strong>Please return this equipment immediately.</strong> Other team members may be waiting to use it for their projects.</p>
                    
                    <p>If you're having issues returning the equipment or need an extension, please contact the equipment manager immediately.</p>
                </div>
                
                <div class='footer'>
                    <p>Neofox Gear Control System<br>
                    This is an automated reminder. Contact admin if you need assistance.</p>
                </div>
            </div>
        </body>
        </html>";
    }
    
    private function getMaintenanceEmailTemplate($data) {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: 'Arial', sans-serif; margin: 0; padding: 0; background-color: #2196F3; }
                .container { max-width: 600px; margin: 0 auto; background: white; border: 3px solid #000; border-radius: 15px; overflow: hidden; }
                .header { background: #000; color: #2196F3; padding: 30px; text-align: center; }
                .content { padding: 30px; }
                .maintenance-info { background: #E3F2FD; border: 2px solid #000; border-radius: 10px; padding: 20px; margin: 20px 0; }
                .detail-row { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #eee; }
                .footer { background: #f8f8f8; padding: 20px; text-align: center; font-size: 12px; color: #666; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>🔧 MAINTENANCE DUE</h1>
                    <p>Neofox Gear Control System</p>
                </div>
                
                <div class='content'>
                    <h2>Maintenance Alert</h2>
                    <p>The following equipment is due for maintenance:</p>
                    
                    <div class='maintenance-info'>
                        <h3 style='margin-top: 0;'>{$data['asset_name']}</h3>
                        <div class='detail-row'>
                            <span class='label'>Asset ID:</span>
                            <span class='value'>{$data['asset_id']}</span>
                        </div>
                        <div class='detail-row'>
                            <span class='label'>Maintenance Type:</span>
                            <span class='value'>{$data['maintenance_type']}</span>
                        </div>
                        <div class='detail-row'>
                            <span class='label'>Due Date:</span>
                            <span class='value'>{$data['due_date']}</span>
                        </div>
                        <div class='detail-row'>
                            <span class='label'>Days Until Due:</span>
                            <span class='value'>{$data['days_until']} days</span>
                        </div>
                    </div>
                    
                    <p><strong>Action Required:</strong> Please schedule the maintenance for this equipment.</p>
                </div>
                
                <div class='footer'>
                    <p>Neofox Gear Control System</p>
                </div>
            </div>
        </body>
        </html>";
    }
    
    private function getAdminCheckoutTemplate($data) {
        return "
        <h3>Equipment Checkout Notification</h3>
        <p><strong>Borrower:</strong> {$data['borrower_name']} ({$data['borrower_email']})</p>
        <p><strong>Equipment:</strong> {$data['asset_name']} ({$data['asset_id']})</p>
        <p><strong>Category:</strong> {$data['category']}</p>
        <p><strong>Checkout Time:</strong> {$data['checkout_date']}</p>
        <p><strong>Expected Return:</strong> {$data['expected_return']}</p>
        <p><strong>Purpose:</strong> {$data['purpose']}</p>
        <p>This is an administrative notification from the Neofox Gear Control System.</p>
        ";
    }
    
    private function getAdminCheckinTemplate($data) {
        return "
        <h3>Equipment Return Notification</h3>
        <p><strong>Returned by:</strong> {$data['borrower_name']} ({$data['borrower_email']})</p>
        <p><strong>Equipment:</strong> {$data['asset_name']} ({$data['asset_id']})</p>
        <p><strong>Category:</strong> {$data['category']}</p>
        <p><strong>Return Time:</strong> {$data['checkin_date']}</p>
        <p><strong>Condition:</strong> {$data['condition']}</p>
        <p><strong>Notes:</strong> {$data['notes']}</p>
        <p>This is an administrative notification from the Neofox Gear Control System.</p>
        ";
    }
    
    /**
     * Get notification statistics
     */
    public function getNotificationStats($days = 30) {
        $query = "SELECT 
                    type,
                    status,
                    COUNT(*) as count,
                    DATE(created_at) as date
                  FROM notification_log 
                  WHERE created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
                  GROUP BY type, status, DATE(created_at)
                  ORDER BY created_at DESC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':days', $days, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get recent notifications
     */
    public function getRecentNotifications($limit = 50) {
        $query = "SELECT * FROM notification_log 
                  ORDER BY created_at DESC 
                  LIMIT :limit";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}