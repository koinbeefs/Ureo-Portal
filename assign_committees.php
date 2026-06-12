<?php
/**
 * Assign Committees to Applications Based on Category
 * TAU-UREO Portal
 */

require_once 'config/database.php';

$conn = getDBConnection();

echo "=== Assigning Committees to Applications ===\n";

// Define category to committee mapping
$category_committee_map = [
    'exempt' => 1,    // Human Use Committee handles exempt reviews
    'expedited' => 1, // Human Use Committee handles expedited reviews  
    'full' => 1,      // Human Use Committee handles full reviews
    // Add other mappings as needed:
    // 'animal_welfare' => 2,  // Animal Use Committee
    // 'plant_use' => 3,       // Plant Use Committee
    // 'microbio_use' => 1,    // Human Use Committee (for now)
    // 'engineering' => 1,      // Human Use Committee (for now)
    // 'it_use' => 1,          // Human Use Committee (for now)
    // 'food_tech' => 3,       // Plant Use Committee
];

// Get applications without committee assignment
$sql = "SELECT queue_number, category, current_status, research_title FROM applications WHERE urec_committee_id IS NULL OR urec_committee_id = 0";
$result = $conn->query($sql);

$updated_count = 0;

if ($result->num_rows > 0) {
    while ($app = $result->fetch_assoc()) {
        $queue_number = $app['queue_number'];
        $category = $app['category'];
        $current_status = $app['current_status'];
        
        echo "\nProcessing: $queue_number\n";
        echo "  Category: $category\n";
        echo "  Current Status: $current_status\n";
        
        // Determine committee_id
        $committee_id = $category_committee_map[$category] ?? 1; // Default to Human Use
        
        echo "  Assigned Committee ID: $committee_id\n";
        
        try {
            $conn->begin_transaction();
            
            // Update application with committee assignment
            $update_sql = "UPDATE applications SET urec_committee_id = ?, current_status = ?, last_updated = NOW() WHERE queue_number = ?";
            $stmt = $conn->prepare($update_sql);
            
            require_once 'config/config.php';
            $new_status = STATUS_FORWARDED_TO_UREC;
            $stmt->bind_param("iss", $committee_id, $new_status, $queue_number);
            $stmt->execute();
            
            // Add to status history
            $history_sql = "INSERT INTO status_history (queue_number, previous_status, new_status, changed_by, changed_by_type, notes) VALUES (?, ?, ?, ?, 'system', ?)";
            $history_stmt = $conn->prepare($history_sql);
            $notes = "System auto-assigned to committee ID $committee_id based on category: $category";
            $history_stmt->bind_param("sssis", $queue_number, $current_status, $new_status, 1, $notes);
            $history_stmt->execute();
            
            // Log the assignment
            $log_sql = "INSERT INTO staff_logs (staff_id, queue_number, action_type, action_details) VALUES (?, ?, 'other', ?)";
            $log_stmt = $conn->prepare($log_sql);
            $action_details = "System assigned application to committee ID $committee_id (Category: $category)";
            $log_stmt->bind_param("iss", 1, $queue_number, $action_details);
            $log_stmt->execute();
            
            $conn->commit();
            
            echo "  ✅ Updated successfully!\n";
            $updated_count++;
            
        } catch (Exception $e) {
            $conn->rollback();
            echo "  ❌ Error: " . $e->getMessage() . "\n";
        }
    }
} else {
    echo "No applications need committee assignment.\n";
}

echo "\n=== Summary ===\n";
echo "Applications processed: $updated_count\n";

if ($updated_count > 0) {
    echo "\nThese applications are now visible to their respective committee chairpersons:\n";
    echo "- Human Use Chair (Committee ID: 1) will see applications assigned to committee 1\n";
    echo "- Animal Use Chair (Committee ID: 2) will see applications assigned to committee 2\n";
    echo "- Plant Use Chair (Committee ID: 3) will see applications assigned to committee 3\n";
    echo "\nChairpersons can now assign evaluators and review these applications.\n";
}

closeDBConnection($conn);
?>
