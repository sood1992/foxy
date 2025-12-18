<?php
/**
 * Audit Log Utility
 * Tracks all actions in the system for accountability
 */

class AuditLog {
    /**
     * Log an action
     */
    public static function log($action, $entityType, $entityId, $description, $details = null) {
        $user = Auth::getCurrentUser();

        $entry = [
            'id' => 'LOG-' . uniqid(),
            'timestamp' => date('Y-m-d H:i:s'),
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'description' => $description,
            'user_id' => $user['user_id'] ?? null,
            'user_name' => $user['username'] ?? 'system',
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'details' => $details
        ];

        // Also add asset info if available
        if ($entityType === 'asset' || $entityType === 'checkout' || $entityType === 'checkin') {
            $assets = JsonDatabase::getAll('assets');
            foreach ($assets as $asset) {
                if ($asset['asset_id'] === $entityId) {
                    $entry['asset_id'] = $asset['asset_id'];
                    $entry['asset_name'] = $asset['asset_name'];
                    break;
                }
            }
        }

        JsonDatabase::insert('audit_log', $entry);

        return $entry;
    }

    /**
     * Get audit logs with filters
     */
    public static function getLogs($filters = []) {
        $logs = JsonDatabase::getAll('audit_log');

        // Sort by timestamp descending (newest first)
        usort($logs, function($a, $b) {
            return strtotime($b['timestamp']) - strtotime($a['timestamp']);
        });

        // Apply filters
        if (!empty($filters['action'])) {
            $logs = array_filter($logs, fn($l) => $l['action'] === $filters['action']);
        }

        if (!empty($filters['user'])) {
            $logs = array_filter($logs, fn($l) =>
                stripos($l['user_name'], $filters['user']) !== false
            );
        }

        if (!empty($filters['dateFrom'])) {
            $from = strtotime($filters['dateFrom']);
            $logs = array_filter($logs, fn($l) => strtotime($l['timestamp']) >= $from);
        }

        if (!empty($filters['dateTo'])) {
            $to = strtotime($filters['dateTo'] . ' 23:59:59');
            $logs = array_filter($logs, fn($l) => strtotime($l['timestamp']) <= $to);
        }

        // Pagination
        $page = intval($filters['page'] ?? 1);
        $limit = intval($filters['limit'] ?? 50);
        $offset = ($page - 1) * $limit;

        return array_slice(array_values($logs), $offset, $limit);
    }
}
