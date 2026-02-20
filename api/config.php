<?php
/**
 * Configuration file for FOXY Inventory System
 */

// Base paths
define('API_BASE_PATH', __DIR__);
define('DATA_PATH', API_BASE_PATH . '/data');
define('UPLOADS_PATH', API_BASE_PATH . '/uploads');

// Ensure directories exist
if (!is_dir(DATA_PATH)) mkdir(DATA_PATH, 0755, true);
if (!is_dir(UPLOADS_PATH)) mkdir(UPLOADS_PATH, 0755, true);
if (!is_dir(UPLOADS_PATH . '/assets')) mkdir(UPLOADS_PATH . '/assets', 0755, true);
if (!is_dir(UPLOADS_PATH . '/transactions')) mkdir(UPLOADS_PATH . '/transactions', 0755, true);

// Email Configuration
define('MAIL_FROM', 'noreply@neofoxmedia.com');
define('MAIL_FROM_NAME', 'FOXY Inventory');
define('ADMIN_EMAIL', 'admin@neofoxmedia.com');
define('EQUIPMENT_MANAGER_EMAIL', 'admin@neofoxmedia.com'); // Can be same or different

// Site Configuration
define('SITE_NAME', 'NeoFox Media');
define('SITE_URL', 'https://foxy.neofoxmedia.com');

// JWT Configuration
define('JWT_SECRET', 'your-secret-key-change-this-in-production-' . md5(__FILE__));
define('JWT_EXPIRY', 86400 * 7); // 7 days

// QR Code API
define('QR_API_URL', 'https://quickchart.io/qr');

// Timezone
date_default_timezone_set('Asia/Kolkata');

// File upload limits
define('MAX_PHOTO_SIZE', 10 * 1024 * 1024); // 10MB
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif', 'webp']);

// Overdue settings
define('OVERDUE_REMINDER_DAYS', [1, 3, 7]); // Days after due date to send reminders
