<?php
/**
 * Fix Stuck Workflow for UREO-0003
 * TAU-UREO Portal
 */

require_once 'config/database.php';

$conn = getDBConnection();

try {
    $conn->begin_transaction();
    
    echo "=== Fixing UREO-0003 Workflow ===\n";
    
    // 1. Validate all documents
    echo "1. Validating documents...\n";
    $validate_sql = "UPDATE documents SET validation_status = 'validated' WHERE queue_number = 'UREO-0003'";
    $result = $conn->query($validate_sql);
    echo "   Documents updated: " . $conn->affected_rows . "\n";
    
    // 2. Set category (default to 'expedited' for now)
    echo "2. Setting application category...\n";
    $category_sql = "UPDATE applications SET category = 'expedited' WHERE queue_number = 'UREO-0003'";
    $result = $conn->query($category_sql);
    echo "   Category set to: expedited\n";
    
    // 3. Progress status to next appropriate stage
    echo "3. Updating application status...\n";
    require_once 'config/config.php';
    $new_status = STATUS_UNDER_AUTO_REVIEW; // Next logical step
    
    $status_sql = "UPDATE applications SET current_status = ?, last_updated = NOW() WHERE queue_number = 'UREO-0003'";
    $stmt = $conn->prepare($status_sql);
    $stmt->bind_param("s", $new_status);
    $stmt->execute();
    echo "   Status updated to: $new_status\n";
    
    // 4. Add status history entry
    echo "4. Adding status history...\n";
    $history_sql = "INSERT INTO status_history (queue_number, previous_status, new_status, changed_by, changed_by_type, notes) VALUES (?, ?, ?, ?, 'system', ?)";
    $history_stmt = $conn->prepare($history_sql);
    $previous_status = 'REQUIREMENTS_PENDING';
    $notes = 'Workflow fix: Documents validated and category assigned. Progressing to automated review.';
    $history_stmt->bind_param("sssis", 'UREO-0003', $previous_status, $new_status, 1, $notes);
    $history_stmt->execute();
    echo "   History entry added\n";
    
    // 5. Log the fix
    echo "5. Logging the fix...\n";
    $log_sql = "INSERT INTO staff_logs (staff_id, queue_number, action_type, action_details) VALUES (?, ?, 'other', ?)";
    $log_stmt = $conn->prepare($log_sql);
    $action_details = 'Workflow fix: Progressed stuck application from INTENT_RECEIVED to UNDER_AUTO_REVIEW';
    $log_stmt->bind_param("iss", 1, 'UREO-0003', $action_details);
    $log_stmt->execute();
    echo "   Fix logged\n";
    
    $conn->commit();
    echo "\n✅ Workflow fix completed successfully!\n";
    echo "Application UREO-0003 is now in: $new_status\n";
    echo "The application should now progress through the automated review process.\n";
    
} catch (Exception $e) {
    $conn->rollback();
    echo "❌ Error fixing workflow: " . $e->getMessage() . "\n";
}

closeDBConnection($conn);
?>
