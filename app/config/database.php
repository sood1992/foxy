<?php
// config/database.php
class Database {
    private $host = 'localhost';
    private $db_name = 'sunburni_foxy';
    private $username = 'sunburni_foxy';
    private $password = 'sunburni_foxy';
    private $conn;

    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->db_name, $this->username, $this->password);
            $this->conn->exec("set names utf8");
        } catch(PDOException $exception) {
            echo "Connection error: " . $exception->getMessage();
        }
        return $this->conn;
    }
}

/*
==============================================
SQL SCHEMA - RUN THIS FIRST IN YOUR DATABASE
==============================================

CREATE DATABASE neofox_gear;
USE neofox_gear;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100),
    role ENUM('admin', 'team_member', 'guest') DEFAULT 'team_member',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE assets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    asset_id VARCHAR(50) UNIQUE NOT NULL,
    asset_name VARCHAR(100) NOT NULL,
    category VARCHAR(50) NOT NULL,
    description TEXT,
    serial_number VARCHAR(100),
    qr_code VARCHAR(255),
    status ENUM('available', 'checked_out', 'maintenance', 'lost') DEFAULT 'available',
    current_borrower VARCHAR(100),
    checkout_date DATETIME,
    expected_return_date DATETIME,
    condition_status ENUM('excellent', 'good', 'needs_repair') DEFAULT 'excellent',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    asset_id VARCHAR(50) NOT NULL,
    borrower_name VARCHAR(100) NOT NULL,
    transaction_type ENUM('checkout', 'checkin') NOT NULL,
    purpose TEXT,
    condition_on_return ENUM('excellent', 'good', 'needs_repair'),
    notes TEXT,
    transaction_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (asset_id) REFERENCES assets(asset_id) ON DELETE CASCADE
);

CREATE TABLE gear_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    requester_name VARCHAR(100) NOT NULL,
    requester_email VARCHAR(100),
    required_items TEXT NOT NULL,
    request_dates VARCHAR(100) NOT NULL,
    purpose TEXT,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    admin_notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert default admin user (password is 'password' - CHANGE THIS!)
INSERT INTO users (username, password, email, role) VALUES 
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'rishabh@neofoxmedia.com', 'admin');

*/
?>