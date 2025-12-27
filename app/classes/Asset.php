<?php
// Enhanced Asset.php with bulk delete and crew management functionality
require_once 'classes/EmailNotification.php';

class Asset {
    private $conn;
    private $table_name = "assets";
    private $errors = [];

    public function __construct($db) {
        $this->conn = $db;
    }

    public function getErrors() {
        return $this->errors;
    }

    private function validateAssetData($data) {
        $this->errors = [];
        
        if (empty($data['asset_name'])) {
            $this->errors[] = "Asset name is required";
        }
        
        if (empty($data['category'])) {
            $this->errors[] = "Category is required";
        }
        
        if (empty($data['asset_id'])) {
            $this->errors[] = "Asset ID is required";
        } elseif ($this->assetIdExists($data['asset_id'])) {
            $this->errors[] = "Asset ID already exists";
        }
        
        return empty($this->errors);
    }

    private function assetIdExists($asset_id) {
        $query = "SELECT COUNT(*) FROM " . $this->table_name . " WHERE asset_id = :asset_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":asset_id", $asset_id);
        $stmt->execute();
        return $stmt->fetchColumn() > 0;
    }

    /**
     * Get all equipment currently checked out by a specific borrower
     */
    public function getBorrowerEquipment($borrower_name) {
        $query = "SELECT * FROM " . $this->table_name . " 
                  WHERE current_borrower = :borrower AND status = 'checked_out' 
                  ORDER BY checkout_date DESC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":borrower", $borrower_name);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Send equipment list email to borrower
     */
    private function sendEquipmentListEmail($borrower_name, $action, $asset_name, $additional_info = '') {
        try {
            // Only send emails if EmailNotification class exists
            if (!class_exists('EmailNotification')) {
                error_log("EmailNotification class not found");
                return false;
            }
            
            // Get borrower's email
            $email = EmailNotification::getUserEmail($borrower_name, $this->conn);
            if (!$email) {
                error_log("No email found for borrower: {$borrower_name}");
                return false;
            }
            
            // Get current equipment list for this borrower
            $current_equipment = $this->getBorrowerEquipment($borrower_name);
            
            // Send appropriate email based on action
            if ($action === 'checkout') {
                return EmailNotification::sendCheckoutConfirmation(
                    $email, 
                    $asset_name, 
                    $borrower_name, 
                    $additional_info, // expected_return date
                    $current_equipment
                );
            } elseif ($action === 'checkin') {
                return EmailNotification::sendCheckinConfirmation(
                    $email, 
                    $asset_name, 
                    $borrower_name, 
                    $additional_info, // condition
                    $current_equipment
                );
            }
            
            return false;
        } catch (Exception $e) {
            error_log("Error sending equipment list email: " . $e->getMessage());
            return false;
        }
    }

    public function create($data) {
        if (!$this->validateAssetData($data)) {
            return false;
        }

        try {
            $this->conn->beginTransaction();
            
            $query = "INSERT INTO " . $this->table_name . " 
                      (asset_id, asset_name, category, description, serial_number, qr_code, status, condition_status, notes) 
                      VALUES (:asset_id, :asset_name, :category, :description, :serial_number, :qr_code, :status, :condition_status, :notes)";
            
            $stmt = $this->conn->prepare($query);
            
            // Set defaults for AI detection compatibility
            $data['status'] = $data['status'] ?? 'available';
            $data['condition_status'] = $data['condition_status'] ?? 'excellent';
            $data['description'] = $data['description'] ?? '';
            $data['serial_number'] = $data['serial_number'] ?? '';
            $data['qr_code'] = $data['qr_code'] ?? '';
            $data['notes'] = $data['notes'] ?? '';
            
            $stmt->bindParam(":asset_id", $data['asset_id']);
            $stmt->bindParam(":asset_name", $data['asset_name']);
            $stmt->bindParam(":category", $data['category']);
            $stmt->bindParam(":description", $data['description']);
            $stmt->bindParam(":serial_number", $data['serial_number']);
            $stmt->bindParam(":qr_code", $data['qr_code']);
            $stmt->bindParam(":status", $data['status']);
            $stmt->bindParam(":condition_status", $data['condition_status']);
            $stmt->bindParam(":notes", $data['notes']);
            
            $result = $stmt->execute();
            
            if ($result) {
                $this->logAssetAction($data['asset_id'], 'created', 'Asset created in system');
                $this->conn->commit();
                return true;
            } else {
                $this->conn->rollBack();
                $this->errors[] = "Failed to create asset";
                return false;
            }
        } catch (PDOException $e) {
            $this->conn->rollBack();
            $this->errors[] = "Database error: " . $e->getMessage();
            return false;
        }
    }

    public function update($id, $data) {
        try {
            $this->conn->beginTransaction();
            
            $query = "UPDATE " . $this->table_name . " 
                      SET asset_name = :asset_name, category = :category, description = :description, 
                          serial_number = :serial_number, condition_status = :condition_status, 
                          notes = :notes, updated_at = NOW()
                      WHERE id = :id";
            
            $stmt = $this->conn->prepare($query);
            
            $stmt->bindParam(":id", $id);
            $stmt->bindParam(":asset_name", $data['asset_name']);
            $stmt->bindParam(":category", $data['category']);
            $stmt->bindParam(":description", $data['description']);
            $stmt->bindParam(":serial_number", $data['serial_number']);
            $stmt->bindParam(":condition_status", $data['condition_status']);
            $stmt->bindParam(":notes", $data['notes']);
            
            $result = $stmt->execute();
            
            if ($result) {
                $asset = $this->getById($id);
                $this->logAssetAction($asset['asset_id'], 'updated', 'Asset information updated');
                $this->conn->commit();
                return true;
            } else {
                $this->conn->rollBack();
                $this->errors[] = "Failed to update asset";
                return false;
            }
        } catch (PDOException $e) {
            $this->conn->rollBack();
            $this->errors[] = "Database error: " . $e->getMessage();
            return false;
        }
    }

    public function delete($id) {
        try {
            $this->conn->beginTransaction();
            
            $asset = $this->getById($id);
            if (!$asset) {
                $this->errors[] = "Asset not found";
                return false;
            }
            
            if ($asset['status'] == 'checked_out') {
                $this->errors[] = "Cannot delete asset that is currently checked out";
                return false;
            }
            
            $query = "DELETE FROM " . $this->table_name . " WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":id", $id);
            
            $result = $stmt->execute();
            
            if ($result) {
                $this->logAssetAction($asset['asset_id'], 'deleted', 'Asset removed from system');
                $this->conn->commit();
                return true;
            } else {
                $this->conn->rollBack();
                $this->errors[] = "Failed to delete asset";
                return false;
            }
        } catch (PDOException $e) {
            $this->conn->rollBack();
            $this->errors[] = "Database error: " . $e->getMessage();
            return false;
        }
    }

    /**
     * NEW: Bulk delete multiple assets
     */
    public function bulkDelete($asset_ids) {
        if (empty($asset_ids) || !is_array($asset_ids)) {
            $this->errors[] = "No assets selected for deletion";
            return false;
        }

        try {
            $this->conn->beginTransaction();
            
            $deleted_count = 0;
            $failed_deletions = [];
            
            foreach ($asset_ids as $asset_id) {
                $asset = $this->getById($asset_id);
                
                if (!$asset) {
                    $failed_deletions[] = "Asset ID {$asset_id} not found";
                    continue;
                }
                
                if ($asset['status'] == 'checked_out') {
                    $failed_deletions[] = "Cannot delete {$asset['asset_name']} - currently checked out";
                    continue;
                }
                
                $query = "DELETE FROM " . $this->table_name . " WHERE id = :id";
                $stmt = $this->conn->prepare($query);
                $stmt->bindParam(":id", $asset_id);
                
                if ($stmt->execute()) {
                    $this->logAssetAction($asset['asset_id'], 'bulk_deleted', 'Asset removed via bulk delete');
                    $deleted_count++;
                } else {
                    $failed_deletions[] = "Failed to delete {$asset['asset_name']}";
                }
            }
            
            if ($deleted_count > 0) {
                $this->conn->commit();
                
                if (!empty($failed_deletions)) {
                    $this->errors = $failed_deletions;
                }
                
                return $deleted_count;
            } else {
                $this->conn->rollBack();
                $this->errors = $failed_deletions;
                return false;
            }
            
        } catch (PDOException $e) {
            $this->conn->rollBack();
            $this->errors[] = "Database error during bulk delete: " . $e->getMessage();
            return false;
        }
    }

    /**
     * Check out an asset with optional transaction control
     */
    public function checkOut($asset_id, $borrower, $expected_return, $purpose, $manage_transaction = true) {
        try {
            $transaction_started = false;
            
            // Only start transaction if we're managing it
            if ($manage_transaction) {
                $this->conn->beginTransaction();
                $transaction_started = true;
            }
            
            // Validate asset exists and is available
            $asset = $this->getByAssetId($asset_id);
            if (!$asset) {
                $this->errors[] = "Asset not found";
                if ($transaction_started) $this->conn->rollBack();
                return false;
            }
            
            if ($asset['status'] !== 'available') {
                $this->errors[] = "Asset is not available for checkout";
                if ($transaction_started) $this->conn->rollBack();
                return false;
            }
            
            // Validate return date is in the future
            if (strtotime($expected_return) <= time()) {
                $this->errors[] = "Expected return date must be in the future";
                if ($transaction_started) $this->conn->rollBack();
                return false;
            }
            
            $query = "UPDATE " . $this->table_name . " 
                      SET status = 'checked_out', current_borrower = :borrower, 
                          checkout_date = NOW(), expected_return_date = :expected_return 
                      WHERE asset_id = :asset_id";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":asset_id", $asset_id);
            $stmt->bindParam(":borrower", $borrower);
            $stmt->bindParam(":expected_return", $expected_return);
            
            if ($stmt->execute()) {
                $this->logTransaction($asset_id, $borrower, 'checkout', $purpose);
                $this->logAssetAction($asset_id, 'checked_out', "Checked out to {$borrower}");
                
                // Only commit if we're managing the transaction
                if ($transaction_started) {
                    $this->conn->commit();
                }
                
                // Send equipment list email to borrower (outside of transaction)
                $this->sendEquipmentListEmail($borrower, 'checkout', $asset['asset_name'], $expected_return);
                
                return true;
            } else {
                if ($transaction_started) $this->conn->rollBack();
                $this->errors[] = "Failed to check out asset";
                return false;
            }
        } catch (PDOException $e) {
            if ($transaction_started) $this->conn->rollBack();
            $this->errors[] = "Database error: " . $e->getMessage();
            return false;
        }
    }

    /**
     * Check in an asset with optional transaction control
     */
    public function checkIn($asset_id, $condition, $notes, $manage_transaction = true) {
        try {
            $transaction_started = false;
            
            // Only start transaction if we're managing it
            if ($manage_transaction) {
                $this->conn->beginTransaction();
                $transaction_started = true;
            }
            
            // Get asset info and validate
            $asset = $this->getByAssetId($asset_id);
            if (!$asset) {
                $this->errors[] = "Asset not found";
                if ($transaction_started) $this->conn->rollBack();
                return false;
            }
            
            if ($asset['status'] !== 'checked_out') {
                $this->errors[] = "Asset is not currently checked out";
                if ($transaction_started) $this->conn->rollBack();
                return false;
            }
            
            $borrower = $asset['current_borrower'];
            
            $query = "UPDATE " . $this->table_name . " 
                      SET status = 'available', current_borrower = NULL, 
                          checkout_date = NULL, expected_return_date = NULL, 
                          condition_status = :condition 
                      WHERE asset_id = :asset_id";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":asset_id", $asset_id);
            $stmt->bindParam(":condition", $condition);
            
            if ($stmt->execute()) {
                $this->logTransaction($asset_id, $borrower, 'checkin', $notes, $condition);
                $this->logAssetAction($asset_id, 'checked_in', "Returned by {$borrower} in {$condition} condition");
                
                // Only commit if we're managing the transaction
                if ($transaction_started) {
                    $this->conn->commit();
                }
                
                // Send updated equipment list email to borrower (outside of transaction)
                $this->sendEquipmentListEmail($borrower, 'checkin', $asset['asset_name'], $condition);
                
                return true;
            } else {
                if ($transaction_started) $this->conn->rollBack();
                $this->errors[] = "Failed to check in asset";
                return false;
            }
        } catch (PDOException $e) {
            if ($transaction_started) $this->conn->rollBack();
            $this->errors[] = "Database error: " . $e->getMessage();
            return false;
        }
    }

    /**
     * Send current equipment list to a borrower (manual trigger)
     */
    public function sendCurrentEquipmentList($borrower_name) {
        try {
            if (!class_exists('EmailNotification')) {
                $this->errors[] = "Email system not available";
                return false;
            }
            
            $email = EmailNotification::getUserEmail($borrower_name, $this->conn);
            if (!$email) {
                $this->errors[] = "No email found for borrower: {$borrower_name}";
                return false;
            }
            
            $current_equipment = $this->getBorrowerEquipment($borrower_name);
            
            return EmailNotification::sendEquipmentList($email, $borrower_name, $current_equipment);
        } catch (Exception $e) {
            $this->errors[] = "Error sending equipment list: " . $e->getMessage();
            return false;
        }
    }

    // Enhanced search functionality
    public function search($term, $category = null, $status = null) {
        $conditions = [];
        $params = [];
        
        if (!empty($term)) {
            $conditions[] = "(asset_name LIKE :term OR asset_id LIKE :term OR description LIKE :term)";
            $params[':term'] = "%{$term}%";
        }
        
        if (!empty($category)) {
            $conditions[] = "category = :category";
            $params[':category'] = $category;
        }
        
        if (!empty($status)) {
            $conditions[] = "status = :status";
            $params[':status'] = $status;
        }
        
        $where_clause = empty($conditions) ? "" : "WHERE " . implode(" AND ", $conditions);
        
        $query = "SELECT * FROM " . $this->table_name . " {$where_clause} ORDER BY asset_name";
        $stmt = $this->conn->prepare($query);
        
        foreach ($params as $param => $value) {
            $stmt->bindValue($param, $value);
        }
        
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get asset history
    public function getAssetHistory($asset_id) {
        $query = "SELECT t.*, a.asset_name 
                  FROM transactions t 
                  JOIN assets a ON t.asset_id = a.asset_id 
                  WHERE t.asset_id = :asset_id 
                  ORDER BY t.transaction_date DESC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":asset_id", $asset_id);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Enhanced statistics with trends
    public function getDetailedStats() {
        $query = "SELECT 
                    COUNT(*) as total_assets,
                    SUM(CASE WHEN status = 'available' THEN 1 ELSE 0 END) as available,
                    SUM(CASE WHEN status = 'checked_out' THEN 1 ELSE 0 END) as checked_out,
                    SUM(CASE WHEN status = 'maintenance' THEN 1 ELSE 0 END) as maintenance,
                    SUM(CASE WHEN status = 'lost' THEN 1 ELSE 0 END) as lost,
                    SUM(CASE WHEN status = 'checked_out' AND expected_return_date < NOW() THEN 1 ELSE 0 END) as overdue,
                    AVG(CASE WHEN status = 'checked_out' THEN DATEDIFF(NOW(), checkout_date) ELSE NULL END) as avg_checkout_days
                  FROM " . $this->table_name;
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Get popular assets (most frequently checked out)
    public function getPopularAssets($limit = 10) {
        $query = "SELECT a.asset_id, a.asset_name, a.category, COUNT(t.id) as checkout_count
                  FROM assets a 
                  LEFT JOIN transactions t ON a.asset_id = t.asset_id AND t.transaction_type = 'checkout'
                  GROUP BY a.id 
                  ORDER BY checkout_count DESC 
                  LIMIT :limit";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":limit", $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get checked out assets with purpose from transactions (MySQL 5.7 compatible)
     */
    public function getCheckedOutAssetsWithPurpose() {
        $query = "SELECT DISTINCT
                    a.*, 
                    t.purpose,
                    t.transaction_date as checkout_transaction_date
                  FROM assets a 
                  LEFT JOIN transactions t ON a.asset_id = t.asset_id 
                      AND t.transaction_type = 'checkout'
                      AND t.transaction_date = (
                          SELECT MAX(t2.transaction_date) 
                          FROM transactions t2 
                          WHERE t2.asset_id = a.asset_id 
                          AND t2.transaction_type = 'checkout'
                      )
                  WHERE a.status = 'checked_out' 
                  ORDER BY a.checkout_date DESC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getById($id) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getByAssetId($asset_id) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE asset_id = :asset_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":asset_id", $asset_id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getAll() {
        $query = "SELECT * FROM " . $this->table_name . " ORDER BY asset_name";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getOverdueAssets() {
        $query = "SELECT * FROM " . $this->table_name . " 
                  WHERE status = 'checked_out' AND expected_return_date < NOW()
                  ORDER BY expected_return_date ASC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getCheckedOutAssets() {
        $query = "SELECT * FROM " . $this->table_name . " WHERE status = 'checked_out' ORDER BY checkout_date DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAssetStats() {
        return $this->getDetailedStats();
    }

    /**
     * Send overdue reminders to all borrowers with overdue equipment
     */
    public function sendOverdueReminders() {
        if (!class_exists('EmailNotification')) {
            return 0;
        }
        return EmailNotification::sendOverdueReminders($this->conn);
    }

    // AI Detection specific methods
    public function createFromAIDetection($detectionData) {
        // Generate unique asset ID
        $assetId = $this->generateAssetId($detectionData['category']);
        
        // Generate QR code
        $qrCodeUrl = $this->generateQRCode($assetId);
        
        $data = [
            'asset_id' => $assetId,
            'asset_name' => $detectionData['asset_name'],
            'category' => $detectionData['category'],
            'description' => $this->buildDescription($detectionData),
            'serial_number' => $detectionData['serial_number'] ?? '',
            'qr_code' => $qrCodeUrl,
            'status' => 'available',
            'condition_status' => 'excellent',
            'notes' => $detectionData['notes'] ?? ''
        ];
        
        if ($this->create($data)) {
            return $assetId;
        }
        return false;
    }

    public function assetExists($asset_id) {
        return $this->assetIdExists($asset_id);
    }

    private function generateAssetId($category) {
        $prefix = 'NF-' . strtoupper(substr($category, 0, 2)) . '-';
        
        do {
            $assetId = $prefix . sprintf('%04d', rand(1000, 9999));
        } while ($this->assetIdExists($assetId));
        
        return $assetId;
    }

    private function generateQRCode($assetId) {
        $baseUrl = 'https://api.qrserver.com/v1/create-qr-code/';
        $params = [
            'size' => '200x200',
            'data' => "Asset ID: $assetId\nNeofox Gear Control\nScan for details"
        ];
        
        return $baseUrl . '?' . http_build_query($params);
    }

    private function buildDescription($detectionData) {
        $parts = [];
        
        if (!empty($detectionData['brand'])) {
            $parts[] = "Brand: " . $detectionData['brand'];
        }
        
        if (!empty($detectionData['model'])) {
            $parts[] = "Model: " . $detectionData['model'];
        }
        
        if (!empty($detectionData['type'])) {
            $parts[] = "Type: " . $detectionData['type'];
        }
        
        if (!empty($detectionData['features'])) {
            $features = is_array($detectionData['features']) ? 
                       implode(', ', $detectionData['features']) : 
                       $detectionData['features'];
            $parts[] = "Features: " . $features;
        }
        
        return implode(' | ', $parts);
    }

    private function logTransaction($asset_id, $borrower, $type, $notes, $condition = null) {
        $query = "INSERT INTO transactions (asset_id, borrower_name, transaction_type, purpose, condition_on_return, notes) 
                  VALUES (:asset_id, :borrower, :type, :purpose, :condition, :notes)";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":asset_id", $asset_id);
        $stmt->bindParam(":borrower", $borrower);
        $stmt->bindParam(":type", $type);
        $stmt->bindParam(":purpose", $notes);
        $stmt->bindParam(":condition", $condition);
        $stmt->bindParam(":notes", $notes);
        $stmt->execute();
    }

    private function logAssetAction($asset_id, $action, $details) {
        // This could be expanded to a separate audit log table
        error_log("ASSET ACTION: {$asset_id} - {$action} - {$details}");
    }
}
?>