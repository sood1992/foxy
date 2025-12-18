<?php
/**
 * Data Migration Script
 * Converts SQL dump data to JSON format for FOXY Inventory System
 *
 * Run this script once to migrate your existing MySQL data to JSON:
 * php api/migrate-data.php
 */

echo "FOXY Data Migration Tool\n";
echo "========================\n\n";

// Configuration
define('DATA_PATH', __DIR__ . '/data');
define('SQL_FILE', dirname(__DIR__) . '/sunburni_foxy.sql');

// Ensure data directory exists
if (!is_dir(DATA_PATH)) {
    mkdir(DATA_PATH, 0755, true);
    echo "Created data directory: " . DATA_PATH . "\n";
}

// Read SQL file
if (!file_exists(SQL_FILE)) {
    die("Error: SQL file not found at " . SQL_FILE . "\n");
}

$sqlContent = file_get_contents(SQL_FILE);
echo "Reading SQL file...\n";

/**
 * Parse INSERT statements from SQL
 */
function parseInserts($sql, $tableName) {
    $pattern = "/INSERT INTO `{$tableName}`.*?VALUES\s*(.+?);/s";

    if (!preg_match($pattern, $sql, $match)) {
        return [];
    }

    $valuesStr = $match[1];

    // Extract column names
    $colPattern = "/INSERT INTO `{$tableName}` \(([^)]+)\)/";
    preg_match($colPattern, $sql, $colMatch);

    $columns = [];
    if (!empty($colMatch[1])) {
        preg_match_all('/`([^`]+)`/', $colMatch[1], $colNames);
        $columns = $colNames[1];
    }

    // Parse rows
    $rows = [];
    $rowPattern = "/\(([^)]+)\)/";
    preg_match_all($rowPattern, $valuesStr, $rowMatches);

    foreach ($rowMatches[1] as $rowStr) {
        $values = parseRowValues($rowStr);

        if (count($values) === count($columns)) {
            $row = array_combine($columns, $values);
            $rows[] = $row;
        }
    }

    return $rows;
}

/**
 * Parse values from a single row
 */
function parseRowValues($str) {
    $values = [];
    $current = '';
    $inQuote = false;
    $escaped = false;

    for ($i = 0; $i < strlen($str); $i++) {
        $char = $str[$i];

        if ($escaped) {
            $current .= $char;
            $escaped = false;
            continue;
        }

        if ($char === '\\') {
            $escaped = true;
            continue;
        }

        if ($char === "'" && !$inQuote) {
            $inQuote = true;
            continue;
        }

        if ($char === "'" && $inQuote) {
            $inQuote = false;
            continue;
        }

        if ($char === ',' && !$inQuote) {
            $values[] = trim($current) === 'NULL' ? null : trim($current);
            $current = '';
            continue;
        }

        $current .= $char;
    }

    // Add last value
    if ($current !== '' || $inQuote) {
        $values[] = trim($current) === 'NULL' ? null : trim($current);
    }

    return $values;
}

// Migrate Assets
echo "Migrating assets...\n";
$assets = parseInserts($sqlContent, 'assets');
file_put_contents(DATA_PATH . '/assets.json', json_encode($assets, JSON_PRETTY_PRINT));
echo "  - Migrated " . count($assets) . " assets\n";

// Migrate Asset Photos
echo "Migrating asset photos...\n";
$photos = parseInserts($sqlContent, 'asset_photos');
file_put_contents(DATA_PATH . '/asset_photos.json', json_encode($photos, JSON_PRETTY_PRINT));
echo "  - Migrated " . count($photos) . " photos\n";

// Migrate Equipment Photos (for QC)
echo "Migrating equipment photos...\n";
$eqPhotos = parseInserts($sqlContent, 'equipment_photos');
file_put_contents(DATA_PATH . '/equipment_photos.json', json_encode($eqPhotos, JSON_PRETTY_PRINT));
echo "  - Migrated " . count($eqPhotos) . " equipment photos\n";

// Migrate Transactions
echo "Migrating transactions...\n";
$transactions = parseInserts($sqlContent, 'transactions');
file_put_contents(DATA_PATH . '/transactions.json', json_encode($transactions, JSON_PRETTY_PRINT));
echo "  - Migrated " . count($transactions) . " transactions\n";

// Migrate Users
echo "Migrating users...\n";
$users = parseInserts($sqlContent, 'users');
file_put_contents(DATA_PATH . '/users.json', json_encode($users, JSON_PRETTY_PRINT));
echo "  - Migrated " . count($users) . " users\n";

// Migrate Gear Requests
echo "Migrating gear requests...\n";
$requests = parseInserts($sqlContent, 'gear_requests');
file_put_contents(DATA_PATH . '/gear_requests.json', json_encode($requests, JSON_PRETTY_PRINT));
echo "  - Migrated " . count($requests) . " requests\n";

// Migrate Maintenance Issues
echo "Migrating maintenance issues...\n";
$issues = parseInserts($sqlContent, 'maintenance_issues');
file_put_contents(DATA_PATH . '/maintenance_issues.json', json_encode($issues, JSON_PRETTY_PRINT));
echo "  - Migrated " . count($issues) . " issues\n";

// Migrate Maintenance Schedule
echo "Migrating maintenance schedule...\n";
$schedule = parseInserts($sqlContent, 'maintenance_schedule');
file_put_contents(DATA_PATH . '/maintenance_schedule.json', json_encode($schedule, JSON_PRETTY_PRINT));
echo "  - Migrated " . count($schedule) . " scheduled tasks\n";

// Migrate Maintenance History
echo "Migrating maintenance history...\n";
$history = parseInserts($sqlContent, 'maintenance_history');
file_put_contents(DATA_PATH . '/maintenance_history.json', json_encode($history, JSON_PRETTY_PRINT));
echo "  - Migrated " . count($history) . " history records\n";

// Migrate Notification Log
echo "Migrating notification log...\n";
$notifications = parseInserts($sqlContent, 'notification_log');
file_put_contents(DATA_PATH . '/notification_log.json', json_encode($notifications, JSON_PRETTY_PRINT));
echo "  - Migrated " . count($notifications) . " notifications\n";

echo "\n========================\n";
echo "Migration complete!\n";
echo "Data files saved to: " . DATA_PATH . "\n";
echo "\nFiles created:\n";
foreach (glob(DATA_PATH . '/*.json') as $file) {
    $size = round(filesize($file) / 1024, 2);
    echo "  - " . basename($file) . " ({$size} KB)\n";
}
