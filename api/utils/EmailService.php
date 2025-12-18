<?php
/**
 * Email Service for FOXY Inventory System
 * Handles all email notifications including checkout, checkin, and overdue reminders
 */

class EmailService {
    /**
     * Send checkout notification
     */
    public static function sendCheckoutNotification($asset, $borrower, $returnDate, $purpose, $photos = []) {
        $subject = "Equipment Checkout: {$asset['asset_name']}";

        // Get borrower email
        $borrowerEmail = self::getBorrowerEmail($borrower);

        // Email to admin
        self::sendEmail(ADMIN_EMAIL, $subject, self::checkoutEmailTemplate($asset, $borrower, $returnDate, $purpose, $photos, true));

        // Email to equipment manager if different
        if (EQUIPMENT_MANAGER_EMAIL !== ADMIN_EMAIL) {
            self::sendEmail(EQUIPMENT_MANAGER_EMAIL, $subject, self::checkoutEmailTemplate($asset, $borrower, $returnDate, $purpose, $photos, true));
        }

        // Email to borrower
        if ($borrowerEmail) {
            self::sendEmail($borrowerEmail, $subject, self::checkoutEmailTemplate($asset, $borrower, $returnDate, $purpose, $photos, false));
        }

        // Log notification
        self::logNotification('checkout', $asset['asset_id'], $borrower, $borrowerEmail);
    }

    /**
     * Send bulk checkout notification
     */
    public static function sendBulkCheckoutNotification($assets, $borrower, $returnDate, $purpose, $photos = []) {
        $count = count($assets);
        $subject = "Equipment Checkout: {$count} items to {$borrower}";

        $borrowerEmail = self::getBorrowerEmail($borrower);

        // Email to admin
        self::sendEmail(ADMIN_EMAIL, $subject, self::bulkCheckoutEmailTemplate($assets, $borrower, $returnDate, $purpose, $photos, true));

        // Email to equipment manager
        if (EQUIPMENT_MANAGER_EMAIL !== ADMIN_EMAIL) {
            self::sendEmail(EQUIPMENT_MANAGER_EMAIL, $subject, self::bulkCheckoutEmailTemplate($assets, $borrower, $returnDate, $purpose, $photos, true));
        }

        // Email to borrower
        if ($borrowerEmail) {
            self::sendEmail($borrowerEmail, $subject, self::bulkCheckoutEmailTemplate($assets, $borrower, $returnDate, $purpose, $photos, false));
        }

        // Log notifications
        foreach ($assets as $asset) {
            self::logNotification('checkout', $asset['asset_id'], $borrower, $borrowerEmail);
        }
    }

    /**
     * Send checkin notification
     */
    public static function sendCheckinNotification($asset, $borrower, $condition, $notes, $photos = []) {
        $subject = "Equipment Returned: {$asset['asset_name']}";

        $borrowerEmail = self::getBorrowerEmail($borrower);

        // Email to admin
        self::sendEmail(ADMIN_EMAIL, $subject, self::checkinEmailTemplate($asset, $borrower, $condition, $notes, $photos, true));

        // Email to equipment manager
        if (EQUIPMENT_MANAGER_EMAIL !== ADMIN_EMAIL) {
            self::sendEmail(EQUIPMENT_MANAGER_EMAIL, $subject, self::checkinEmailTemplate($asset, $borrower, $condition, $notes, $photos, true));
        }

        // Email to borrower
        if ($borrowerEmail) {
            self::sendEmail($borrowerEmail, $subject, self::checkinEmailTemplate($asset, $borrower, $condition, $notes, $photos, false));
        }

        // Log notification
        self::logNotification('checkin', $asset['asset_id'], $borrower, $borrowerEmail);
    }

    /**
     * Send overdue reminder to borrower (multiple assets)
     */
    public static function sendOverdueReminder($email, $borrower, $assets) {
        $count = count($assets);
        $subject = "OVERDUE: {$count} equipment item(s) past due";

        $result = self::sendEmail($email, $subject, self::overdueReminderTemplate($assets, $borrower));

        // Log notification
        foreach ($assets as $asset) {
            self::logNotification('overdue_reminder', $asset['asset_id'], $borrower, $email);
        }

        return $result;
    }

    /**
     * Send overdue summary to admin
     */
    public static function sendOverdueSummaryToAdmin($email, $overdueAssets) {
        $count = count($overdueAssets);
        $subject = "Daily Overdue Report: {$count} item(s) overdue";

        return self::sendEmail($email, $subject, self::overdueSummaryTemplate($overdueAssets));
    }

    /**
     * Send single overdue reminder (legacy support)
     */
    public static function sendSingleOverdueReminder($asset, $borrower, $daysOverdue) {
        $subject = "OVERDUE: {$asset['asset_name']} - {$daysOverdue} days past due";

        $borrowerEmail = self::getBorrowerEmail($borrower);

        // Email to admin
        self::sendEmail(ADMIN_EMAIL, $subject, self::overdueEmailTemplate($asset, $borrower, $daysOverdue, true));

        // Email to equipment manager
        if (EQUIPMENT_MANAGER_EMAIL !== ADMIN_EMAIL) {
            self::sendEmail(EQUIPMENT_MANAGER_EMAIL, $subject, self::overdueEmailTemplate($asset, $borrower, $daysOverdue, true));
        }

        // Email to borrower
        if ($borrowerEmail) {
            self::sendEmail($borrowerEmail, $subject, self::overdueEmailTemplate($asset, $borrower, $daysOverdue, false));
        }

        // Log notification
        self::logNotification('overdue_reminder', $asset['asset_id'], $borrower, $borrowerEmail);
    }

    /**
     * Get borrower email from users
     */
    private static function getBorrowerEmail($borrower) {
        $users = JsonDatabase::getAll('users');
        foreach ($users as $user) {
            if (strtolower($user['username']) === strtolower($borrower) ||
                strtolower(str_replace('_', '', $user['username'])) === strtolower(str_replace('_', '', $borrower))) {
                return $user['email'] ?? null;
            }
        }
        return null;
    }

    /**
     * Send email
     */
    private static function sendEmail($to, $subject, $body) {
        $headers = [
            'MIME-Version: 1.0',
            'Content-type: text/html; charset=UTF-8',
            'From: ' . MAIL_FROM_NAME . ' <' . MAIL_FROM . '>',
            'Reply-To: ' . ADMIN_EMAIL,
            'X-Mailer: FOXY Inventory System'
        ];

        return mail($to, $subject, $body, implode("\r\n", $headers));
    }

    /**
     * Log notification
     */
    private static function logNotification($type, $assetId, $borrower, $email) {
        JsonDatabase::insert('notification_log', [
            'type' => $type,
            'asset_id' => $assetId,
            'borrower_name' => $borrower,
            'recipient_email' => $email,
            'status' => 'sent',
            'sent_at' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Checkout email template
     */
    private static function checkoutEmailTemplate($asset, $borrower, $returnDate, $purpose, $photos, $isAdmin) {
        $photoSection = '';
        if (!empty($photos)) {
            $photoSection = '<h3 style="color: #FFD700; margin-top: 20px;">Equipment Photos</h3>';
            $photoSection .= '<p style="color: #999;">Photos taken at checkout for QC reference:</p>';
            $photoSection .= '<div style="display: flex; gap: 10px; flex-wrap: wrap;">';
            foreach ($photos as $photo) {
                $photoSection .= '<img src="' . SITE_URL . '/' . $photo . '" style="max-width: 150px; border-radius: 8px;" />';
            }
            $photoSection .= '</div>';
        }

        return self::emailWrapper("
            <h2 style='color: #FFD700; margin: 0;'>Equipment Checked Out</h2>
            <p style='color: #999; margin-top: 5px;'>" . date('F j, Y g:i A') . "</p>

            <div style='background: #1a1a1a; border-radius: 12px; padding: 20px; margin: 20px 0;'>
                <h3 style='color: #fff; margin: 0 0 15px 0;'>{$asset['asset_name']}</h3>
                <table style='width: 100%; color: #ccc;'>
                    <tr>
                        <td style='padding: 8px 0; color: #999;'>Asset ID:</td>
                        <td style='padding: 8px 0; color: #FFD700; font-family: monospace;'>{$asset['asset_id']}</td>
                    </tr>
                    <tr>
                        <td style='padding: 8px 0; color: #999;'>Category:</td>
                        <td style='padding: 8px 0;'>{$asset['category']}</td>
                    </tr>
                    <tr>
                        <td style='padding: 8px 0; color: #999;'>Serial Number:</td>
                        <td style='padding: 8px 0; font-family: monospace;'>" . ($asset['serial_number'] ?? 'N/A') . "</td>
                    </tr>
                    <tr>
                        <td style='padding: 8px 0; color: #999;'>Checked Out To:</td>
                        <td style='padding: 8px 0; font-weight: bold;'>{$borrower}</td>
                    </tr>
                    <tr>
                        <td style='padding: 8px 0; color: #999;'>Expected Return:</td>
                        <td style='padding: 8px 0; color: #4CAF50;'>" . date('F j, Y', strtotime($returnDate)) . "</td>
                    </tr>
                    " . ($purpose ? "<tr>
                        <td style='padding: 8px 0; color: #999;'>Purpose:</td>
                        <td style='padding: 8px 0;'>{$purpose}</td>
                    </tr>" : "") . "
                </table>
            </div>

            {$photoSection}

            <p style='color: #999; font-size: 14px; margin-top: 20px;'>
                " . ($isAdmin ? "This equipment has been checked out from your inventory." : "Please return this equipment by the expected return date. If you need to extend, contact the equipment manager.") . "
            </p>
        ");
    }

    /**
     * Bulk checkout email template
     */
    private static function bulkCheckoutEmailTemplate($assets, $borrower, $returnDate, $purpose, $photos, $isAdmin) {
        $itemsList = '';
        foreach ($assets as $asset) {
            $itemsList .= "
                <tr style='border-bottom: 1px solid #333;'>
                    <td style='padding: 12px 8px; color: #FFD700; font-family: monospace;'>{$asset['asset_id']}</td>
                    <td style='padding: 12px 8px;'>{$asset['asset_name']}</td>
                    <td style='padding: 12px 8px; color: #999;'>{$asset['category']}</td>
                </tr>
            ";
        }

        $photoSection = '';
        if (!empty($photos)) {
            $photoSection = '<h3 style="color: #FFD700; margin-top: 20px;">Equipment Photos</h3>';
            $photoSection .= '<div style="display: flex; gap: 10px; flex-wrap: wrap;">';
            foreach ($photos as $photo) {
                $photoSection .= '<img src="' . SITE_URL . '/' . $photo . '" style="max-width: 150px; border-radius: 8px;" />';
            }
            $photoSection .= '</div>';
        }

        $count = count($assets);

        return self::emailWrapper("
            <h2 style='color: #FFD700; margin: 0;'>Equipment Checked Out</h2>
            <p style='color: #999; margin-top: 5px;'>{$count} items • " . date('F j, Y g:i A') . "</p>

            <div style='background: #1a1a1a; border-radius: 12px; padding: 20px; margin: 20px 0;'>
                <table style='width: 100%; color: #ccc; margin-bottom: 15px;'>
                    <tr>
                        <td style='padding: 8px 0; color: #999;'>Checked Out To:</td>
                        <td style='padding: 8px 0; font-weight: bold;'>{$borrower}</td>
                    </tr>
                    <tr>
                        <td style='padding: 8px 0; color: #999;'>Expected Return:</td>
                        <td style='padding: 8px 0; color: #4CAF50;'>" . date('F j, Y', strtotime($returnDate)) . "</td>
                    </tr>
                    " . ($purpose ? "<tr>
                        <td style='padding: 8px 0; color: #999;'>Purpose:</td>
                        <td style='padding: 8px 0;'>{$purpose}</td>
                    </tr>" : "") . "
                </table>

                <h3 style='color: #fff; margin: 20px 0 10px 0; font-size: 14px; text-transform: uppercase; letter-spacing: 1px;'>Items ({$count})</h3>
                <table style='width: 100%; color: #ccc;'>
                    <thead>
                        <tr style='border-bottom: 2px solid #FFD700;'>
                            <th style='text-align: left; padding: 10px 8px; color: #999; font-size: 12px;'>ID</th>
                            <th style='text-align: left; padding: 10px 8px; color: #999; font-size: 12px;'>EQUIPMENT</th>
                            <th style='text-align: left; padding: 10px 8px; color: #999; font-size: 12px;'>CATEGORY</th>
                        </tr>
                    </thead>
                    <tbody>
                        {$itemsList}
                    </tbody>
                </table>
            </div>

            {$photoSection}

            <p style='color: #999; font-size: 14px; margin-top: 20px;'>
                " . ($isAdmin ? "These items have been checked out from your inventory." : "Please return all items by the expected return date.") . "
            </p>
        ");
    }

    /**
     * Checkin email template
     */
    private static function checkinEmailTemplate($asset, $borrower, $condition, $notes, $photos, $isAdmin) {
        $conditionColor = $condition === 'excellent' ? '#4CAF50' : ($condition === 'good' ? '#2196F3' : '#FF5722');

        $photoSection = '';
        if (!empty($photos)) {
            $photoSection = '<h3 style="color: #FFD700; margin-top: 20px;">Return Condition Photos</h3>';
            $photoSection .= '<p style="color: #999;">Photos taken at check-in for QC reference:</p>';
            $photoSection .= '<div style="display: flex; gap: 10px; flex-wrap: wrap;">';
            foreach ($photos as $photo) {
                $photoSection .= '<img src="' . SITE_URL . '/' . $photo . '" style="max-width: 150px; border-radius: 8px;" />';
            }
            $photoSection .= '</div>';
        }

        $notesSection = $notes ? "
            <div style='background: #2d2d2d; border-radius: 8px; padding: 15px; margin-top: 15px;'>
                <p style='color: #999; margin: 0 0 5px 0; font-size: 12px;'>NOTES</p>
                <p style='color: #fff; margin: 0;'>{$notes}</p>
            </div>
        " : '';

        return self::emailWrapper("
            <h2 style='color: #4CAF50; margin: 0;'>Equipment Returned</h2>
            <p style='color: #999; margin-top: 5px;'>" . date('F j, Y g:i A') . "</p>

            <div style='background: #1a1a1a; border-radius: 12px; padding: 20px; margin: 20px 0;'>
                <h3 style='color: #fff; margin: 0 0 15px 0;'>{$asset['asset_name']}</h3>
                <table style='width: 100%; color: #ccc;'>
                    <tr>
                        <td style='padding: 8px 0; color: #999;'>Asset ID:</td>
                        <td style='padding: 8px 0; color: #FFD700; font-family: monospace;'>{$asset['asset_id']}</td>
                    </tr>
                    <tr>
                        <td style='padding: 8px 0; color: #999;'>Returned By:</td>
                        <td style='padding: 8px 0; font-weight: bold;'>{$borrower}</td>
                    </tr>
                    <tr>
                        <td style='padding: 8px 0; color: #999;'>Condition:</td>
                        <td style='padding: 8px 0;'>
                            <span style='background: {$conditionColor}22; color: {$conditionColor}; padding: 4px 12px; border-radius: 20px; font-size: 13px;'>" . ucfirst($condition) . "</span>
                        </td>
                    </tr>
                </table>
                {$notesSection}
            </div>

            {$photoSection}

            <p style='color: #999; font-size: 14px; margin-top: 20px;'>
                " . ($isAdmin ? "This equipment has been returned to inventory." : "Thank you for returning this equipment.") . "
            </p>
        ");
    }

    /**
     * Overdue email template
     */
    private static function overdueEmailTemplate($asset, $borrower, $daysOverdue, $isAdmin) {
        return self::emailWrapper("
            <h2 style='color: #FF5722; margin: 0;'>⚠️ Equipment Overdue</h2>
            <p style='color: #999; margin-top: 5px;'>{$daysOverdue} days past expected return date</p>

            <div style='background: #1a1a1a; border-radius: 12px; padding: 20px; margin: 20px 0; border-left: 4px solid #FF5722;'>
                <h3 style='color: #fff; margin: 0 0 15px 0;'>{$asset['asset_name']}</h3>
                <table style='width: 100%; color: #ccc;'>
                    <tr>
                        <td style='padding: 8px 0; color: #999;'>Asset ID:</td>
                        <td style='padding: 8px 0; color: #FFD700; font-family: monospace;'>{$asset['asset_id']}</td>
                    </tr>
                    <tr>
                        <td style='padding: 8px 0; color: #999;'>Currently With:</td>
                        <td style='padding: 8px 0; font-weight: bold;'>{$borrower}</td>
                    </tr>
                    <tr>
                        <td style='padding: 8px 0; color: #999;'>Was Due:</td>
                        <td style='padding: 8px 0; color: #FF5722;'>" . date('F j, Y', strtotime($asset['expected_return_date'])) . "</td>
                    </tr>
                    <tr>
                        <td style='padding: 8px 0; color: #999;'>Days Overdue:</td>
                        <td style='padding: 8px 0; color: #FF5722; font-weight: bold;'>{$daysOverdue}</td>
                    </tr>
                </table>
            </div>

            <p style='color: #ccc; font-size: 14px; margin-top: 20px;'>
                " . ($isAdmin ? "This equipment is overdue. Please follow up with {$borrower} to arrange return." : "<strong>Please return this equipment as soon as possible.</strong> If you need to extend the checkout period, please contact the equipment manager immediately.") . "
            </p>

            <div style='margin-top: 20px;'>
                <a href='" . SITE_URL . "/checkin' style='display: inline-block; background: #FFD700; color: #000; padding: 12px 24px; border-radius: 8px; text-decoration: none; font-weight: bold;'>Check In Equipment</a>
            </div>
        ");
    }

    /**
     * Overdue reminder template (for borrower with multiple items)
     */
    private static function overdueReminderTemplate($assets, $borrower) {
        $itemsList = '';
        foreach ($assets as $asset) {
            $daysOverdue = $asset['days_overdue'] ?? 0;
            $dueDate = date('M j, Y', strtotime($asset['expected_return_date']));
            $itemsList .= "
                <tr style='border-bottom: 1px solid #333;'>
                    <td style='padding: 12px 8px; color: #FFD700; font-family: monospace;'>{$asset['asset_id']}</td>
                    <td style='padding: 12px 8px;'>{$asset['asset_name']}</td>
                    <td style='padding: 12px 8px; color: #FF5722;'>{$dueDate}</td>
                    <td style='padding: 12px 8px; color: #FF5722; font-weight: bold;'>{$daysOverdue} days</td>
                </tr>
            ";
        }

        $count = count($assets);

        return self::emailWrapper("
            <h2 style='color: #FF5722; margin: 0;'>⚠️ Overdue Equipment Reminder</h2>
            <p style='color: #999; margin-top: 5px;'>You have {$count} overdue item(s)</p>

            <div style='background: #1a1a1a; border-radius: 12px; padding: 20px; margin: 20px 0; border-left: 4px solid #FF5722;'>
                <p style='color: #ccc; margin: 0 0 15px 0;'>Hi <strong>{$borrower}</strong>, the following equipment is overdue:</p>

                <table style='width: 100%; color: #ccc;'>
                    <thead>
                        <tr style='border-bottom: 2px solid #FF5722;'>
                            <th style='text-align: left; padding: 10px 8px; color: #999; font-size: 12px;'>ID</th>
                            <th style='text-align: left; padding: 10px 8px; color: #999; font-size: 12px;'>EQUIPMENT</th>
                            <th style='text-align: left; padding: 10px 8px; color: #999; font-size: 12px;'>DUE DATE</th>
                            <th style='text-align: left; padding: 10px 8px; color: #999; font-size: 12px;'>OVERDUE</th>
                        </tr>
                    </thead>
                    <tbody>
                        {$itemsList}
                    </tbody>
                </table>
            </div>

            <p style='color: #ccc; font-size: 14px; margin-top: 20px;'>
                <strong>Please return this equipment as soon as possible.</strong><br>
                If you need to extend the checkout period, please contact the equipment manager immediately.
            </p>

            <div style='margin-top: 20px;'>
                <a href='" . SITE_URL . "/checkin' style='display: inline-block; background: #FFD700; color: #000; padding: 12px 24px; border-radius: 8px; text-decoration: none; font-weight: bold;'>Check In Equipment</a>
            </div>
        ");
    }

    /**
     * Overdue summary template (for admin)
     */
    private static function overdueSummaryTemplate($overdueAssets) {
        // Group by borrower
        $byBorrower = [];
        foreach ($overdueAssets as $asset) {
            $borrower = $asset['current_borrower'] ?? 'Unknown';
            if (!isset($byBorrower[$borrower])) {
                $byBorrower[$borrower] = [];
            }
            $byBorrower[$borrower][] = $asset;
        }

        $borrowerSections = '';
        foreach ($byBorrower as $borrower => $assets) {
            $itemsList = '';
            foreach ($assets as $asset) {
                $daysOverdue = $asset['days_overdue'] ?? 0;
                $dueDate = date('M j, Y', strtotime($asset['expected_return_date']));
                $itemsList .= "
                    <tr style='border-bottom: 1px solid #333;'>
                        <td style='padding: 8px; color: #FFD700; font-family: monospace; font-size: 13px;'>{$asset['asset_id']}</td>
                        <td style='padding: 8px; font-size: 13px;'>{$asset['asset_name']}</td>
                        <td style='padding: 8px; color: #FF5722; font-size: 13px;'>{$daysOverdue} days</td>
                    </tr>
                ";
            }

            $count = count($assets);
            $borrowerSections .= "
                <div style='background: #1a1a1a; border-radius: 8px; padding: 15px; margin-bottom: 15px;'>
                    <h4 style='color: #fff; margin: 0 0 10px 0;'>{$borrower} <span style='color: #FF5722; font-size: 14px;'>({$count} item" . ($count > 1 ? 's' : '') . ")</span></h4>
                    <table style='width: 100%; color: #ccc;'>
                        <thead>
                            <tr>
                                <th style='text-align: left; padding: 5px 8px; color: #666; font-size: 11px;'>ID</th>
                                <th style='text-align: left; padding: 5px 8px; color: #666; font-size: 11px;'>EQUIPMENT</th>
                                <th style='text-align: left; padding: 5px 8px; color: #666; font-size: 11px;'>OVERDUE</th>
                            </tr>
                        </thead>
                        <tbody>{$itemsList}</tbody>
                    </table>
                </div>
            ";
        }

        $totalCount = count($overdueAssets);
        $borrowerCount = count($byBorrower);

        return self::emailWrapper("
            <h2 style='color: #FF5722; margin: 0;'>📋 Daily Overdue Report</h2>
            <p style='color: #999; margin-top: 5px;'>" . date('F j, Y') . "</p>

            <div style='background: linear-gradient(135deg, #FF572233 0%, #1a1a1a 100%); border-radius: 12px; padding: 20px; margin: 20px 0;'>
                <div style='display: flex; gap: 30px;'>
                    <div>
                        <div style='font-size: 32px; font-weight: bold; color: #FF5722;'>{$totalCount}</div>
                        <div style='color: #999; font-size: 13px;'>Total Overdue</div>
                    </div>
                    <div>
                        <div style='font-size: 32px; font-weight: bold; color: #FFD700;'>{$borrowerCount}</div>
                        <div style='color: #999; font-size: 13px;'>Team Members</div>
                    </div>
                </div>
            </div>

            <h3 style='color: #fff; margin: 25px 0 15px 0; font-size: 14px; text-transform: uppercase; letter-spacing: 1px;'>By Team Member</h3>

            {$borrowerSections}

            <div style='margin-top: 25px;'>
                <a href='" . SITE_URL . "' style='display: inline-block; background: #FFD700; color: #000; padding: 12px 24px; border-radius: 8px; text-decoration: none; font-weight: bold;'>View Dashboard</a>
            </div>
        ");
    }

    /**
     * Email wrapper template
     */
    private static function emailWrapper($content) {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        </head>
        <body style='margin: 0; padding: 0; background-color: #0d0d0d; font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, sans-serif;'>
            <table width='100%' cellpadding='0' cellspacing='0' style='background-color: #0d0d0d;'>
                <tr>
                    <td align='center' style='padding: 40px 20px;'>
                        <table width='600' cellpadding='0' cellspacing='0' style='background-color: #111; border-radius: 16px; overflow: hidden;'>
                            <!-- Header -->
                            <tr>
                                <td style='background: linear-gradient(135deg, #1a1a1a 0%, #111 100%); padding: 30px; text-align: center; border-bottom: 1px solid #333;'>
                                    <div style='font-size: 28px; font-weight: bold; color: #FFD700;'>FOXY</div>
                                    <div style='color: #666; font-size: 12px; margin-top: 5px;'>" . SITE_NAME . " Inventory</div>
                                </td>
                            </tr>
                            <!-- Content -->
                            <tr>
                                <td style='padding: 30px;'>
                                    {$content}
                                </td>
                            </tr>
                            <!-- Footer -->
                            <tr>
                                <td style='background: #0a0a0a; padding: 20px 30px; border-top: 1px solid #333;'>
                                    <p style='color: #666; font-size: 12px; margin: 0; text-align: center;'>
                                        This is an automated message from FOXY Inventory System.<br>
                                        " . SITE_NAME . " • <a href='" . SITE_URL . "' style='color: #FFD700;'>" . SITE_URL . "</a>
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </body>
        </html>
        ";
    }
}
