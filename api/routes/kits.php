<?php
/**
 * Equipment Kits Routes
 */

function handleKits($method, $id, $action) {
    switch ($method) {
        case 'GET':
            if ($id) {
                getKit($id);
            } else {
                getAllKits();
            }
            break;

        case 'POST':
            Auth::requireAuth();
            createKit();
            break;

        case 'PUT':
            Auth::requireAuth();
            updateKit($id);
            break;

        case 'DELETE':
            Auth::requireAuth();
            deleteKit($id);
            break;

        default:
            Response::error('Method not allowed', 405);
    }
}

function getAllKits() {
    $kits = JsonDatabase::getAll('kits');
    Response::json(['kits' => $kits]);
}

function getKit($kitId) {
    $kits = JsonDatabase::getAll('kits');
    foreach ($kits as $kit) {
        if ($kit['id'] === $kitId) {
            Response::json(['kit' => $kit]);
        }
    }
    Response::error('Kit not found', 404);
}

function createKit() {
    $data = json_decode(file_get_contents('php://input'), true);

    if (empty($data['name'])) {
        Response::error('Kit name is required', 400);
    }

    $kit = [
        'id' => 'KIT-' . strtoupper(substr(uniqid(), -6)),
        'name' => $data['name'],
        'description' => $data['description'] ?? '',
        'asset_ids' => $data['asset_ids'] ?? [],
        'created_by' => Auth::getCurrentUser()['username'] ?? 'system',
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s')
    ];

    JsonDatabase::insert('kits', $kit);

    // Log action
    AuditLog::log('create', 'kit', $kit['id'], "Created kit: {$kit['name']}", [
        'asset_count' => count($kit['asset_ids'])
    ]);

    Response::json(['kit' => $kit, 'message' => 'Kit created successfully']);
}

function updateKit($kitId) {
    if (!$kitId) {
        Response::error('Kit ID required', 400);
    }

    $data = json_decode(file_get_contents('php://input'), true);

    $updated = JsonDatabase::update('kits', 'id', $kitId, [
        'name' => $data['name'],
        'description' => $data['description'] ?? '',
        'asset_ids' => $data['asset_ids'] ?? [],
        'updated_at' => date('Y-m-d H:i:s')
    ]);

    if (!$updated) {
        Response::error('Kit not found', 404);
    }

    // Log action
    AuditLog::log('update', 'kit', $kitId, "Updated kit: {$data['name']}");

    Response::json(['message' => 'Kit updated successfully']);
}

function deleteKit($kitId) {
    if (!$kitId) {
        Response::error('Kit ID required', 400);
    }

    // Get kit info for logging
    $kits = JsonDatabase::getAll('kits');
    $kitName = '';
    foreach ($kits as $kit) {
        if ($kit['id'] === $kitId) {
            $kitName = $kit['name'];
            break;
        }
    }

    $deleted = JsonDatabase::delete('kits', 'id', $kitId);

    if (!$deleted) {
        Response::error('Kit not found', 404);
    }

    // Log action
    AuditLog::log('delete', 'kit', $kitId, "Deleted kit: {$kitName}");

    Response::json(['message' => 'Kit deleted successfully']);
}
