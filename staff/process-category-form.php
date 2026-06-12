<?php
/**
 * Process Category Form Submission
 * TAU-UREO Portal
 */

require_once '../config/config.php';
require_once '../includes/functions.php';
require_once '../includes/email-template-functions.php';
require_once '../includes/category-form-pdf-generator.php';

requireLogin();
checkSessionTimeout();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: dashboard.php");
    exit();
}

$queue_number = $_POST['queue_number'] ?? '';

if (empty($queue_number)) {
    echo json_encode(['success' => false, 'message' => 'Invalid request parameters.']);
    exit();
}

$conn = getDBConnection();

// Get application details
$app_stmt = $conn->prepare("SELECT * FROM applications WHERE queue_number = ?");
$app_stmt->bind_param("s", $queue_number);
$app_stmt->execute();
$application = $app_stmt->get_result()->fetch_assoc();

if (!$application) {
    echo json_encode(['success' => false, 'message' => 'Application not found.']);
    exit();
}

// Check if user can edit this application
$can_edit = ($application['assigned_staff_id'] == $_SESSION['user_id'] || !$application['assigned_staff_id']);
if (!$can_edit) {
    echo json_encode(['success' => false, 'message' => 'You do not have permission to edit this application.']);
    exit();
}

// Get category form data
$form_stmt = $conn->prepare("SELECT form_data FROM fillable_forms WHERE queue_number = ? AND form_type = 'category_form'");
$form_stmt->bind_param("s", $queue_number);
$form_stmt->execute();
$category_form_result = $form_stmt->get_result()->fetch_assoc();

if (!$category_form_result) {
    echo json_encode(['success' => false, 'message' => 'Category form data not found.']);
    exit();
}

$category_form_data = json_decode($category_form_result['form_data'], true);
$category = $category_form_data['category'] ?? 'human';
$review_type = $category_form_data['review_type'] ?? 'expedited';

try {
    // Start transaction with shorter timeout
    $conn->begin_transaction();
    
    // Set shorter lock timeout to prevent deadlocks
    $conn->query("SET SESSION innodb_lock_wait_timeout = 10");

    // Update category form with submitted data
    $submitted_data = $_POST;
    unset($submitted_data['queue_number']); // Remove queue_number from form data
    
    $updated_form_data = array_merge($category_form_data, [
        'submitted_data' => $submitted_data,
        'submitted_at' => date('Y-m-d H:i:s'),
        'submitted_by' => $_SESSION['user_id']
    ]);

    $update_stmt = $conn->prepare("UPDATE fillable_forms SET form_data = ? WHERE queue_number = ? AND form_type = 'category_form'");
    $update_stmt->bind_param("ss", json_encode($updated_form_data), $queue_number);
    $update_stmt->execute();

    // Generate category form PDF using the new generator
    $category_pdf_path = generateCategoryFormPDF($queue_number, $category, $updated_form_data);
    
    if (!$category_pdf_path) {
        throw new Exception("Failed to generate category form PDF");
    }

    // Generate combined PDF (QF02 with remarks + Category Form)
    $combined_pdf_path = generateCombinedReviewPDF($queue_number, $category, $updated_form_data);
    
    if (!$combined_pdf_path) {
        throw new Exception("Failed to generate combined review PDF");
    }

    // Update application status
    $new_status = 'CATEGORY_FORMS_COMPLETED';
    $app_update_stmt = $conn->prepare("UPDATE applications SET current_status = ?, last_updated = NOW() WHERE queue_number = ?");
    $app_update_stmt->bind_param("ss", $new_status, $queue_number);
    $app_update_stmt->execute();

    // Store PDF paths in category form data
    $updated_form_data['category_pdf_path'] = $category_pdf_path;
    $updated_form_data['combined_pdf_path'] = $combined_pdf_path;
    
    $final_update_stmt = $conn->prepare("UPDATE fillable_forms SET form_data = ? WHERE queue_number = ? AND form_type = 'category_form'");
    $final_update_stmt->bind_param("ss", json_encode($updated_form_data), $queue_number);
    $final_update_stmt->execute();

    // Update category form token record to mark as completed
    $token_update_stmt = $conn->prepare("UPDATE fillable_forms SET completed_at = NOW() WHERE queue_number = ? AND form_type = 'category_token'");
    $token_update_stmt->bind_param("s", $queue_number);
    $token_update_stmt->execute();

    // Log activity
    logStaffActivity($_SESSION['user_id'], $queue_number, 'other', "Category form completed for " . ucfirst($category));

    // Add status history
    $history_stmt = $conn->prepare("INSERT INTO status_history (queue_number, previous_status, new_status, changed_by_type, changed_by, notes) VALUES (?, ?, 'staff', ?, ?)");
    $notes = ucfirst($category) . " category form completed for " . ucfirst($review_type) . " review";
    $history_stmt->bind_param("ssis", $queue_number, $application['current_status'], $_SESSION['user_id'], $notes);
    $history_stmt->execute();

    // Send notification email to applicant
    $template_code = 'CATEGORY_FORMS_COMPLETED';
    $subject = "Category Forms Completed - " . ucfirst($category);
    $body = getEmailTemplate($template_code);

    if ($body) {
        $body = str_replace('{{applicant_name}}', $application['applicant_name'], $body);
        $body = str_replace('{{queue_number}}', $queue_number, $body);
        $body = str_replace('{{category}}', ucfirst($category), $body);
        $body = str_replace('{{review_type}}', ucfirst($review_type), $body);
        sendEmail($application['applicant_email'], $subject, $body, $queue_number, 'category_forms_completed');
    }

    // Create system message
    $msg_stmt = $conn->prepare("INSERT INTO system_messages (queue_number, message_type, subject, message_body) VALUES (?, 'update', ?, ?)");
    $subject = "Category Forms Completed - " . $queue_number;
    $message_body = "Application " . $queue_number . " has completed " . ucfirst($category) . " category forms for " . ucfirst($review_type) . " review.\n\nApplicant: " . $application['applicant_name'] . "\nCategory: " . ucfirst($category) . "\nReview Type: " . ucfirst($review_type) . "\n\nPDFs Generated:\n- Category Form: " . $category_pdf_path . "\n- Combined Review: " . $combined_pdf_path;
    $msg_stmt->bind_param("sss", $queue_number, $subject, $message_body);
    $msg_stmt->execute();

    // Commit transaction
    $conn->commit();

    closeDBConnection($conn);

    echo json_encode([
        'success' => true,
        'message' => 'Category forms completed successfully.',
        'category' => $category,
        'review_type' => $review_type,
        'category_pdf_path' => $category_pdf_path,
        'combined_pdf_path' => $combined_pdf_path
    ]);

} catch (Exception $e) {
    // Rollback transaction on error
    if (isset($conn) && $conn->ping()) {
        $conn->rollback();
    }
    
    if (isset($conn)) {
        closeDBConnection($conn);
    }
    
    // Provide more specific error messages
    $error_message = $e->getMessage();
    if (strpos($error_message, 'lock wait timeout') !== false) {
        $error_message = "System is busy, please try again. The category form process timed out due to high system load.";
    } elseif (strpos($error_message, 'deadlock') !== false) {
        $error_message = "System conflict detected, please try again. Another process was accessing the same application.";
    }
    
    echo json_encode(['success' => false, 'message' => 'Error processing category forms: ' . $error_message]);
}

exit();
?>
