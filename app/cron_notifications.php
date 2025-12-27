<?php
// cron_notifications.php - Daily notification sender
// This file should be run via cron job daily

// Set error reporting for cron jobs
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Set timezone
date_default_timezone_set('Asia/Kolkata'); // Adjust to your timezone

// Include required files
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/classes/NotificationSystem.php';
require_once __DIR__ . '/classes/MaintenanceTracker.php';

// Create log directory if it doesn't exist
$log_dir = __DIR__ . '/logs';
if (!is_dir($log_dir)) {
    mkdir($log_dir, 0755, true);
}

$log_file = $log_dir . '/notification_cron.log';

// Function to log messages
function logMessage($message) {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[{$timestamp}] {$message}" . PHP_EOL;
    file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
    echo $log_entry; // Also output to console if run manually
}

try {
    logMessage("=== Starting daily notification check ===");
    
    // Initialize database connection
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        throw new Exception("Failed to connect to database");
    }
    
    logMessage("Database connection established");
    
    // Initialize notification system and maintenance tracker
    $notifications = new NotificationSystem($db);
    $maintenance = new MaintenanceTracker($db);
    
    // Send overdue notifications
    logMessage("Checking for overdue equipment...");
    $overdue_sent = $notifications->sendOverdueNotifications();
    logMessage("Sent {$overdue_sent} overdue notifications");
    
    // Send maintenance reminders
    logMessage("Checking for upcoming maintenance...");
    $maintenance_sent = $notifications->sendMaintenanceNotifications();
    logMessage("Sent {$maintenance_sent} maintenance reminders");
    
    // Check for assets due for routine maintenance
    logMessage("Checking for assets due for routine maintenance...");
    $assets_due = $maintenance->getAssetsDueForRoutine();
    $routine_scheduled = 0;
    
    foreach ($assets_due as $asset) {
        // Auto-schedule routine maintenance for assets that haven't had maintenance in 90+ days
        $maintenance_data = [
            'asset_id' => $asset['id'],
            'maintenance_type' => 'routine',
            'scheduled_date' => date('Y-m-d H:i:s', strtotime('+7 days')), // Schedule for next week
            'next_maintenance_date' => date('Y-m-d H:i:s', strtotime('+7 days')),
            'assigned_to' => null,
            'priority' => 'medium',
            'notes' => 'Auto-scheduled routine maintenance - ' . $asset['days_since_maintenance'] . ' days since last maintenance',
            'estimated_duration' => 2, // 2 hours default
            'cost_estimate' => null
        ];
        
        try {
            if ($maintenance->scheduleMaintenance($maintenance_data)) {
                $routine_scheduled++;
                logMessage("Auto-scheduled routine maintenance for asset: {$asset['asset_name']} (ID: {$asset['asset_id']})");
            }
        } catch (Exception $e) {
            logMessage("Error scheduling maintenance for asset {$asset['asset_id']}: " . $e->getMessage());
        }
    }
    
    logMessage("Auto-scheduled {$routine_scheduled} routine maintenance tasks");
    
    // Summary
    $total_notifications = $overdue_sent + $maintenance_sent;
    logMessage("=== Daily notification check completed ===");
    logMessage("Summary: {$total_notifications} notifications sent, {$routine_scheduled} maintenance tasks scheduled");
    
    // Optional: Send daily summary email to admin
    if ($total_notifications > 0 || $routine_scheduled > 0) {
        $admin_summary = "
        <h3>Daily Neofox Gear Control Summary</h3>
        <p><strong>Date:</strong> " . date('Y-m-d H:i:s') . "</p>
        <ul>
            <li><strong>Overdue notifications sent:</strong> {$overdue_sent}</li>
            <li><strong>Maintenance reminders sent:</strong> {$maintenance_sent}</li>
            <li><strong>Routine maintenance auto-scheduled:</strong> {$routine_scheduled}</li>
        </ul>
        <p>Check the system dashboard for more details.</p>
        ";
        
        $notifications->sendEmail(
            'admin@neofoxmedia.com', // Change to your admin email
            'Daily Gear Control Summary - ' . date('Y-m-d'),
            $admin_summary
        );
        
        logMessage("Daily summary email sent to admin");
    }
    
} catch (Exception $e) {
    $error_message = "ERROR: " . $e->getMessage();
    logMessage($error_message);
    
    // Try to send error notification to admin
    try {
        $error_email = "
        <h3>Neofox Gear Control - Cron Job Error</h3>
        <p><strong>Date:</strong> " . date('Y-m-d H:i:s') . "</p>
        <p><strong>Error:</strong> {$e->getMessage()}</p>
        <p><strong>File:</strong> {$e->getFile()}</p>
        <p><strong>Line:</strong> {$e->getLine()}</p>
        <p>Please check the system and resolve this issue.</p>
        ";
        
        // Use simple mail() function for error notifications
        mail(
            'admin@neofoxmedia.com', // Change to your admin email
            'URGENT: Gear Control Cron Job Failed',
            $error_email,
            'Content-Type: text/html; charset=UTF-8' . "\r\n" .
            'From: Neofox Gear Control <noreply@neofoxmedia.com>'
        );
    } catch (Exception $mail_error) {
        logMessage("Failed to send error email: " . $mail_error->getMessage());
    }
    
    exit(1); // Exit with error code
}

// Optional: Clean up old log entries (keep last 30 days)
try {
    $log_content = file_get_contents($log_file);
    $log_lines = explode("\n", $log_content);
    
    // Keep only last 1000 lines to prevent log file from growing too large
    if (count($log_lines) > 1000) {
        $recent_lines = array_slice($log_lines, -1000);
        file_put_contents($log_file, implode("\n", $recent_lines));
        logMessage("Log file trimmed to last 1000 entries");
    }
} catch (Exception $e) {
    logMessage("Warning: Could not trim log file: " . $e->getMessage());
}

exit(0); // Exit successfully
?>