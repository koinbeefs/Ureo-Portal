<?php
/**
 * Reset Application Status for Testing
 * TAU-UREO Portal
 */

require_once 'config/config.php';
require_once 'includes/functions.php';

$queue_number = 'UREO-0001';
echo "<h2>Reset Application Status: $queue_number</h2>";

$conn = getDBConnection();

// Check current status first
$app_stmt = $conn->prepare("SELECT * FROM applications WHERE queue_number = ?");
$app_stmt->bind_param("s", $queue_number);
$app_stmt->execute();
$application = $app_stmt->get_result()->fetch_assoc();

if (!$application) {
    echo "<p style='color: red;'>❌ Application not found</p>";
    exit();
}

echo "<h3>Current Status: " . $application['current_status'] . "</h3>";

// Reset to a status that allows approval
$new_status = 'CATEGORIZED'; // This is a good status for testing approval

$update_stmt = $conn->prepare("UPDATE applications SET current_status = ?, last_updated = NOW() WHERE queue_number = ?");
$update_stmt->bind_param("ss", $new_status, $queue_number);

if ($update_stmt->execute()) {
    echo "<p style='color: green;'>✅ Application status reset to: <strong>$new_status</strong></p>";
    
    // Add to status history
    $history_stmt = $conn->prepare("INSERT INTO status_history (queue_number, previous_status, new_status, changed_by_type, changed_by, notes) VALUES (?, ?, ?, 'staff', ?, ?)");
    $notes = "Status reset for testing purposes";
    $staff_id = 1; // Use a variable instead of literal
    $history_stmt->bind_param("sssis", $queue_number, $application['current_status'], $new_status, $staff_id, $notes);
    $history_stmt->execute();
    
    echo "<p>✅ Status history updated</p>";
    
    // Clean up any category form records from previous tests
    $cleanup_stmt = $conn->prepare("DELETE FROM fillable_forms WHERE queue_number = ? AND form_type IN ('category_form', 'category_token')");
    $cleanup_stmt->bind_param("s", $queue_number);
    $cleanup_stmt->execute();
    
    if ($cleanup_stmt->affected_rows > 0) {
        echo "<p>✅ Cleaned up " . $cleanup_stmt->affected_rows . " category form records</p>";
    }
    
} else {
    echo "<p style='color: red;'>❌ Failed to reset status</p>";
}

closeDBConnection($conn);

echo "<h3>Next Steps</h3>";
echo "<p>Now you can test the approval process again:</p>";
echo "<ul>";
echo "<li>Go to the staff dashboard</li>";
echo "<li>Find application UREO-0001</li>";
echo "<li>Click 'Approve Application'</li>";
echo "<li>Select review type and submit</li>";
echo "</ul>";

?>
