<?php
/**
 * Cron Job: Check for equipment needing maintenance
 * Schedule: Weekly on Monday at 8:00 AM
 * Command: /usr/local/bin/php /path/to/api/cron/maintenance-check.php
 */

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/utils/JsonDatabase.php';

echo "=== FOXY Maintenance Check ===\n";
echo "Running at: " . date('Y-m-d H:i:s') . "\n\n";

$assets = JsonDatabase::getAll('assets');
$needsMaintenance = [];
$highUsage = [];

foreach ($assets as $asset) {
    // Check if maintenance interval exceeded
    $interval = $asset['maintenance_interval_days'] ?? 90;
    $lastMaintenance = $asset['last_maintenance_date'] ?? $asset['created_at'] ?? date('Y-m-d', strtotime('-1 year'));

    $daysSinceMaintenance = floor((time() - strtotime($lastMaintenance)) / 86400);

    if ($daysSinceMaintenance >= $interval) {
        $asset['days_since_maintenance'] = $daysSinceMaintenance;
        $needsMaintenance[] = $asset;
    }

    // Check for high usage items (more than 50 checkouts)
    if (($asset['total_checkouts'] ?? 0) > 50) {
        $highUsage[] = $asset;
    }
}

echo "Equipment needing maintenance: " . count($needsMaintenance) . "\n";
echo "High usage items (50+ checkouts): " . count($highUsage) . "\n\n";

if (empty($needsMaintenance) && empty($highUsage)) {
    echo "All equipment is up to date.\n";
    exit(0);
}

// Build report
$report = "=== Weekly Maintenance Report ===\n";
$report .= "Generated: " . date('Y-m-d H:i:s') . "\n\n";

if (!empty($needsMaintenance)) {
    $report .= "** EQUIPMENT DUE FOR MAINTENANCE **\n";
    $report .= str_repeat('-', 50) . "\n";

    // Sort by days overdue
    usort($needsMaintenance, function($a, $b) {
        return $b['days_since_maintenance'] - $a['days_since_maintenance'];
    });

    foreach ($needsMaintenance as $asset) {
        $report .= "\n{$asset['asset_name']} ({$asset['asset_id']})\n";
        $report .= "  Category: {$asset['category']}\n";
        $report .= "  Days since maintenance: {$asset['days_since_maintenance']}\n";
        $report .= "  Total checkouts: " . ($asset['total_checkouts'] ?? 0) . "\n";
        $report .= "  Current status: {$asset['status']}\n";
        if ($asset['status'] === 'checked_out') {
            $report .= "  Current holder: {$asset['current_borrower']}\n";
        }
    }
    $report .= "\n";
}

if (!empty($highUsage)) {
    $report .= "\n** HIGH USAGE EQUIPMENT (Consider inspection) **\n";
    $report .= str_repeat('-', 50) . "\n";

    // Sort by usage
    usort($highUsage, function($a, $b) {
        return ($b['total_checkouts'] ?? 0) - ($a['total_checkouts'] ?? 0);
    });

    foreach (array_slice($highUsage, 0, 10) as $asset) {
        $report .= "{$asset['asset_name']} ({$asset['asset_id']}): {$asset['total_checkouts']} checkouts\n";
    }
}

$report .= "\n" . str_repeat('=', 50) . "\n";
$report .= "Please schedule maintenance for overdue items.\n";

// Output to console
echo $report;

// Send email to admin
if (defined('ADMIN_EMAIL') && ADMIN_EMAIL) {
    $subject = 'FOXY: Weekly Maintenance Report';
    if (count($needsMaintenance) > 0) {
        $subject .= ' - ' . count($needsMaintenance) . ' items due';
    }
    
    if (mail(ADMIN_EMAIL, $subject, $report)) {
        echo "\nReport sent to " . ADMIN_EMAIL . "\n";
    } else {
        echo "\nFailed to send email report.\n";
    }
}

echo "\n=== Complete ===\n";
