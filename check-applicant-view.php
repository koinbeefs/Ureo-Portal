<?php
/**
 * Check Applicant View of Category Form
 * TAU-UREO Portal
 */

require_once 'config/config.php';
require_once 'includes/functions.php';

$queue_number = 'UREO-0001';

$conn = getDBConnection();

// Get category form data (what applicant would see)
$form_stmt = $conn->prepare("SELECT form_data FROM fillable_forms WHERE queue_number = ? AND form_type = 'category_form'");
$form_stmt->bind_param("s", $queue_number);
$form_stmt->execute();
$category_form = $form_stmt->get_result()->fetch_assoc();

echo "<h2>Applicant View - Category Form Data</h2>";

if ($category_form) {
    $form_data = json_decode($category_form['form_data'], true);
    
    echo "<h3>What Applicant Can Access:</h3>";
    echo "<ul>";
    foreach ($form_data as $key => $value) {
        if ($key === 'annotated_qf02_path') {
            echo "<li style='color: red;'><strong>$key</strong>: $value ⚠️ SECURITY RISK!</li>";
        } else {
            echo "<li style='color: green;'><strong>$key</strong>: $value ✅</li>";
        }
    }
    echo "</ul>";
    
    if (isset($form_data['annotated_qf02_path'])) {
        echo "<h3 style='color: red;'>🚨 SECURITY ISSUE: Applicant can access annotated PDF!</h3>";
        echo "<p>Annotated PDF path: " . htmlspecialchars($form_data['annotated_qf02_path']) . "</p>";
    } else {
        echo "<h3 style='color: green;'>✅ SECURE: Applicant cannot access annotated PDF</h3>";
    }
} else {
    echo "<p>No category form found.</p>";
}

closeDBConnection($conn);
?>
