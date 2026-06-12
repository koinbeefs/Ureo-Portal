<?php
/**
 * Get QF02 remarks for floating annotations
 */

session_start();
require_once '../config/config.php';
require_once '../includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['queue_number'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$queue_number = $_SESSION['queue_number'];

// Validate queue number parameter
if (isset($_GET['queue']) && $_GET['queue'] !== $queue_number) {
    // Additional security check - ensure queue matches session
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid queue number']);
    exit;
}

try {
    $conn = getDBConnection();
    
    // Get QF02 form data with remarks
    $stmt = $conn->prepare("SELECT form_data FROM fillable_forms WHERE queue_number = ? AND form_type = 'qf02'");
    $stmt->bind_param("s", $queue_number);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'QF02 form not found']);
        exit;
    }
    
    $form_data = $result->fetch_assoc();
    $decoded_data = json_decode($form_data['form_data'], true);
    
    if (!$decoded_data) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Invalid form data']);
        exit;
    }
    
    // Extract only the remarks fields
    $remarks = [];
    for ($i = 1; $i <= 20; $i++) {
        $remark_key = "crit_{$i}_remarks";
        if (isset($decoded_data[$remark_key]) && !empty(trim($decoded_data[$remark_key]))) {
            $remarks[$remark_key] = $decoded_data[$remark_key];
        }
    }
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'remarks' => $remarks,
        'queue_number' => $queue_number
    ]);
    
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} finally {
    if (isset($conn)) {
        closeDBConnection($conn);
    }
}
?>
