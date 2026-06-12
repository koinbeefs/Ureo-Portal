<?php
/**
 * Clean Annotated PDF from Category Form
 * TAU-UREO Portal
 */

require_once 'config/config.php';
require_once 'includes/functions.php';

$queue_number = 'UREO-0001';

$conn = getDBConnection();

// Get current category form data
$form_stmt = $conn->prepare("SELECT form_data FROM fillable_forms WHERE queue_number = ? AND form_type = 'category_form'");
$form_stmt->bind_param("s", $queue_number);
$form_stmt->execute();
$category_form = $form_stmt->get_result()->fetch_assoc();

if ($category_form) {
    $form_data = json_decode($category_form['form_data'], true);
    
    echo "<h2>Current Category Form Data</h2>";
    echo "<pre>" . print_r($form_data, true) . "</pre>";
    
    // Remove annotated_qf02_path if it exists
    if (isset($form_data['annotated_qf02_path'])) {
        unset($form_data['annotated_qf02_path']);
        
        // Update the category form without the annotated PDF path
        $clean_form_data = json_encode($form_data);
        $update_stmt = $conn->prepare("UPDATE fillable_forms SET form_data = ? WHERE queue_number = ? AND form_type = 'category_form'");
        $update_stmt->bind_param("ss", $clean_form_data, $queue_number);
        
        if ($update_stmt->execute()) {
            echo "<h3 style='color: green;'>✅ Annotated PDF path removed from category form</h3>";
            echo "<p>Applicants can no longer access the annotated PDF.</p>";
        } else {
            echo "<h3 style='color: red;'>❌ Error updating category form</h3>";
        }
        
        echo "<h3>Clean Category Form Data</h3>";
        echo "<pre>" . print_r($form_data, true) . "</pre>";
    } else {
        echo "<h3 style='color: green;'>✅ No annotated PDF path found in category form</h3>";
    }
} else {
    echo "<h3 style='color: orange;'>⚠️ No category form found</h3>";
}

closeDBConnection($conn);
?>
