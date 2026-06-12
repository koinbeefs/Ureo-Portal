<?php
require_once 'config/config.php';

try {
    $conn = getDBConnection();
    
    // Direct SQL to fix the ENUM
    $sql = "ALTER TABLE fillable_forms MODIFY COLUMN form_type ENUM('qf01', 'qf02', 'category_form') NOT NULL";
    $result = $conn->query($sql);
    
    if ($result) {
        echo "SUCCESS: ENUM updated";
    } else {
        echo "ERROR: " . $conn->error;
    }
    
    closeDBConnection($conn);
} catch (Exception $e) {
    echo "EXCEPTION: " . $e->getMessage();
}
?>
