# FOXY Inventory System - Deployment Guide
## NeoFox Media Equipment Management

---

## Table of Contents
1. [Login Credentials](#login-credentials)
2. [Pre-Deployment Checklist](#pre-deployment-checklist)
3. [cPanel Deployment Steps](#cpanel-deployment-steps)
4. [Database Migration](#database-migration)
5. [Email Configuration](#email-configuration)
6. [Cron Jobs Setup](#cron-jobs-setup)
7. [Post-Deployment Verification](#post-deployment-verification)
8. [Troubleshooting](#troubleshooting)

---

## Login Credentials

### Default User Accounts

| Role | Username | Password | Access Level |
|------|----------|----------|--------------|
| Admin | `Admin` | `ZinX1234!@` | Full access - all features |
| Equipment Manager | `eqmanager` | `eqmanager123` | Checkout, checkin, kits, reports |
| Team Member | `rahul` | `neofox123` | Request gear, view calendar |
| Team Member | `priya` | `neofox123` | Request gear, view calendar |
| Team Member | `amit` | `neofox123` | Request gear, view calendar |

**IMPORTANT:** Change all passwords after first login!

### Role Permissions

```
Admin:
├── All Equipment Manager permissions
├── User management (create/edit/delete users)
├── System settings
└── Delete equipment

Equipment Manager:
├── All Team Member permissions
├── Check out / Check in equipment
├── Create and manage kits
├── View audit logs
├── View analytics and reports
├── Approve/reject gear requests
└── Equipment status dashboard

Team Member:
├── View equipment catalog
├── Request gear (pending approval)
├── View calendar
├── View own requests
└── Personal settings
```

---

## Pre-Deployment Checklist

### 1. Export Your Existing Data

If you have existing data in your old system, export it:

```sql
-- Run these in phpMyAdmin on your OLD system
-- Export assets
SELECT * FROM assets INTO OUTFILE '/tmp/assets.csv'
FIELDS TERMINATED BY ',' ENCLOSED BY '"' LINES TERMINATED BY '\n';

-- Export transactions
SELECT * FROM transactions INTO OUTFILE '/tmp/transactions.csv'
FIELDS TERMINATED BY ',' ENCLOSED BY '"' LINES TERMINATED BY '\n';
```

Or use phpMyAdmin's Export feature:
1. Go to phpMyAdmin
2. Select your database
3. Click "Export"
4. Choose "Custom" format
5. Select tables: `assets`, `transactions`, `users`
6. Format: JSON or CSV
7. Click "Go"

### 2. Files to Upload

```
foxy/
├── api/                    # PHP Backend
│   ├── config.php          # ⚠️ UPDATE THIS
│   ├── index.php
│   ├── routes/
│   ├── utils/
│   └── data/               # JSON database files (created automatically)
├── dist/                   # Built React frontend
│   ├── index.html
│   └── assets/
├── .htaccess               # URL rewriting
└── DEPLOYMENT.md           # This file
```

---

## cPanel Deployment Steps

### Step 1: Access File Manager

1. Log in to Bluehost cPanel: `https://your-domain.com/cpanel`
2. Go to **File Manager**
3. Navigate to `public_html` (or your subdomain folder)

### Step 2: Upload Files

**Option A: Upload ZIP (Recommended)**

1. On your local machine, create a ZIP of the `foxy` folder
2. In cPanel File Manager, click **Upload**
3. Upload the ZIP file
4. Right-click the ZIP → **Extract**
5. Move contents to your desired location

**Option B: FTP Upload**

1. Use FileZilla or similar FTP client
2. Connect with your cPanel FTP credentials
3. Upload all files to `public_html/foxy` (or root)

### Step 3: Configure API Settings

Edit `api/config.php`:

```php
<?php
// ===========================================
// FOXY Configuration - UPDATE THESE VALUES
// ===========================================

// Site URL (no trailing slash)
define('SITE_URL', 'https://inventory.neofoxmedia.com');

// JWT Secret Key - CHANGE THIS TO A RANDOM STRING!
define('JWT_SECRET', 'your-super-secret-key-change-me-now-' . bin2hex(random_bytes(16)));

// Email Configuration
define('SMTP_HOST', 'mail.neofoxmedia.com');  // Or smtp.gmail.com
define('SMTP_PORT', 587);
define('SMTP_USER', 'inventory@neofoxmedia.com');
define('SMTP_PASS', 'your-email-password');
define('SMTP_FROM', 'inventory@neofoxmedia.com');
define('SMTP_FROM_NAME', 'NeoFox Equipment');

// Admin email for notifications
define('ADMIN_EMAIL', 'admin@neofoxmedia.com');

// File uploads
define('UPLOADS_PATH', __DIR__ . '/uploads');
define('MAX_PHOTO_SIZE', 10 * 1024 * 1024); // 10MB
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif', 'webp']);

// QR Code API
define('QR_API_URL', 'https://api.qrserver.com/v1/create-qr-code/');

// Data path
define('DATA_PATH', __DIR__ . '/data');
```

### Step 4: Set File Permissions

In cPanel File Manager or via SSH:

```bash
# Make data directory writable
chmod 755 api/data
chmod 644 api/data/*.json

# Make uploads directory writable
chmod 755 api/uploads
chmod 755 api/uploads/assets
chmod 755 api/uploads/transactions

# Protect sensitive files
chmod 600 api/config.php
```

### Step 5: Configure .htaccess

Create/update `.htaccess` in your root folder:

```apache
# Enable rewrite engine
RewriteEngine On

# Handle React Router (SPA)
RewriteBase /
RewriteRule ^index\.html$ - [L]
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteCond %{REQUEST_URI} !^/api
RewriteRule . /index.html [L]

# Route API requests
RewriteRule ^api/(.*)$ api/index.php [QSA,L]

# Security headers
<IfModule mod_headers.c>
    Header set X-Content-Type-Options "nosniff"
    Header set X-Frame-Options "SAMEORIGIN"
    Header set X-XSS-Protection "1; mode=block"
</IfModule>

# Protect sensitive files
<FilesMatch "\.(json|php\.bak|env)$">
    Order Allow,Deny
    Deny from all
</FilesMatch>

# Allow JSON for API responses only
<FilesMatch "^api/">
    <FilesMatch "\.json$">
        Order Allow,Deny
        Allow from all
    </FilesMatch>
</FilesMatch>

# Enable CORS for API
<IfModule mod_headers.c>
    <FilesMatch "api/">
        Header set Access-Control-Allow-Origin "*"
        Header set Access-Control-Allow-Methods "GET, POST, PUT, DELETE, OPTIONS"
        Header set Access-Control-Allow-Headers "Content-Type, Authorization"
    </FilesMatch>
</IfModule>
```

---

## Database Migration

### Step 1: Create Data Directory Structure

The system will auto-create these, but you can manually create:

```
api/data/
├── users.json
├── assets.json
├── transactions.json
├── kits.json
├── reservations.json
├── audit_log.json
├── asset_photos.json
├── equipment_photos.json
├── maintenance_issues.json
└── maintenance_schedule.json
```

### Step 2: Initialize Users

Create `api/data/users.json`:

```json
[
  {
    "user_id": "USR-001",
    "username": "Admin",
    "email": "admin@neofoxmedia.com",
    "password": "$2y$10$YourHashedPasswordHere",
    "role": "admin",
    "phone": "+91 98765 43210",
    "created_at": "2024-01-01 00:00:00"
  },
  {
    "user_id": "USR-002",
    "username": "eqmanager",
    "email": "equipment@neofoxmedia.com",
    "password": "$2y$10$YourHashedPasswordHere",
    "role": "equipment_manager",
    "phone": "+91 98765 43211",
    "created_at": "2024-01-01 00:00:00"
  }
]
```

**Or run the setup script:**

Access `https://your-domain.com/api/setup-users.php` once to create default users.

### Step 3: Migrate Existing Equipment Data

If you have existing equipment data, create a migration script:

**Option A: Upload JSON directly**

Convert your existing data to this format in `api/data/assets.json`:

```json
[
  {
    "asset_id": "CAM001",
    "asset_name": "Sony A7 III",
    "category": "Camera",
    "description": "Full-frame mirrorless camera",
    "serial_number": "SN123456789",
    "qr_code": "https://api.qrserver.com/v1/create-qr-code/?text=CAM001&size=350",
    "status": "available",
    "current_borrower": null,
    "checkout_date": null,
    "expected_return_date": null,
    "condition_status": "excellent",
    "storage_location": "Room A",
    "shelf": "Shelf 1",
    "notes": "",
    "total_checkouts": 45,
    "created_at": "2023-01-15 10:00:00",
    "updated_at": "2024-12-01 14:30:00"
  }
]
```

**Option B: Create migration script**

Create `api/migrate.php`:

```php
<?php
/**
 * Migration Script - Run ONCE to import existing data
 * Access: https://your-domain.com/api/migrate.php
 * DELETE THIS FILE AFTER MIGRATION!
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/utils/JsonDatabase.php';

// Your exported SQL data (paste here or load from file)
$oldAssets = [
    // Paste your exported data here as PHP array
    // Or load from CSV/JSON file
];

$imported = 0;
$errors = [];

foreach ($oldAssets as $old) {
    try {
        $asset = [
            'asset_id' => $old['asset_id'] ?? ('IMP-' . str_pad($imported + 1, 3, '0', STR_PAD_LEFT)),
            'asset_name' => $old['asset_name'] ?? $old['name'],
            'category' => $old['category'] ?? 'Other',
            'description' => $old['description'] ?? '',
            'serial_number' => $old['serial_number'] ?? '',
            'qr_code' => 'https://api.qrserver.com/v1/create-qr-code/?text=' . urlencode($old['asset_id']) . '&size=350',
            'status' => mapStatus($old['status'] ?? 'available'),
            'current_borrower' => $old['current_borrower'] ?? null,
            'checkout_date' => $old['checkout_date'] ?? null,
            'expected_return_date' => $old['expected_return_date'] ?? null,
            'condition_status' => $old['condition'] ?? 'excellent',
            'storage_location' => $old['location'] ?? '',
            'shelf' => $old['shelf'] ?? '',
            'notes' => $old['notes'] ?? '',
            'total_checkouts' => intval($old['total_checkouts'] ?? 0),
            'created_at' => $old['created_at'] ?? date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];

        JsonDatabase::insert('assets', $asset);
        $imported++;
    } catch (Exception $e) {
        $errors[] = "Failed to import {$old['asset_id']}: " . $e->getMessage();
    }
}

function mapStatus($oldStatus) {
    $map = [
        'available' => 'available',
        'in' => 'available',
        'out' => 'checked_out',
        'checked_out' => 'checked_out',
        'repair' => 'maintenance',
        'maintenance' => 'maintenance'
    ];
    return $map[strtolower($oldStatus)] ?? 'available';
}

echo "<h1>Migration Complete</h1>";
echo "<p>Imported: {$imported} assets</p>";
if (!empty($errors)) {
    echo "<h2>Errors:</h2><ul>";
    foreach ($errors as $err) echo "<li>{$err}</li>";
    echo "</ul>";
}
echo "<p style='color:red;font-weight:bold;'>DELETE THIS FILE NOW!</p>";
```

### Step 4: Migrate Transactions (Optional)

For `api/data/transactions.json`:

```json
[
  {
    "transaction_id": "TXN-ABC12345",
    "asset_id": "CAM001",
    "asset_name": "Sony A7 III",
    "borrower_name": "Rahul Kumar",
    "transaction_type": "checkout",
    "project": "Wedding - Sharma",
    "purpose": "Main camera",
    "notes": "",
    "transaction_date": "2024-12-15 09:00:00"
  },
  {
    "transaction_id": "TXN-ABC12346",
    "asset_id": "CAM001",
    "asset_name": "Sony A7 III",
    "borrower_name": "Rahul Kumar",
    "transaction_type": "checkin",
    "condition_on_return": "excellent",
    "notes": "Cleaned and ready",
    "transaction_date": "2024-12-17 18:00:00"
  }
]
```

---

## Email Configuration

### Option 1: Bluehost Email (Recommended)

1. In cPanel, go to **Email Accounts**
2. Create: `inventory@neofoxmedia.com`
3. Update `api/config.php`:

```php
define('SMTP_HOST', 'mail.neofoxmedia.com');
define('SMTP_PORT', 465);  // or 587
define('SMTP_USER', 'inventory@neofoxmedia.com');
define('SMTP_PASS', 'your-email-password');
define('SMTP_SECURE', 'ssl');  // or 'tls'
```

### Option 2: Gmail SMTP

1. Enable 2FA on your Gmail account
2. Generate App Password: Google Account → Security → App Passwords
3. Update config:

```php
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'your-email@gmail.com');
define('SMTP_PASS', 'your-16-char-app-password');
define('SMTP_SECURE', 'tls');
```

### Option 3: SendGrid/Mailgun (High Volume)

For sending many emails, consider a transactional email service.

---

## Cron Jobs Setup

### Access Cron Jobs in cPanel

1. Log in to cPanel
2. Go to **Cron Jobs** (under Advanced)
3. Add the following jobs:

### Job 1: Overdue Equipment Reminders

**Schedule:** Daily at 9:00 AM
**Command:**
```
/usr/local/bin/php /home/username/public_html/api/cron/overdue-reminders.php
```

**Create the file** `api/cron/overdue-reminders.php`:

```php
<?php
/**
 * Cron Job: Send overdue equipment reminders
 * Schedule: Daily at 9:00 AM
 */

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/utils/JsonDatabase.php';
require_once dirname(__DIR__) . '/utils/EmailService.php';

$assets = JsonDatabase::getAll('assets');
$overdueAssets = [];

foreach ($assets as $asset) {
    if ($asset['status'] === 'checked_out' &&
        !empty($asset['expected_return_date']) &&
        strtotime($asset['expected_return_date']) < strtotime('today')) {
        $overdueAssets[] = $asset;
    }
}

if (empty($overdueAssets)) {
    echo "No overdue equipment.\n";
    exit(0);
}

// Group by borrower
$byBorrower = [];
foreach ($overdueAssets as $asset) {
    $borrower = $asset['current_borrower'];
    if (!isset($byBorrower[$borrower])) {
        $byBorrower[$borrower] = [];
    }
    $byBorrower[$borrower][] = $asset;
}

// Send reminders
foreach ($byBorrower as $borrower => $assets) {
    // Get borrower email from users
    $users = JsonDatabase::getAll('users');
    $email = null;
    foreach ($users as $user) {
        if (strcasecmp($user['username'], $borrower) === 0) {
            $email = $user['email'];
            break;
        }
    }

    if ($email) {
        EmailService::sendOverdueReminder($email, $borrower, $assets);
        echo "Sent reminder to {$borrower} ({$email}) for " . count($assets) . " items.\n";
    }
}

// Notify admin
$adminSummary = "Overdue Equipment Summary:\n\n";
foreach ($overdueAssets as $asset) {
    $days = floor((time() - strtotime($asset['expected_return_date'])) / 86400);
    $adminSummary .= "- {$asset['asset_name']} ({$asset['asset_id']}): {$asset['current_borrower']} - {$days} days overdue\n";
}

mail(ADMIN_EMAIL, 'FOXY: Daily Overdue Report', $adminSummary);
echo "Admin notified.\n";
```

### Job 2: Maintenance Reminders

**Schedule:** Weekly on Monday at 8:00 AM
**Command:**
```
/usr/local/bin/php /home/username/public_html/api/cron/maintenance-check.php
```

**Create the file** `api/cron/maintenance-check.php`:

```php
<?php
/**
 * Cron Job: Check for equipment needing maintenance
 * Schedule: Weekly on Monday at 8:00 AM
 */

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/utils/JsonDatabase.php';

$assets = JsonDatabase::getAll('assets');
$needsMaintenance = [];

foreach ($assets as $asset) {
    // Check if maintenance interval exceeded
    $interval = $asset['maintenance_interval_days'] ?? 90;
    $lastMaintenance = $asset['last_maintenance_date'] ?? $asset['created_at'];

    if (strtotime($lastMaintenance) < strtotime("-{$interval} days")) {
        $needsMaintenance[] = $asset;
    }
}

if (empty($needsMaintenance)) {
    echo "No equipment needs maintenance.\n";
    exit(0);
}

$message = "The following equipment is due for maintenance:\n\n";
foreach ($needsMaintenance as $asset) {
    $lastDate = $asset['last_maintenance_date'] ?? 'Never';
    $message .= "- {$asset['asset_name']} ({$asset['asset_id']})\n";
    $message .= "  Last maintenance: {$lastDate}\n";
    $message .= "  Total checkouts: {$asset['total_checkouts']}\n\n";
}

mail(ADMIN_EMAIL, 'FOXY: Weekly Maintenance Report', $message);
echo "Maintenance report sent.\n";
```

### Job 3: Daily Backup

**Schedule:** Daily at 2:00 AM
**Command:**
```
/usr/local/bin/php /home/username/public_html/api/cron/backup.php
```

**Create the file** `api/cron/backup.php`:

```php
<?php
/**
 * Cron Job: Backup JSON database files
 * Schedule: Daily at 2:00 AM
 */

require_once dirname(__DIR__) . '/config.php';

$dataDir = DATA_PATH;
$backupDir = dirname(__DIR__) . '/backups';
$date = date('Y-m-d_H-i-s');

// Create backup directory
if (!is_dir($backupDir)) {
    mkdir($backupDir, 0755, true);
}

// Create dated backup folder
$backupPath = $backupDir . '/backup_' . $date;
mkdir($backupPath, 0755, true);

// Copy all JSON files
$files = glob($dataDir . '/*.json');
foreach ($files as $file) {
    $filename = basename($file);
    copy($file, $backupPath . '/' . $filename);
    echo "Backed up: {$filename}\n";
}

// Create ZIP archive
$zipFile = $backupDir . '/backup_' . $date . '.zip';
$zip = new ZipArchive();
if ($zip->open($zipFile, ZipArchive::CREATE) === TRUE) {
    foreach (glob($backupPath . '/*') as $file) {
        $zip->addFile($file, basename($file));
    }
    $zip->close();
    echo "Created: {$zipFile}\n";

    // Remove folder, keep only ZIP
    array_map('unlink', glob($backupPath . '/*'));
    rmdir($backupPath);
}

// Delete backups older than 30 days
$oldBackups = glob($backupDir . '/backup_*.zip');
foreach ($oldBackups as $backup) {
    if (filemtime($backup) < strtotime('-30 days')) {
        unlink($backup);
        echo "Deleted old backup: " . basename($backup) . "\n";
    }
}

echo "Backup complete.\n";
```

### Cron Job Summary Table

| Job | Schedule | cPanel Setting |
|-----|----------|----------------|
| Overdue Reminders | Daily 9 AM | `0 9 * * *` |
| Maintenance Check | Monday 8 AM | `0 8 * * 1` |
| Daily Backup | Daily 2 AM | `0 2 * * *` |

### Setting Up in cPanel

1. Go to **Cron Jobs**
2. Under "Add New Cron Job":
   - **Common Settings:** Choose schedule or enter custom
   - **Command:** Enter the full path to PHP script
3. Click **Add New Cron Job**

Example for Bluehost:
```
Minute: 0
Hour: 9
Day: *
Month: *
Weekday: *
Command: /usr/local/bin/php /home/neofoxme/public_html/inventory/api/cron/overdue-reminders.php
```

---

## Post-Deployment Verification

### Step 1: Test Login

1. Go to `https://your-domain.com/login`
2. Login with Admin credentials
3. Verify dashboard loads

### Step 2: Test Core Features

- [ ] Add new equipment
- [ ] Check out equipment
- [ ] Check in equipment
- [ ] Upload photo during checkout
- [ ] Create a kit
- [ ] Make a reservation
- [ ] View audit log
- [ ] View analytics

### Step 3: Test Email

1. Go to Settings
2. Send a test email
3. Verify it arrives

### Step 4: Test Cron Jobs

Run manually first:
```bash
php /path/to/api/cron/overdue-reminders.php
php /path/to/api/cron/backup.php
```

### Step 5: Security Check

- [ ] Change all default passwords
- [ ] Verify `.htaccess` blocks direct JSON access
- [ ] Test API authentication works
- [ ] Remove `setup-users.php` and `migrate.php`

---

## Troubleshooting

### Common Issues

**1. Blank page / 500 Error**
```bash
# Check PHP error log
tail -f /home/username/logs/error.log

# Enable error display temporarily
# Add to api/index.php:
ini_set('display_errors', 1);
error_reporting(E_ALL);
```

**2. API returns 404**
- Check `.htaccess` exists and has correct rules
- Verify `mod_rewrite` is enabled
- Check file paths in config.php

**3. Permission denied errors**
```bash
chmod 755 api/data
chmod 755 api/uploads
chmod 644 api/data/*.json
```

**4. Email not sending**
- Verify SMTP credentials
- Check if port 587/465 is open
- Try using Bluehost's mail server first
- Check spam folder

**5. Login not working**
- Clear browser cache
- Check JWT_SECRET is set
- Verify users.json has correct password hashes

**6. Photos not uploading**
```bash
chmod 755 api/uploads/assets
chmod 755 api/uploads/transactions
# Check PHP upload_max_filesize
```

### Getting Help

1. Check browser console (F12) for JavaScript errors
2. Check Network tab for API errors
3. Review `api/data/audit_log.json` for recent actions
4. Check PHP error logs in cPanel

---

## Quick Reference

### URLs
- **App:** `https://your-domain.com/`
- **Login:** `https://your-domain.com/login`
- **API:** `https://your-domain.com/api/`

### Important Files
- Config: `api/config.php`
- Users: `api/data/users.json`
- Assets: `api/data/assets.json`
- Logs: `api/data/audit_log.json`

### Default Passwords (CHANGE THESE!)
- Admin: `ZinX1234!@`
- Equipment Manager: `eqmanager123`
- Team Members: `neofox123`

---

*Last Updated: December 2024*
*FOXY Inventory System v2.0*
