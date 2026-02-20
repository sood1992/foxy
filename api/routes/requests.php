<?php
/**
 * Gear request routes
 */

function handleRequests($method, $id, $action) {
    switch ($method) {
        case 'GET':
            if ($id) {
                getRequest($id);
            } else {
                getRequests();
            }
            break;

        case 'POST':
            if ($action === 'approve') {
                Auth::requireAuth();
                approveRequest($id);
            } elseif ($action === 'reject') {
                Auth::requireAuth();
                rejectRequest($id);
            } else {
                createRequest();
            }
            break;

        default:
            Response::error('Method not allowed', 405);
    }
}

function getRequests() {
    $requests = JsonDatabase::getAll('gear_requests');

    // Sort by created_at descending
    usort($requests, function($a, $b) {
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    });

    // Filter by status if provided
    $status = $_GET['status'] ?? null;
    if ($status) {
        $requests = array_filter($requests, fn($r) => $r['status'] === $status);
    }

    Response::success(['requests' => array_values($requests)]);
}

function getRequest($id) {
    $request = JsonDatabase::findById('gear_requests', $id);

    if (!$request) {
        Response::error('Request not found', 404);
    }

    Response::success(['request' => $request]);
}

function createRequest() {
    $input = json_decode(file_get_contents('php://input'), true);

    $name = $input['requester_name'] ?? '';
    $email = $input['requester_email'] ?? '';
    $items = $input['required_items'] ?? '';
    $dates = $input['request_dates'] ?? '';
    $purpose = $input['purpose'] ?? '';

    if (!$name || !$items || !$dates) {
        Response::error('Name, items, and dates are required');
    }

    $request = JsonDatabase::insert('gear_requests', [
        'requester_name' => $name,
        'requester_email' => $email,
        'required_items' => $items,
        'request_dates' => $dates,
        'purpose' => $purpose,
        'status' => 'pending',
        'admin_notes' => ''
    ]);

    Response::success(['request' => $request], 'Request submitted successfully');
}

function approveRequest($id) {
    $input = json_decode(file_get_contents('php://input'), true);

    $request = JsonDatabase::findById('gear_requests', $id);
    if (!$request) {
        Response::error('Request not found', 404);
    }

    if ($request['status'] !== 'pending') {
        Response::error('Request has already been processed');
    }

    $adminNotes = $input['admin_notes'] ?? '';

    // Parse items and checkout
    $items = $request['required_items'];
    // Try to extract asset IDs from items string (format: "Item Name (ASSET_ID)")
    preg_match_all('/\(([A-Z]{3}\d{3})\)/', $items, $matches);
    $assetIds = $matches[1] ?? [];

    $errors = [];
    $checkedOut = 0;

    if (!empty($assetIds)) {
        // Parse dates - try to get return date
        $returnDate = null;
        if (preg_match('/to\s+(\d{1,2}\/\d{1,2}\/\d{4})/i', $request['request_dates'], $dateMatch)) {
            $returnDate = date('Y-m-d', strtotime($dateMatch[1]));
        } else {
            $returnDate = date('Y-m-d', strtotime('+7 days'));
        }

        foreach ($assetIds as $assetId) {
            $asset = JsonDatabase::find('assets', 'asset_id', $assetId);

            if (!$asset) {
                $errors[] = "Asset {$assetId} not found";
                continue;
            }

            if ($asset['status'] !== 'available') {
                $errors[] = "Asset {$assetId} ({$asset['asset_name']}) is not available";
                continue;
            }

            // Checkout
            JsonDatabase::update('assets', 'asset_id', $assetId, [
                'status' => 'checked_out',
                'current_borrower' => $request['requester_name'],
                'checkout_date' => date('Y-m-d H:i:s'),
                'expected_return_date' => $returnDate,
                'total_checkouts' => ($asset['total_checkouts'] ?? 0) + 1
            ]);

            // Log transaction
            JsonDatabase::insert('transactions', [
                'asset_id' => $assetId,
                'asset_name' => $asset['asset_name'],
                'borrower_name' => $request['requester_name'],
                'transaction_type' => 'checkout',
                'purpose' => $request['purpose'] ?? 'Gear Request',
                'notes' => 'Approved from gear request #' . $id,
                'transaction_date' => date('Y-m-d H:i:s')
            ]);

            $checkedOut++;
        }
    }

    // Update request
    $finalNotes = $adminNotes;
    if (!empty($errors)) {
        $finalNotes .= "\n\nErrors: " . implode('; ', $errors);
    }

    JsonDatabase::updateById('gear_requests', $id, [
        'status' => 'approved',
        'admin_notes' => trim($finalNotes)
    ]);

    Response::success([
        'checked_out' => $checkedOut,
        'errors' => $errors
    ], 'Request approved');
}

function rejectRequest($id) {
    $input = json_decode(file_get_contents('php://input'), true);

    $request = JsonDatabase::findById('gear_requests', $id);
    if (!$request) {
        Response::error('Request not found', 404);
    }

    if ($request['status'] !== 'pending') {
        Response::error('Request has already been processed');
    }

    JsonDatabase::updateById('gear_requests', $id, [
        'status' => 'rejected',
        'admin_notes' => $input['admin_notes'] ?? ''
    ]);

    Response::success(null, 'Request rejected');
}
