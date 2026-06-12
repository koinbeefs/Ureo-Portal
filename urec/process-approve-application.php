<?php
declare(strict_types=1);
/**
 * UREC Process Application Approval/Rejection
 * TAU-UREO Portal
 */

require_once '../config/config.php';
require_once '../includes/functions.php';
require_once '../includes/email-template-functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: dashboard.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$committee_id = $_SESSION['committee_id'] ?? null;
$queue_number = $_POST['queue_number'] ?? '';
$action = $_POST['action'] ?? '';
$remarks = $_POST['remarks'] ?? '';

if (empty($queue_number) || !in_array($action, ['approve', 'reject', 'request_revision'])) {
    header("Location: dashboard.php?error=invalid_request");
    exit();
}

$conn = getDBConnection();

// Get application details
$app_stmt = $conn->prepare("SELECT * FROM applications WHERE queue_number = ?");
$app_stmt->bind_param("s", $queue_number);
$app_stmt->execute();
$application = $app_stmt->get_result()->fetch_assoc();

if (!$application) {
    header("Location: dashboard.php?error=not_found");
    exit();
}

// Check if application is in valid state for UREC approval
$valid_approval_states = [
    STATUS_UREC_REVIEW_REQUIRED,
    STATUS_UNDER_ETHICAL_REVIEW,
    STATUS_FORWARDED_TO_UREC
];

if (!in_array($application['current_status'], $valid_approval_states)) {
    header("Location: view-application.php?queue=" . urlencode($queue_number) . "&error=invalid_state");
    exit();
}

// Check if this UREC member is assigned to review this application
if ($application['urec_reviewed_by'] && $application['urec_reviewed_by'] != $user_id) {
    header("Location: view-application.php?queue=" . urlencode($queue_number) . "&error=already_assigned");
    exit();
}

try {
    $conn->begin_transaction();
    
    $new_status = '';
    $email_subject = '';
    $email_body = '';
    $system_message = '';
    
    switch ($action) {
        case 'approve':
            $new_status = STATUS_APPROVED;
            $email_subject = "Application Approved - " . $queue_number;
            $system_message = "Your application has been approved by the UREC committee.";
            break;
            
        case 'reject':
            $new_status = STATUS_REJECTED;
            $email_subject = "Application Rejected - " . $queue_number;
            $system_message = "Your application has been rejected by the UREC committee.";
            break;
            
        case 'request_revision':
            $new_status = STATUS_REVISIONS_REQUIRED;
            $email_subject = "Revisions Requested - " . $queue_number;
            $system_message = "The UREC committee has requested revisions for your application.";
            break;
    }
    
    // Update application
    $update_stmt = $conn->prepare("UPDATE applications SET current_status = ?, urec_review_notes = ?, urec_decision = ?, urec_decision_date = NOW(), urec_reviewed_by = ?, last_updated = NOW() WHERE queue_number = ?");
    $decision = $action === 'approve' ? 'approved' : ($action === 'reject' ? 'rejected' : 'revisions_required');
    $update_stmt->bind_param("sssis", $new_status, $remarks, $decision, $user_id, $queue_number);
    $update_stmt->execute();
    
    // Log activity
    logStaffActivity($user_id, $queue_number, 'status_change', "UREC decision: $action");
    
    // Add to status history
    $history_stmt = $conn->prepare("INSERT INTO status_history (queue_number, previous_status, new_status, changed_by, changed_by_type, notes, urec_committee_id) VALUES (?, ?, ?, ?, 'urec', ?, ?)");
    $history_notes = ucfirst($action) . " by UREC committee: " . $remarks;
    $history_stmt->bind_param("sssis", $queue_number, $application['current_status'], $new_status, $user_id, $history_notes, $committee_id);
    $history_stmt->execute();
    
    // Send email to applicant
    $applicant_body = "Dear " . htmlspecialchars($application['applicant_name']) . ",\n\n";
    $applicant_body .= $system_message . "\n\n";
    $applicant_body .= "Application Details:\n";
    $applicant_body .= "Queue Number: " . htmlspecialchars($queue_number) . "\n";
    $applicant_body .= "Review Committee: UREC " . htmlspecialchars($_SESSION['committee_designation']) . "\n";
    $applicant_body .= "Decision Date: " . date('Y-m-d H:i:s') . "\n\n";
    
    if ($action === 'request_revision') {
        $deadline = $_POST['revision_deadline'] ?? date('Y-m-d', strtotime('+2 weeks'));
        $applicant_body .= "Revision Deadline: " . htmlspecialchars($deadline) . "\n\n";
        
        // Also update revision deadline
        $deadline_stmt = $conn->prepare("UPDATE applications SET urec_revision_deadline = ? WHERE queue_number = ?");
        $deadline_stmt->bind_param("ss", $deadline, $queue_number);
        $deadline_stmt->execute();
    }
    
    $applicant_body .= "Review Notes:\n" . htmlspecialchars($remarks) . "\n\n";
    
    if ($action === 'approve') {
        $applicant_body .= "Congratulations! Your application has been approved. You will receive further instructions regarding the certificate issuance.\n\n";
    } elseif ($action === 'reject') {
        $applicant_body .= "We regret to inform you that your application has been rejected. You may submit a new application if you wish to address the concerns raised.\n\n";
    } else {
        $applicant_body .= "Please log in to your portal to submit the required revisions.\n\n";
    }
    
    $applicant_body .= "Best regards,\nTAU-UREO Portal";
    
    sendEmail($application['applicant_email'], $email_subject, $applicant_body, $queue_number, 'urec_decision');
    
    // Create system message for applicant
    $msg_stmt = $conn->prepare("INSERT INTO system_messages (queue_number, message_type, subject, message_body) VALUES (?, 'urec_decision', ?, ?)");
    $msg_stmt->bind_param("sss", $queue_number, $email_subject, $system_message . "\n\nNotes: " . $remarks);
    $msg_stmt->execute();
    
    $conn->commit();
    
    $_SESSION['success_message'] = "Application decision submitted successfully.";
    header("Location: view-application.php?queue=" . urlencode($queue_number));
    exit();
    
} catch (Exception $e) {
    $conn->rollback();
    $_SESSION['error_message'] = "Error processing decision: " . $e->getMessage();
    header("Location: view-application.php?queue=" . urlencode($queue_number));
    exit();
}

closeDBConnection($conn);
?>
