<?php
/**
 * Quick State Check
 */

echo "<h2>🔍 Quick Application State Check</h2>";

require_once 'config/config.php';
require_once 'includes/functions.php';

$queue_number = 'UREO-0001';

try {
    $conn = getDBConnection();
    
    // Check current application status
    $app_stmt = $conn->prepare("SELECT queue_number, current_status, category, applicant_name FROM applications WHERE queue_number = ?");
    $app_stmt->bind_param("s", $queue_number);
    $app_stmt->execute();
    $app = $app_stmt->get_result()->fetch_assoc();
    
    echo "<h3>Application Status</h3>";
    echo "<pre>";
    echo "Queue Number: " . $app['queue_number'] . "\n";
    echo "Applicant: " . $app['applicant_name'] . "\n";
    echo "Current Status: " . $app['current_status'] . "\n";
    echo "Category: " . $app['category'] . "\n";
    echo "</pre>";
    
    // Check category forms
    $form_stmt = $conn->prepare("SELECT form_type, form_data FROM fillable_forms WHERE queue_number = ? ORDER BY created_at DESC");
    $form_stmt->bind_param("s", $queue_number);
    $form_stmt->execute();
    $forms = $form_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    echo "<h3>Forms</h3>";
    foreach ($forms as $form) {
        echo "<strong>" . $form['form_type'] . "</strong>:<br>";
        $data = json_decode($form['form_data'], true);
        if (isset($data['annotated_qf02_path'])) {
            echo "<span style='color: red;'>⚠️ CONTAINS ANNOTATED PDF PATH!</span><br>";
        }
        echo "<pre>" . htmlspecialchars(json_encode($data, JSON_PRETTY_PRINT)) . "</pre><br>";
    }
    
    // Check status history
    $hist_stmt = $conn->prepare("SELECT * FROM status_history WHERE queue_number = ? ORDER BY timestamp DESC LIMIT 5");
    $hist_stmt->bind_param("s", $queue_number);
    $hist_stmt->execute();
    $history = $hist_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    echo "<h3>Recent Status History</h3>";
    foreach ($history as $hist) {
        echo "<strong>" . $hist['timestamp'] . "</strong>: " . $hist['previous_status'] . " → " . $hist['new_status'] . "<br>";
        echo "<em>" . $hist['notes'] . "</em><br><br>";
    }
    
    closeDBConnection($conn);
    
} catch (Exception $e) {
    echo "<h3 style='color: red;'>Error: " . $e->getMessage() . "</h3>";
}

?>
