<?php
/**
 * Check Form Completion Status for UREO-0003
 * TAU-UREO Portal
 */

require_once 'config/database.php';

$conn = getDBConnection();

echo "=== Form Completion Status for UREO-0003 ===\n";

// Check if fillable_forms table exists
$table_check = $conn->query("SHOW TABLES LIKE 'fillable_forms'");
if ($table_check->num_rows == 0) {
    echo "❌ fillable_forms table does not exist. Creating it...\n";
    
    // Create the table
    $create_sql = "
    CREATE TABLE fillable_forms (
        form_id INT AUTO_INCREMENT PRIMARY KEY,
        queue_number VARCHAR(20) NOT NULL,
        form_type ENUM('qf01', 'qf02') NOT NULL,
        form_data JSON,
        completed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        file_generated TINYINT(1) DEFAULT 1,
        file_uploaded TINYINT(1) DEFAULT 0,
        FOREIGN KEY (queue_number) REFERENCES applications(queue_number) ON DELETE CASCADE,
        UNIQUE KEY unique_form (queue_number, form_type),
        INDEX idx_queue (queue_number)
    )";
    
    $conn->query($create_sql);
    echo "✅ fillable_forms table created.\n";
}

// Check form completion status
echo "\n=== Current Form Status ===\n";
$forms_sql = "SELECT form_type, form_data, completed_at, file_generated, file_uploaded FROM fillable_forms WHERE queue_number = 'UREO-0003'";
$forms_result = $conn->query($forms_sql);

if ($forms_result->num_rows > 0) {
    while ($form = $forms_result->fetch_assoc()) {
        echo "Form: {$form['form_type']}\n";
        echo "  Completed: {$form['completed_at']}\n";
        echo "  File Generated: " . ($form['file_generated'] ? 'Yes' : 'No') . "\n";
        echo "  File Uploaded: " . ($form['file_uploaded'] ? 'Yes' : 'No') . "\n";
        echo "  Data Size: " . strlen($form['form_data']) . " bytes\n\n";
    }
} else {
    echo "❌ No forms completed yet for UREO-0003\n";
}

// Check category form completion
echo "=== Category Form Status ===\n";
$category_sql = "SELECT form_id, form_data, completed_at FROM category_form_tokens WHERE queue_number = 'UREO-0003'";
$category_result = $conn->query($category_sql);

if ($category_result->num_rows > 0) {
    while ($cat = $category_result->fetch_assoc()) {
        echo "Category Form:\n";
        echo "  Completed: {$cat['completed_at']}\n";
        echo "  Data Size: " . strlen($cat['form_data']) . " bytes\n";
    }
} else {
    echo "❌ Category form not completed yet\n";
}

// Check what's needed for workflow progression
echo "\n=== Workflow Requirements ===\n";
$qf01_done = $conn->query("SELECT COUNT(*) as count FROM fillable_forms WHERE queue_number = 'UREO-0003' AND form_type = 'qf01'")->fetch_assoc()['count'] > 0;
$qf02_done = $conn->query("SELECT COUNT(*) as count FROM fillable_forms WHERE queue_number = 'UREO-0003' AND form_type = 'qf02'")->fetch_assoc()['count'] > 0;
$category_done = $conn->query("SELECT COUNT(*) as count FROM category_form_tokens WHERE queue_number = 'UREO-0003'")->fetch_assoc()['count'] > 0;

echo "QF01 Form: " . ($qf01_done ? '✅ Completed' : '❌ Missing') . "\n";
echo "QF02 Form: " . ($qf02_done ? '✅ Completed' : '❌ Missing') . "\n";
echo "Category Form: " . ($category_done ? '✅ Completed' : '❌ Missing') . "\n";

if ($qf01_done && $qf02_done && $category_done) {
    echo "\n✅ All forms completed! Application should progress.\n";
} else {
    echo "\n❌ Forms missing. Application cannot progress until all are completed.\n";
}

closeDBConnection($conn);
?>
