<?php
/**
 * Import data from phpMyAdmin JSON export
 *
 * Usage:
 *   1. Place your exported JSON file as 'old_data.json' in the same directory
 *   2. Run: php import_phpmyadmin_json.php
 *
 * Or run via browser with: import_phpmyadmin_json.php?file=old_data.json
 */

require_once 'config/database.php';

class PhpMyAdminJsonImporter {
    private $conn;
    private $importedCounts = [];
    private $errors = [];
    private $skipped = [];

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();

        if (!$this->conn) {
            throw new Exception("Database connection failed");
        }
    }

    /**
     * Parse phpMyAdmin JSON export format
     * Format can be either:
     * - JSON lines (one JSON object per line)
     * - JSON array containing objects
     */
    public function parseJsonFile($filepath) {
        if (!file_exists($filepath)) {
            throw new Exception("File not found: $filepath");
        }

        $content = file_get_contents($filepath);

        // Try parsing as standard JSON first
        $data = json_decode($content, true);

        if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
            return $data;
        }

        // Try parsing as JSON lines (phpMyAdmin format)
        $lines = explode("\n", $content);
        $objects = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;

            // Remove trailing comma if present
            $line = rtrim($line, ',');

            $obj = json_decode($line, true);
            if ($obj !== null) {
                $objects[] = $obj;
            }
        }

        if (!empty($objects)) {
            return $objects;
        }

        throw new Exception("Could not parse JSON file. Last error: " . json_last_error_msg());
    }

    /**
     * Main import function
     */
    public function import($filepath) {
        echo "Starting import from: $filepath\n";
        echo str_repeat("=", 60) . "\n\n";

        $data = $this->parseJsonFile($filepath);
        $tables = [];

        // Extract tables from phpMyAdmin format
        foreach ($data as $item) {
            if (isset($item['type'])) {
                if ($item['type'] === 'header') {
                    echo "phpMyAdmin Export Version: " . ($item['version'] ?? 'unknown') . "\n";
                } elseif ($item['type'] === 'database') {
                    echo "Database: " . ($item['name'] ?? 'unknown') . "\n\n";
                } elseif ($item['type'] === 'table' && isset($item['data'])) {
                    $tables[$item['name']] = $item['data'];
                }
            }
        }

        // Import order matters due to foreign keys
        $importOrder = ['users', 'assets', 'transactions'];

        foreach ($importOrder as $tableName) {
            if (isset($tables[$tableName])) {
                echo "Importing table: $tableName (" . count($tables[$tableName]) . " records)\n";
                $this->importTable($tableName, $tables[$tableName]);
                echo "\n";
            }
        }

        // Import any remaining tables
        foreach ($tables as $tableName => $tableData) {
            if (!in_array($tableName, $importOrder)) {
                echo "Importing table: $tableName (" . count($tableData) . " records)\n";
                $this->importTable($tableName, $tableData);
                echo "\n";
            }
        }

        $this->printSummary();
    }

    /**
     * Import a single table
     */
    private function importTable($tableName, $data) {
        if (empty($data)) {
            echo "  No data to import\n";
            return;
        }

        $this->importedCounts[$tableName] = 0;
        $this->skipped[$tableName] = 0;

        switch ($tableName) {
            case 'users':
                $this->importUsers($data);
                break;
            case 'assets':
                $this->importAssets($data);
                break;
            case 'transactions':
                $this->importTransactions($data);
                break;
            default:
                $this->importGenericTable($tableName, $data);
        }
    }

    /**
     * Import users table
     */
    private function importUsers($data) {
        foreach ($data as $row) {
            try {
                // Check if user already exists
                $checkStmt = $this->conn->prepare("SELECT id FROM users WHERE username = ?");
                $checkStmt->execute([$row['username']]);

                if ($checkStmt->fetch()) {
                    echo "  Skipping existing user: {$row['username']}\n";
                    $this->skipped['users']++;
                    continue;
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

                $this->importedCounts['users']++;
                echo "  Imported user: {$row['username']}\n";

            } catch (PDOException $e) {
                $this->errors[] = "Users - {$row['username']}: " . $e->getMessage();
            }
        }
    }

    /**
     * Import assets table
     */
    private function importAssets($data) {
        foreach ($data as $row) {
            try {
                // Check if asset already exists
                $checkStmt = $this->conn->prepare("SELECT id FROM assets WHERE asset_id = ?");
                $checkStmt->execute([$row['asset_id']]);

                if ($checkStmt->fetch()) {
                    echo "  Skipping existing asset: {$row['asset_id']}\n";
                    $this->skipped['assets']++;
                    continue;
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

                $this->importedCounts['assets']++;
                echo "  Imported asset: {$row['asset_id']} - {$row['asset_name']}\n";

            } catch (PDOException $e) {
                $this->errors[] = "Assets - {$row['asset_id']}: " . $e->getMessage();
            }
        }
    }

    /**
     * Import transactions table
     */
    private function importTransactions($data) {
        foreach ($data as $row) {
            try {
                // Check if transaction ID already exists (if provided)
                if (isset($row['id'])) {
                    $checkStmt = $this->conn->prepare("SELECT id FROM transactions WHERE id = ?");
                    $checkStmt->execute([$row['id']]);

                    if ($checkStmt->fetch()) {
                        $this->skipped['transactions']++;
                        continue;
                    }
                }

                // Verify asset exists
                $assetCheck = $this->conn->prepare("SELECT asset_id FROM assets WHERE asset_id = ?");
                $assetCheck->execute([$row['asset_id']]);

                if (!$assetCheck->fetch()) {
                    echo "  Warning: Asset {$row['asset_id']} not found, skipping transaction\n";
                    $this->skipped['transactions']++;
                    continue;
                }

                $stmt = $this->conn->prepare("
                    INSERT INTO transactions (
                        asset_id, borrower_name, transaction_type, purpose,
                        condition_on_return, notes, transaction_date
                    ) VALUES (?, ?, ?, ?, ?, ?, ?)
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

                $this->importedCounts['transactions']++;

            } catch (PDOException $e) {
                $this->errors[] = "Transactions - Asset {$row['asset_id']}: " . $e->getMessage();
            }
        }

        echo "  Imported {$this->importedCounts['transactions']} transactions\n";
    }

    /**
     * Generic table importer for other tables
     */
    private function importGenericTable($tableName, $data) {
        if (empty($data)) return;

        $this->importedCounts[$tableName] = 0;
        $this->skipped[$tableName] = 0;

        // Get column names from first row
        $columns = array_keys($data[0]);

        // Remove 'id' column to let auto-increment work
        $columns = array_filter($columns, fn($col) => $col !== 'id');
        $columns = array_values($columns);

        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
        $columnList = implode(', ', array_map(fn($c) => "`$c`", $columns));

        $stmt = $this->conn->prepare("INSERT INTO `$tableName` ($columnList) VALUES ($placeholders)");

        foreach ($data as $row) {
            try {
                $values = [];
                foreach ($columns as $col) {
                    $values[] = $row[$col] ?? null;
                }
                $stmt->execute($values);
                $this->importedCounts[$tableName]++;
            } catch (PDOException $e) {
                $this->errors[] = "$tableName: " . $e->getMessage();
                $this->skipped[$tableName]++;
            }
        }

        echo "  Imported {$this->importedCounts[$tableName]} records\n";
    }

    /**
     * Print import summary
     */
    private function printSummary() {
        echo "\n" . str_repeat("=", 60) . "\n";
        echo "IMPORT SUMMARY\n";
        echo str_repeat("=", 60) . "\n\n";

        echo "Records imported:\n";
        foreach ($this->importedCounts as $table => $count) {
            $skipped = $this->skipped[$table] ?? 0;
            echo "  $table: $count imported";
            if ($skipped > 0) {
                echo ", $skipped skipped";
            }
            echo "\n";
        }

        if (!empty($this->errors)) {
            echo "\nErrors encountered:\n";
            foreach ($this->errors as $error) {
                echo "  - $error\n";
            }
        }

        echo "\nImport completed!\n";
    }
}

// Run the importer
try {
    // Get file from command line argument or GET parameter
    $file = $argv[1] ?? $_GET['file'] ?? 'old_data.json';

    // Make path relative to script directory if not absolute
    if ($file[0] !== '/') {
        $file = __DIR__ . '/' . $file;
    }

    // Set content type for CLI vs browser
    if (php_sapi_name() === 'cli') {
        // CLI mode - already outputting text
    } else {
        header('Content-Type: text/plain');
    }

    $importer = new PhpMyAdminJsonImporter();
    $importer->import($file);

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
