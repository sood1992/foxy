<?php
/**
 * Backup/Export data to JSON for migration
 *
 * Usage: php backup_data.php
 * Or via browser: backup_data.php
 *
 * Creates: foxy_backup_[timestamp].json
 */

require_once 'config/database.php';

class DataBackup {
    private $conn;

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();

        if (!$this->conn) {
            throw new Exception("Database connection failed");
        }
    }

    public function exportAll() {
        $backup = [
            'exported_at' => date('Y-m-d H:i:s'),
            'tables' => []
        ];

        // Tables to backup
        $tables = ['users', 'assets', 'transactions', 'asset_photos'];

        foreach ($tables as $table) {
            try {
                $stmt = $this->conn->query("SELECT * FROM `$table`");
                $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $backup['tables'][$table] = $data;
                echo "Exported $table: " . count($data) . " records\n";
            } catch (PDOException $e) {
                echo "Warning: Could not export $table - " . $e->getMessage() . "\n";
            }
        }

        return $backup;
    }

    public function saveToFile($backup) {
        $filename = 'foxy_backup_' . date('Y-m-d_His') . '.json';
        $json = json_encode($backup, JSON_PRETTY_PRINT);

        if (file_put_contents($filename, $json)) {
            echo "\nBackup saved to: $filename\n";
            echo "File size: " . round(filesize($filename) / 1024, 2) . " KB\n";
            return $filename;
        } else {
            throw new Exception("Failed to write backup file");
        }
    }
}

// Run backup
try {
    if (php_sapi_name() !== 'cli') {
        header('Content-Type: text/plain');
    }

    echo "=== Foxy Data Backup ===\n\n";

    $backup = new DataBackup();
    $data = $backup->exportAll();
    $filename = $backup->saveToFile($data);

    echo "\n=== Backup Complete ===\n";
    echo "Download this file and keep it safe!\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
