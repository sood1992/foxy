<?php
// classes/GearRequest.php
class GearRequest {
    private $conn;
    private $table_name = "gear_requests";
    private $errors = [];

    public function __construct($db) {
        $this->conn = $db;
    }

    public function getErrors() {
        return $this->errors;
    }

    public function create($data) {
        try {
            $query = "INSERT INTO " . $this->table_name . " 
                      (requester_name, requester_email, required_items, request_dates, purpose) 
                      VALUES (:name, :email, :items, :dates, :purpose)";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":name", $data['requester_name']);
            $stmt->bindParam(":email", $data['requester_email']);
            $stmt->bindParam(":items", $data['required_items']);
            $stmt->bindParam(":dates", $data['request_dates']);
            $stmt->bindParam(":purpose", $data['purpose']);
            
            $result = $stmt->execute();
            
            if ($result) {
                error_log("✅ Gear request created successfully, attempting to send admin notification");
                
                // Send notification email to admin team
                $email_result = $this->sendAdminNotification($data);
                
                if ($email_result) {
                    error_log("✅ Admin notification email sent successfully");
                } else {
                    error_log("❌ Admin notification email failed to send");
                }
                
                return true;
            } else {
                $this->errors[] = "Failed to create gear request";
                error_log("❌ Failed to create gear request in database");
                return false;
            }
        } catch (PDOException $e) {
            $this->errors[] = "Database error: " . $e->getMessage();
            error_log("GearRequest create error: " . $e->getMessage());
            return false;
        }
    }

    public function getAll() {
        try {
            $query = "SELECT * FROM " . $this->table_name . " ORDER BY created_at DESC";
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("GearRequest getAll error: " . $e->getMessage());
            return [];
        }
    }

    public function updateStatus($id, $status, $admin_notes = '') {
        try {
            $query = "UPDATE " . $this->table_name . " 
                      SET status = :status, admin_notes = :notes 
                      WHERE id = :id";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":id", $id);
            $stmt->bindParam(":status", $status);
            $stmt->bindParam(":notes", $admin_notes);
            
            $result = $stmt->execute();
            
            if ($result) {
                // Send status update notification to requester
                $request = $this->getById($id);
                if ($request && $request['requester_email']) {
                    $this->sendStatusUpdateNotification($request, $status, $admin_notes);
                }
                return true;
            } else {
                $this->errors[] = "Failed to update request status";
                return false;
            }
        } catch (PDOException $e) {
            $this->errors[] = "Database error: " . $e->getMessage();
            error_log("GearRequest updateStatus error: " . $e->getMessage());
            return false;
        }
    }

    // NEW METHOD: Get request by ID (needed for approve & checkout)
    public function getById($id) {
        try {
            $query = "SELECT * FROM " . $this->table_name . " WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":id", $id);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("GearRequest getById error: " . $e->getMessage());
            return false;
        }
    }

    // NEW METHOD: Send admin notification when new request is created
    private function sendAdminNotification($data) {
        try {
            error_log("📧 Attempting to send admin notification for gear request");
            
            if (!class_exists('EmailNotification')) {
                error_log("❌ EmailNotification class not found for admin notification");
                return false;
            }

            $subject = "🔔 New Gear Request - " . $data['requester_name'];
            
            $message = $this->buildAdminNotificationEmail($data);
            
            // Send to both admin emails
            $admin_emails = ['team@neofoxmedia.com', 'sood1992@gmail.com'];
            $success_count = 0;
            $total_emails = count($admin_emails);
            
            foreach ($admin_emails as $admin_email) {
                error_log("📧 Sending admin notification to: {$admin_email}");
                
                if (EmailNotification::sendEmail($admin_email, $subject, $message)) {
                    $success_count++;
                    error_log("✅ Admin notification sent successfully to: {$admin_email}");
                } else {
                    error_log("❌ Failed to send admin notification to: {$admin_email}");
                }
            }
            
            $success = ($success_count > 0);
            error_log("📊 Admin notification summary: {$success_count}/{$total_emails} emails sent successfully");
            
            return $success;
        } catch (Exception $e) {
            error_log("❌ Exception in sendAdminNotification: " . $e->getMessage());
            return false;
        }
    }

    // NEW METHOD: Send status update notification to requester
    private function sendStatusUpdateNotification($request, $status, $admin_notes) {
        try {
            if (!class_exists('EmailNotification')) {
                error_log("EmailNotification class not found for status update");
                return false;
            }

            $status_text = ucfirst($status);
            $subject = "📋 Request Update - Your gear request has been {$status_text}";
            
            $message = $this->buildStatusUpdateEmail($request, $status, $admin_notes);
            
            return EmailNotification::sendEmail($request['requester_email'], $subject, $message);
        } catch (Exception $e) {
            error_log("Error sending status update notification: " . $e->getMessage());
            return false;
        }
    }

    // NEW METHOD: Build admin notification email
    private function buildAdminNotificationEmail($data) {
        $html = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>New Gear Request</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background-color: #f5f5f5; }
                .container { max-width: 600px; margin: 0 auto; background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
                .header { background: #FFD700; color: #000; padding: 30px 20px; text-align: center; }
                .header h1 { margin: 0; font-size: 24px; }
                .content { padding: 30px 20px; }
                .request-info { background: #f8f9fa; border-radius: 8px; padding: 20px; margin-bottom: 20px; }
                .info-row { display: flex; margin-bottom: 15px; }
                .info-label { font-weight: bold; min-width: 120px; color: #333; }
                .info-value { flex: 1; color: #666; }
                .equipment-list { background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 8px; padding: 15px; font-family: monospace; white-space: pre-line; }
                .footer { background: #f8f9fa; padding: 20px; text-align: center; font-size: 14px; color: #666; }
                .action-button { background: #FFD700; color: #000; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: bold; display: inline-block; margin: 20px 0; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>🎬 New Gear Request</h1>
                    <p style='margin: 10px 0 0 0; opacity: 0.8;'>Neofox Gear Control System</p>
                </div>
                
                <div class='content'>
                    <p><strong>A new gear request has been submitted and requires your approval.</strong></p>
                    
                    <div class='request-info'>
                        <div class='info-row'>
                            <span class='info-label'>Requester:</span>
                            <span class='info-value'>{$data['requester_name']} ({$data['requester_email']})</span>
                        </div>
                        <div class='info-row'>
                            <span class='info-label'>Dates:</span>
                            <span class='info-value'>{$data['request_dates']}</span>
                        </div>
                        <div class='info-row'>
                            <span class='info-label'>Purpose:</span>
                            <span class='info-value'>" . (isset($data['purpose']) && $data['purpose'] ? htmlspecialchars($data['purpose']) : 'Not specified') . "</span>
                        </div>
                    </div>
                    
                    <h3>📦 Requested Equipment:</h3>
                    <div class='equipment-list'>{$data['required_items']}</div>
                    
                    <div style='text-align: center; margin-top: 30px;'>
                        <a href='https://gear.neofoxmedia.com/requests.php' class='action-button'>
                            Review & Approve Request
                        </a>
                    </div>
                    
                    <p style='margin-top: 30px; font-size: 14px; color: #666;'>
                        Please log in to the gear control system to approve or reject this request.
                    </p>
                </div>
                
                <div class='footer'>
                    <p>This is an automated message from Neofox Gear Control System.</p>
                    <p style='margin-top: 10px;'>
                        <strong>Neofox Media</strong> | Equipment Management
                    </p>
                </div>
            </div>
        </body>
        </html>";
        
        return $html;
    }

    // NEW METHOD: Build status update email
    private function buildStatusUpdateEmail($request, $status, $admin_notes) {
        $status_colors = [
            'approved' => '#28a745',
            'rejected' => '#dc3545',
            'pending' => '#ffc107'
        ];
        
        $color = $status_colors[$status] ?? '#6c757d';
        $status_text = ucfirst($status);
        
        $html = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Request Status Update</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background-color: #f5f5f5; }
                .container { max-width: 600px; margin: 0 auto; background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
                .header { background: {$color}; color: white; padding: 30px 20px; text-align: center; }
                .header h1 { margin: 0; font-size: 24px; }
                .content { padding: 30px 20px; }
                .status-box { background: {$color}20; border: 2px solid {$color}; border-radius: 8px; padding: 20px; text-align: center; margin-bottom: 20px; }
                .request-info { background: #f8f9fa; border-radius: 8px; padding: 20px; margin-bottom: 20px; }
                .info-row { display: flex; margin-bottom: 10px; }
                .info-label { font-weight: bold; min-width: 100px; color: #333; }
                .info-value { flex: 1; color: #666; }
                .equipment-list { background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 8px; padding: 15px; font-family: monospace; white-space: pre-line; margin: 15px 0; }
                .footer { background: #f8f9fa; padding: 20px; text-align: center; font-size: 14px; color: #666; }
                .notes { background: #e9ecef; border-radius: 8px; padding: 15px; margin-top: 20px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>📋 Request Status Update</h1>
                    <p style='margin: 10px 0 0 0; opacity: 0.9;'>Neofox Gear Control System</p>
                </div>
                
                <div class='content'>
                    <div class='status-box'>
                        <h2 style='margin: 0; color: {$color};'>Your request has been {$status_text}</h2>
                    </div>
                    
                    <p><strong>Hi {$request['requester_name']},</strong></p>
                    <p>Your gear request status has been updated.</p>
                    
                    <div class='request-info'>
                        <div class='info-row'>
                            <span class='info-label'>Status:</span>
                            <span class='info-value' style='color: {$color}; font-weight: bold;'>{$status_text}</span>
                        </div>
                        <div class='info-row'>
                            <span class='info-label'>Dates:</span>
                            <span class='info-value'>{$request['request_dates']}</span>
                        </div>
                        <div class='info-row'>
                            <span class='info-label'>Purpose:</span>
                            <span class='info-value'>" . (isset($request['purpose']) && $request['purpose'] ? htmlspecialchars($request['purpose']) : 'Not specified') . "</span>
                        </div>
                    </div>
                    
                    <h3>📦 Requested Equipment:</h3>
                    <div class='equipment-list'>{$request['required_items']}</div>";
        
        if ($admin_notes && trim($admin_notes) !== '') {
            $html .= "
                    <div class='notes'>
                        <h4 style='margin: 0 0 10px 0; color: #333;'>📝 Admin Notes:</h4>
                        <p style='margin: 0; color: #666;'>" . nl2br(htmlspecialchars($admin_notes)) . "</p>
                    </div>";
        }
        
        if ($status === 'approved') {
            $html .= "
                    <div style='background: #d4edda; border: 1px solid #c3e6cb; border-radius: 8px; padding: 15px; margin-top: 20px;'>
                        <h4 style='margin: 0 0 10px 0; color: #155724;'>✅ Next Steps:</h4>
                        <p style='margin: 0; color: #155724;'>Your equipment will be prepared for pickup. You'll receive another email when it's ready for checkout.</p>
                    </div>";
        }
        
        $html .= "
                    <p style='margin-top: 30px; font-size: 14px; color: #666;'>
                        Questions? Contact us at <a href='mailto:team@neofoxmedia.com'>team@neofoxmedia.com</a>
                    </p>
                </div>
                
                <div class='footer'>
                    <p>This is an automated message from Neofox Gear Control System.</p>
                    <p style='margin-top: 10px;'>
                        <strong>Neofox Media</strong> | Equipment Management
                    </p>
                </div>
            </div>
        </body>
        </html>";
        
        return $html;
    }
}
?>