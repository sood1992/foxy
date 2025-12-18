<?php
/**
 * User Setup Script
 * Run this once to create default user accounts with proper password hashes
 *
 * Usage: php api/setup-users.php
 */

echo "FOXY User Setup\n";
echo "===============\n\n";

define('DATA_PATH', __DIR__ . '/data');

// Ensure data directory exists
if (!is_dir(DATA_PATH)) {
    mkdir(DATA_PATH, 0755, true);
}

// Default users to create
$defaultUsers = [
    [
        'id' => 1,
        'username' => 'Admin',
        'password' => 'ZinX1234!@',
        'name' => 'Administrator',
        'email' => 'admin@neofoxmedia.com',
        'phone' => '',
        'role' => 'admin',
        'status' => 'active'
    ],
    [
        'id' => 2,
        'username' => 'eqmanager',
        'password' => 'eqmanager123',
        'name' => 'Equipment Manager',
        'email' => 'equipment@neofoxmedia.com',
        'phone' => '',
        'role' => 'equipment_manager',
        'status' => 'active'
    ],
    [
        'id' => 3,
        'username' => 'Rahul_Singh',
        'password' => 'neofox123',
        'name' => 'Rahul Singh',
        'email' => 'rahul@neofoxmedia.com',
        'phone' => '+919876543210',
        'role' => 'team_member',
        'status' => 'active'
    ],
    [
        'id' => 4,
        'username' => 'Priya_Sharma',
        'password' => 'neofox123',
        'name' => 'Priya Sharma',
        'email' => 'priya@neofoxmedia.com',
        'phone' => '+919876543211',
        'role' => 'team_member',
        'status' => 'active'
    ],
    [
        'id' => 5,
        'username' => 'Amit_Kumar',
        'password' => 'neofox123',
        'name' => 'Amit Kumar',
        'email' => 'amit@neofoxmedia.com',
        'phone' => '+919876543212',
        'role' => 'team_member',
        'status' => 'active'
    ],
    [
        'id' => 6,
        'username' => 'Sneha_Patel',
        'password' => 'neofox123',
        'name' => 'Sneha Patel',
        'email' => 'sneha@neofoxmedia.com',
        'phone' => '+919876543213',
        'role' => 'team_member',
        'status' => 'active'
    ],
    [
        'id' => 7,
        'username' => 'Vikram_Mehta',
        'password' => 'neofox123',
        'name' => 'Vikram Mehta',
        'email' => 'vikram@neofoxmedia.com',
        'phone' => '+919876543214',
        'role' => 'team_member',
        'status' => 'active'
    ],
    [
        'id' => 8,
        'username' => 'Ananya_Reddy',
        'password' => 'neofox123',
        'name' => 'Ananya Reddy',
        'email' => 'ananya@neofoxmedia.com',
        'phone' => '+919876543215',
        'role' => 'team_member',
        'status' => 'active'
    ],
    [
        'id' => 9,
        'username' => 'Karan_Joshi',
        'password' => 'neofox123',
        'name' => 'Karan Joshi',
        'email' => 'karan@neofoxmedia.com',
        'phone' => '+919876543216',
        'role' => 'team_member',
        'status' => 'active'
    ],
    [
        'id' => 10,
        'username' => 'Neha_Gupta',
        'password' => 'neofox123',
        'name' => 'Neha Gupta',
        'email' => 'neha@neofoxmedia.com',
        'phone' => '+919876543217',
        'role' => 'team_member',
        'status' => 'active'
    ]
];

// Hash passwords and prepare users
$users = [];
foreach ($defaultUsers as $user) {
    $plainPassword = $user['password'];
    $user['password'] = password_hash($plainPassword, PASSWORD_BCRYPT);
    $user['created_at'] = date('Y-m-d H:i:s');
    $users[] = $user;

    echo "Created user: {$user['username']} ({$user['role']})\n";
    echo "  Password: {$plainPassword}\n";
}

// Save to JSON
$filePath = DATA_PATH . '/users.json';
file_put_contents($filePath, json_encode($users, JSON_PRETTY_PRINT));

echo "\n===============\n";
echo "Setup complete!\n";
echo "Created " . count($users) . " users\n";
echo "Data saved to: {$filePath}\n";
echo "\nLogin Credentials:\n";
echo "==================\n";
echo "Admin:            Admin / ZinX1234!@\n";
echo "Equipment Mgr:    eqmanager / eqmanager123\n";
echo "Team Members:     [username] / neofox123\n";
