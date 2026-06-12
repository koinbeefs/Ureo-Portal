<?php
/**
 * Fix Plant Use Committee Assignment Based on AI Classification JSON
 * TAU-UREO Portal
 */

require_once 'config/database.php';

$conn = getDBConnection();

echo "=== Reading AI Classification from JSON ===\n";

$queue_number = 'UREO-0003';
$json_file = __DIR__ . "/uploads/{$queue_number}/ai_classification.json";

if (!file_exists($json_file)) {
    echo "❌ AI classification file not found: $json_file\n";
    exit();
}

// Read and parse JSON
$json_content = file_get_contents($json_file);
$classification = json_decode($json_content, true);

if (!$classification) {
    echo "❌ Failed to parse AI classification JSON\n";
    exit();
}

echo "AI Classification Results:\n";
echo "  AI Predicted: " . $classification['ai_prediction']['predicted'] . "\n";
echo "  Staff Final Category: " . $classification['staff_feedback']['final_category'] . "\n";
echo "  Staff Reviewed: " . ($classification['staff_reviewed'] ? 'Yes' : 'No') . "\n";
echo "  Reviewed At: " . $classification['staff_feedback']['reviewed_at'] . "\n";

// Use staff's final category if available, otherwise use AI prediction
$final_category = $classification['staff_feedback']['final_category'] ?? $classification['ai_prediction']['predicted'];

echo "\nFinal Category for Assignment: $final_category\n";

// Map categories to committee IDs
$committee_mapping = [
    'Human Use' => 1,
    'Animal Welfare' => 2,
    'Plant Use' => 3,
    'Microbiological/Biotechnological Use' => 1,
    'Engineering' => 1,
    'Information Technology Use' => 1,
    'Food Technology Use' => 3
];

$committee_id = $committee_mapping[$final_category] ?? 1; // default to Human Use

echo "Assigned Committee ID: $committee_id\n";

// Get current application status
$app_sql = "SELECT current_status, urec_committee_id, category FROM applications WHERE queue_number = ?";
$app_stmt = $conn->prepare($app_sql);
$app_stmt->bind_param("s", $queue_number);
$app_stmt->execute();
$app_result = $app_stmt->get_result();
$app = $app_result->fetch_assoc();

if (!$app) {
    echo "❌ Application $queue_number not found\n";
    exit();
}

echo "\nCurrent Application Status:\n";
echo "  Current Status: {$app['current_status']}\n";
echo "  Current Committee ID: {$app['urec_committee_id']}\n";
echo "  Category: {$app['category']}\n";

// Update application if needed
if ($app['urec_committee_id'] != $committee_id || $app['current_status'] !== 'FORWARDED_TO_UREC') {
    echo "\nUpdating application...\n";
    
    try {
        $conn->begin_transaction();
        
        require_once 'config/config.php';
        $new_status = STATUS_FORWARDED_TO_UREC;
        
        // Update application
        $update_sql = "UPDATE applications SET urec_committee_id = ?, current_status = ?, last_updated = NOW() WHERE queue_number = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("iss", $committee_id, $new_status, $queue_number);
        $update_stmt->execute();
        
        // Add to status history
        $history_sql = "INSERT INTO status_history (queue_number, previous_status, new_status, changed_by, changed_by_type, notes) VALUES (?, ?, ?, ?, 'system', ?)";
        $history_stmt = $conn->prepare($history_sql);
        $previous_status = $app['current_status'];
        $notes = "System assigned to committee based on AI classification: $final_category (Committee ID: $committee_id) - Staff reviewed and confirmed";
        $system_user_id = 1;
        $history_stmt->bind_param("sssis", $queue_number, $previous_status, $new_status, $system_user_id, $notes);
        $history_stmt->execute();
        
        // Log the assignment
        $log_sql = "INSERT INTO staff_logs (staff_id, queue_number, action_type, action_details) VALUES (?, ?, 'other', ?)";
        $log_stmt = $conn->prepare($log_sql);
        $action_details = "AI-based committee assignment: $final_category → Committee ID $committee_id (Staff confirmed)";
        $system_user_id = 1;
        $log_stmt->bind_param("iss", $system_user_id, $queue_number, $action_details);
        $log_stmt->execute();
        
        $conn->commit();
        
        echo "✅ Successfully updated!\n";
        echo "  New Committee ID: $committee_id\n";
        echo "  New Status: $new_status\n";
        
        if ($committee_id == 3) {
            echo "\n🌱 SUCCESS! UREO-0003 is now assigned to Plant Use Committee!\n";
            echo "The Plant Use Chairperson (plant_use_chair) should see this application in their dashboard.\n";
        }
        
    } catch (Exception $e) {
        $conn->rollback();
        echo "❌ Error: " . $e->getMessage() . "\n";
    }
} else {
    echo "\n✅ Application already correctly assigned.\n";
}

closeDBConnection($conn);
?>
