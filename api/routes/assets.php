<?php
/**
 * Asset routes
 */

function handleAssets($method, $id, $action) {
    // Special routes
    if ($id === 'categories') {
        return getCategories();
    }

    if ($id === 'search') {
        return searchAssets();
    }

    if ($id === 'bulk-checkout') {
        Auth::requireAuth();
        return bulkCheckout();
    }

    // Standard CRUD
    switch ($method) {
        case 'GET':
            if ($id) {
                getAsset($id);
            } else {
                getAssets();
            }
            break;

        case 'POST':
            Auth::requireAuth();
            if ($action === 'checkout') {
                checkoutAsset($id);
            } elseif ($action === 'checkin') {
                checkinAsset($id);
            } elseif ($action === 'photos') {
                uploadAssetPhoto($id);
            } else {
                createAsset();
            }
            break;

        case 'PUT':
            Auth::requireAuth();
            updateAsset($id);
            break;

        case 'DELETE':
            Auth::requireAdmin();
            deleteAsset($id);
            break;

        default:
            Response::error('Method not allowed', 405);
    }
}

function getAssets() {
    $assets = JsonDatabase::getAll('assets');
    $photos = JsonDatabase::getAll('asset_photos');

    // Add main photo to each asset
    foreach ($assets as &$asset) {
        foreach ($photos as $photo) {
            if ($photo['asset_id'] === $asset['asset_id'] && $photo['photo_type'] === 'main') {
                $asset['photo'] = $photo['photo_path'];
                break;
            }
        }
    }

    // Filter by status if provided
    $status = $_GET['status'] ?? null;
    if ($status) {
        $assets = array_filter($assets, fn($a) => $a['status'] === $status);
    }

    // Filter by category if provided
    $category = $_GET['category'] ?? null;
    if ($category) {
        $assets = array_filter($assets, fn($a) => $a['category'] === $category);
    }

    // Sort by name
    usort($assets, fn($a, $b) => strcasecmp($a['asset_name'], $b['asset_name']));

    Response::success(['assets' => array_values($assets)]);
}

function getAsset($id) {
    $asset = JsonDatabase::find('assets', 'asset_id', $id);

    if (!$asset) {
        Response::error('Asset not found', 404);
    }

    // Get photos
    $allPhotos = JsonDatabase::getAll('asset_photos');
    $asset['photos'] = array_values(array_filter($allPhotos, fn($p) => $p['asset_id'] === $id));

    // Get main photo
    foreach ($asset['photos'] as $photo) {
        if ($photo['photo_type'] === 'main') {
            $asset['photo'] = $photo['photo_path'];
            break;
        }
    }

    Response::success(['asset' => $asset]);
}

function createAsset() {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input['asset_name']) {
        Response::error('Asset name is required');
    }

    // Generate asset ID
    $category = $input['category'] ?? 'Other';
    $prefix = getAssetPrefix($category);
    $assets = JsonDatabase::getAll('assets');

    // Find next number for this prefix
    $maxNum = 0;
    foreach ($assets as $asset) {
        if (strpos($asset['asset_id'], $prefix) === 0) {
            $num = intval(substr($asset['asset_id'], strlen($prefix)));
            if ($num > $maxNum) $maxNum = $num;
        }
    }
    $assetId = $prefix . str_pad($maxNum + 1, 3, '0', STR_PAD_LEFT);

    // Generate QR code URL
    $qrCode = QR_API_URL . '?text=' . urlencode($assetId) . '&size=350&format=png&ecc=M&margin=1';

    $asset = [
        'asset_id' => $assetId,
        'asset_name' => $input['asset_name'],
        'category' => $category,
        'description' => $input['description'] ?? '',
        'serial_number' => $input['serial_number'] ?? '',
        'qr_code' => $qrCode,
        'status' => 'available',
        'current_borrower' => null,
        'checkout_date' => null,
        'expected_return_date' => null,
        'condition_status' => $input['condition_status'] ?? 'excellent',
        'storage_location' => $input['storage_location'] ?? '',
        'shelf' => $input['shelf'] ?? '',
        'notes' => $input['notes'] ?? '',
        'maintenance_interval_days' => 90,
        'total_checkouts' => 0
    ];

    $asset = JsonDatabase::insert('assets', $asset);

    Response::success(['asset_id' => $assetId, 'asset' => $asset], 'Asset created successfully');
}

function updateAsset($id) {
    $input = json_decode(file_get_contents('php://input'), true);

    $asset = JsonDatabase::find('assets', 'asset_id', $id);
    if (!$asset) {
        Response::error('Asset not found', 404);
    }

    // Allowed fields to update
    $allowedFields = ['asset_name', 'category', 'description', 'serial_number', 'condition_status', 'storage_location', 'shelf', 'notes', 'status'];
    $updates = array_intersect_key($input, array_flip($allowedFields));

    $updated = JsonDatabase::update('assets', 'asset_id', $id, $updates);

    Response::success(['asset' => $updated], 'Asset updated successfully');
}

function deleteAsset($id) {
    $asset = JsonDatabase::find('assets', 'asset_id', $id);
    if (!$asset) {
        Response::error('Asset not found', 404);
    }

    // Delete asset
    JsonDatabase::delete('assets', 'asset_id', $id);

    // Delete associated photos
    $photos = JsonDatabase::getAll('asset_photos');
    $photos = array_filter($photos, fn($p) => $p['asset_id'] !== $id);
    JsonDatabase::saveAll('asset_photos', array_values($photos));

    Response::success(null, 'Asset deleted successfully');
}

function checkoutAsset($id) {
    $input = json_decode(file_get_contents('php://input'), true);

    $asset = JsonDatabase::find('assets', 'asset_id', $id);
    if (!$asset) {
        Response::error('Asset not found', 404);
    }

    if ($asset['status'] !== 'available') {
        Response::error('Asset is not available for checkout');
    }

    $borrower = $input['borrower_name'] ?? '';
    $returnDate = $input['expected_return_date'] ?? '';
    $project = $input['project'] ?? '';
    $purpose = $input['purpose'] ?? '';
    $notes = $input['notes'] ?? '';
    $photos = $input['photos'] ?? [];

    if (!$borrower) {
        Response::error('Borrower name is required');
    }

    // Update asset
    JsonDatabase::update('assets', 'asset_id', $id, [
        'status' => 'checked_out',
        'current_borrower' => $borrower,
        'checkout_date' => date('Y-m-d H:i:s'),
        'expected_return_date' => $returnDate ?: null,
        'current_project' => $project,
        'total_checkouts' => ($asset['total_checkouts'] ?? 0) + 1
    ]);

    // Log transaction
    $transactionId = 'TXN-' . strtoupper(substr(uniqid(), -8));
    JsonDatabase::insert('transactions', [
        'transaction_id' => $transactionId,
        'asset_id' => $id,
        'asset_name' => $asset['asset_name'],
        'borrower_name' => $borrower,
        'transaction_type' => 'checkout',
        'project' => $project,
        'purpose' => $purpose,
        'notes' => $notes,
        'transaction_date' => date('Y-m-d H:i:s')
    ]);

    // Send email notification
    $updatedAsset = JsonDatabase::find('assets', 'asset_id', $id);
    EmailService::sendCheckoutNotification($updatedAsset, $borrower, $returnDate, $purpose, $photos);

    // Log to audit
    AuditLog::log('checkout', 'asset', $id, "Checked out to {$borrower}" . ($project ? " for project: {$project}" : ''));

    Response::success(['transaction_id' => $transactionId], 'Asset checked out successfully');
}

function checkinAsset($id) {
    $input = json_decode(file_get_contents('php://input'), true);

    $asset = JsonDatabase::find('assets', 'asset_id', $id);
    if (!$asset) {
        Response::error('Asset not found', 404);
    }

    if ($asset['status'] !== 'checked_out') {
        Response::error('Asset is not checked out');
    }

    $condition = $input['condition_on_return'] ?? 'excellent';
    $notes = $input['notes'] ?? '';
    $photos = $input['photos'] ?? [];
    $borrower = $asset['current_borrower'];

    // Update status based on condition
    $newStatus = $condition === 'needs_repair' ? 'maintenance' : 'available';

    // Update asset
    JsonDatabase::update('assets', 'asset_id', $id, [
        'status' => $newStatus,
        'current_borrower' => null,
        'checkout_date' => null,
        'expected_return_date' => null,
        'current_project' => null,
        'last_returned_date' => date('Y-m-d H:i:s'),
        'condition_status' => $condition
    ]);

    // Log transaction
    JsonDatabase::insert('transactions', [
        'asset_id' => $id,
        'asset_name' => $asset['asset_name'],
        'borrower_name' => $borrower,
        'transaction_type' => 'checkin',
        'condition_on_return' => $condition,
        'notes' => $notes,
        'transaction_date' => date('Y-m-d H:i:s')
    ]);

    // Send email notification
    EmailService::sendCheckinNotification($asset, $borrower, $condition, $notes, $photos);

    // Log to audit
    AuditLog::log('checkin', 'asset', $id, "Returned by {$borrower}, condition: {$condition}");

    Response::success(null, 'Asset checked in successfully');
}

function bulkCheckout() {
    $input = json_decode(file_get_contents('php://input'), true);

    $assetIds = $input['asset_ids'] ?? [];
    $borrower = $input['borrower_name'] ?? '';
    $returnDate = $input['expected_return_date'] ?? '';
    $purpose = $input['purpose'] ?? '';
    $notes = $input['notes'] ?? '';
    $photos = $input['photos'] ?? [];

    if (empty($assetIds)) {
        Response::error('No assets selected');
    }

    if (!$borrower) {
        Response::error('Borrower name is required');
    }

    $checkedOut = [];
    $errors = [];

    foreach ($assetIds as $id) {
        $asset = JsonDatabase::find('assets', 'asset_id', $id);

        if (!$asset) {
            $errors[] = "Asset {$id} not found";
            continue;
        }

        if ($asset['status'] !== 'available') {
            $errors[] = "Asset {$id} ({$asset['asset_name']}) is not available";
            continue;
        }

        // Update asset
        JsonDatabase::update('assets', 'asset_id', $id, [
            'status' => 'checked_out',
            'current_borrower' => $borrower,
            'checkout_date' => date('Y-m-d H:i:s'),
            'expected_return_date' => $returnDate ?: null,
            'total_checkouts' => ($asset['total_checkouts'] ?? 0) + 1
        ]);

        // Log transaction
        JsonDatabase::insert('transactions', [
            'asset_id' => $id,
            'asset_name' => $asset['asset_name'],
            'borrower_name' => $borrower,
            'transaction_type' => 'checkout',
            'purpose' => $purpose,
            'notes' => $notes,
            'transaction_date' => date('Y-m-d H:i:s')
        ]);

        $checkedOut[] = $asset;
    }

    // Send bulk email notification
    if (!empty($checkedOut)) {
        EmailService::sendBulkCheckoutNotification($checkedOut, $borrower, $returnDate, $purpose, $photos);
    }

    $result = [
        'checked_out' => count($checkedOut),
        'errors' => $errors
    ];

    if (!empty($errors)) {
        Response::success($result, 'Checkout completed with some errors');
    } else {
        Response::success($result, 'All items checked out successfully');
    }
}

function uploadAssetPhoto($id) {
    if (!isset($_FILES['photo'])) {
        Response::error('No photo uploaded');
    }

    $asset = JsonDatabase::find('assets', 'asset_id', $id);
    if (!$asset) {
        Response::error('Asset not found', 404);
    }

    $file = $_FILES['photo'];
    $photoType = $_POST['photo_type'] ?? 'main';
    $transactionType = $_POST['transaction_type'] ?? null; // checkout or checkin

    // Validate file
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ALLOWED_EXTENSIONS)) {
        Response::error('Invalid file type. Allowed: ' . implode(', ', ALLOWED_EXTENSIONS));
    }

    if ($file['size'] > MAX_PHOTO_SIZE) {
        Response::error('File too large. Max size: ' . (MAX_PHOTO_SIZE / 1024 / 1024) . 'MB');
    }

    // Determine save directory
    $saveDir = $transactionType ? UPLOADS_PATH . '/transactions' : UPLOADS_PATH . '/assets';
    if (!is_dir($saveDir)) mkdir($saveDir, 0755, true);

    // Generate filename
    $filename = $id . '_' . $photoType . '_' . time() . '.' . $ext;
    $filepath = $saveDir . '/' . $filename;
    $relativePath = 'api/uploads/' . ($transactionType ? 'transactions' : 'assets') . '/' . $filename;

    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        Response::error('Failed to save photo');
    }

    // Save to database
    if (!$transactionType) {
        // Asset photo
        JsonDatabase::insert('asset_photos', [
            'asset_id' => $id,
            'photo_path' => $relativePath,
            'photo_type' => $photoType,
            'file_size' => $file['size'],
            'mime_type' => $file['type'],
            'description' => $_POST['description'] ?? ucfirst($photoType)
        ]);
    } else {
        // Transaction photo (for QC)
        JsonDatabase::insert('equipment_photos', [
            'asset_id' => $id,
            'photo_type' => $transactionType,
            'photo_path' => $relativePath,
            'borrower_name' => $_POST['borrower_name'] ?? null,
            'notes' => $_POST['notes'] ?? null,
            'file_size' => $file['size'],
            'mime_type' => $file['type']
        ]);
    }

    Response::success(['photo_path' => $relativePath], 'Photo uploaded successfully');
}

function getCategories() {
    $assets = JsonDatabase::getAll('assets');
    $categories = [];

    foreach ($assets as $asset) {
        $cat = $asset['category'];
        if (!isset($categories[$cat])) {
            $categories[$cat] = 0;
        }
        $categories[$cat]++;
    }

    $result = [];
    foreach ($categories as $name => $count) {
        $result[] = ['name' => $name, 'count' => $count];
    }

    usort($result, fn($a, $b) => $b['count'] - $a['count']);

    Response::success(['categories' => $result]);
}

function searchAssets() {
    $query = $_GET['q'] ?? '';
    if (!$query) {
        Response::success(['assets' => []]);
    }

    $assets = JsonDatabase::getAll('assets');
    $query = strtolower($query);

    $results = array_filter($assets, function($asset) use ($query) {
        return strpos(strtolower($asset['asset_name']), $query) !== false ||
               strpos(strtolower($asset['asset_id']), $query) !== false ||
               strpos(strtolower($asset['serial_number'] ?? ''), $query) !== false;
    });

    Response::success(['assets' => array_values($results)]);
}

function getAssetPrefix($category) {
    $prefixes = [
        'Camera' => 'CAM',
        'Lens' => 'LEN',
        'Audio' => 'AUD',
        'Lighting' => 'LIT',
        'Storage' => 'STO',
        'Monitor' => 'MON',
        'Tripod' => 'TRI',
        'Cables' => 'CAB',
        'Other' => 'OTH'
    ];
    return $prefixes[$category] ?? 'OTH';
}
