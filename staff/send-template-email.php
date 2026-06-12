<?php
/**
 * Send Template Email
 * TAU-UREO Portal
 */

require_once '../config/config.php';
require_once '../includes/functions.php';
require_once '../includes/email-template-functions.php';

requireLogin();
checkSessionTimeout();

// Check if this is an AJAX request
$is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    if ($is_ajax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Invalid request method']);
        exit();
    }
    header("Location: dashboard.php");
    exit();
}

$queue_number = $_POST['queue_number'] ?? '';
$template_code = $_POST['template_code'] ?? '';
$custom_subject = $_POST['subject'] ?? '';
$custom_body = $_POST['body'] ?? '';

if (empty($queue_number) || empty($template_code)) {
    if ($is_ajax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        exit();
    }
    header("Location: view-application.php?queue=" . urlencode($queue_number) . "&error=invalid_request");
    exit();
}

$staff_id = $_SESSION['user_id'];
$conn = getDBConnection();

// Get application details
$app_stmt = $conn->prepare("SELECT * FROM applications WHERE queue_number = ?");
$app_stmt->bind_param("s", $queue_number);
$app_stmt->execute();
$application = $app_stmt->get_result()->fetch_assoc();

if (!$application) {
    if ($is_ajax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Application not found']);
        exit();
    }
    header("Location: dashboard.php?error=not_found");
    exit();
}

// Get base placeholders from application
$placeholders = getApplicationPlaceholders($queue_number);

// Add custom placeholders based on template type
if ($template_code === 'INCOMPLETE_DOCS' && !empty($_POST['missing_documents'])) {
    $placeholders['missing_documents'] = $_POST['missing_documents'];
} elseif ($template_code === 'MISSING_SIGNATURES' && !empty($_POST['unsigned_documents'])) {
    $placeholders['unsigned_documents'] = $_POST['unsigned_documents'];
} elseif ($template_code === 'CONDITIONAL_APPROVAL' && !empty($_POST['conditions'])) {
    $placeholders['conditions'] = $_POST['conditions'];
} elseif ($template_code === 'REJECTED' && !empty($_POST['rejection_reason'])) {
    $placeholders['rejection_reason'] = $_POST['rejection_reason'];
} elseif ($template_code === 'REVISIONS_NEEDED' && !empty($_POST['revisions_list'])) {
    $placeholders['revisions_list'] = $_POST['revisions_list'];
} elseif ($template_code === 'GENERAL_UPDATE' && !empty($_POST['message_content'])) {
    $placeholders['message_content'] = $_POST['message_content'];
} elseif ($template_code === 'CERTIFICATE_ISSUED') {
    // Get certificate details
    $cert_stmt = $conn->prepare("SELECT * FROM certificates WHERE queue_number = ? ORDER BY issued_at DESC LIMIT 1");
    $cert_stmt->bind_param("s", $queue_number);
    $cert_stmt->execute();
    $certificate = $cert_stmt->get_result()->fetch_assoc();
    
    if ($certificate) {
        $placeholders['certificate_number'] = $certificate['certificate_number'];
        $placeholders['valid_until'] = date('F d, Y', strtotime($certificate['valid_until']));
    }
}

// Process the custom body with placeholders
$final_body = processEmailTemplate($custom_body, $placeholders);
$final_subject = processEmailTemplate($custom_subject, $placeholders);

// Get template attachments (if any)
$attachments = getTemplateAttachments($template_code);

// Create system message instead of sending email
try {
    // Determine message type based on template
    $message_type = 'update'; // Default
    if (in_array($template_code, ['APPROVED', 'CONDITIONAL_APPROVAL'])) {
        $message_type = 'approval';
    } elseif ($template_code === 'REJECTED') {
        $message_type = 'rejection';
    } elseif ($template_code === 'CERTIFICATE_ISSUED') {
        $message_type = 'certificate';
    } elseif ($template_code === 'REPLY_INTENT') {
        $message_type = 'requirement';
    }
    
    // Insert system message
    $msg_stmt = $conn->prepare("INSERT INTO system_messages (queue_number, message_type, subject, message_body, created_at) VALUES (?, ?, ?, ?, NOW())");
    $msg_stmt->bind_param('ssss', $queue_number, $message_type, $final_subject, $final_body);
    $msg_stmt->execute();
    
    // If there are attachments (like REPLY_INTENT), insert them into system_documents
    if (!empty($attachments)) {
        $doc_stmt = $conn->prepare("INSERT INTO system_documents (queue_number, document_name, document_path, document_type, provided_at) VALUES (?, ?, ?, 'template', NOW())");
        
        foreach ($attachments as $attachment) {
            if (isset($attachment['path']) && file_exists($attachment['path'])) {
                // Store relative path from portal root
                $relative_path = str_replace(__DIR__ . '/../', '', $attachment['path']);
                $doc_name = $attachment['name'] ?? basename($attachment['path']);
                
                $doc_stmt->bind_param('sss', $queue_number, $doc_name, $relative_path);
                $doc_stmt->execute();
            }
        }
    }
    
    // Log the activity
    $activity_details = "Sent system message using template: " . $template_code;
    logStaffActivity($staff_id, $queue_number, 'sent_reply', $activity_details);
    
    // Update application status based on template type
    if ($template_code === 'REVISIONS_NEEDED') {
        $update_stmt = $conn->prepare("UPDATE applications SET current_status = 'REVISIONS_REQUIRED', last_updated = NOW() WHERE queue_number = ?");
        $update_stmt->bind_param('s', $queue_number);
        $update_stmt->execute();
        
        // Log status change in history
        $status_stmt = $conn->prepare("INSERT INTO status_history (queue_number, previous_status, new_status, changed_by, changed_by_type, notes) VALUES (?, ?, 'REVISIONS_REQUIRED', ?, 'staff', 'Revision requested via template')");
        $status_stmt->bind_param('ssi', $queue_number, $application['current_status'], $staff_id);
        $status_stmt->execute();
    }
    
    // Record in messages table for communication history
    $hist_stmt = $conn->prepare("
        INSERT INTO messages (queue_number, sender_type, sender_id, message_content, sent_at) 
        VALUES (?, 'staff', ?, ?, NOW())
    ");
    $hist_stmt->bind_param("sis", $queue_number, $staff_id, $final_body);
    $hist_stmt->execute();
    
    closeDBConnection($conn);
    
    if ($is_ajax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'Message sent successfully']);
        exit();
    }
    
    header("Location: view-application.php?queue=" . urlencode($queue_number) . "&success=message_sent");
    
} catch (Exception $e) {
    error_log("Error creating system message: " . $e->getMessage());
    closeDBConnection($conn);
    
    if ($is_ajax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Failed to send message: ' . $e->getMessage()]);
        exit();
    }
    
    header("Location: view-application.php?queue=" . urlencode($queue_number) . "&error=message_failed");
}
exit();
