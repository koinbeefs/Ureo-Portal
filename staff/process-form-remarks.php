<?php
/**
 * Process Form Remarks Submission
 * TAU-UREO Portal
 * Handles the "Finalize Marks" functionality from review-form.php
 */

require_once '../config/config.php';
require_once '../includes/functions.php';
require_once '../includes/email-template-functions.php';

requireLogin();
checkSessionTimeout();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: dashboard.php");
    exit();
}

$queue_number = $_POST['queue_number'] ?? '';
$form_type = $_POST['form_type'] ?? '';
$action = $_POST['action'] ?? '';

if (empty($queue_number) || $action !== 'save_remarks') {
    echo json_encode(['success' => false, 'message' => 'Invalid request parameters.']);
    exit();
}

$conn = getDBConnection();

try {
    // Start transaction
    $conn->begin_transaction();
    
    // Get application details
    $app_stmt = $conn->prepare("SELECT * FROM applications WHERE queue_number = ?");
    $app_stmt->bind_param("s", $queue_number);
    $app_stmt->execute();
    $application = $app_stmt->get_result()->fetch_assoc();

    if (!$application) {
        throw new Exception('Application not found.');
    }

    // Check if user can edit this application
    $can_edit = ($application['assigned_staff_id'] == $_SESSION['user_id'] || !$application['assigned_staff_id']);
    if (!$can_edit) {
        throw new Exception('You do not have permission to edit this application.');
    }

    // Get current form data
    $form_stmt = $conn->prepare("SELECT form_data FROM fillable_forms WHERE queue_number = ? AND form_type = ?");
    $form_stmt->bind_param("ss", $queue_number, $form_type);
    $form_stmt->execute();
    $form_result = $form_stmt->get_result()->fetch_assoc();

    if (!$form_result) {
        throw new Exception('Form data not found.');
    }

    $form_data = json_decode($form_result['form_data'], true) ?? [];

    // Update remarks in form data
    $criteria = range(1, 20);
    $remarks_updated = false;
    
    foreach ($criteria as $num) {
        $remark_key = "crit_{$num}_remarks";
        if (isset($_POST[$remark_key])) {
            $new_remark = trim($_POST[$remark_key]);
            
            // Initialize remarks array if not exists
            if (!isset($form_data[$remark_key])) {
                $form_data[$remark_key] = [];
            }
            
            // Add new remark with timestamp and user info
            if (!empty($new_remark)) {
                $form_data[$remark_key][] = [
                    'role' => 'staff',
                    'text' => $new_remark,
                    'user_id' => $_SESSION['user_id'],
                    'timestamp' => date('Y-m-d H:i:s')
                ];
                $remarks_updated = true;
            }
        }
    }

    // Handle general remark if provided
    if (isset($_POST['general_remark']) && !empty(trim($_POST['general_remark']))) {
        if (!isset($form_data['general_remarks'])) {
            $form_data['general_remarks'] = [];
        }
        
        $form_data['general_remarks'][] = [
            'role' => 'staff',
            'text' => trim($_POST['general_remark']),
            'user_id' => $_SESSION['user_id'],
            'timestamp' => date('Y-m-d H:i:s')
        ];
        $remarks_updated = true;
    }

    if ($remarks_updated) {
        // Add metadata about the update
        $form_data['remarks_finalized'] = [
            'finalized_by' => $_SESSION['user_id'],
            'finalized_at' => date('Y-m-d H:i:s'),
            'finalized_by_name' => $_SESSION['full_name'] ?? 'Staff Member'
        ];

        // Save updated form data
        $updated_json = json_encode($form_data);
        $update_stmt = $conn->prepare("UPDATE fillable_forms SET form_data = ? WHERE queue_number = ? AND form_type = ?");
        $update_stmt->bind_param("sss", $updated_json, $queue_number, $form_type);
        $update_stmt->execute();

        // Update application status to indicate remarks have been finalized
        $new_status = 'REMARKS_FINALIZED';
        $status_update_stmt = $conn->prepare("UPDATE applications SET current_status = ?, last_updated = NOW() WHERE queue_number = ?");
        $status_update_stmt->bind_param("ss", $new_status, $queue_number);
        $status_update_stmt->execute();

        // Log activity
        logStaffActivity($_SESSION['user_id'], $queue_number, 'other', "Finalized remarks for {$form_type}");

        // Add status history
        $history_stmt = $conn->prepare("INSERT INTO status_history (queue_number, previous_status, new_status, changed_by_type, changed_by, notes) VALUES (?, ?, 'staff', ?, ?)");
        $notes = "Remarks finalized for {$form_type} form";
        $history_stmt->bind_param("ssis", $queue_number, $application['current_status'], $_SESSION['user_id'], $notes);
        $history_stmt->execute();

        // Send notification email to applicant
        $template_code = 'REMARKS_FINALIZED';
        $subject = "Review Remarks Available - " . $queue_number;
        $body = getEmailTemplate($template_code);

        if ($body) {
            $body = str_replace('{{applicant_name}}', $application['applicant_name'], $body);
            $body = str_replace('{{queue_number}}', $queue_number, $body);
            $body = str_replace('{{form_type}}', strtoupper($form_type), $body);
            sendEmail($application['applicant_email'], $subject, $body, $queue_number, 'remarks_finalized');
        }

        // Create system message
        $msg_stmt = $conn->prepare("INSERT INTO system_messages (queue_number, message_type, subject, message_body) VALUES (?, 'update', ?, ?)");
        $subject = "Remarks Finalized - " . $queue_number;
        $message_body = "Application " . $queue_number . " has had remarks finalized for " . strtoupper($form_type) . " form.\n\nApplicant: " . $application['applicant_name'] . "\nStaff: " . ($_SESSION['full_name'] ?? 'Staff Member') . "\nFinalized: " . date('Y-m-d H:i:s');
        $msg_stmt->bind_param("sss", $queue_number, $subject, $message_body);
        $msg_stmt->execute();
    }

    // Commit transaction
    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Remarks saved successfully! The applicant has been notified.',
        'remarks_updated' => $remarks_updated
    ]);

} catch (Exception $e) {
    // Rollback transaction on error
    if (isset($conn) && $conn->ping()) {
        $conn->rollback();
    }
    
    echo json_encode(['success' => false, 'message' => 'Error processing remarks: ' . $e->getMessage()]);
}

closeDBConnection($conn);
exit();
?>
