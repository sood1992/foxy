<?php
// classes/WhatsAppSender.php
class WhatsAppSender {
    
    // OPTION 1: Twilio WhatsApp API (Recommended - Most Reliable)
    private static $twilio_account_sid = 'YOUR_TWILIO_ACCOUNT_SID';
    private static $twilio_auth_token = 'YOUR_TWILIO_AUTH_TOKEN';
    private static $twilio_whatsapp_number = 'whatsapp:+19037761266'; // Twilio Sandbox number
    
    // OPTION 2: Ultramsg API (Alternative - Quick Setup)
    private static $ultramsg_token = 'YOUR_ULTRAMSG_TOKEN';
    private static $ultramsg_instance_id = 'YOUR_INSTANCE_ID';
    
    /**
     * Send PDF via WhatsApp using Twilio (Primary Method)
     */
    public static function sendPDFViaTwilio($recipient_phone, $pdf_url, $borrower_name, $equipment_count) {
        try {
            // Format phone number (ensure it starts with country code)
            $formatted_phone = self::formatPhoneNumber($recipient_phone);
            
            $message_body = "🎬 *Neofox Media - Equipment Checkout Agreement*\n\n";
            $message_body .= "Hi {$borrower_name}!\n\n";
            $message_body .= "Your equipment checkout agreement is ready:\n";
            $message_body .= "📦 *{$equipment_count} items* checked out\n";
            $message_body .= "📋 *Agreement PDF:* {$pdf_url}\n\n";
            $message_body .= "Please sign and return the agreement.\n\n";
            $message_body .= "Need help? Reply to this message or call us.\n\n";
            $message_body .= "_Neofox Media Equipment Control_";
            
            // Twilio API endpoint
            $url = "https://api.twilio.com/2010-04-01/Accounts/" . self::$twilio_account_sid . "/Messages.json";
            
            // Prepare data
            $data = [
                'From' => self::$twilio_whatsapp_number,
                'To' => 'whatsapp:' . $formatted_phone,
                'Body' => $message_body,
                'MediaUrl' => $pdf_url // Twilio can send PDF as media
            ];
            
            // cURL request
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_USERPWD, self::$twilio_account_sid . ':' . self::$twilio_auth_token);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/x-www-form-urlencoded'
            ]);
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($http_code == 201) {
                error_log("✅ WhatsApp sent successfully via Twilio to: {$formatted_phone}");
                return ['success' => true, 'method' => 'twilio', 'response' => $response];
            } else {
                error_log("❌ Twilio WhatsApp failed: {$response}");
                return ['success' => false, 'error' => $response, 'http_code' => $http_code];
            }
            
        } catch (Exception $e) {
            error_log("❌ Twilio WhatsApp exception: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Send PDF via WhatsApp using Ultramsg (Fallback Method)
     */
    public static function sendPDFViaUltramsg($recipient_phone, $pdf_url, $borrower_name, $equipment_count) {
        try {
            $formatted_phone = self::formatPhoneNumber($recipient_phone);
            
            // Send text message first
            $message = "🎬 *Neofox Media - Equipment Checkout*\n\n";
            $message .= "Hi {$borrower_name}!\n\n";
            $message .= "✅ {$equipment_count} items checked out successfully\n";
            $message .= "📋 Your agreement: {$pdf_url}\n\n";
            $message .= "Please download, sign & return.\n\n";
            $message .= "_Neofox Equipment Control_";
            
            // Ultramsg API endpoint
            $url = "https://api.ultramsg.com/" . self::$ultramsg_instance_id . "/messages/chat";
            
            $data = [
                'token' => self::$ultramsg_token,
                'to' => $formatted_phone,
                'body' => $message
            ];
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($http_code == 200) {
                error_log("✅ WhatsApp sent successfully via Ultramsg to: {$formatted_phone}");
                
                // Also try to send the PDF as document
                self::sendDocumentViaUltramsg($formatted_phone, $pdf_url, "Equipment_Agreement.pdf");
                
                return ['success' => true, 'method' => 'ultramsg', 'response' => $response];
            } else {
                error_log("❌ Ultramsg WhatsApp failed: {$response}");
                return ['success' => false, 'error' => $response, 'http_code' => $http_code];
            }
            
        } catch (Exception $e) {
            error_log("❌ Ultramsg WhatsApp exception: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Send document via Ultramsg
     */
    private static function sendDocumentViaUltramsg($phone, $pdf_url, $filename) {
        try {
            $url = "https://api.ultramsg.com/" . self::$ultramsg_instance_id . "/messages/document";
            
            $data = [
                'token' => self::$ultramsg_token,
                'to' => $phone,
                'document' => $pdf_url,
                'caption' => "📋 Equipment Checkout Agreement - Please sign and return"
            ];
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($http_code == 200) {
                error_log("✅ Document sent via Ultramsg");
                return true;
            } else {
                error_log("❌ Document send failed via Ultramsg: {$response}");
                return false;
            }
            
        } catch (Exception $e) {
            error_log("❌ Document send exception: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Main method to send WhatsApp with auto-fallback
     */
    public static function sendEquipmentCheckoutPDF($recipient_phone, $pdf_url, $borrower_name, $equipment_count) {
        if (empty($recipient_phone)) {
            return ['success' => false, 'error' => 'Phone number required'];
        }
        
        // Try Twilio first (more reliable)
        if (!empty(self::$twilio_account_sid) && !empty(self::$twilio_auth_token)) {
            $result = self::sendPDFViaTwilio($recipient_phone, $pdf_url, $borrower_name, $equipment_count);
            if ($result['success']) {
                return $result;
            }
            error_log("Twilio failed, trying Ultramsg...");
        }
        
        // Fallback to Ultramsg
        if (!empty(self::$ultramsg_token) && !empty(self::$ultramsg_instance_id)) {
            return self::sendPDFViaUltramsg($recipient_phone, $pdf_url, $borrower_name, $equipment_count);
        }
        
        return ['success' => false, 'error' => 'No WhatsApp service configured'];
    }
    
    /**
     * Format phone number for international use
     */
    private static function formatPhoneNumber($phone) {
        // Remove all non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // Add country code if missing (assuming US +1 for now)
        if (strlen($phone) == 10) {
            $phone = '1' . $phone;
        }
        
        // Ensure it starts with +
        if (!str_starts_with($phone, '+')) {
            $phone = '+' . $phone;
        }
        
        return $phone;
    }
    
    /**
     * Get user's phone number from database
     */
    public static function getUserPhone($username, $db_connection) {
        try {
            // First try to get from users table (you may need to add phone column)
            $query = "SELECT phone FROM users WHERE username = :username";
            $stmt = $db_connection->prepare($query);
            $stmt->bindParam(":username", $username);
            $stmt->execute();
            
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($user && !empty($user['phone'])) {
                return $user['phone'];
            }
            
            // Fallback: check if phone is stored in email field or another field
            // You might need to adjust this based on your database structure
            
            return null;
        } catch (PDOException $e) {
            error_log("Error getting user phone: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Test WhatsApp functionality
     */
    public static function testWhatsApp($test_phone = null) {
        $test_phone = $test_phone ?: '+1234567890'; // Replace with your test number
        
        $result = self::sendEquipmentCheckoutPDF(
            $test_phone,
            'https://example.com/test.pdf',
            'Test User',
            3
        );
        
        if ($result['success']) {
            error_log("✅ WhatsApp test successful via " . $result['method']);
        } else {
            error_log("❌ WhatsApp test failed: " . $result['error']);
        }
        
        return $result;
    }
}

/*
==============================================
WHATSAPP SETUP INSTRUCTIONS
==============================================

OPTION 1: Twilio WhatsApp (Recommended)
1. Go to https://www.twilio.com/whatsapp
2. Create account and get Account SID and Auth Token
3. For production: Apply for WhatsApp Business API approval
4. For testing: Use Twilio Sandbox (faster setup)
5. Replace the credentials in this file

OPTION 2: Ultramsg (Alternative)
1. Go to https://ultramsg.com/
2. Create account and get API token
3. Connect your WhatsApp number
4. Get Instance ID from dashboard
5. Replace credentials in this file

OPTION 3: Other Services (Pick any one)
- ChatAPI: https://chat-api.com/
- WhatsMate: https://whatsmate.com/
- Green API: https://green-api.com/

DATABASE MODIFICATION:
Add phone column to users table:
ALTER TABLE users ADD COLUMN phone VARCHAR(20) AFTER email;
*/
?>