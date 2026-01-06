<?php
// classes/MaintenanceTracker.php
class MaintenanceTracker {
    private $conn;
    
    public function __construct($db) {
        $this->conn = $db;
    }
    
    /**
     * Get maintenance statistics
     */
    public function getMaintenanceStats() {
        try {
            // Check if maintenance tables exist
            $tables_exist = $this->checkMaintenanceTables();
            
            if (!$tables_exist) {
                return [
                    'pending_maintenance' => 0,
                    'overdue_maintenance' => 0,
                    'completed_this_month' => 0,
                    'open_issues' => 0,
                    'total_maintenance_cost' => 0
                ];
            }
            
            $stats = [];
            
            // Pending maintenance
            $result = $this->conn->query("SELECT COUNT(*) as count FROM maintenance_schedule WHERE status = 'scheduled'");
            $stats['pending_maintenance'] = $result ? $result->fetch_assoc()['count'] : 0;
            
            // Overdue maintenance
            $result = $this->conn->query("SELECT COUNT(*) as count FROM maintenance_schedule WHERE status = 'scheduled' AND scheduled_date < NOW()");
            $stats['overdue_maintenance'] = $result ? $result->fetch_assoc()['count'] : 0;
            
            // Completed this month
            $result = $this->conn->query("SELECT COUNT(*) as count FROM maintenance_schedule WHERE status = 'completed' AND MONTH(completed_date) = MONTH(NOW()) AND YEAR(completed_date) = YEAR(NOW())");
            $stats['completed_this_month'] = $result ? $result->fetch_assoc()['count'] : 0;
            
            // Open issues
            $result = $this->conn->query("SELECT COUNT(*) as count FROM maintenance_issues WHERE status = 'open'");
            $stats['open_issues'] = $result ? $result->fetch_assoc()['count'] : 0;
            
            // Total maintenance cost this year
            $result = $this->conn->query("SELECT SUM(actual_cost) as total FROM maintenance_schedule WHERE status = 'completed' AND YEAR(completed_date) = YEAR(NOW())");
            $stats['total_maintenance_cost'] = $result ? ($result->fetch_assoc()['total'] ?? 0) : 0;
            
            return $stats;
            
        } catch (Exception $e) {
            return [
                'pending_maintenance' => 0,
                'overdue_maintenance' => 0,
                'completed_this_month' => 0,
                'open_issues' => 0,
                'total_maintenance_cost' => 0
            ];
        }
    }
    
    /**
     * Get upcoming maintenance tasks
     */
    public function getUpcomingMaintenance($days = 30) {
        try {
            if (!$this->checkMaintenanceTables()) {
                return [];
            }
            
            $query = "SELECT ms.*, a.asset_name, a.category 
                      FROM maintenance_schedule ms 
                      LEFT JOIN assets a ON ms.asset_id = a.asset_id 
                      WHERE ms.status = 'scheduled' 
                      AND ms.scheduled_date BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL ? DAY)
                      ORDER BY ms.scheduled_date ASC";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param('i', $days);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $tasks = [];
            while ($row = $result->fetch_assoc()) {
                $tasks[] = $row;
            }
            
            return $tasks;
            
        } catch (Exception $e) {
            return [];
        }
    }
    
    /**
     * Get overdue maintenance tasks
     */
    public function getOverdueMaintenance() {
        try {
            if (!$this->checkMaintenanceTables()) {
                return [];
            }
            
            $query = "SELECT ms.*, a.asset_name, a.category,
                             DATEDIFF(NOW(), ms.scheduled_date) as days_overdue
                      FROM maintenance_schedule ms 
                      LEFT JOIN assets a ON ms.asset_id = a.asset_id 
                      WHERE ms.status = 'scheduled' 
                      AND ms.scheduled_date < NOW()
                      ORDER BY ms.scheduled_date ASC";
            
            $result = $this->conn->query($query);
            
            $tasks = [];
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $tasks[] = $row;
                }
            }
            
            return $tasks;
            
        } catch (Exception $e) {
            return [];
        }
    }
    
    /**
     * Get open issues
     */
    public function getOpenIssues() {
        try {
            if (!$this->checkMaintenanceTables()) {
                return [];
            }
            
            $query = "SELECT mi.*, a.asset_name, a.category 
                      FROM maintenance_issues mi 
                      LEFT JOIN assets a ON mi.asset_id = a.asset_id 
                      WHERE mi.status IN ('open', 'in_progress')
                      ORDER BY mi.severity DESC, mi.reported_date DESC";
            
            $result = $this->conn->query($query);
            
            $issues = [];
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $issues[] = $row;
                }
            }
            
            return $issues;
            
        } catch (Exception $e) {
            return [];
        }
    }
    
    /**
     * Schedule new maintenance
     */
    public function scheduleMaintenance($data) {
        try {
            if (!$this->checkMaintenanceTables()) {
                throw new Exception("Maintenance tables not found");
            }
            
            $query = "INSERT INTO maintenance_schedule 
                      (asset_id, maintenance_type, scheduled_date, priority, notes, assigned_to, estimated_duration, cost_estimate)
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param('ssssssid', 
                $data['asset_id'],
                $data['maintenance_type'],
                $data['scheduled_date'],
                $data['priority'],
                $data['notes'],
                $data['assigned_to'],
                $data['estimated_duration'],
                $data['cost_estimate']
            );
            
            if ($stmt->execute()) {
                return $this->conn->insert_id;
            }
            
            return false;
            
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Report new issue
     */
    public function reportIssue($data) {
        try {
            if (!$this->checkMaintenanceTables()) {
                throw new Exception("Maintenance tables not found");
            }
            
            $query = "INSERT INTO maintenance_issues 
                      (asset_id, reported_by, issue_type, description, severity, reported_date)
                      VALUES (?, ?, ?, ?, ?, NOW())";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param('sssss', 
                $data['asset_id'],
                $data['reported_by'],
                $data['issue_type'],
                $data['description'],
                $data['severity']
            );
            
            if ($stmt->execute()) {
                return $this->conn->insert_id;
            }
            
            return false;
            
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Complete maintenance task
     */
    public function completeMaintenance($task_id, $completion_data) {
        try {
            if (!$this->checkMaintenanceTables()) {
                return false;
            }
            
            $query = "UPDATE maintenance_schedule 
                      SET status = 'completed',
                          completed_date = NOW(),
                          actual_duration = ?,
                          actual_cost = ?,
                          completion_notes = ?,
                          completed_by = ?
                      WHERE id = ?";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param('idssi',
                $completion_data['actual_duration'],
                $completion_data['actual_cost'],
                $completion_data['completion_notes'],
                $completion_data['completed_by'],
                $task_id
            );
            
            return $stmt->execute();
            
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Resolve issue
     */
    public function resolveIssue($issue_id, $resolution_data) {
        try {
            if (!$this->checkMaintenanceTables()) {
                return false;
            }
            
            $query = "UPDATE maintenance_issues 
                      SET status = 'resolved',
                          resolved_date = NOW(),
                          resolved_by = ?,
                          resolution_notes = ?,
                          resolution_cost = ?
                      WHERE id = ?";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param('ssdi',
                $resolution_data['resolved_by'],
                $resolution_data['resolution_notes'],
                $resolution_data['resolution_cost'],
                $issue_id
            );
            
            return $stmt->execute();
            
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Check if maintenance tables exist
     */
    private function checkMaintenanceTables() {
        try {
            $tables = ['maintenance_schedule', 'maintenance_issues'];
            
            foreach ($tables as $table) {
                $result = $this->conn->query("SHOW TABLES LIKE '{$table}'");
                if (!$result || $result->num_rows == 0) {
                    return false;
                }
            }
            
            return true;
            
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Get maintenance history
     */
    public function getMaintenanceHistory($asset_id = null, $limit = 50) {
        try {
            if (!$this->checkMaintenanceTables()) {
                return [];
            }
            
            $query = "SELECT ms.*, a.asset_name, a.category 
                      FROM maintenance_schedule ms 
                      LEFT JOIN assets a ON ms.asset_id = a.asset_id 
                      WHERE ms.status = 'completed'";
            
            if ($asset_id) {
                $query .= " AND ms.asset_id = ?";
            }
            
            $query .= " ORDER BY ms.completed_date DESC LIMIT ?";
            
            $stmt = $this->conn->prepare($query);
            
            if ($asset_id) {
                $stmt->bind_param('si', $asset_id, $limit);
            } else {
                $stmt->bind_param('i', $limit);
            }
            
            $stmt->execute();
            $result = $stmt->get_result();
            
            $history = [];
            while ($row = $result->fetch_assoc()) {
                $history[] = $row;
            }
            
            return $history;
            
        } catch (Exception $e) {
            return [];
        }
    }
}
?>