<?php
// Minimal approval test with logging
file_put_contents('minimal_log.txt', "Starting approval test...\n");

try {
    require_once 'config/config.php';
    file_put_contents('minimal_log.txt', "Config loaded\n", FILE_APPEND);
    
    $conn = getDBConnection();
    file_put_contents('minimal_log.txt', "DB connected\n", FILE_APPEND);
    
    $queue_number = 'UREO-0001';
    
    // Get application
    $stmt = $conn->prepare("SELECT current_status FROM applications WHERE queue_number = ?");
    $stmt->bind_param("s", $queue_number);
    $stmt->execute();
    $app = $stmt->get_result()->fetch_assoc();
    
    file_put_contents('minimal_log.txt', "Current status: " . $app['current_status'] . "\n", FILE_APPEND);
    
    // Test status history insert
    $history_stmt = $conn->prepare("INSERT INTO status_history (queue_number, previous_status, new_status, changed_by, changed_by_type, notes, timestamp) VALUES (?, ?, ?, ?, ?, ?, NOW())");
    $notes = "Test approval";
    $history_stmt->bind_param("ssssis", $queue_number, $app['current_status'], 'CATEGORY_FORMS_REQUIRED', 1, 'staff', $notes);
    $result = $history_stmt->execute();
    
    file_put_contents('minimal_log.txt', "History insert result: " . ($result ? 'SUCCESS' : 'FAILED') . "\n", FILE_APPEND);
    
    if (!$result) {
        file_put_contents('minimal_log.txt', "Error: " . $history_stmt->error . "\n", FILE_APPEND);
    }
    
    closeDBConnection($conn);
    file_put_contents('minimal_log.txt', "DB closed\n", FILE_APPEND);
    
} catch (Exception $e) {
    file_put_contents('minimal_log.txt', "Error: " . $e->getMessage() . "\n", FILE_APPEND);
}

echo "Check minimal_log.txt for results";
?>
