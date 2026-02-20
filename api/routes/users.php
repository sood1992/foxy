<?php
/**
 * User routes
 */

function handleUsers($method, $id, $action) {
    if ($id === 'borrowers') {
        getBorrowers();
        return;
    }

    switch ($method) {
        case 'GET':
            Auth::requireAuth();
            if ($id) {
                getUser($id);
            } else {
                getUsers();
            }
            break;

        case 'POST':
            Auth::requireAdmin();
            createUser();
            break;

        case 'PUT':
            Auth::requireAdmin();
            updateUser($id);
            break;

        case 'DELETE':
            Auth::requireAdmin();
            deleteUser($id);
            break;

        default:
            Response::error('Method not allowed', 405);
    }
}

function getUsers() {
    $users = JsonDatabase::getAll('users');

    // Remove passwords from response
    $users = array_map(function($user) {
        unset($user['password']);
        return $user;
    }, $users);

    Response::success(['users' => array_values($users)]);
}

function getUser($id) {
    $user = JsonDatabase::findById('users', $id);

    if (!$user) {
        Response::error('User not found', 404);
    }

    unset($user['password']);
    Response::success(['user' => $user]);
}

function createUser() {
    $input = json_decode(file_get_contents('php://input'), true);

    $username = $input['username'] ?? '';
    $password = $input['password'] ?? '';
    $email = $input['email'] ?? '';
    $role = $input['role'] ?? 'team_member';

    if (!$username || !$password) {
        Response::error('Username and password required');
    }

    // Check if username exists
    $existing = JsonDatabase::find('users', 'username', $username);
    if ($existing) {
        Response::error('Username already exists');
    }

    $user = JsonDatabase::insert('users', [
        'username' => $username,
        'password' => Auth::hashPassword($password),
        'email' => $email,
        'role' => $role
    ]);

    unset($user['password']);
    Response::success(['user' => $user], 'User created successfully');
}

function updateUser($id) {
    $input = json_decode(file_get_contents('php://input'), true);

    $user = JsonDatabase::findById('users', $id);
    if (!$user) {
        Response::error('User not found', 404);
    }

    $updates = [];

    if (isset($input['email'])) {
        $updates['email'] = $input['email'];
    }

    if (isset($input['role'])) {
        $updates['role'] = $input['role'];
    }

    if (!empty($input['password'])) {
        $updates['password'] = Auth::hashPassword($input['password']);
    }

    if (!empty($updates)) {
        $user = JsonDatabase::updateById('users', $id, $updates);
    }

    unset($user['password']);
    Response::success(['user' => $user], 'User updated successfully');
}

function deleteUser($id) {
    $user = JsonDatabase::findById('users', $id);
    if (!$user) {
        Response::error('User not found', 404);
    }

    JsonDatabase::deleteById('users', $id);
    Response::success(null, 'User deleted successfully');
}

function getBorrowers() {
    $users = JsonDatabase::getAll('users');
    $borrowers = array_map(fn($u) => $u['username'], $users);

    // Also get unique borrowers from transactions
    $transactions = JsonDatabase::getAll('transactions');
    foreach ($transactions as $tx) {
        if (!in_array($tx['borrower_name'], $borrowers)) {
            $borrowers[] = $tx['borrower_name'];
        }
    }

    sort($borrowers);
    Response::success(['borrowers' => array_values(array_unique($borrowers))]);
}
