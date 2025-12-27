<?php
/**
 * Restore data from JSON backup
 *
 * Usage: php restore_data.php foxy_backup_2025-12-27_123456.json
 * Or via browser: restore_data.php?file=foxy_backup_2025-12-27_123456.json
 */

require_once 'config/database.php';

class DataRestore {
    private $conn;
    private $counts = [];

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();

        if (!$this->conn) {
            throw new Exception("Database connection failed");
        }
    }

    public function restore($filepath) {
        if (!file_exists($filepath)) {
            throw new Exception("Backup file not found: $filepath");
        }

        $json = file_get_contents($filepath);
        $backup = json_decode($json, true);

        if (!$backup || !isset($backup['tables'])) {
            throw new Exception("Invalid backup file format");
        }

        echo "Backup from: " . ($backup['exported_at'] ?? 'unknown') . "\n\n";

        // Restore in order (users first, then assets, then transactions)
        $restoreOrder = ['users', 'assets', 'asset_photos', 'transactions'];

        foreach ($restoreOrder as $table) {
            if (isset($backup['tables'][$table])) {
                $this->restoreTable($table, $backup['tables'][$table]);
            }
        }

        return $this->counts;
    }

    private function restoreTable($table, $data) {
        if (empty($data)) {
            echo "$table: No data to restore\n";
            return;
        }

        $this->counts[$table] = ['imported' => 0, 'skipped' => 0];

        foreach ($data as $row) {
            try {
                switch ($table) {
                    case 'users':
                        $this->restoreUser($row);
                        break;
                    case 'assets':
                        $this->restoreAsset($row);
                        break;
                    case 'asset_photos':
                        $this->restoreAssetPhoto($row);
                        break;
                    case 'transactions':
                        $this->restoreTransaction($row);
                        break;
                }
                $this->counts[$table]['imported']++;
            } catch (PDOException $e) {
                if (strpos($e->getMessage(), 'Duplicate') !== false) {
                    $this->counts[$table]['skipped']++;
                } else {
                    echo "  Error in $table: " . $e->getMessage() . "\n";
                }
            }
        }

        echo "$table: {$this->counts[$table]['imported']} imported, {$this->counts[$table]['skipped']} skipped\n";
    }

    private function restoreUser($row) {
        // Check if exists
        $check = $this->conn->prepare("SELECT id FROM users WHERE username = ?");
        $check->execute([$row['username']]);
        if ($check->fetch()) {
            throw new PDOException("Duplicate entry");
        }

        $stmt = $this->conn->prepare("
            INSERT INTO users (username, password, email, role, created_at)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $row['username'],
            $row['password'],
            $row['email'] ?? null,
            $row['role'] ?? 'team_member',
            $row['created_at'] ?? date('Y-m-d H:i:s')
        ]);
    }

    private function restoreAsset($row) {
        // Check if exists
        $check = $this->conn->prepare("SELECT id FROM assets WHERE asset_id = ?");
        $check->execute([$row['asset_id']]);
        if ($check->fetch()) {
            throw new PDOException("Duplicate entry");
        }

        $stmt = $this->conn->prepare("
            INSERT INTO assets (
                asset_id, asset_name, category, description, serial_number,
                qr_code, status, current_borrower, checkout_date, expected_return_date,
                condition_status, notes, created_at, updated_at,
                last_maintenance_date, next_maintenance_due, maintenance_interval_days,
                last_returned_date, total_checkouts
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $row['asset_id'],
            $row['asset_name'],
            $row['category'],
            $row['description'] ?? null,
            $row['serial_number'] ?? null,
            $row['qr_code'] ?? null,
            $row['status'] ?? 'available',
            $row['current_borrower'] ?? null,
            $row['checkout_date'] ?? null,
            $row['expected_return_date'] ?? null,
            $row['condition_status'] ?? 'excellent',
            $row['notes'] ?? null,
            $row['created_at'] ?? date('Y-m-d H:i:s'),
            $row['updated_at'] ?? date('Y-m-d H:i:s'),
            $row['last_maintenance_date'] ?? null,
            $row['next_maintenance_due'] ?? null,
            $row['maintenance_interval_days'] ?? 90,
            $row['last_returned_date'] ?? null,
            $row['total_checkouts'] ?? 0
        ]);
    }

    private function restoreAssetPhoto($row) {
        $stmt = $this->conn->prepare("
            INSERT INTO asset_photos (asset_id, photo_path, photo_type, upload_date, file_size, mime_type, description)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $row['asset_id'],
            $row['photo_path'],
            $row['photo_type'] ?? 'main',
            $row['upload_date'] ?? date('Y-m-d H:i:s'),
            $row['file_size'] ?? null,
            $row['mime_type'] ?? 'image/jpeg',
            $row['description'] ?? null
        ]);
    }

    private function restoreTransaction($row) {
        $stmt = $this->conn->prepare("
            INSERT INTO transactions (asset_id, borrower_name, transaction_type, purpose, condition_on_return, notes, transaction_date)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $row['asset_id'],
            $row['borrower_name'],
            $row['transaction_type'],
            $row['purpose'] ?? null,
            $row['condition_on_return'] ?? null,
            $row['notes'] ?? null,
            $row['transaction_date'] ?? date('Y-m-d H:i:s')
        ]);
    }
}

// Run restore
try {
    if (php_sapi_name() !== 'cli') {
        header('Content-Type: text/plain');
    }

    $file = $argv[1] ?? $_GET['file'] ?? null;

    if (!$file) {
        echo "Usage: php restore_data.php <backup_file.json>\n";
        echo "   Or: restore_data.php?file=<backup_file.json>\n";
        exit(1);
    }

    if ($file[0] !== '/') {
        $file = __DIR__ . '/' . $file;
    }

    echo "=== Foxy Data Restore ===\n\n";

    $restore = new DataRestore();
    $restore->restore($file);

    echo "\n=== Restore Complete ===\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
