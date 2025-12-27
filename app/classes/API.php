<?php
// classes/API.php
class API {
    private $conn;
    private $asset;
    private $gear_request;
    
    public function __construct($db) {
        $this->conn = $db;
        $this->asset = new Asset($db);
        $this->gear_request = new GearRequest($db);
    }
    
    public function handleRequest() {
        header('Content-Type: application/json');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');
        
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit();
        }
        
        $request_uri = $_SERVER['REQUEST_URI'];
        $path = parse_url($request_uri, PHP_URL_PATH);
        $path_parts = explode('/', trim($path, '/'));
        
        // Remove 'api' from path if present
        if (isset($path_parts[0]) && $path_parts[0] === 'api') {
            array_shift($path_parts);
        }
        
        $endpoint = $path_parts[0] ?? '';
        $method = $_SERVER['REQUEST_METHOD'];
        
        try {
            switch ($endpoint) {
                case 'assets':
                    $this->handleAssetsEndpoint($method, $path_parts);
                    break;
                case 'checkout':
                    $this->handleCheckoutEndpoint($method);
                    break;
                case 'checkin':
                    $this->handleCheckinEndpoint($method);
                    break;
                case 'requests':
                    $this->handleRequestsEndpoint($method, $path_parts);
                    break;
                case 'stats':
                    $this->handleStatsEndpoint($method);
                    break;
                case 'auth':
                    $this->handleAuthEndpoint($method, $path_parts);
                    break;
                default:
                    $this->sendResponse(404, ['error' => 'Endpoint not found']);
            }
        } catch (Exception $e) {
            $this->sendResponse(500, ['error' => 'Internal server error', 'message' => $e->getMessage()]);
        }
    }
    
    private function handleAssetsEndpoint($method, $path_parts) {
        switch ($method) {
            case 'GET':
                if (isset($path_parts[1])) {
                    // Get specific asset
                    $asset_id = $path_parts[1];
                    $asset = $this->asset->getByAssetId($asset_id);
                    if ($asset) {
                        $this->sendResponse(200, ['data' => $asset]);
                    } else {
                        $this->sendResponse(404, ['error' => 'Asset not found']);
                    }
                } else {
                    // Get all assets with optional filters
                    $category = $_GET['category'] ?? null;
                    $status = $_GET['status'] ?? null;
                    $search = $_GET['search'] ?? null;
                    
                    if ($search || $category || $status) {
                        $assets = $this->asset->search($search, $category, $status);
                    } else {
                        $assets = $this->asset->getAll();
                    }
                    
                    $this->sendResponse(200, ['data' => $assets, 'count' => count($assets)]);
                }
                break;
                
            case 'POST':
                $input = $this->getInputData();
                if ($this->asset->create($input)) {
                    $this->sendResponse(201, ['message' => 'Asset created successfully']);
                } else {
                    $this->sendResponse(400, ['error' => 'Failed to create asset', 'details' => $this->asset->getErrors()]);
                }
                break;
                
            case 'PUT':
                if (!isset($path_parts[1])) {
                    $this->sendResponse(400, ['error' => 'Asset ID required']);
                    return;
                }
                
                $asset_id = $path_parts[1];
                $input = $this->getInputData();
                
                // Get asset ID from database
                $asset = $this->asset->getByAssetId($asset_id);
                if (!$asset) {
                    $this->sendResponse(404, ['error' => 'Asset not found']);
                    return;
                }
                
                if ($this->asset->update($asset['id'], $input)) {
                    $this->sendResponse(200, ['message' => 'Asset updated successfully']);
                } else {
                    $this->sendResponse(400, ['error' => 'Failed to update asset', 'details' => $this->asset->getErrors()]);
                }
                break;
                
            case 'DELETE':
                if (!isset($path_parts[1])) {
                    $this->sendResponse(400, ['error' => 'Asset ID required']);
                    return;
                }
                
                $asset_id = $path_parts[1];
                $asset = $this->asset->getByAssetId($asset_id);
                if (!$asset) {
                    $this->sendResponse(404, ['error' => 'Asset not found']);
                    return;
                }
                
                if ($this->asset->delete($asset['id'])) {
                    $this->sendResponse(200, ['message' => 'Asset deleted successfully']);
                } else {
                    $this->sendResponse(400, ['error' => 'Failed to delete asset', 'details' => $this->asset->getErrors()]);
                }
                break;
                
            default:
                $this->sendResponse(405, ['error' => 'Method not allowed']);
        }
    }
    
    private function handleCheckoutEndpoint($method) {
        if ($method !== 'POST') {
            $this->sendResponse(405, ['error' => 'Method not allowed']);
            return;
        }
        
        $input = $this->getInputData();
        $required_fields = ['asset_id', 'borrower', 'expected_return'];
        
        foreach ($required_fields as $field) {
            if (!isset($input[$field]) || empty($input[$field])) {
                $this->sendResponse(400, ['error' => "Field '{$field}' is required"]);
                return;
            }
        }
        
        $purpose = $input['purpose'] ?? '';
        
        if ($this->asset->checkOut($input['asset_id'], $input['borrower'], $input['expected_return'], $purpose)) {
            $this->sendResponse(200, ['message' => 'Asset checked out successfully']);
        } else {
            $this->sendResponse(400, ['error' => 'Failed to check out asset', 'details' => $this->asset->getErrors()]);
        }
    }
    
    private function handleCheckinEndpoint($method) {
        if ($method !== 'POST') {
            $this->sendResponse(405, ['error' => 'Method not allowed']);
            return;
        }
        
        $input = $this->getInputData();
        $required_fields = ['asset_id', 'condition'];
        
        foreach ($required_fields as $field) {
            if (!isset($input[$field]) || empty($input[$field])) {
                $this->sendResponse(400, ['error' => "Field '{$field}' is required"]);
                return;
            }
        }
        
        $notes = $input['notes'] ?? '';
        
        if ($this->asset->checkIn($input['asset_id'], $input['condition'], $notes)) {
            $this->sendResponse(200, ['message' => 'Asset checked in successfully']);
        } else {
            $this->sendResponse(400, ['error' => 'Failed to check in asset', 'details' => $this->asset->getErrors()]);
        }
    }
    
    private function handleRequestsEndpoint($method, $path_parts) {
        switch ($method) {
            case 'GET':
                $requests = $this->gear_request->getAll();
                $this->sendResponse(200, ['data' => $requests, 'count' => count($requests)]);
                break;
                
            case 'POST':
                $input = $this->getInputData();
                $required_fields = ['requester_name', 'required_items', 'request_dates'];
                
                foreach ($required_fields as $field) {
                    if (!isset($input[$field]) || empty($input[$field])) {
                        $this->sendResponse(400, ['error' => "Field '{$field}' is required"]);
                        return;
                    }
                }
                
                if ($this->gear_request->create($input)) {
                    $this->sendResponse(201, ['message' => 'Request created successfully']);
                } else {
                    $this->sendResponse(400, ['error' => 'Failed to create request']);
                }
                break;
                
            case 'PUT':
                if (!isset($path_parts[1])) {
                    $this->sendResponse(400, ['error' => 'Request ID required']);
                    return;
                }
                
                $request_id = $path_parts[1];
                $input = $this->getInputData();
                
                if (!isset($input['status'])) {
                    $this->sendResponse(400, ['error' => 'Status is required']);
                    return;
                }
                
                $admin_notes = $input['admin_notes'] ?? '';
                
                if ($this->gear_request->updateStatus($request_id, $input['status'], $admin_notes)) {
                    $this->sendResponse(200, ['message' => 'Request updated successfully']);
                } else {
                    $this->sendResponse(400, ['error' => 'Failed to update request']);
                }
                break;
                
            default:
                $this->sendResponse(405, ['error' => 'Method not allowed']);
        }
    }
    
    private function handleStatsEndpoint($method) {
        if ($method !== 'GET') {
            $this->sendResponse(405, ['error' => 'Method not allowed']);
            return;
        }
        
        $stats = $this->asset->getDetailedStats();
        $overdue = $this->asset->getOverdueAssets();
        $checked_out = $this->asset->getCheckedOutAssets();
        $popular = $this->asset->getPopularAssets();
        
        $this->sendResponse(200, [
            'stats' => $stats,
            'overdue' => $overdue,
            'checked_out' => $checked_out,
            'popular_assets' => $popular
        ]);
    }
    
    private function handleAuthEndpoint($method, $path_parts) {
        if ($method !== 'POST') {
            $this->sendResponse(405, ['error' => 'Method not allowed']);
            return;
        }
        
        $action = $path_parts[1] ?? '';
        
        switch ($action) {
            case 'login':
                $input = $this->getInputData();
                
                if (!isset($input['username']) || !isset($input['password'])) {
                    $this->sendResponse(400, ['error' => 'Username and password required']);
                    return;
                }
                
                $query = "SELECT id, username, password, role FROM users WHERE username = :username";
                $stmt = $this->conn->prepare($query);
                $stmt->bindParam(":username", $input['username']);
                $stmt->execute();
                
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($user && password_verify($input['password'], $user['password'])) {
                    // Generate API token (in production, use JWT or similar)
                    $token = bin2hex(random_bytes(32));
                    
                    // Store token in database (you'd need a tokens table)
                    // For now, just return user info
                    
                    $this->sendResponse(200, [
                        'message' => 'Login successful',
                        'user' => [
                            'id' => $user['id'],
                            'username' => $user['username'],
                            'role' => $user['role']
                        ],
                        'token' => $token
                    ]);
                } else {
                    $this->sendResponse(401, ['error' => 'Invalid credentials']);
                }
                break;
                
            default:
                $this->sendResponse(404, ['error' => 'Auth endpoint not found']);
        }
    }
    
    private function getInputData() {
        return json_decode(file_get_contents('php://input'), true) ?: [];
    }
    
    private function sendResponse($status_code, $data) {
        http_response_code($status_code);
        echo json_encode($data);
        exit();
    }
    
    // Validate API token (if using token-based auth)
    private function validateToken($token) {
        // Implementation depends on your token storage strategy
        // This is a placeholder
        return true;
    }
}

// api.php (endpoint file)
/*
<?php
require_once 'config/database.php';
require_once 'classes/Asset.php';
require_once 'classes/GearRequest.php';
require_once 'classes/API.php';

$database = new Database();
$db = $database->getConnection();

$api = new API($db);
$api->handleRequest();
?>
*/
?>