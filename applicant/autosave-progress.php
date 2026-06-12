<?php
declare(strict_types=1);

/**
 * Autosave Form Progress
 * Saves current form data to a local JSON file to prevent data loss.
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

// Ensure the user is logged in
requireApplicantLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('HTTP/1.1 405 Method Not Allowed');
    exit;
}

$queue_number = $_SESSION['queue_number'];
$form_type = $_POST['form_type'] ?? '';

if (empty($form_type)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Missing form type']);
    exit;
}

// Filter out sensitive or unnecessary fields if needed
$data = $_POST;
unset($data['form_type']);

// Ensure target directory exists
$target_dir = UPLOAD_DIR . $queue_number . '/';
if (!is_dir($target_dir)) {
    mkdir($target_dir, 0777, true);
}

$file_name = "progress_{$form_type}.json";
$file_path = $target_dir . $file_name;

// Save current data state
$success = file_put_contents($file_path, json_encode([
    'last_updated' => date('Y-m-d H:i:s'),
    'data' => $data
], JSON_PRETTY_PRINT));

header('Content-Type: application/json');
if ($success !== false) {
    echo json_encode([
        'success' => true, 
        'message' => 'Progress autosaved locally',
        'timestamp' => date('H:i:s')
    ]);
} else {
    echo json_encode([
        'success' => false, 
        'message' => 'Failed to save progress'
    ]);
}
