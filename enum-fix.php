<?php
require_once 'config/config.php';

echo "Starting ENUM fix...\n";

try {
    $conn = getDBConnection();
    
    // Check current structure
    $result = $conn->query("DESCRIBE fillable_forms");
    while ($row = $result->fetch_assoc()) {
        if ($row['Field'] === 'form_type') {
            echo "Current form_type: " . $row['Type'] . "\n";
            break;
        }
    }
    
    // Try to add category_form
    echo "Attempting to add category_form...\n";
    $conn->query("ALTER TABLE fillable_forms MODIFY COLUMN form_type ENUM('qf01', 'qf02', 'category_form') NOT NULL");
    echo "ENUM update completed\n";
    
    // Verify
    $result = $conn->query("DESCRIBE fillable_forms");
    while ($row = $result->fetch_assoc()) {
        if ($row['Field'] === 'form_type') {
            echo "New form_type: " . $row['Type'] . "\n";
            break;
        }
    }
    
    closeDBConnection($conn);
    echo "SUCCESS!\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
?>
