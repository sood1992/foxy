<?php
/**
 * Cron Job: Send overdue equipment reminders
 * Schedule: Daily at 9:00 AM
 * Command: /usr/local/bin/php /path/to/api/cron/overdue-reminders.php
 */

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/utils/JsonDatabase.php';
require_once dirname(__DIR__) . '/utils/EmailService.php';

echo "=== FOXY Overdue Reminders ===\n";
echo "Running at: " . date('Y-m-d H:i:s') . "\n\n";

$assets = JsonDatabase::getAll('assets');
$overdueAssets = [];

foreach ($assets as $asset) {
    if ($asset['status'] === 'checked_out' &&
        !empty($asset['expected_return_date']) &&
        strtotime($asset['expected_return_date']) < strtotime('today')) {
        $overdueAssets[] = $asset;
    }
}

if (empty($overdueAssets)) {
    echo "No overdue equipment found.\n";
    exit(0);
}

echo "Found " . count($overdueAssets) . " overdue items.\n\n";

// Group by borrower
$byBorrower = [];
foreach ($overdueAssets as $asset) {
    $borrower = $asset['current_borrower'];
    if (!isset($byBorrower[$borrower])) {
        $byBorrower[$borrower] = [];
    }
    $byBorrower[$borrower][] = $asset;
}

// Get users for email addresses
$users = JsonDatabase::getAll('users');
$userEmails = [];
foreach ($users as $user) {
    $userEmails[strtolower($user['username'])] = $user['email'];
}

// Send reminders to borrowers
foreach ($byBorrower as $borrower => $assets) {
    $email = $userEmails[strtolower($borrower)] ?? null;

    if ($email) {
        $itemList = '';
        foreach ($assets as $asset) {
            $daysOverdue = floor((time() - strtotime($asset['expected_return_date'])) / 86400);
            $itemList .= "- {$asset['asset_name']} ({$asset['asset_id']}) - {$daysOverdue} days overdue\n";
        }

        $subject = 'FOXY: Equipment Overdue Reminder';
        $message = "Hi {$borrower},\n\n";
        $message .= "The following equipment is overdue for return:\n\n";
        $message .= $itemList;
        $message .= "\nPlease return these items as soon as possible.\n\n";
        $message .= "Thank you,\nNeoFox Equipment Team";

        if (mail($email, $subject, $message)) {
            echo "Sent reminder to {$borrower} ({$email}) for " . count($assets) . " items.\n";
        } else {
            echo "Failed to send to {$borrower} ({$email})\n";
        }
    } else {
        echo "No email found for borrower: {$borrower}\n";
    }
}

// Send summary to admin
$adminSummary = "=== Daily Overdue Equipment Report ===\n";
$adminSummary .= "Date: " . date('Y-m-d') . "\n";
$adminSummary .= "Total overdue items: " . count($overdueAssets) . "\n\n";

foreach ($byBorrower as $borrower => $assets) {
    $adminSummary .= "** {$borrower} (" . count($assets) . " items) **\n";
    foreach ($assets as $asset) {
        $daysOverdue = floor((time() - strtotime($asset['expected_return_date'])) / 86400);
        $adminSummary .= "  - {$asset['asset_name']} ({$asset['asset_id']}): {$daysOverdue} days overdue\n";
    }
    $adminSummary .= "\n";
}

if (defined('ADMIN_EMAIL') && ADMIN_EMAIL) {
    mail(ADMIN_EMAIL, 'FOXY: Daily Overdue Report - ' . count($overdueAssets) . ' items', $adminSummary);
    echo "\nAdmin summary sent to " . ADMIN_EMAIL . "\n";
}

echo "\n=== Complete ===\n";
