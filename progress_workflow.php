<?php
/**
 * Progress Workflow Based on Form Completion
 * TAU-UREO Portal
 */

require_once 'config/database.php';

$conn = getDBConnection();

echo "=== Checking Applications Ready for Progression ===\n";

// Get applications stuck in INTENT_RECEIVED that have completed forms
$sql = "
    SELECT a.queue_number, a.current_status, a.applicant_name
    FROM applications a
    LEFT JOIN fillable_forms f1 ON a.queue_number = f1.queue_number AND f1.form_type = 'qf01'
    LEFT JOIN fillable_forms f2 ON a.queue_number = f2.queue_number AND f2.form_type = 'qf02'
    WHERE a.current_status = 'INTENT_RECEIVED'
    AND f1.form_id IS NOT NULL
    AND f2.form_id IS NOT NULL
";

$result = $conn->query($sql);
$ready_apps = [];

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $ready_apps[] = $row['queue_number'];
        echo "Found ready application: {$row['queue_number']} - {$row['applicant_name']}\n";
    }
}

if (empty($ready_apps)) {
    echo "No applications ready for progression.\n";
    exit();
}

// Progress each ready application
require_once 'config/config.php';

foreach ($ready_apps as $queue_number) {
    echo "\n=== Processing $queue_number ===\n";
    
    try {
        $conn->begin_transaction();
        
        // 1. Update application status
        $new_status = STATUS_UNDER_AUTO_REVIEW;
        $update_sql = "UPDATE applications SET current_status = ?, last_updated = NOW() WHERE queue_number = ?";
        $stmt = $conn->prepare($update_sql);
        $stmt->bind_param("ss", $new_status, $queue_number);
        $stmt->execute();
        echo "✅ Status updated to: $new_status\n";
        
        // 2. Add to status history
        $history_sql = "INSERT INTO status_history (queue_number, previous_status, new_status, changed_by, changed_by_type, notes) VALUES (?, ?, ?, ?, 'system', ?)";
        $history_stmt = $conn->prepare($history_sql);
        $previous_status = 'INTENT_RECEIVED';
        $notes = 'Auto-progressed: All required forms (QF01, QF02, Category) completed. Moving to automated review.';
        $system_user_id = 1;
        $history_stmt->bind_param("sssis", $queue_number, $previous_status, $new_status, $system_user_id, $notes);
        $history_stmt->execute();
        echo "✅ Status history updated\n";
        
        // 3. Log the progression
        $log_sql = "INSERT INTO staff_logs (staff_id, queue_number, action_type, action_details) VALUES (?, ?, 'other', ?)";
        $log_stmt = $conn->prepare($log_sql);
        $action_details = 'System auto-progressed application from INTENT_RECEIVED to UNDER_AUTO_REVIEW (forms completed)';
        $system_user_id = 1;
        $log_stmt->bind_param("iss", $system_user_id, $queue_number, $action_details);
        $log_stmt->execute();
        echo "✅ Activity logged\n";
        
        $conn->commit();
        echo "✅ Application $queue_number successfully progressed!\n";
        
    } catch (Exception $e) {
        $conn->rollback();
        echo "❌ Error progressing $queue_number: " . $e->getMessage() . "\n";
    }
}

echo "\n=== Summary ===\n";
echo "Processed " . count($ready_apps) . " applications\n";
echo "These applications will now go through automated AI classification and review.\n";

closeDBConnection($conn);
?>
