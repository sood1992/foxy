<?php
/**
 * Maintenance routes
 */

function handleMaintenance($method, $id, $action) {
    Auth::requireAuth();

    if ($id === 'schedule') {
        if ($method === 'GET') {
            getSchedule();
        } elseif ($method === 'POST') {
            createScheduledTask();
        }
        return;
    }

    if ($id === 'issues') {
        if ($method === 'GET') {
            getIssues();
        } elseif ($method === 'POST') {
            createIssue();
        }
        return;
    }

    // Handle specific issue or task
    if ($action === 'complete') {
        completeTask($id);
        return;
    }

    switch ($method) {
        case 'PUT':
            updateIssue($id);
            break;

        case 'DELETE':
            deleteIssue($id);
            break;

        default:
            Response::error('Method not allowed', 405);
    }
}

function getIssues() {
    $issues = JsonDatabase::getAll('maintenance_issues');
    $assets = JsonDatabase::getAll('assets');

    // Add asset name to issues
    foreach ($issues as &$issue) {
        foreach ($assets as $asset) {
            if ($asset['id'] == $issue['asset_id'] || $asset['asset_id'] == $issue['asset_id']) {
                $issue['asset_name'] = $asset['asset_name'];
                break;
            }
        }
    }

    // Sort by severity (critical first) then by date
    usort($issues, function($a, $b) {
        $severityOrder = ['critical' => 0, 'high' => 1, 'medium' => 2, 'low' => 3];
        $aSev = $severityOrder[$a['severity']] ?? 4;
        $bSev = $severityOrder[$b['severity']] ?? 4;

        if ($aSev !== $bSev) return $aSev - $bSev;

        return strtotime($b['created_at']) - strtotime($a['created_at']);
    });

    Response::success(['issues' => array_values($issues)]);
}

function createIssue() {
    $input = json_decode(file_get_contents('php://input'), true);

    $assetId = $input['asset_id'] ?? null;
    $issueType = $input['issue_type'] ?? 'mechanical';
    $severity = $input['severity'] ?? 'medium';
    $description = $input['description'] ?? '';
    $reportedBy = $input['reported_by'] ?? '';

    if (!$assetId || !$description) {
        Response::error('Asset ID and description are required');
    }

    $issue = JsonDatabase::insert('maintenance_issues', [
        'asset_id' => $assetId,
        'issue_type' => $issueType,
        'severity' => $severity,
        'description' => $description,
        'reported_by' => $reportedBy,
        'status' => 'open',
        'reported_date' => date('Y-m-d H:i:s')
    ]);

    // Update asset status if critical
    if ($severity === 'critical' || $severity === 'high') {
        JsonDatabase::updateById('assets', $assetId, ['status' => 'maintenance']);
    }

    Response::success(['issue' => $issue], 'Issue reported successfully');
}

function updateIssue($id) {
    $input = json_decode(file_get_contents('php://input'), true);

    $issue = JsonDatabase::findById('maintenance_issues', $id);
    if (!$issue) {
        Response::error('Issue not found', 404);
    }

    $allowedFields = ['status', 'resolution_notes', 'resolved_by', 'resolution_cost'];
    $updates = array_intersect_key($input, array_flip($allowedFields));

    if (isset($updates['status']) && $updates['status'] === 'resolved') {
        $updates['resolved_date'] = date('Y-m-d H:i:s');
    }

    $updated = JsonDatabase::updateById('maintenance_issues', $id, $updates);

    Response::success(['issue' => $updated], 'Issue updated successfully');
}

function deleteIssue($id) {
    $issue = JsonDatabase::findById('maintenance_issues', $id);
    if (!$issue) {
        Response::error('Issue not found', 404);
    }

    JsonDatabase::deleteById('maintenance_issues', $id);
    Response::success(null, 'Issue deleted successfully');
}

function getSchedule() {
    $schedule = JsonDatabase::getAll('maintenance_schedule');
    $assets = JsonDatabase::getAll('assets');

    // Add asset name
    foreach ($schedule as &$task) {
        foreach ($assets as $asset) {
            if ($asset['id'] == $task['asset_id'] || $asset['asset_id'] == $task['asset_id']) {
                $task['asset_name'] = $asset['asset_name'];
                break;
            }
        }
    }

    // Sort by scheduled date
    usort($schedule, function($a, $b) {
        return strtotime($a['scheduled_date']) - strtotime($b['scheduled_date']);
    });

    Response::success(['schedule' => array_values($schedule)]);
}

function createScheduledTask() {
    $input = json_decode(file_get_contents('php://input'), true);

    $assetId = $input['asset_id'] ?? null;
    $type = $input['maintenance_type'] ?? 'routine';
    $scheduledDate = $input['scheduled_date'] ?? null;
    $assignedTo = $input['assigned_to'] ?? null;
    $notes = $input['notes'] ?? '';
    $priority = $input['priority'] ?? 'medium';

    if (!$assetId || !$scheduledDate) {
        Response::error('Asset ID and scheduled date are required');
    }

    $task = JsonDatabase::insert('maintenance_schedule', [
        'asset_id' => $assetId,
        'maintenance_type' => $type,
        'scheduled_date' => $scheduledDate,
        'assigned_to' => $assignedTo,
        'priority' => $priority,
        'status' => 'scheduled',
        'notes' => $notes
    ]);

    Response::success(['task' => $task], 'Task scheduled successfully');
}

function completeTask($id) {
    $input = json_decode(file_get_contents('php://input'), true);

    $task = JsonDatabase::findById('maintenance_schedule', $id);
    if (!$task) {
        Response::error('Task not found', 404);
    }

    $updates = [
        'status' => 'completed',
        'completed_date' => date('Y-m-d H:i:s'),
        'completion_notes' => $input['completion_notes'] ?? '',
        'completed_by' => $input['completed_by'] ?? '',
        'actual_cost' => $input['actual_cost'] ?? null,
        'actual_duration' => $input['actual_duration'] ?? null
    ];

    $updated = JsonDatabase::updateById('maintenance_schedule', $id, $updates);

    // Log history
    JsonDatabase::insert('maintenance_history', [
        'maintenance_id' => $id,
        'action' => 'completed',
        'details' => $input['completion_notes'] ?? ''
    ]);

    Response::success(['task' => $updated], 'Task completed');
}
