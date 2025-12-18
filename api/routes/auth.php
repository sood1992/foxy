<?php
/**
 * Authentication routes
 */

function handleAuth($method, $action, $subAction) {
    switch ($action) {
        case 'login':
            if ($method !== 'POST') Response::error('Method not allowed', 405);
            handleLogin();
            break;

        case 'verify':
            handleVerify();
            break;

        case 'logout':
            handleLogout();
            break;

        default:
            Response::error('Not found', 404);
    }
}

function handleLogin() {
    $input = json_decode(file_get_contents('php://input'), true);

    $username = $input['username'] ?? '';
    $password = $input['password'] ?? '';

    if (!$username || !$password) {
        Response::error('Username and password required');
    }

    $users = JsonDatabase::getAll('users');
    $user = null;

    foreach ($users as $u) {
        if (strtolower($u['username']) === strtolower($username)) {
            $user = $u;
            break;
        }
    }

    if (!$user) {
        Response::error('Invalid credentials');
    }

    // Check password
    if (!Auth::verifyPassword($password, $user['password'])) {
        Response::error('Invalid credentials');
    }

    // Generate token
    $token = Auth::generateToken($user);

    Response::success([
        'user' => [
            'id' => $user['id'],
            'username' => $user['username'],
            'email' => $user['email'] ?? null,
            'role' => $user['role']
        ],
        'token' => $token
    ], 'Login successful');
}

function handleVerify() {
    $user = Auth::getCurrentUser();

    if (!$user) {
        Response::error('Invalid or expired token', 401);
    }

    Response::success(['valid' => true, 'user' => $user]);
}

function handleLogout() {
    Response::success(null, 'Logged out successfully');
}
