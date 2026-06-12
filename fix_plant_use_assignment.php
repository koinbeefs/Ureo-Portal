<?php
/**
 * Fix Plant Use Assignment for UREO-0003
 * TAU-UREO Portal
 */

require_once 'config/database.php';

$conn = getDBConnection();

echo "=== Fixing Plant Use Assignment ===\n";

// Get UREO-0003 details
$sql = "SELECT queue_number, category, research_title, current_status, urec_committee_id FROM applications WHERE queue_number = 'UREO-0003'";
$result = $conn->query($sql);
$app = $result->fetch_assoc();

if (!$app) {
    echo "Application UREO-0003 not found.\n";
    exit();
}

echo "Current Status:\n";
echo "  Queue: {$app['queue_number']}\n";
echo "  Title: {$app['research_title']}\n";
echo "  Category: {$app['category']}\n";
echo "  Current Status: {$app['current_status']}\n";
echo "  UREC Committee ID: {$app['urec_committee_id']}\n";

// The title suggests this is about plant varieties, so it should be plant_use category
echo "\nThis appears to be a Plant Use research study based on the title.\n";
echo "Updating category to 'plant_use' and assigning to Plant Use Committee (ID: 3)...\n";

try {
    $conn->begin_transaction();
    
    // Update category and committee assignment
    $update_sql = "UPDATE applications SET category = 'plant_use', urec_committee_id = 3, current_status = ?, last_updated = NOW() WHERE queue_number = 'UREO-0003'";
    $stmt = $conn->prepare($update_sql);
    
    require_once 'config/config.php';
    $new_status = STATUS_FORWARDED_TO_UREC;
    $stmt->bind_param("s", $new_status);
    $stmt->execute();
    
    // Add to status history
    $history_sql = "INSERT INTO status_history (queue_number, previous_status, new_status, changed_by, changed_by_type, notes) VALUES (?, ?, ?, ?, 'system', ?)";
    $history_stmt = $conn->prepare($history_sql);
    $previous_status = $app['current_status'];
    $notes = "System corrected category from '{$app['category']}' to 'plant_use' and assigned to Plant Use Committee based on research content";
    $history_stmt->bind_param("sssis", 'UREO-0003', $previous_status, $new_status, 1, $notes);
    $history_stmt->execute();
    
    // Log the change
    $log_sql = "INSERT INTO staff_logs (staff_id, queue_number, action_type, action_details) VALUES (?, ?, 'other', ?)";
    $log_stmt = $conn->prepare($log_sql);
    $action_details = "System corrected category to 'plant_use' and assigned to Plant Use Committee (ID: 3)";
    $log_stmt->bind_param("iss", 1, 'UREO-0003', $action_details);
    $log_stmt->execute();
    
    $conn->commit();
    
    echo "\n✅ Successfully updated UREO-0003!\n";
    echo "  New Category: plant_use\n";
    echo "  New UREC Committee ID: 3 (Plant Use Committee)\n";
    echo "  New Status: $new_status\n";
    
    echo "\nThe Plant Use Chairperson (plant_use_chair) should now see this application in their dashboard.\n";
    
} catch (Exception $e) {
    $conn->rollback();
    echo "\n❌ Error: " . $e->getMessage() . "\n";
}

closeDBConnection($conn);
?>
