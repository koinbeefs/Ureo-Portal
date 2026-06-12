<?php
/**
 * System Settings Handler
 * TAU-UREO Portal - Admin Only
 */

require_once '../config/config.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

requireLogin();
checkSessionTimeout();

// Check if user is admin
if ($_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

$conn = getDBConnection();

try {
    // Prepare update statement
    $stmt = $conn->prepare("
        INSERT INTO system_settings (setting_key, setting_value, last_updated)
        VALUES (?, ?, NOW())
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), last_updated = NOW()
    ");
    
    // Process all POST data
    foreach ($_POST as $key => $value) {
        // Handle checkboxes (convert to 1 or 0)
        if (in_array($key, ['allow_public_tracking', 'enable_auto_assignment', 'notify_new_application', 
                            'notify_status_change', 'notify_new_message', 'notify_document_upload', 'maintenance_mode'])) {
            $value = isset($_POST[$key]) ? '1' : '0';
        }
        
        // Skip empty password fields
        if ($key === 'smtp_password' && empty($value)) {
            continue;
        }
        
        $stmt->bind_param("ss", $key, $value);
        $stmt->execute();
    }
    
    // Log activity
    logStaffActivity($_SESSION['user_id'], null, 'other', "Updated system settings");
    
    echo json_encode(['success' => true, 'message' => 'Settings saved successfully']);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

closeDBConnection($conn);
?>
