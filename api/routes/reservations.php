<?php
/**
 * Reservations/Requests Routes
 * Team members can request gear, Equipment Manager/Admin approve
 */

function handleReservations($method, $id, $action) {
    switch ($method) {
        case 'GET':
            getAllReservations();
            break;

        case 'POST':
            if ($action === 'approve') {
                approveReservation($id);
            } elseif ($action === 'reject') {
                rejectReservation($id);
            } else {
                createReservation();
            }
            break;

        case 'DELETE':
            cancelReservation($id);
            break;

        default:
            Response::error('Method not allowed', 405);
    }
}

function getAllReservations() {
    Auth::requireAuth();
    $user = Auth::getCurrentUser();

    $reservations = JsonDatabase::getAll('reservations');

    // Enrich with asset names
    $assets = JsonDatabase::getAll('assets');
    $assetMap = [];
    foreach ($assets as $asset) {
        $assetMap[$asset['asset_id']] = $asset;
    }

    foreach ($reservations as &$res) {
        $res['asset_name'] = $assetMap[$res['asset_id']]['asset_name'] ?? null;
        $res['asset_category'] = $assetMap[$res['asset_id']]['category'] ?? null;
    }

    // Team members only see their own reservations
    if ($user['role'] === 'team_member') {
        $reservations = array_filter($reservations, fn($r) =>
            strtolower($r['reserved_by']) === strtolower($user['username'])
        );
    }

    Response::json(['reservations' => array_values($reservations)]);
}

function createReservation() {
    Auth::requireAuth();
    $user = Auth::getCurrentUser();
    $data = json_decode(file_get_contents('php://input'), true);

    // Validate dates
    $startDate = strtotime($data['start_date']);
    $endDate = strtotime($data['end_date']);

    if ($endDate < $startDate) {
        Response::error('End date must be after start date', 400);
    }

    // Check for conflicts
    $reservations = JsonDatabase::getAll('reservations');
    foreach ($reservations as $existing) {
        if ($existing['asset_id'] !== $data['asset_id']) continue;
        if (in_array($existing['status'], ['cancelled', 'rejected'])) continue;

        $existingStart = strtotime($existing['start_date']);
        $existingEnd = strtotime($existing['end_date']);

        if ($startDate <= $existingEnd && $endDate >= $existingStart) {
            Response::error('Equipment is already reserved for these dates', 400);
        }
    }

    // Get asset info
    $assets = JsonDatabase::getAll('assets');
    $asset = null;
    foreach ($assets as $a) {
        if ($a['asset_id'] === $data['asset_id']) {
            $asset = $a;
            break;
        }
    }

    if (!$asset) {
        Response::error('Asset not found', 404);
    }

    // Team members create pending requests, admins/managers create confirmed
    $isManager = in_array($user['role'], ['admin', 'equipment_manager']);
    $status = $isManager ? 'confirmed' : 'pending';

    $reservation = [
        'id' => 'REQ-' . strtoupper(substr(uniqid(), -8)),
        'asset_id' => $data['asset_id'],
        'asset_name' => $asset['asset_name'],
        'reserved_by' => $data['reserved_by'] ?? $user['username'],
        'start_date' => $data['start_date'],
        'end_date' => $data['end_date'],
        'purpose' => $data['purpose'] ?? '',
        'status' => $status,
        'created_by' => $user['username'],
        'created_at' => date('Y-m-d H:i:s')
    ];

    JsonDatabase::insert('reservations', $reservation);

    // Log action
    $action = $status === 'pending' ? 'Gear request' : 'Reservation';
    AuditLog::log('reservation', $data['asset_id'], $reservation['id'],
        "{$action}: {$asset['asset_name']} for {$reservation['reserved_by']} ({$data['start_date']} to {$data['end_date']})"
    );

    // Send email notification to admin/manager for pending requests
    if ($status === 'pending') {
        // Notification will be sent via email service
    }

    Response::json([
        'reservation' => $reservation,
        'message' => $status === 'pending' ? 'Request submitted for approval' : 'Reservation created'
    ]);
}

function approveReservation($reservationId) {
    $user = Auth::requireAuth();

    // Only admin/manager can approve
    if (!in_array($user['role'], ['admin', 'equipment_manager'])) {
        Response::error('Permission denied', 403);
    }

    if (!$reservationId) {
        Response::error('Reservation ID required', 400);
    }

    // Find reservation
    $reservations = JsonDatabase::getAll('reservations');
    $reservation = null;
    foreach ($reservations as $r) {
        if ($r['id'] === $reservationId) {
            $reservation = $r;
            break;
        }
    }

    if (!$reservation) {
        Response::error('Reservation not found', 404);
    }

    if ($reservation['status'] !== 'pending') {
        Response::error('Reservation is not pending', 400);
    }

    // Update status
    JsonDatabase::update('reservations', 'id', $reservationId, [
        'status' => 'confirmed',
        'approved_by' => $user['username'],
        'approved_at' => date('Y-m-d H:i:s')
    ]);

    // Log action
    AuditLog::log('reservation', $reservation['asset_id'], $reservationId,
        "Approved gear request from {$reservation['reserved_by']}"
    );

    Response::json(['message' => 'Request approved']);
}

function rejectReservation($reservationId) {
    $user = Auth::requireAuth();

    // Only admin/manager can reject
    if (!in_array($user['role'], ['admin', 'equipment_manager'])) {
        Response::error('Permission denied', 403);
    }

    if (!$reservationId) {
        Response::error('Reservation ID required', 400);
    }

    $data = json_decode(file_get_contents('php://input'), true);

    // Find reservation
    $reservations = JsonDatabase::getAll('reservations');
    $reservation = null;
    foreach ($reservations as $r) {
        if ($r['id'] === $reservationId) {
            $reservation = $r;
            break;
        }
    }

    if (!$reservation) {
        Response::error('Reservation not found', 404);
    }

    // Update status
    JsonDatabase::update('reservations', 'id', $reservationId, [
        'status' => 'rejected',
        'rejected_by' => $user['username'],
        'rejected_at' => date('Y-m-d H:i:s'),
        'rejection_reason' => $data['reason'] ?? ''
    ]);

    // Log action
    AuditLog::log('reservation', $reservation['asset_id'], $reservationId,
        "Rejected gear request from {$reservation['reserved_by']}"
    );

    Response::json(['message' => 'Request rejected']);
}

function cancelReservation($reservationId) {
    $user = Auth::requireAuth();

    if (!$reservationId) {
        Response::error('Reservation ID required', 400);
    }

    // Find reservation
    $reservations = JsonDatabase::getAll('reservations');
    $reservation = null;
    foreach ($reservations as $r) {
        if ($r['id'] === $reservationId) {
            $reservation = $r;
            break;
        }
    }

    if (!$reservation) {
        Response::error('Reservation not found', 404);
    }

    // Team members can only cancel their own
    if ($user['role'] === 'team_member' &&
        strtolower($reservation['reserved_by']) !== strtolower($user['username'])) {
        Response::error('Permission denied', 403);
    }

    // Update status
    JsonDatabase::update('reservations', 'id', $reservationId, [
        'status' => 'cancelled',
        'cancelled_by' => $user['username'],
        'cancelled_at' => date('Y-m-d H:i:s')
    ]);

    // Log action
    AuditLog::log('reservation', $reservation['asset_id'], $reservationId,
        "Cancelled reservation for {$reservation['reserved_by']}"
    );

    Response::json(['message' => 'Reservation cancelled']);
}
