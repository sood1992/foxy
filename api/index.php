<?php
/**
 * FOXY - NeoFox Media Inventory Management System
 * REST API Entry Point
 *
 * Routes all API requests to appropriate handlers
 */

// CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Error handling
set_error_handler(function($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

// Load configuration and utilities
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/utils/JsonDatabase.php';
require_once __DIR__ . '/utils/Auth.php';
require_once __DIR__ . '/utils/EmailService.php';
require_once __DIR__ . '/utils/Response.php';
require_once __DIR__ . '/utils/AuditLog.php';

// Parse request
$method = $_SERVER['REQUEST_METHOD'];
$uri = $_SERVER['REQUEST_URI'];
$basePath = '/api';

// Remove base path and query string
$path = parse_url($uri, PHP_URL_PATH);
$path = preg_replace('#^' . preg_quote($basePath) . '#', '', $path);
$path = trim($path, '/');
$segments = $path ? explode('/', $path) : [];

// Route the request
try {
    $resource = $segments[0] ?? '';
    $id = $segments[1] ?? null;
    $action = $segments[2] ?? null;

    switch ($resource) {
        case 'auth':
            require_once __DIR__ . '/routes/auth.php';
            handleAuth($method, $id, $action);
            break;

        case 'assets':
            require_once __DIR__ . '/routes/assets.php';
            handleAssets($method, $id, $action);
            break;

        case 'transactions':
            require_once __DIR__ . '/routes/transactions.php';
            handleTransactions($method, $id, $action);
            break;

        case 'users':
            require_once __DIR__ . '/routes/users.php';
            handleUsers($method, $id, $action);
            break;

        case 'requests':
            require_once __DIR__ . '/routes/requests.php';
            handleRequests($method, $id, $action);
            break;

        case 'maintenance':
            require_once __DIR__ . '/routes/maintenance.php';
            handleMaintenance($method, $id, $action);
            break;

        case 'stats':
            require_once __DIR__ . '/routes/stats.php';
            handleStats($method, $id, $action);
            break;

        case 'kits':
            require_once __DIR__ . '/routes/kits.php';
            handleKits($method, $id, $action);
            break;

        case 'reservations':
            require_once __DIR__ . '/routes/reservations.php';
            handleReservations($method, $id, $action);
            break;

        case 'audit':
            Auth::requireAuth();
            $filters = [
                'action' => $_GET['action'] ?? '',
                'user' => $_GET['user'] ?? '',
                'dateFrom' => $_GET['dateFrom'] ?? '',
                'dateTo' => $_GET['dateTo'] ?? '',
                'page' => $_GET['page'] ?? 1,
                'limit' => $_GET['limit'] ?? 50
            ];
            $logs = AuditLog::getLogs($filters);
            Response::json(['logs' => $logs]);
            break;

        default:
            Response::error('Not found', 404);
    }
} catch (Exception $e) {
    error_log('API Error: ' . $e->getMessage());
    Response::error($e->getMessage(), 500);
}
