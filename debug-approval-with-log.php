<?php
/**
 * Debug Approval Process with Full Logging
 * TAU-UREO Portal
 */

// Enable all error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Create log file
$log_file = 'approval_debug_' . date('Y-m-d_H-i-s') . '.log';
$log_path = __DIR__ . '/' . $log_file;

function log_message($message) {
    global $log_path;
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[$timestamp] $message\n";
    file_put_contents($log_path, $log_entry, FILE_APPEND);
    echo $log_entry . "<br>";
}

// Start logging
log_message("=== APPROVAL PROCESS DEBUG START ===");

// Simulate the approval process
$_POST['queue_number'] = 'UREO-0001';
$_POST['review_type'] = 'expedited';
$_SESSION['user_id'] = 1;

log_message("POST Data: " . json_encode($_POST));
log_message("Session Data: user_id = " . $_SESSION['user_id']);

require_once 'config/config.php';
require_once 'includes/functions.php';
require_once 'includes/email-template-functions.php';

try {
    $queue_number = $_POST['queue_number'] ?? '';
    $review_type = $_POST['review_type'] ?? '';
    
    log_message("Queue Number: $queue_number");
    log_message("Review Type: $review_type");
    
    // Validate review type
    $valid_review_types = ['exempt', 'expedited', 'full'];
    if (!in_array($review_type, $valid_review_types)) {
        log_message("ERROR: Invalid review type");
        exit();
    }
    
    log_message("Review type validation: PASSED");
    
    $conn = getDBConnection();
    log_message("Database connection: ESTABLISHED");
    
    // Get application details
    $app_stmt = $conn->prepare("SELECT * FROM applications WHERE queue_number = ?");
    $app_stmt->bind_param("s", $queue_number);
    $app_stmt->execute();
    $application = $app_stmt->get_result()->fetch_assoc();
    
    if (!$application) {
        log_message("ERROR: Application not found");
        exit();
    }
    
    log_message("Application found: " . json_encode([
        'queue_number' => $application['queue_number'],
        'current_status' => $application['current_status'],
        'applicant_name' => $application['applicant_name']
    ]));
    
    // Check if user can edit
    $can_edit = ($application['assigned_staff_id'] == $_SESSION['user_id'] || !$application['assigned_staff_id']);
    log_message("Can edit: " . ($can_edit ? 'YES' : 'NO'));
    
    // Check valid status
    $valid_statuses = ['REGISTERED', 'UNDER_AUTO_REVIEW', 'STAFF_REVIEW_REQUIRED', 'UNDER_STAFF_REVIEW', 'CATEGORIZED'];
    $is_valid_status = in_array($application['current_status'], $valid_statuses);
    log_message("Valid status: " . ($is_valid_status ? 'YES' : 'NO') . " (Current: " . $application['current_status'] . ")");
    
    // Determine new status
    if ($review_type === 'exempt') {
        $new_status = 'UREC_REVIEW_REQUIRED';
    } else {
        $new_status = 'CATEGORY_FORMS_REQUIRED';
    }
    log_message("New status will be: $new_status");
    
    // Update application
    $update_stmt = $conn->prepare("UPDATE applications SET current_status = ?, category = ?, last_updated = NOW() WHERE queue_number = ?");
    $update_stmt->bind_param("sss", $new_status, $review_type, $queue_number);
    $update_result = $update_stmt->execute();
    log_message("Application update: " . ($update_result ? 'SUCCESS' : 'FAILED'));
    
    // Log approval activity
    $log_result = logStaffActivity($_SESSION['user_id'], $queue_number, 'approved', "Application approved with $review_type review");
    log_message("Staff activity log: " . ($log_result ? 'SUCCESS' : 'FAILED'));
    
    // Add status history entry
    $history_stmt = $conn->prepare("INSERT INTO status_history (queue_number, previous_status, new_status, changed_by, changed_by_type, notes, timestamp) VALUES (?, ?, ?, ?, ?, ?, NOW())");
    $notes = "Application approved for " . ucfirst($review_type) . " Review";
    $history_stmt->bind_param("ssssis", $queue_number, $application['current_status'], $new_status, $_SESSION['user_id'], 'staff', $notes);
    $history_result = $history_stmt->execute();
    log_message("Status history insert: " . ($history_result ? 'SUCCESS' : 'FAILED'));
    
    if ($review_type !== 'exempt') {
        log_message("=== CATEGORY FORMS WORKFLOW START ===");
        
        // Get QF02 form data
        $form_stmt = $conn->prepare("SELECT form_data FROM fillable_forms WHERE queue_number = ? AND form_type = 'qf02'");
        $form_stmt->bind_param("s", $queue_number);
        $form_stmt->execute();
        $qf02_data = $form_stmt->get_result()->fetch_assoc();
        
        if (!$qf02_data) {
            log_message("ERROR: QF02 form data not found");
        } else {
            log_message("QF02 form data: FOUND");
            
            // Determine category
            $ai_classification_path = 'uploads/' . $queue_number . '/ai_classification.json';
            if (file_exists($ai_classification_path)) {
                $ai_data = json_decode(file_get_contents($ai_classification_path), true);
                $category = $ai_data['staff_feedback']['final_category'] ?? $ai_data['ai_prediction']['predicted'] ?? 'human';
                log_message("Category determined: $category");
            } else {
                $category = 'human';
                log_message("Category: DEFAULT (human)");
            }
            
            // Create category form record
            $category_form_data = [
                'category' => $category,
                'review_type' => $review_type,
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            log_message("Category form data: " . json_encode($category_form_data));
            
            $insert_stmt = $conn->prepare("INSERT INTO fillable_forms (queue_number, form_type, form_data) VALUES (?, 'category_form', ?)");
            $insert_stmt->bind_param("ss", $queue_number, json_encode($category_form_data));
            $insert_result = $insert_stmt->execute();
            log_message("Category form insert: " . ($insert_result ? 'SUCCESS' : 'FAILED'));
            
            // Create system message
            $msg_stmt = $conn->prepare("INSERT INTO system_messages (queue_number, message_type, subject, message_body) VALUES (?, 'requirement', ?, ?)");
            $subject = "Category Forms Required - " . $queue_number;
            $message_body = "Application " . $queue_number . " requires " . ucfirst($category) . " category forms for " . ucfirst($review_type) . " review.";
            $msg_stmt->bind_param("sss", $queue_number, $subject, $message_body);
            $msg_result = $msg_stmt->execute();
            log_message("System message: " . ($msg_result ? 'SUCCESS' : 'FAILED'));
        }
        
        log_message("=== CATEGORY FORMS WORKFLOW END ===");
    }
    
    closeDBConnection($conn);
    log_message("Database connection: CLOSED");
    
    log_message("=== APPROVAL PROCESS DEBUG END ===");
    
    echo "<h3>✅ Debug completed successfully!</h3>";
    echo "<p>Log file created: <strong>$log_file</strong></p>";
    
} catch (Exception $e) {
    log_message("FATAL ERROR: " . $e->getMessage());
    log_message("Error trace: " . $e->getTraceAsString());
    echo "<h3>❌ Error occurred: " . $e->getMessage() . "</h3>";
}

?>
