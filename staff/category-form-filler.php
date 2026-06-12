<?php
/**
 * Category Form Filler Interface
 * TAU-UREO Portal
 */

require_once '../config/config.php';
require_once '../includes/functions.php';

requireLogin();
checkSessionTimeout();

$queue_number = $_GET['queue'] ?? '';

if (empty($queue_number)) {
    echo '<div class="alert alert-danger">Invalid request.</div>';
    exit();
}

$conn = getDBConnection();

// Get application details and category form data
$app_stmt = $conn->prepare("
    SELECT a.*, cf.form_data as category_form_data 
    FROM applications a 
    LEFT JOIN fillable_forms cf ON a.queue_number = cf.queue_number AND cf.form_type = 'category_form'
    WHERE a.queue_number = ?
");
$app_stmt->bind_param("s", $queue_number);
$app_stmt->execute();
$result = $app_stmt->get_result();

if ($result->num_rows === 0) {
    echo '<div class="alert alert-danger">Application not found or category forms not required.</div>';
    exit();
}

$application = $result->fetch_assoc();
$category_form_data = json_decode($application['category_form_data'], true) ?? [];

if (empty($category_form_data)) {
    echo '<div class="alert alert-danger">Category form data not found.</div>';
    exit();
}

$category = $category_form_data['category'] ?? 'human';
$review_type = $category_form_data['review_type'] ?? 'expedited';

// Check if user can edit this application
$can_edit = ($application['assigned_staff_id'] == $_SESSION['user_id'] || !$application['assigned_staff_id']);

closeDBConnection($conn);

// Get category-specific form template
$form_template_path = "../assets/to_send/for_reply_to_categories/for_reply_to_" . $category . "/TAU-REO-" . strtoupper($category) . "-checklist.php";

if (!file_exists($form_template_path)) {
    echo '<div class="alert alert-danger">Category form template not found for ' . ucfirst($category) . '.</div>';
    exit();
}

// Include the category form template
include $form_template_path;
?>
