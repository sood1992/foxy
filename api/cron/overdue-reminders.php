<?php
/**
 * Cron Job: Send Overdue Reminders
 *
 * Run this script daily via cron:
 * 0 9 * * * /usr/bin/php /home/sunburni/public_html/foxy/api/cron/overdue-reminders.php
 *
 * This will run every day at 9:00 AM
 */

// Set up paths
define('BASE_PATH', dirname(dirname(__FILE__)));

require_once BASE_PATH . '/config.php';
require_once BASE_PATH . '/utils/JsonDatabase.php';
require_once BASE_PATH . '/utils/EmailService.php';

// Log function
function logMessage($message) {
    $logFile = BASE_PATH . '/logs/cron.log';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND | LOCK_EX);
    echo "[$timestamp] $message\n";
}

// Ensure logs directory exists
$logsDir = BASE_PATH . '/logs';
if (!is_dir($logsDir)) {
    mkdir($logsDir, 0755, true);
}

logMessage("Starting overdue reminders check...");

try {
    // Get all assets
    $assets = JsonDatabase::getAll('assets');
    $now = time();
    $today = date('Y-m-d');

    // Find overdue assets
    $overdueAssets = [];
    foreach ($assets as $asset) {
        if ($asset['status'] !== 'checked_out') continue;
        if (empty($asset['expected_return_date'])) continue;

        $dueDate = strtotime($asset['expected_return_date']);
        if ($dueDate < $now) {
            $daysOverdue = floor(($now - $dueDate) / (60 * 60 * 24));
            $asset['days_overdue'] = $daysOverdue;
            $overdueAssets[] = $asset;
        }
    }

    if (empty($overdueAssets)) {
        logMessage("No overdue assets found.");
        exit(0);
    }

    logMessage("Found " . count($overdueAssets) . " overdue asset(s).");

    // Group by borrower
    $byBorrower = [];
    foreach ($overdueAssets as $asset) {
        $borrower = $asset['current_borrower'] ?? 'Unknown';
        if (!isset($byBorrower[$borrower])) {
            $byBorrower[$borrower] = [];
        }
        $byBorrower[$borrower][] = $asset;
    }

    // Get users for email addresses
    $users = JsonDatabase::getAll('users');
    $userEmails = [];
    foreach ($users as $user) {
        $userEmails[strtolower($user['username'])] = $user['email'] ?? null;
    }

    // Send reminders to borrowers
    foreach ($byBorrower as $borrower => $assets) {
        $borrowerEmail = $userEmails[strtolower($borrower)] ?? null;

        if ($borrowerEmail) {
            $success = EmailService::sendOverdueReminder($borrowerEmail, $borrower, $assets);
            if ($success) {
                logMessage("Sent reminder to $borrower ($borrowerEmail) for " . count($assets) . " item(s).");
            } else {
                logMessage("Failed to send reminder to $borrower ($borrowerEmail).");
            }
        } else {
            logMessage("No email found for borrower: $borrower");
        }
    }

    // Send summary to admin
    $adminEmail = ADMIN_EMAIL;
    if (!empty($adminEmail)) {
        $success = EmailService::sendOverdueSummaryToAdmin($adminEmail, $overdueAssets);
        if ($success) {
            logMessage("Sent overdue summary to admin ($adminEmail).");
        } else {
            logMessage("Failed to send summary to admin.");
        }
    }

    logMessage("Overdue reminders check completed.");

} catch (Exception $e) {
    logMessage("Error: " . $e->getMessage());
    exit(1);
}

exit(0);
