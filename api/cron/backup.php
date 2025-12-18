<?php
/**
 * Cron Job: Backup JSON database files
 * Schedule: Daily at 2:00 AM
 * Command: /usr/local/bin/php /path/to/api/cron/backup.php
 */

require_once dirname(__DIR__) . '/config.php';

echo "=== FOXY Daily Backup ===\n";
echo "Running at: " . date('Y-m-d H:i:s') . "\n\n";

$dataDir = DATA_PATH;
$backupDir = dirname(__DIR__) . '/backups';
$date = date('Y-m-d_H-i-s');

// Create backup directory if not exists
if (!is_dir($backupDir)) {
    mkdir($backupDir, 0755, true);
    echo "Created backup directory: {$backupDir}\n";
}

// Create dated backup folder
$backupPath = $backupDir . '/backup_' . $date;
mkdir($backupPath, 0755, true);

// Copy all JSON files
$files = glob($dataDir . '/*.json');
$backupCount = 0;

foreach ($files as $file) {
    $filename = basename($file);
    if (copy($file, $backupPath . '/' . $filename)) {
        $size = round(filesize($file) / 1024, 2);
        echo "Backed up: {$filename} ({$size} KB)\n";
        $backupCount++;
    } else {
        echo "FAILED: {$filename}\n";
    }
}

echo "\nBacked up {$backupCount} files.\n";

// Create ZIP archive
$zipFile = $backupDir . '/backup_' . $date . '.zip';
$zip = new ZipArchive();

if ($zip->open($zipFile, ZipArchive::CREATE) === TRUE) {
    foreach (glob($backupPath . '/*') as $file) {
        $zip->addFile($file, basename($file));
    }
    $zip->close();
    
    $zipSize = round(filesize($zipFile) / 1024, 2);
    echo "Created ZIP: backup_{$date}.zip ({$zipSize} KB)\n";

    // Remove folder, keep only ZIP
    array_map('unlink', glob($backupPath . '/*'));
    rmdir($backupPath);
} else {
    echo "Failed to create ZIP archive.\n";
}

// Delete backups older than 30 days
echo "\nCleaning old backups...\n";
$oldBackups = glob($backupDir . '/backup_*.zip');
$deleted = 0;

foreach ($oldBackups as $backup) {
    if (filemtime($backup) < strtotime('-30 days')) {
        unlink($backup);
        echo "Deleted: " . basename($backup) . "\n";
        $deleted++;
    }
}

if ($deleted === 0) {
    echo "No old backups to delete.\n";
} else {
    echo "Deleted {$deleted} old backups.\n";
}

// List current backups
echo "\nCurrent backups:\n";
$currentBackups = glob($backupDir . '/backup_*.zip');
rsort($currentBackups);
foreach (array_slice($currentBackups, 0, 5) as $backup) {
    $size = round(filesize($backup) / 1024, 2);
    $date = date('Y-m-d H:i', filemtime($backup));
    echo "  - " . basename($backup) . " ({$size} KB) - {$date}\n";
}

echo "\n=== Backup Complete ===\n";
