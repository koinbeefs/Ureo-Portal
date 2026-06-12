<?php
/**
 * Check AI Classification for Committee Assignment
 * TAU-UREO Portal
 */

require_once 'config/database.php';

$conn = getDBConnection();

echo "=== AI Classification Data ===\n";

// Check if ai_classifications table exists and get data
$table_check = $conn->query("SHOW TABLES LIKE 'ai_classifications'");
if ($table_check->num_rows == 0) {
    echo "❌ ai_classifications table does not exist.\n";
    exit();
}

// Get AI classification for UREO-0003
$sql = "SELECT * FROM ai_classifications WHERE queue_number = 'UREO-0003'";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    $classification = $result->fetch_assoc();
    
    echo "AI Classification for UREO-0003:\n";
    echo "  Predicted Categories: " . $classification['predicted_categories'] . "\n";
    echo "  Predicted Primary: " . $classification['predicted_primary'] . "\n";
    echo "  Confidence Level: " . $classification['confidence_level'] . "\n";
    echo "  Max Score: " . $classification['max_score'] . "\n";
    echo "  Reasoning: " . substr($classification['reasoning'], 0, 200) . "...\n";
    echo "  Staff Verified: " . ($classification['staff_verified'] ? 'Yes' : 'No') . "\n";
    
    // Parse predicted categories
    $categories = json_decode($classification['predicted_categories'], true);
    if ($categories && is_array($categories)) {
        echo "\n  Detected Categories:\n";
        foreach ($categories as $category => $score) {
            echo "    - $category: $score\n";
        }
    }
    
    // Determine committee based on AI classification
    $predicted_primary = $classification['predicted_primary'];
    $committee_id = 1; // default to Human Use
    
    $committee_mapping = [
        'Human Use' => 1,
        'Animal Welfare' => 2, 
        'Plant Use' => 3,
        'Microbiological/Biotechnological Use' => 1,
        'Engineering' => 1,
        'Information Technology Use' => 1,
        'Food Technology Use' => 3
    ];
    
    if (isset($committee_mapping[$predicted_primary])) {
        $committee_id = $committee_mapping[$predicted_primary];
    }
    
    echo "\nRecommended Committee Assignment:\n";
    echo "  Primary Category: $predicted_primary\n";
    echo "  Committee ID: $committee_id\n";
    
    // Now update the application
    echo "\nUpdating application committee assignment...\n";
    
    try {
        $conn->begin_transaction();
        
        // Get current application data
        $app_sql = "SELECT current_status, urec_committee_id FROM applications WHERE queue_number = 'UREO-0003'";
        $app_result = $conn->query($app_sql);
        $app = $app_result->fetch_assoc();
        
        // Update application with correct committee based on AI classification
        $update_sql = "UPDATE applications SET urec_committee_id = ?, current_status = ?, last_updated = NOW() WHERE queue_number = 'UREO-0003'";
        $stmt = $conn->prepare($update_sql);
        
        require_once 'config/config.php';
        $new_status = STATUS_FORWARDED_TO_UREC;
        $stmt->bind_param("is", $committee_id, $new_status);
        $stmt->execute();
        
        // Add to status history
        $history_sql = "INSERT INTO status_history (queue_number, previous_status, new_status, changed_by, changed_by_type, notes) VALUES (?, ?, ?, ?, 'system', ?)";
        $history_stmt = $conn->prepare($history_sql);
        $previous_status = $app['current_status'];
        $notes = "System assigned to committee based on AI classification: $predicted_primary (Committee ID: $committee_id)";
        $history_stmt->bind_param("sssis", 'UREO-0003', $previous_status, $new_status, 1, $notes);
        $history_stmt->execute();
        
        // Log the assignment
        $log_sql = "INSERT INTO staff_logs (staff_id, queue_number, action_type, action_details) VALUES (?, ?, 'other', ?)";
        $log_stmt = $conn->prepare($log_sql);
        $action_details = "AI-based committee assignment: $predicted_primary → Committee ID $committee_id";
        $log_stmt->bind_param("iss", 1, 'UREO-0003', $action_details);
        $log_stmt->execute();
        
        $conn->commit();
        
        echo "✅ Successfully updated committee assignment!\n";
        echo "  UREO-0003 is now assigned to Committee ID: $committee_id ($predicted_primary)\n";
        echo "  Status updated to: $new_status\n";
        
        if ($committee_id == 3) {
            echo "\n🌱 The Plant Use Chairperson should now see this application!\n";
        }
        
    } catch (Exception $e) {
        $conn->rollback();
        echo "❌ Error: " . $e->getMessage() . "\n";
    }
    
} else {
    echo "❌ No AI classification found for UREO-0003\n";
    echo "The application may not have gone through AI classification yet.\n";
}

closeDBConnection($conn);
?>
