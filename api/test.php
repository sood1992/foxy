<?php
/**
 * API Test - Delete this file after testing!
 * Access: https://neofoxmedia.com/foxynew/api/test.php
 */

header('Content-Type: application/json');

echo json_encode([
    'status' => 'ok',
    'message' => 'PHP is working!',
    'php_version' => phpversion(),
    'time' => date('Y-m-d H:i:s'),
    'document_root' => $_SERVER['DOCUMENT_ROOT'],
    'script_filename' => $_SERVER['SCRIPT_FILENAME'],
    'request_uri' => $_SERVER['REQUEST_URI']
]);
