<?php
// bulk_scanner.php - Bulk QR Scanner System
// Fix session path issue
if (!session_id()) {
    ini_set('session.save_path', sys_get_temp_dir());
    session_start();
}

require_once 'config/database.php';
require_once 'classes/Asset.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();
$asset = new Asset($db);

// Get all users for dropdown
$users_query = "SELECT username FROM users WHERE role IN ('admin', 'team_member') ORDER BY username";
$users_stmt = $db->prepare($users_query);
$users_stmt->execute();
$users = $users_stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle bulk operations via AJAX
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json');
    
    $operation = $_POST['operation'];
    $asset_id = $_POST['asset_id'];
    
    // Get asset info
    $asset_info = $asset->getByAssetId($asset_id);
    
    if (!$asset_info) {
        echo json_encode(['success' => false, 'message' => 'Asset not found']);
        exit();
    }
    
    if ($operation == 'bulk_checkout') {
        $borrower = $_POST['borrower_name'];
        $expected_return = $_POST['expected_return_date'];
        $purpose = $_POST['purpose'];
        
        if ($asset_info['status'] != 'available') {
            echo json_encode(['success' => false, 'message' => 'Asset not available', 'asset' => $asset_info]);
            exit();
        }
        
        if ($asset->checkOut($asset_id, $borrower, $expected_return, $purpose)) {
            echo json_encode(['success' => true, 'message' => 'Checked out successfully', 'asset' => $asset_info]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Checkout failed', 'asset' => $asset_info]);
        }
        
    } elseif ($operation == 'bulk_checkin') {
        $condition = $_POST['condition'];
        $notes = $_POST['notes'];
        
        if ($asset_info['status'] != 'checked_out') {
            echo json_encode(['success' => false, 'message' => 'Asset not checked out', 'asset' => $asset_info]);
            exit();
        }
        
        if ($asset->checkIn($asset_id, $condition, $notes)) {
            echo json_encode(['success' => true, 'message' => 'Checked in successfully', 'asset' => $asset_info]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Checkin failed', 'asset' => $asset_info]);
        }
    }
    
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bulk QR Scanner - Neofox Gear Control</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
    <style>
        .scanner-container {
            max-width: 500px;
            margin: 0 auto;
        }
        .operation-panel {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .scanned-item {
            background: white;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 15px;
            margin: 10px 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .scanned-item.success {
            border-left: 5px solid #28a745;
            background-color: #f8fff9;
        }
        .scanned-item.error {
            border-left: 5px solid #dc3545;
            background-color: #fff8f8;
        }
        .scanned-item.pending {
            border-left: 5px solid #ffc107;
            background-color: #fffdf7;
        }
        .scan-history {
            max-height: 400px;
            overflow-y: auto;
        }
        .scanner-status {
            text-align: center;
            padding: 20px;
            border: 2px dashed #ccc;
            border-radius: 10px;
            margin: 20px 0;
        }
        .mode-selector {
            margin-bottom: 20px;
        }
        .mode-btn {
            margin: 0 10px;
        }
        #qr-reader {
            border: 2px solid #007bff;
            border-radius: 10px;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-light bg-light">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-cogs"></i> Neofox Gear Control
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="index.php">Dashboard</a>
                <a class="nav-link" href="assets.php">Assets</a>
                <a class="nav-link" href="bulk_manage.php">Bulk Manage</a>
                <a class="nav-link" href="logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row">
            <div class="col-md-12 text-center">
                <h2><i class="fas fa-qrcode"></i> Bulk QR Scanner</h2>
                <p class="text-muted">Scan multiple QR codes to checkout or checkin assets in bulk</p>
            </div>
        </div>

        <div class="row">
            <!-- Scanner Section -->
            <div class="col-md-6">
                <div class="operation-panel">
                    <h5><i class="fas fa-cog"></i> Scanner Mode</h5>
                    
                    <div class="mode-selector text-center">
                        <button type="button" class="btn btn-success mode-btn" onclick="setMode('checkout')" id="checkoutModeBtn">
                            <i class="fas fa-sign-out-alt"></i> Checkout Mode
                        </button>
                        <button type="button" class="btn btn-warning mode-btn" onclick="setMode('checkin')" id="checkinModeBtn">
                            <i class="fas fa-sign-in-alt"></i> Checkin Mode
                        </button>
                    </div>
                    
                    <div id="currentMode" class="text-center mb-3">
                        <span class="badge bg-light text-dark">Select a mode to start scanning</span>
                    </div>
                </div>

                <!-- Checkout Form -->
                <div class="card" id="checkoutSettings" style="display: none;">
                    <div class="card-header bg-success text-white">
                        <h6><i class="fas fa-sign-out-alt"></i> Checkout Settings</h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="bulk_borrower" class="form-label">Borrower *</label>
                            <select class="form-control" id="bulk_borrower" required>
                                <option value="">Select team member...</option>
                                <?php foreach ($users as $user): ?>
                                <option value="<?php echo htmlspecialchars($user['username']); ?>">
                                    <?php echo htmlspecialchars($user['username']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="bulk_return_date" class="form-label">Expected Return *</label>
                            <input type="datetime-local" class="form-control" id="bulk_return_date" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="bulk_purpose" class="form-label">Purpose</label>
                            <input type="text" class="form-control" id="bulk_purpose" placeholder="Project or shoot purpose...">
                        </div>
                    </div>
                </div>

                <!-- Checkin Form -->
                <div class="card" id="checkinSettings" style="display: none;">
                    <div class="card-header bg-warning text-dark">
                        <h6><i class="fas fa-sign-in-alt"></i> Checkin Settings</h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="bulk_condition" class="form-label">Equipment Condition *</label>
                            <select class="form-control" id="bulk_condition" required>
                                <option value="">Select condition...</option>
                                <option value="excellent">Excellent</option>
                                <option value="good">Good</option>
                                <option value="needs_repair">Needs Repair</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="bulk_notes" class="form-label">Return Notes</label>
                            <input type="text" class="form-control" id="bulk_notes" placeholder="Any issues or observations...">
                        </div>
                    </div>
                </div>

                <!-- Scanner -->
                <div class="scanner-container">
                    <div id="scanner-status" class="scanner-status">
                        <i class="fas fa-camera fa-2x text-muted"></i>
                        <p>Select a mode to activate scanner</p>
                    </div>
                    <div id="qr-reader" style="display: none;"></div>
                    
                    <div class="text-center mt-3">
                        <button id="start-scan" class="btn btn-primary" style="display: none;" onclick="startScanning()">
                            <i class="fas fa-play"></i> Start Scanning
                        </button>
                        <button id="stop-scan" class="btn btn-danger" style="display: none;" onclick="stopScanning()">
                            <i class="fas fa-stop"></i> Stop Scanning
                        </button>
                    </div>
                </div>
            </div>

            <!-- Scan Results -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5><i class="fas fa-history"></i> Scan Results</h5>
                        <div>
                            <span id="success-count" class="badge bg-success">0 Success</span>
                            <span id="error-count" class="badge bg-danger">0 Errors</span>
                            <button class="btn btn-sm btn-outline-secondary" onclick="clearResults()">Clear</button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div id="scan-results" class="scan-history">
                            <div class="text-center text-muted p-4">
                                <i class="fas fa-inbox fa-2x"></i>
                                <p>Scan results will appear here</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let html5QrcodeScanner = null;
        let isScanning = false;
        let currentMode = null;
        let successCount = 0;
        let errorCount = 0;
        let processedAssets = new Set(); // Track processed assets to avoid duplicates
        let lastScannedTime = 0; // Track when last scan happened
        let scanCooldown = 2000; // 2 second cooldown between scans of same item

        function setMode(mode) {
            currentMode = mode;
            
            // Update UI
            if (mode === 'checkout') {
                document.getElementById('checkoutModeBtn').classList.add('active');
                document.getElementById('checkinModeBtn').classList.remove('active');
                document.getElementById('checkoutSettings').style.display = 'block';
                document.getElementById('checkinSettings').style.display = 'none';
                document.getElementById('currentMode').innerHTML = '<span class="badge bg-success">CHECKOUT MODE</span>';
                
                // Set default return date
                const tomorrow = new Date();
                tomorrow.setDate(tomorrow.getDate() + 1);
                tomorrow.setHours(17, 0, 0, 0);
                document.getElementById('bulk_return_date').value = tomorrow.toISOString().slice(0, 16);
                
            } else if (mode === 'checkin') {
                document.getElementById('checkinModeBtn').classList.add('active');
                document.getElementById('checkoutModeBtn').classList.remove('active');
                document.getElementById('checkinSettings').style.display = 'block';
                document.getElementById('checkoutSettings').style.display = 'none';
                document.getElementById('currentMode').innerHTML = '<span class="badge bg-warning">CHECKIN MODE</span>';
            }
            
            // Show scanner controls
            document.getElementById('scanner-status').innerHTML = `
                <i class="fas fa-qrcode fa-2x text-primary"></i>
                <p>Ready to scan in ${mode.toUpperCase()} mode</p>
            `;
            document.getElementById('start-scan').style.display = 'inline-block';
        }

        function startScanning() {
            if (!currentMode) {
                alert('Please select a mode first (Checkout or Checkin)');
                return;
            }
            
            if (!validateSettings()) {
                return;
            }
            
            if (!isScanning) {
                html5QrcodeScanner = new Html5QrcodeScanner(
                    "qr-reader", 
                    { 
                        fps: 5,  // Reduced from 10 to slow down scanning
                        qrbox: {width: 250, height: 250},
                        rememberLastUsedCamera: true,
                        experimentalFeatures: {
                            useBarCodeDetectorIfSupported: true
                        }
                    }
                );
                html5QrcodeScanner.render(onScanSuccess, onScanFailure);
                
                document.getElementById('qr-reader').style.display = 'block';
                document.getElementById('scanner-status').style.display = 'none';
                document.getElementById('start-scan').style.display = 'none';
                document.getElementById('stop-scan').style.display = 'inline-block';
                isScanning = true;
            }
        }

        function stopScanning() {
            if (isScanning && html5QrcodeScanner) {
                html5QrcodeScanner.clear();
                document.getElementById('qr-reader').style.display = 'none';
                document.getElementById('scanner-status').style.display = 'block';
                document.getElementById('start-scan').style.display = 'inline-block';
                document.getElementById('stop-scan').style.display = 'none';
                isScanning = false;
            }
        }

        function validateSettings() {
            if (currentMode === 'checkout') {
                if (!document.getElementById('bulk_borrower').value) {
                    alert('Please select a borrower');
                    return false;
                }
                if (!document.getElementById('bulk_return_date').value) {
                    alert('Please set an expected return date');
                    return false;
                }
            } else if (currentMode === 'checkin') {
                if (!document.getElementById('bulk_condition').value) {
                    alert('Please select equipment condition');
                    return false;
                }
            }
            return true;
        }

        function onScanSuccess(decodedText, decodedResult) {
            // Add cooldown to prevent rapid repeated scans
            const currentTime = Date.now();
            if (currentTime - lastScannedTime < scanCooldown) {
                return; // Ignore rapid repeated scans
            }
            lastScannedTime = currentTime;
            
            // Extract asset_id from QR code URL
            const urlMatch = decodedText.match(/asset_id=([^&]+)/);
            if (!urlMatch) {
                addResult('error', 'Invalid QR Code', 'QR code does not contain asset_id');
                return;
            }
            
            const assetId = urlMatch[1];
            
            // Check if already processed
            if (processedAssets.has(assetId)) {
                // Show feedback but don't add to results again
                showToast('warning', `${assetId} already processed`);
                return;
            }
            
            // Process the asset
            processAsset(assetId);
        }

        function onScanFailure(error) {
            // Silent - don't spam console with scan failures
        }

        function processAsset(assetId) {
            const formData = new FormData();
            formData.append('ajax', '1');
            formData.append('operation', 'bulk_' + currentMode);
            formData.append('asset_id', assetId);
            
            if (currentMode === 'checkout') {
                formData.append('borrower_name', document.getElementById('bulk_borrower').value);
                formData.append('expected_return_date', document.getElementById('bulk_return_date').value);
                formData.append('purpose', document.getElementById('bulk_purpose').value);
            } else {
                formData.append('condition', document.getElementById('bulk_condition').value);
                formData.append('notes', document.getElementById('bulk_notes').value);
            }
            
            fetch('bulk_scanner.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                processedAssets.add(assetId);
                
                if (data.success) {
                    successCount++;
                    addResult('success', data.asset.asset_name, data.message);
                } else {
                    errorCount++;
                    addResult('error', data.asset ? data.asset.asset_name : assetId, data.message);
                }
                
                updateCounters();
            })
            .catch(error => {
                errorCount++;
                addResult('error', assetId, 'Network error: ' + error.message);
                updateCounters();
            });
        }

        function addResult(type, assetName, message) {
            const resultsContainer = document.getElementById('scan-results');
            
            // Clear placeholder text
            if (resultsContainer.children.length === 1 && resultsContainer.children[0].classList.contains('text-center')) {
                resultsContainer.innerHTML = '';
            }
            
            const resultDiv = document.createElement('div');
            resultDiv.className = `scanned-item ${type}`;
            
            const icon = type === 'success' ? 'check-circle' : type === 'error' ? 'times-circle' : 'clock';
            const iconColor = type === 'success' ? 'success' : type === 'error' ? 'danger' : 'warning';
            
            resultDiv.innerHTML = `
                <div>
                    <strong>${assetName}</strong>
                    <small class="d-block text-muted">${message}</small>
                </div>
                <i class="fas fa-${icon} text-${iconColor}"></i>
            `;
            
            resultsContainer.insertBefore(resultDiv, resultsContainer.firstChild);
            
            // Keep only last 20 results
            while (resultsContainer.children.length > 20) {
                resultsContainer.removeChild(resultsContainer.lastChild);
            }
        }

        function updateCounters() {
            document.getElementById('success-count').textContent = successCount + ' Success';
            document.getElementById('error-count').textContent = errorCount + ' Errors';
        }

        function clearResults() {
            document.getElementById('scan-results').innerHTML = `
                <div class="text-center text-muted p-4">
                    <i class="fas fa-inbox fa-2x"></i>
                    <p>Scan results will appear here</p>
                </div>
            `;
            successCount = 0;
            errorCount = 0;
            processedAssets.clear();
            updateCounters();
        }

        function showToast(type, message) {
            // Create a temporary toast notification
            const toast = document.createElement('div');
            toast.className = `alert alert-${type === 'warning' ? 'warning' : 'info'} alert-dismissible fade show position-fixed`;
            toast.style.cssText = 'top: 20px; right: 20px; z-index: 9999; max-width: 300px;';
            toast.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            
            document.body.appendChild(toast);
            
            // Auto remove after 3 seconds
            setTimeout(() => {
                if (toast.parentNode) {
                    toast.parentNode.removeChild(toast);
                }
            }, 3000);
        }
    </script>
</body>
</html>