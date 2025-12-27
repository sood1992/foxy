<?php
/*
==============================================
cron/overdue_alerts.php - Daily Cron Job
==============================================
Place this file in a cron/ subdirectory
Set up cron job: 0 9 * * * /usr/bin/php /path/to/your/site/cron/overdue_alerts.php
*/

require_once '../config/database.php';
require_once '../classes/Asset.php';
require_once '../classes/EmailNotification.php';

$database = new Database();
$db = $database->getConnection();
$asset = new Asset($db);

$overdue_assets = $asset->getOverdueAssets();

foreach ($overdue_assets as $item) {
    $days_overdue = ceil((time() - strtotime($item['expected_return_date'])) / (60 * 60 * 24));
    
    // Send alert to admin
    EmailNotification::sendOverdueAlert(
        'admin@neofox.com',
        $item['asset_name'],
        $item['current_borrower'],
        $days_overdue
    );
    
    // Log the alert
    error_log("Overdue alert sent for asset: " . $item['asset_name'] . " (Borrower: " . $item['current_borrower'] . ")");
}

echo "Processed " . count($overdue_assets) . " overdue alerts.\n";

/*
==============================================
.htaccess - Security and URL Rewriting
==============================================
Place this file in your root directory
*/

/*
RewriteEngine On

# Force HTTPS (uncomment if using SSL)
# RewriteCond %{HTTPS} off
# RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

# Clean URLs for assets
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^asset/([a-zA-Z0-9]+)$ checkout.php?asset_id=$1 [L]

# Security Headers
<IfModule mod_headers.c>
    Header always set X-Content-Type-Options nosniff
    Header always set X-Frame-Options DENY
    Header always set X-XSS-Protection "1; mode=block"
</IfModule>

# Protect sensitive files
<Files "config/database.php">
    Order allow,deny
    Deny from all
</Files>

<Files "cron/*">
    Order allow,deny
    Deny from all
</Files>

# Protect class files from direct access
<Files "classes/*.php">
    Order allow,deny
    Deny from all
</Files>
*/
?>