<?php
// crew_checkout.php - Bulk crew equipment checkout system
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Fix session path issue
if (!session_id()) {
    ini_set('session.save_path', sys_get_temp_dir());
    session_start();
}

require_once 'config/database.php';
require_once 'classes/Asset.php';
require_once 'classes/EmailNotification.php';
require_once 'classes/CheckoutPDFGenerator.php';
require_once 'classes/WhatsAppSender.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();
$asset = new Asset($db);

// Get all users for dropdown
$users_query = "SELECT username, email FROM users WHERE role IN ('admin', 'team_member') ORDER BY username";
$users_stmt = $db->prepare($users_query);
$users_stmt->execute();
$users = $users_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all available equipment
$available_assets = $asset->search('', null, 'available');

// Get categories for filtering
$categories_query = "SELECT DISTINCT category FROM assets ORDER BY category";
$categories_stmt = $db->prepare($categories_query);
$categories_stmt->execute();
$categories = $categories_stmt->fetchAll(PDO::FETCH_COLUMN);

// Handle WhatsApp AJAX request
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'send_whatsapp') {
    header('Content-Type: application/json');

    try {
        $phone = $_POST['phone'] ?? '';
        $crew_member = $_POST['crew_member'] ?? '';
        $pdf_url = $_POST['pdf_url'] ?? '';
        $equipment_count = $_POST['equipment_count'] ?? 0;
        $project_name = $_POST['project_name'] ?? '';

        if (empty($phone)) {
            echo json_encode(['success' => false, 'error' => 'Phone number required']);
            exit;
        }

        $result = WhatsAppSender::sendEquipmentCheckoutPDF(
            $phone,
            $pdf_url,
            $crew_member,
            (int)$equipment_count
        );

        echo json_encode($result);
        exit;

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && !isset($_POST['action'])) {
    try {
        $db->beginTransaction();
        
        $project_name = trim($_POST['project_name'] ?? '');
        $return_date = $_POST['return_date'] ?? '';
        $crew_assignments = $_POST['crew_assignments'] ?? [];
        
        if (empty($project_name)) {
            throw new Exception('Please enter a project name');
        }
        if (empty($return_date)) {
            throw new Exception('Please select a return date');
        }
        if (strtotime($return_date) <= time()) {
            throw new Exception('Return date must be in the future');
        }
        if (empty($crew_assignments)) {
            throw new Exception('Please add at least one crew member');
        }
        
        $results = [];
        $total_assigned = 0;
        $failed_assignments = 0;

        // Process each crew member's assignments
        foreach ($crew_assignments as $crew_key => $equipment_data) {
            // Get the actual crew member name from the form data
            $crew_member = trim($equipment_data['member'] ?? '');

            if (empty($equipment_data['equipment']) || empty($crew_member)) {
                continue;
            }

            $purpose = trim($equipment_data['purpose'] ?? $project_name);
            $crew_results = [];
            
            foreach ($equipment_data['equipment'] as $asset_id) {
                if (empty($asset_id)) continue;
                
                try {
                    // Check out equipment to this crew member
                    $checkout_result = $asset->checkOut($asset_id, $crew_member, $return_date, $purpose, false);
                    
                    if ($checkout_result) {
                        $asset_info = $asset->getByAssetId($asset_id);
                        $crew_results[] = [
                            'asset_id' => $asset_id,
                            'asset_name' => $asset_info['asset_name'],
                            'success' => true,
                            'message' => 'Successfully checked out'
                        ];
                        $total_assigned++;
                    } else {
                        $crew_results[] = [
                            'asset_id' => $asset_id,
                            'success' => false,
                            'message' => 'Checkout failed'
                        ];
                        $failed_assignments++;
                    }
                } catch (Exception $e) {
                    $crew_results[] = [
                        'asset_id' => $asset_id,
                        'success' => false,
                        'message' => $e->getMessage()
                    ];
                    $failed_assignments++;
                }
            }
            
            $results[$crew_member] = [
                'equipment' => $crew_results,
                'purpose' => $purpose,
                'email' => getUserEmail($crew_member, $db)
            ];
        }
        
        if ($total_assigned > 0) {
            $db->commit();

            // Initialize PDF generator
            $pdfGenerator = new CheckoutPDFGenerator($db);
            $pdf_results = [];

            // Send emails, generate PDFs, and prepare printouts
            foreach ($results as $crew_member => $crew_data) {
                if (!empty($crew_data['equipment'])) {
                    $successful_equipment = array_filter($crew_data['equipment'], function($item) {
                        return $item['success'];
                    });

                    if (!empty($successful_equipment)) {
                        // Generate PDF receipt for this crew member
                        $equipment_for_pdf = [];
                        foreach ($successful_equipment as $item) {
                            $asset_data = $asset->getByAssetId($item['asset_id']);
                            if ($asset_data) {
                                $equipment_for_pdf[] = [
                                    'asset_id' => $item['asset_id'],
                                    'asset_name' => $item['asset_name'],
                                    'category' => $asset_data['category'] ?? 'N/A',
                                    'condition_status' => $asset_data['condition_status'] ?? 'Good',
                                    'serial_number' => $asset_data['serial_number'] ?? 'N/A'
                                ];
                            }
                        }

                        // Generate PDF
                        $pdf_result = $pdfGenerator->generateCheckoutPDF(
                            $crew_member,
                            $equipment_for_pdf,
                            $return_date,
                            $crew_data['purpose'] ?? $project_name
                        );

                        if ($pdf_result['success']) {
                            $pdf_results[$crew_member] = $pdf_result;
                            $results[$crew_member]['pdf'] = $pdf_result;
                        }

                        // Send email with PDF attachment info
                        if ($crew_data['email']) {
                            sendCrewCheckoutEmail($crew_member, $crew_data['email'], $successful_equipment, $project_name, $return_date, $crew_data['purpose']);
                        }
                    }
                }
            }

            $success_message = "Successfully processed crew checkout for {$project_name}. {$total_assigned} items assigned";
            if ($failed_assignments > 0) {
                $success_message .= ", {$failed_assignments} failed";
            }
            if (!empty($pdf_results)) {
                $success_message .= ". PDF receipts generated for " . count($pdf_results) . " crew member(s).";
            }

            // Store results for display and printing
            $_SESSION['crew_checkout_results'] = [
                'project_name' => $project_name,
                'return_date' => $return_date,
                'results' => $results,
                'success_message' => $success_message,
                'pdf_results' => $pdf_results
            ];

        } else {
            $db->rollBack();
            $error_message = "No equipment was successfully assigned.";
        }
        
    } catch (Exception $e) {
        $db->rollBack();
        $error_message = "Error processing crew checkout: " . $e->getMessage();
    }
}

function getUserEmail($username, $db) {
    $query = "SELECT email FROM users WHERE username = :username";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':username', $username);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    return $user ? $user['email'] : null;
}

function sendCrewCheckoutEmail($crew_member, $email, $equipment, $project_name, $return_date, $purpose) {
    try {
        $subject = "🎬 Equipment Assignment - {$project_name}";
        
        $equipment_list = '';
        foreach ($equipment as $item) {
            if ($item['success']) {
                $equipment_list .= "• {$item['asset_name']} ({$item['asset_id']})\n";
            }
        }
        
        $message = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background-color: #f5f5f5; }
                .container { max-width: 600px; margin: 0 auto; background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
                .header { background: #FFD700; color: #000; padding: 30px 20px; text-align: center; }
                .header h1 { margin: 0; font-size: 24px; }
                .content { padding: 30px 20px; }
                .project-info { background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #FFD700; }
                .equipment-list { background: #f8f9fa; border-radius: 8px; padding: 15px; white-space: pre-line; font-family: monospace; }
                .footer { background: #f8f9fa; padding: 20px; text-align: center; font-size: 14px; color: #666; }
                .important { background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 8px; padding: 15px; margin-top: 20px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>🎬 Equipment Assignment</h1>
                    <p style='margin: 10px 0 0 0; opacity: 0.9;'>Neofox Gear Control</p>
                </div>
                
                <div class='content'>
                    <div class='project-info'>
                        <p style='margin: 0; font-size: 16px;'><strong>Hi {$crew_member},</strong></p>
                        <p style='margin: 10px 0 0 0;'>You have been assigned equipment for: <strong>{$project_name}</strong></p>
                        <p style='margin: 10px 0 0 0;'><strong>Purpose:</strong> {$purpose}</p>
                        <p style='margin: 10px 0 0 0;'><strong>Return Date:</strong> " . date('M j, Y g:i A', strtotime($return_date)) . "</p>
                    </div>
                    
                    <h3>📦 Your Assigned Equipment:</h3>
                    <div class='equipment-list'>{$equipment_list}</div>
                    
                    <div class='important'>
                        <h4 style='margin: 0 0 10px 0; color: #856404;'>📋 Important Reminders:</h4>
                        <ul style='margin: 0; padding-left: 20px;'>
                            <li>Please check all equipment before leaving</li>
                            <li>Report any damage or issues immediately</li>
                            <li>Return all equipment by the due date</li>
                            <li>You will receive a printed receipt for your records</li>
                        </ul>
                    </div>
                    
                    <div style='margin-top: 30px; text-align: center;'>
                        <p style='color: #666; font-size: 14px;'>
                            Questions? Contact us at <a href='mailto:team@neofoxmedia.com'>team@neofoxmedia.com</a>
                        </p>
                    </div>
                </div>
                
                <div class='footer'>
                    <p>This is an automated message from Neofox Gear Control System.</p>
                    <p style='margin-top: 10px;'>
                        <strong>Neofox Media</strong> | Equipment Management
                    </p>
                </div>
            </div>
        </body>
        </html>";
        
        return EmailNotification::sendEmail($email, $subject, $message);
    } catch (Exception $e) {
        error_log("Error sending crew checkout email: " . $e->getMessage());
        return false;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crew Checkout - Neofox Gear Control</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        :root {
            --neofox-yellow: #FFD700;
            --yellow-light: #FFF9C4;
            --yellow-dark: #F57F17;
            --black-primary: #000000;
            --black-light: #2d2d2d;
            --white-primary: #FFFFFF;
            --gray-50: #FAFAFA;
            --gray-100: #F5F5F5;
            --gray-200: #E5E5E5;
            --gray-300: #D4D4D4;
            --gray-400: #A3A3A3;
            --gray-500: #737373;
            --gray-600: #525252;
            --gray-700: #404040;
            --green-500: #10B981;
            --orange-500: #F59E0B;
            --red-500: #EF4444;
            --blue-500: #3B82F6;
            --shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 25px rgba(0, 0, 0, 0.15);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, var(--neofox-yellow) 0%, var(--yellow-light) 100%);
            color: var(--gray-700);
            line-height: 1.6;
            min-height: 100vh;
        }

        /* Navigation */
        .navbar {
            background: var(--black-primary) !important;
            border-bottom: 3px solid var(--neofox-yellow);
            box-shadow: var(--shadow);
        }

        .navbar-brand, .nav-link {
            color: var(--neofox-yellow) !important;
            font-weight: 600;
        }

        .nav-link:hover {
            color: white !important;
            transform: translateY(-1px);
        }

        /* Main Container */
        .main-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem 1rem;
        }

        .page-header {
            text-align: center;
            margin-bottom: 3rem;
        }

        .page-title {
            color: var(--black-primary);
            font-size: 3rem;
            font-weight: 900;
            margin-bottom: 0.5rem;
            text-transform: uppercase;
            letter-spacing: -1px;
        }

        .page-subtitle {
            color: var(--gray-600);
            font-size: 1.2rem;
            font-weight: 500;
        }

        /* Cards */
        .main-card {
            background: var(--white-primary);
            border-radius: 20px;
            box-shadow: var(--shadow-lg);
            overflow: hidden;
            margin-bottom: 2rem;
        }

        .card-header {
            background: var(--black-primary);
            color: var(--neofox-yellow);
            padding: 2rem;
            text-align: center;
        }

        .card-header h2 {
            font-size: 1.8rem;
            font-weight: 800;
            margin: 0;
            text-transform: uppercase;
            letter-spacing: -0.5px;
        }

        .card-body {
            padding: 3rem;
        }

        /* Form Sections */
        .form-section {
            margin-bottom: 3rem;
            padding-bottom: 2rem;
            border-bottom: 1px solid var(--gray-200);
        }

        .form-section:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }

        .section-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--black-primary);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .section-icon {
            width: 40px;
            height: 40px;
            background: var(--neofox-yellow);
            color: var(--black-primary);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
        }

        /* Form Elements */
        .form-label {
            font-weight: 600;
            color: var(--black-primary);
            margin-bottom: 0.75rem;
            font-size: 1rem;
        }

        .form-control, .form-select {
            border-radius: 12px;
            border: 2px solid var(--gray-300);
            padding: 1rem;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--neofox-yellow);
            box-shadow: 0 0 0 0.2rem rgba(255, 215, 0, 0.25);
        }

        /* Crew Assignment Cards */
        .crew-assignments {
            display: grid;
            gap: 1.5rem;
        }

        .crew-card {
            background: var(--gray-50);
            border: 2px solid var(--gray-200);
            border-radius: 16px;
            padding: 1.5rem;
            transition: all 0.3s ease;
        }

        .crew-card:hover {
            border-color: var(--neofox-yellow);
            box-shadow: 0 4px 12px rgba(255, 215, 0, 0.2);
        }

        .crew-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--gray-300);
        }

        .crew-name {
            font-weight: 700;
            color: var(--black-primary);
            font-size: 1.1rem;
        }

        .remove-crew {
            background: var(--red-500);
            color: white;
            border: none;
            border-radius: 8px;
            padding: 0.5rem;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .remove-crew:hover {
            background: #dc2626;
            transform: scale(1.05);
        }

        /* Equipment Selection */
        .equipment-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .equipment-search {
            position: relative;
        }

        .search-input {
            width: 100%;
            padding: 0.75rem 1rem 0.75rem 3rem;
            border: 2px solid var(--gray-300);
            border-radius: 12px;
            font-size: 0.9rem;
        }

        .search-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray-400);
        }

        .equipment-list {
            max-height: 300px;
            overflow-y: auto;
            border: 2px solid var(--gray-200);
            border-radius: 12px;
            background: white;
        }

        .equipment-item {
            padding: 1rem;
            border-bottom: 1px solid var(--gray-100);
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: background 0.2s;
        }

        .equipment-item:hover {
            background: var(--yellow-light);
        }

        .equipment-item:last-child {
            border-bottom: none;
        }

        .equipment-info {
            flex: 1;
        }

        .equipment-name {
            font-weight: 600;
            color: var(--black-primary);
            margin-bottom: 0.25rem;
        }

        .equipment-details {
            font-size: 0.85rem;
            color: var(--gray-500);
        }

        .status-badge {
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            background: rgba(16, 185, 129, 0.1);
            color: var(--green-500);
        }

        .add-equipment-btn {
            background: var(--neofox-yellow);
            color: var(--black-primary);
            border: none;
            border-radius: 8px;
            padding: 0.5rem 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .add-equipment-btn:hover {
            background: var(--yellow-dark);
            transform: translateY(-1px);
        }

        .add-equipment-btn:disabled {
            background: var(--gray-300);
            color: var(--gray-500);
            cursor: not-allowed;
            transform: none;
        }

        /* Selected Equipment */
        .selected-equipment {
            margin-top: 1rem;
        }

        .selected-item {
            background: white;
            border: 1px solid var(--gray-300);
            border-radius: 8px;
            padding: 0.75rem;
            margin-bottom: 0.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .selected-item:last-child {
            margin-bottom: 0;
        }

        .remove-item {
            background: var(--red-500);
            color: white;
            border: none;
            border-radius: 6px;
            padding: 0.25rem 0.5rem;
            font-size: 0.8rem;
            cursor: pointer;
        }

        .remove-item:hover {
            background: #dc2626;
        }

        /* Buttons */
        .btn {
            padding: 1rem 2rem;
            border-radius: 12px;
            font-weight: 700;
            text-decoration: none;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 1rem;
        }

        .btn-primary {
            background: var(--black-primary);
            color: var(--white-primary);
        }

        .btn-primary:hover {
            background: var(--black-light);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.2);
        }

        .btn-secondary {
            background: var(--gray-200);
            color: var(--gray-700);
        }

        .btn-secondary:hover {
            background: var(--gray-300);
            color: var(--gray-700);
        }

        .btn-success {
            background: var(--green-500);
            color: white;
        }

        .btn-success:hover {
            background: #059669;
            transform: translateY(-2px);
        }

        .btn-lg {
            padding: 1.25rem 2.5rem;
            font-size: 1.1rem;
        }

        .action-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin-top: 3rem;
        }

        /* Alerts */
        .alert {
            padding: 1.5rem;
            border-radius: 12px;
            margin-bottom: 2rem;
            border: 1px solid;
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            border-color: rgba(16, 185, 129, 0.3);
            color: #065f46;
        }

        .alert-danger {
            background: rgba(239, 68, 68, 0.1);
            border-color: rgba(239, 68, 68, 0.3);
            color: #991b1b;
        }

        /* Results Display */
        .results-section {
            margin-top: 2rem;
        }

        .crew-result {
            background: var(--gray-50);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1rem;
        }

        .crew-result h4 {
            color: var(--black-primary);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .equipment-result {
            padding: 0.5rem 0;
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .equipment-result:last-child {
            border-bottom: none;
        }

        .result-success {
            color: var(--green-500);
        }

        .result-error {
            color: var(--red-500);
        }

        /* Print Styles */
        .print-section {
            background: var(--white-primary);
            border: 2px solid var(--black-primary);
            border-radius: 12px;
            padding: 2rem;
            margin-top: 2rem;
        }

        .print-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin-bottom: 2rem;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .main-container {
                padding: 1rem 0.5rem;
            }

            .page-title {
                font-size: 2rem;
            }

            .card-body {
                padding: 1.5rem;
            }

            .equipment-grid {
                grid-template-columns: 1fr;
            }

            .action-buttons {
                flex-direction: column;
            }

            .crew-header {
                flex-direction: column;
                gap: 1rem;
                align-items: stretch;
            }
        }

        /* Loading States */
        .loading {
            opacity: 0.6;
            pointer-events: none;
        }

        .spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid #f3f3f3;
            border-top: 3px solid var(--neofox-yellow);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Empty States */
        .empty-state {
            text-align: center;
            padding: 3rem 2rem;
            color: var(--gray-500);
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: var(--gray-400);
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-cogs"></i> Neofox Gear Control
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="index.php">Dashboard</a>
                <a class="nav-link" href="assets.php">Assets</a>
                <a class="nav-link" href="bulk_scanner_v2.php">QR Scanner</a>
                <a class="nav-link active" href="crew_checkout.php">Crew Checkout</a>
                <a class="nav-link" href="requests.php">Requests</a>
                <a class="nav-link" href="logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <div class="main-container">
        <!-- Page Header -->
        <div class="page-header">
            <h1 class="page-title">
                <i class="fas fa-users"></i> Crew Checkout
            </h1>
            <p class="page-subtitle">Assign equipment to multiple crew members for shoots and projects</p>
        </div>

        <!-- Alerts -->
        <?php if (isset($success_message)): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
        </div>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
        </div>
        <?php endif; ?>

        <!-- Results Display -->
        <?php if (isset($_SESSION['crew_checkout_results'])): ?>
        <?php $checkout_results = $_SESSION['crew_checkout_results']; ?>
        <div class="main-card">
            <div class="card-header">
                <h2>
                    <i class="fas fa-check-circle"></i> Checkout Complete
                </h2>
                <p style="margin: 10px 0 0 0; opacity: 0.9;"><?php echo htmlspecialchars($checkout_results['project_name']); ?></p>
            </div>
            <div class="card-body">
                <div class="print-section">
                    <div class="print-buttons">
                        <button class="btn btn-primary" onclick="printAllReceipts()">
                            <i class="fas fa-print"></i> Print All Receipts
                        </button>
                        <?php if (!empty($checkout_results['pdf_results'])): ?>
                        <button class="btn btn-success" onclick="downloadAllPDFs()">
                            <i class="fas fa-file-pdf"></i> Download All PDFs
                        </button>
                        <?php endif; ?>
                        <button class="btn btn-secondary" onclick="startNewCheckout()">
                            <i class="fas fa-plus"></i> New Checkout
                        </button>
                    </div>

                    <div class="results-section">
                        <?php foreach ($checkout_results['results'] as $crew_member => $crew_data): ?>
                        <?php if (!empty($crew_data['equipment'])): ?>
                        <div class="crew-result" id="receipt-<?php echo htmlspecialchars($crew_member); ?>">
                            <h4>
                                <i class="fas fa-user"></i>
                                <?php echo htmlspecialchars($crew_member); ?>
                            </h4>

                            <!-- Action Buttons for this crew member -->
                            <div class="crew-actions" style="display: flex; gap: 0.5rem; margin-bottom: 1rem; flex-wrap: wrap;">
                                <button class="btn btn-sm btn-secondary" onclick="printSingleReceipt('<?php echo htmlspecialchars($crew_member); ?>')">
                                    <i class="fas fa-print"></i> Print
                                </button>
                                <?php if (isset($crew_data['pdf']) && $crew_data['pdf']['success']): ?>
                                <a href="<?php echo htmlspecialchars($crew_data['pdf']['filepath']); ?>" class="btn btn-sm btn-success" download>
                                    <i class="fas fa-file-pdf"></i> Download PDF
                                </a>
                                <?php endif; ?>
                                <button class="btn btn-sm" style="background: #25D366; color: white;" onclick="openWhatsAppModal('<?php echo htmlspecialchars($crew_member); ?>', '<?php echo isset($crew_data['pdf']) && $crew_data['pdf']['success'] ? htmlspecialchars($crew_data['pdf']['url']) : ''; ?>', <?php echo count(array_filter($crew_data['equipment'], function($e) { return $e['success']; })); ?>)">
                                    <i class="fab fa-whatsapp"></i> Send WhatsApp
                                </button>
                            </div>

                            <?php foreach ($crew_data['equipment'] as $item): ?>
                            <div class="equipment-result">
                                <span>
                                    <?php echo htmlspecialchars($item['asset_name'] ?? $item['asset_id']); ?>
                                    <?php if (isset($item['asset_id'])): ?>
                                    <small class="text-muted">(<?php echo htmlspecialchars($item['asset_id']); ?>)</small>
                                    <?php endif; ?>
                                </span>
                                <span class="<?php echo $item['success'] ? 'result-success' : 'result-error'; ?>">
                                    <i class="fas fa-<?php echo $item['success'] ? 'check' : 'times'; ?>"></i>
                                    <?php echo htmlspecialchars($item['message']); ?>
                                </span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- WhatsApp Modal -->
        <div class="modal fade" id="whatsappModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header" style="background: #25D366; color: white;">
                        <h5 class="modal-title">
                            <i class="fab fa-whatsapp"></i> Send Receipt via WhatsApp
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <form id="whatsappForm">
                            <input type="hidden" id="wa_crew_member" name="crew_member">
                            <input type="hidden" id="wa_pdf_url" name="pdf_url">
                            <input type="hidden" id="wa_equipment_count" name="equipment_count">

                            <div class="mb-3">
                                <label class="form-label"><strong>Recipient:</strong></label>
                                <p id="wa_recipient_name" class="form-control-static" style="font-weight: 600;"></p>
                            </div>

                            <div class="mb-3">
                                <label for="wa_phone" class="form-label">Phone Number <span style="color: red;">*</span></label>
                                <input type="tel" class="form-control" id="wa_phone" name="phone" placeholder="+1 234 567 8900" required>
                                <small class="form-text text-muted">Include country code (e.g., +1 for US, +91 for India)</small>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Message Preview:</label>
                                <div id="wa_message_preview" style="background: #f0f0f0; padding: 1rem; border-radius: 8px; font-size: 0.9rem;">
                                    <!-- Message preview will be shown here -->
                                </div>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn" style="background: #25D366; color: white;" onclick="sendWhatsApp()">
                            <i class="fab fa-whatsapp"></i> Send WhatsApp
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <script>
            // Store PDF results for download
            var pdfResults = <?php echo json_encode($checkout_results['pdf_results'] ?? []); ?>;

            function downloadAllPDFs() {
                for (var crewMember in pdfResults) {
                    if (pdfResults[crewMember].filepath) {
                        var link = document.createElement('a');
                        link.href = pdfResults[crewMember].filepath;
                        link.download = '';
                        document.body.appendChild(link);
                        link.click();
                        document.body.removeChild(link);
                    }
                }
            }

            function openWhatsAppModal(crewMember, pdfUrl, equipmentCount) {
                document.getElementById('wa_crew_member').value = crewMember;
                document.getElementById('wa_pdf_url').value = pdfUrl;
                document.getElementById('wa_equipment_count').value = equipmentCount;
                document.getElementById('wa_recipient_name').textContent = crewMember;

                // Update message preview
                var preview = document.getElementById('wa_message_preview');
                preview.innerHTML = '<p>🎬 <strong>Neofox Media - Equipment Checkout</strong></p>' +
                    '<p>Hi ' + crewMember + '!</p>' +
                    '<p>✅ ' + equipmentCount + ' items checked out for: <strong><?php echo addslashes($checkout_results['project_name']); ?></strong></p>' +
                    '<p>📅 Return by: <?php echo date('M j, Y', strtotime($checkout_results['return_date'])); ?></p>' +
                    (pdfUrl ? '<p>📋 Your agreement PDF is attached.</p>' : '') +
                    '<p><em>Neofox Equipment Control</em></p>';

                var modal = new bootstrap.Modal(document.getElementById('whatsappModal'));
                modal.show();
            }

            function sendWhatsApp() {
                var phone = document.getElementById('wa_phone').value.trim();
                var crewMember = document.getElementById('wa_crew_member').value;
                var pdfUrl = document.getElementById('wa_pdf_url').value;
                var equipmentCount = document.getElementById('wa_equipment_count').value;

                if (!phone) {
                    alert('Please enter a phone number');
                    return;
                }

                // Send via AJAX to server
                var formData = new FormData();
                formData.append('action', 'send_whatsapp');
                formData.append('phone', phone);
                formData.append('crew_member', crewMember);
                formData.append('pdf_url', pdfUrl);
                formData.append('equipment_count', equipmentCount);
                formData.append('project_name', '<?php echo addslashes($checkout_results['project_name']); ?>');

                fetch('crew_checkout.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('WhatsApp message sent successfully!');
                        bootstrap.Modal.getInstance(document.getElementById('whatsappModal')).hide();
                    } else {
                        // Fallback: open WhatsApp Web with pre-filled message
                        var message = encodeURIComponent(
                            '🎬 *Neofox Media - Equipment Checkout*\n\n' +
                            'Hi ' + crewMember + '!\n\n' +
                            '✅ ' + equipmentCount + ' items checked out for: *<?php echo addslashes($checkout_results['project_name']); ?>*\n' +
                            '📅 Return by: <?php echo date('M j, Y', strtotime($checkout_results['return_date'])); ?>\n\n' +
                            (pdfUrl ? '📋 Your agreement: ' + pdfUrl + '\n\n' : '') +
                            '_Neofox Equipment Control_'
                        );
                        window.open('https://wa.me/' + phone.replace(/[^0-9]/g, '') + '?text=' + message, '_blank');
                        bootstrap.Modal.getInstance(document.getElementById('whatsappModal')).hide();
                    }
                })
                .catch(error => {
                    // Fallback to WhatsApp Web
                    var message = encodeURIComponent(
                        '🎬 *Neofox Media - Equipment Checkout*\n\n' +
                        'Hi ' + crewMember + '!\n\n' +
                        '✅ ' + equipmentCount + ' items checked out for: *<?php echo addslashes($checkout_results['project_name']); ?>*\n' +
                        '📅 Return by: <?php echo date('M j, Y', strtotime($checkout_results['return_date'])); ?>\n\n' +
                        (pdfUrl ? '📋 Your agreement: ' + pdfUrl + '\n\n' : '') +
                        '_Neofox Equipment Control_'
                    );
                    window.open('https://wa.me/' + phone.replace(/[^0-9]/g, '') + '?text=' + message, '_blank');
                    bootstrap.Modal.getInstance(document.getElementById('whatsappModal')).hide();
                });
            }
        </script>
        <?php unset($_SESSION['crew_checkout_results']); ?>
        <?php else: ?>

        <!-- Main Form -->
        <div class="main-card">
            <div class="card-header">
                <h2>
                    <i class="fas fa-clipboard-list"></i> Setup Crew Equipment Assignment
                </h2>
            </div>
            
            <div class="card-body">
                <form method="POST" id="crew-checkout-form">
                    <!-- Project Information -->
                    <div class="form-section">
                        <h3 class="section-title">
                            <div class="section-icon">
                                <i class="fas fa-project-diagram"></i>
                            </div>
                            Project Information
                        </h3>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <label class="form-label">Project/Shoot Name *</label>
                                <input type="text" class="form-control" name="project_name" required 
                                       placeholder="e.g., Corporate Video Shoot">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Expected Return Date *</label>
                                <input type="datetime-local" class="form-control" name="return_date" required>
                            </div>
                        </div>
                    </div>

                    <!-- Crew Assignments -->
                    <div class="form-section">
                        <h3 class="section-title">
                            <div class="section-icon">
                                <i class="fas fa-users"></i>
                            </div>
                            Crew Assignments
                            <button type="button" class="btn btn-secondary ms-auto" onclick="addCrewMember()">
                                <i class="fas fa-plus"></i> Add Crew Member
                            </button>
                        </h3>
                        
                        <div class="crew-assignments" id="crew-assignments">
                            <!-- Crew member cards will be added here -->
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <div class="action-buttons">
                        <button type="submit" class="btn btn-success btn-lg">
                            <i class="fas fa-check"></i> Process Crew Checkout
                        </button>
                        <button type="button" class="btn btn-secondary btn-lg" onclick="resetForm()">
                            <i class="fas fa-refresh"></i> Reset Form
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Equipment Search Modal -->
    <div class="modal fade" id="equipmentModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-search"></i> Select Equipment
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="equipment-search mb-3">
                        <i class="fas fa-search search-icon"></i>
                        <input type="text" class="search-input" id="equipment-search" 
                               placeholder="Search by name, ID, or category..." oninput="filterEquipment()">
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Filter by Category</label>
                            <select class="form-select" id="category-filter" onchange="filterEquipment()">
                                <option value="">All Categories</option>
                                <?php foreach ($categories as $category): ?>
                                <option value="<?php echo htmlspecialchars($category); ?>">
                                    <?php echo htmlspecialchars($category); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Show</label>
                            <select class="form-select" id="limit-filter" onchange="filterEquipment()">
                                <option value="20">20 items</option>
                                <option value="50">50 items</option>
                                <option value="100">100 items</option>
                                <option value="">All items</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="equipment-list" id="equipment-list">
                        <!-- Equipment items will be loaded here -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Global variables
        let crewCounter = 0;
        let currentCrewMember = null;
        let availableEquipment = <?php echo json_encode($available_assets); ?>;
        let users = <?php echo json_encode($users); ?>;
        let filteredEquipment = [...availableEquipment];

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            addCrewMember(); // Add first crew member by default
            
            // Set default return date (tomorrow at 5 PM)
            const tomorrow = new Date();
            tomorrow.setDate(tomorrow.getDate() + 1);
            tomorrow.setHours(17, 0, 0, 0);
            document.querySelector('input[name="return_date"]').value = tomorrow.toISOString().slice(0, 16);
            
            loadEquipmentList();
        });

        function addCrewMember() {
            crewCounter++;
            const crewAssignments = document.getElementById('crew-assignments');
            
            const crewCard = document.createElement('div');
            crewCard.className = 'crew-card';
            crewCard.id = `crew-${crewCounter}`;
            
            crewCard.innerHTML = `
                <div class="crew-header">
                    <div class="crew-name">Crew Member ${crewCounter}</div>
                    <button type="button" class="remove-crew" onclick="removeCrewMember(${crewCounter})">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Team Member *</label>
                        <select class="form-select" name="crew_assignments[crew_${crewCounter}][member]" required onchange="updateCrewName(${crewCounter}, this.value)">
                            <option value="">Select team member...</option>
                            ${users.map(user => `<option value="${user.username}">${user.username}</option>`).join('')}
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Specific Purpose</label>
                        <input type="text" class="form-control" name="crew_assignments[crew_${crewCounter}][purpose]" 
                               placeholder="e.g., Camera operator, Sound engineer">
                    </div>
                </div>
                
                <div class="equipment-assignment">
                    <label class="form-label">Assigned Equipment</label>
                    <button type="button" class="btn btn-secondary mb-2" onclick="openEquipmentModal(${crewCounter})">
                        <i class="fas fa-plus"></i> Add Equipment
                    </button>
                    
                    <div class="selected-equipment" id="selected-equipment-${crewCounter}">
                        <div class="empty-state">
                            <i class="fas fa-box-open"></i>
                            <p>No equipment assigned yet</p>
                        </div>
                    </div>
                </div>
            `;
            
            crewAssignments.appendChild(crewCard);
        }

        function removeCrewMember(crewId) {
            const crewCard = document.getElementById(`crew-${crewId}`);
            if (crewCard) {
                crewCard.remove();
            }
        }

        function updateCrewName(crewId, username) {
            const crewNameElement = document.querySelector(`#crew-${crewId} .crew-name`);
            if (username) {
                crewNameElement.textContent = username;
            } else {
                crewNameElement.textContent = `Crew Member ${crewId}`;
            }
        }

        function openEquipmentModal(crewId) {
            currentCrewMember = crewId;
            const modal = new bootstrap.Modal(document.getElementById('equipmentModal'));
            modal.show();
            filterEquipment();
        }

        function loadEquipmentList() {
            const equipmentList = document.getElementById('equipment-list');
            
            if (filteredEquipment.length === 0) {
                equipmentList.innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-search"></i>
                        <p>No equipment found matching your criteria</p>
                    </div>
                `;
                return;
            }
            
            let html = '';
            filteredEquipment.forEach(item => {
                const isAlreadySelected = isEquipmentSelected(item.asset_id);
                
                html += `
                    <div class="equipment-item">
                        <div class="equipment-info">
                            <div class="equipment-name">${item.asset_name}</div>
                            <div class="equipment-details">${item.category} • ${item.asset_id}</div>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="status-badge">Available</span>
                            <button class="add-equipment-btn" 
                                    onclick="addEquipmentToCrew('${item.asset_id}', '${item.asset_name}', '${item.category}')"
                                    ${isAlreadySelected ? 'disabled' : ''}>
                                ${isAlreadySelected ? 'Added' : 'Add'}
                            </button>
                        </div>
                    </div>
                `;
            });
            
            equipmentList.innerHTML = html;
        }

        function filterEquipment() {
            const search = document.getElementById('equipment-search').value.toLowerCase();
            const category = document.getElementById('category-filter').value;
            const limit = parseInt(document.getElementById('limit-filter').value) || Infinity;
            
            filteredEquipment = availableEquipment.filter(item => {
                const matchesSearch = !search || 
                    item.asset_name.toLowerCase().includes(search) ||
                    item.asset_id.toLowerCase().includes(search) ||
                    item.category.toLowerCase().includes(search);
                    
                const matchesCategory = !category || item.category === category;
                
                return matchesSearch && matchesCategory;
            }).slice(0, limit);
            
            loadEquipmentList();
        }

        function isEquipmentSelected(assetId) {
            const allSelectedEquipment = document.querySelectorAll('input[name*="[equipment][]"]');
            return Array.from(allSelectedEquipment).some(input => input.value === assetId);
        }

        function addEquipmentToCrew(assetId, assetName, category) {
            if (!currentCrewMember) return;
            
            const selectedContainer = document.getElementById(`selected-equipment-${currentCrewMember}`);
            
            // Remove empty state if it exists
            const emptyState = selectedContainer.querySelector('.empty-state');
            if (emptyState) {
                emptyState.remove();
            }
            
            // Create new selected item
            const selectedItem = document.createElement('div');
            selectedItem.className = 'selected-item';
            selectedItem.innerHTML = `
                <div>
                    <div class="equipment-name">${assetName}</div>
                    <div class="equipment-details">${category} • ${assetId}</div>
                </div>
                <button type="button" class="remove-item" onclick="removeEquipmentFromCrew('${assetId}', ${currentCrewMember})">
                    <i class="fas fa-times"></i>
                </button>
                <input type="hidden" name="crew_assignments[crew_${currentCrewMember}][equipment][]" value="${assetId}">
            `;
            
            selectedContainer.appendChild(selectedItem);
            
            // Update the equipment list to show this item as added
            loadEquipmentList();
            
            // Close modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('equipmentModal'));
            modal.hide();
        }

        function removeEquipmentFromCrew(assetId, crewId) {
            const selectedContainer = document.getElementById(`selected-equipment-${crewId}`);
            const items = selectedContainer.querySelectorAll('.selected-item');
            
            items.forEach(item => {
                const input = item.querySelector('input[type="hidden"]');
                if (input && input.value === assetId) {
                    item.remove();
                }
            });
            
            // Add empty state if no items left
            if (selectedContainer.children.length === 0) {
                selectedContainer.innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-box-open"></i>
                        <p>No equipment assigned yet</p>
                    </div>
                `;
            }
            
            // Refresh equipment list if modal is open
            if (document.getElementById('equipmentModal').classList.contains('show')) {
                loadEquipmentList();
            }
        }

        function resetForm() {
            if (confirm('Are you sure you want to reset the form? All data will be lost.')) {
                document.getElementById('crew-checkout-form').reset();
                document.getElementById('crew-assignments').innerHTML = '';
                crewCounter = 0;
                addCrewMember();
            }
        }

        // Form validation before submission
        document.getElementById('crew-checkout-form')?.addEventListener('submit', function(e) {
            // Check if project name is filled
            const projectName = document.querySelector('input[name="project_name"]').value.trim();
            if (!projectName) {
                e.preventDefault();
                alert('Please enter a project name.');
                return false;
            }

            // Check return date
            const returnDate = new Date(document.querySelector('input[name="return_date"]').value);
            const now = new Date();
            if (returnDate <= now) {
                e.preventDefault();
                alert('Return date must be in the future.');
                return false;
            }

            // Check if at least one crew member has a member selected and equipment assigned
            const crewCards = document.querySelectorAll('.crew-card');
            if (crewCards.length === 0) {
                e.preventDefault();
                alert('Please add at least one crew member.');
                return false;
            }

            let hasValidAssignment = false;
            crewCards.forEach(card => {
                const memberSelect = card.querySelector('select[name*="[member]"]');
                const equipmentInputs = card.querySelectorAll('input[name*="[equipment][]"]');

                if (memberSelect && memberSelect.value && equipmentInputs.length > 0) {
                    hasValidAssignment = true;
                }
            });

            if (!hasValidAssignment) {
                e.preventDefault();
                alert('Please select a team member and assign at least one piece of equipment to at least one crew member.');
                return false;
            }

            // Show loading state
            const submitBtn = e.target.querySelector('button[type="submit"]');
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
            submitBtn.disabled = true;
        });

        // Print functions
        function printAllReceipts() {
            const crewResults = document.querySelectorAll('.crew-result');
            
            crewResults.forEach((crewResult, index) => {
                setTimeout(() => {
                    printReceipt(crewResult);
                }, index * 500); // Delay each print by 500ms
            });
        }

        function printSingleReceipt(crewMember) {
            const crewResult = document.getElementById(`receipt-${crewMember}`);
            if (crewResult) {
                printReceipt(crewResult);
            }
        }

        function printReceipt(crewElement) {
            const projectName = "<?php echo addslashes($_SESSION['crew_checkout_results']['project_name'] ?? 'Project'); ?>";
            const returnDate = "<?php echo isset($_SESSION['crew_checkout_results']['return_date']) ? date('M j, Y g:i A', strtotime($_SESSION['crew_checkout_results']['return_date'])) : ''; ?>";
            
            const crewMember = crewElement.querySelector('h4').textContent.trim();
            const equipmentItems = crewElement.querySelectorAll('.equipment-result');
            
            let equipmentList = '';
            equipmentItems.forEach(item => {
                const equipmentName = item.querySelector('span:first-child').textContent.trim();
                const status = item.querySelector('.result-success') ? '✓' : '✗';
                equipmentList += `<tr><td>${status}</td><td>${equipmentName}</td></tr>`;
            });
            
            const printWindow = window.open('', '_blank');
            printWindow.document.write(`
                <!DOCTYPE html>
                <html>
                <head>
                    <title>Equipment Receipt - ${crewMember}</title>
                    <style>
                        body { 
                            font-family: Arial, sans-serif; 
                            margin: 0; 
                            padding: 20px; 
                            font-size: 14px;
                        }
                        .receipt {
                            max-width: 600px;
                            margin: 0 auto;
                            border: 2px solid #000;
                            padding: 20px;
                        }
                        .header {
                            text-align: center;
                            border-bottom: 2px solid #000;
                            padding-bottom: 15px;
                            margin-bottom: 20px;
                        }
                        .company-name {
                            font-size: 24px;
                            font-weight: bold;
                            margin-bottom: 5px;
                        }
                        .receipt-title {
                            font-size: 18px;
                            font-weight: bold;
                            margin-bottom: 10px;
                        }
                        .info-section {
                            margin-bottom: 20px;
                        }
                        .info-row {
                            display: flex;
                            justify-content: space-between;
                            margin-bottom: 8px;
                            padding: 5px 0;
                            border-bottom: 1px solid #eee;
                        }
                        .info-label {
                            font-weight: bold;
                        }
                        table {
                            width: 100%;
                            border-collapse: collapse;
                            margin-top: 15px;
                        }
                        th, td {
                            border: 1px solid #000;
                            padding: 8px;
                            text-align: left;
                        }
                        th {
                            background: #f0f0f0;
                            font-weight: bold;
                        }
                        .footer {
                            margin-top: 30px;
                            border-top: 2px solid #000;
                            padding-top: 15px;
                            text-align: center;
                            font-size: 12px;
                        }
                        .signature-section {
                            margin-top: 40px;
                            display: flex;
                            justify-content: space-between;
                        }
                        .signature-box {
                            width: 200px;
                            text-align: center;
                        }
                        .signature-line {
                            border-top: 1px solid #000;
                            margin-top: 30px;
                            padding-top: 5px;
                            font-size: 12px;
                        }
                        @media print {
                            body { margin: 0; }
                            .receipt { border: 2px solid #000; }
                        }
                    </style>
                </head>
                <body>
                    <div class="receipt">
                        <div class="header">
                            <div class="company-name">NEOFOX MEDIA</div>
                            <div style="font-size: 14px; color: #666;">Equipment Management</div>
                            <div class="receipt-title">EQUIPMENT CHECKOUT RECEIPT</div>
                        </div>
                        
                        <div class="info-section">
                            <div class="info-row">
                                <span class="info-label">Project:</span>
                                <span>${projectName}</span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Crew Member:</span>
                                <span>${crewMember}</span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Checkout Date:</span>
                                <span>${new Date().toLocaleDateString()} ${new Date().toLocaleTimeString()}</span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Return Due:</span>
                                <span>${returnDate}</span>
                            </div>
                        </div>
                        
                        <table>
                            <thead>
                                <tr>
                                    <th width="50">Status</th>
                                    <th>Equipment</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${equipmentList}
                            </tbody>
                        </table>
                        
                        <div class="signature-section">
                            <div class="signature-box">
                                <div class="signature-line">Equipment Manager</div>
                            </div>
                            <div class="signature-box">
                                <div class="signature-line">Crew Member Signature</div>
                            </div>
                        </div>
                        
                        <div class="footer">
                            <p><strong>IMPORTANT:</strong> Please check all equipment before leaving. Report any damage immediately.</p>
                            <p>Return all equipment by due date. Contact: team@neofoxmedia.com</p>
                            <p>Generated: ${new Date().toLocaleString()}</p>
                        </div>
                    </div>
                </body>
                </html>
            `);
            
            printWindow.document.close();
            
            // Wait for content to load, then print
            setTimeout(() => {
                printWindow.print();
            }, 500);
        }

        function startNewCheckout() {
            window.location.href = 'crew_checkout.php';
        }

    </script>
</body>
</html>