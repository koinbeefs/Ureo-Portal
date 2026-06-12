<?php
/**
 * Process Staff Actions (Approve/Reject/Revise)
 * TAU-UREO Portal
 */

require_once '../config/config.php';
require_once '../includes/functions.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: dashboard.php");
    exit();
}

$queue_number = $_POST['queue_number'] ?? '';
$action = $_POST['action'] ?? '';
$notes = trim($_POST['notes'] ?? '');
$staff_id = $_SESSION['user_id'];

if (empty($queue_number) || empty($action)) {
    header("Location: dashboard.php?error=invalid_request");
    exit();
}

$conn = getDBConnection();

try {
    // Get current application
    $app_stmt = $conn->prepare("SELECT * FROM applications WHERE queue_number = ?");
    $app_stmt->bind_param("s", $queue_number);
    $app_stmt->execute();
    $application = $app_stmt->get_result()->fetch_assoc();
    
    if (!$application) {
        throw new Exception("Application not found");
    }
    
    $old_status = $application['current_status'];
    $new_status = '';
    $activity_desc = '';
    $email_subject = '';
    $email_message = '';
    
    // Determine new status based on action
    switch ($action) {
        case 'approve':
            $new_status = 'CATEGORIZED';
            $activity_desc = 'Approved and categorized application';
            $email_subject = "Application Approved - Queue #" . $queue_number;
            $email_message = "Your application has been approved by staff and categorized. It is now proceeding to ethical review.";
            break;
            
        case 'reject':
            $new_status = 'REJECTED';
            $activity_desc = 'Rejected application';
            $email_subject = "Application Status Update - Queue #" . $queue_number;
            $email_message = "Your application has been reviewed. Unfortunately, it has been rejected.";
            if (!empty($notes)) {
                $email_message .= "\n\nReason: " . $notes;
            }
            break;
            
        case 'revision':
            $new_status = 'REVISIONS_REQUIRED';
            $activity_desc = 'Requested revisions';
            $email_subject = "Revision Required - Queue #" . $queue_number;
            $email_message = "Your application requires revisions. Please review the notes and resubmit.";
            if (!empty($notes)) {
                $email_message .= "\n\nRequired changes: " . $notes;
            }

            // Clear existing signatures to force re-signing
            $sigDir = '../uploads/' . $queue_number . '/signatures/';
            if (is_dir($sigDir)) {
                $files = glob($sigDir . '*');
                foreach ($files as $file) {
                    if (is_file($file)) {
                        unlink($file);
                    }
                }
            }
            break;
            
        default:
            throw new Exception("Invalid action");
    }
    
    // Begin transaction
    $conn->begin_transaction();
    
    // Update application status
    $update_stmt = $conn->prepare("UPDATE applications SET current_status = ?, assigned_staff_id = ?, last_updated = NOW() WHERE queue_number = ?");
    $update_stmt->bind_param("sis", $new_status, $staff_id, $queue_number);
    $update_stmt->execute();
    
    // Insert status history
    $history_stmt = $conn->prepare("INSERT INTO status_history (queue_number, old_status, new_status, changed_by, notes, timestamp) VALUES (?, ?, ?, ?, ?, NOW())");
    $history_stmt->bind_param("sssis", $queue_number, $old_status, $new_status, $staff_id, $notes);
    $history_stmt->execute();
    
    // Log activity
    $log_action = 'other';
    if ($action === 'approve') {
        $log_action = 'approved';
    } elseif ($action === 'reject') {
        $log_action = 'rejected';
    }
    logStaffActivity($staff_id, $queue_number, $log_action, $activity_desc);
    
    // Commit transaction
    $conn->commit();
    
    // Create system message instead of sending email
    $message_body = $email_message . "\n\n";
    $message_body .= "Queue Number: " . $queue_number . "\n";
    $message_body .= "Research Title: " . $application['research_title'];
    
    if (!empty($notes)) {
        $message_body .= "\n\nNotes: " . $notes;
    }
    
    // For revision requests, include QF-02 remarks if they exist
    if ($action === 'revision') {
        $qf02_stmt = $conn->prepare("SELECT form_data FROM fillable_forms WHERE queue_number = ? AND form_type = 'qf02'");
        $qf02_stmt->bind_param('s', $queue_number);
        $qf02_stmt->execute();
        $qf02_result = $qf02_stmt->get_result()->fetch_assoc();
        
        if ($qf02_result) {
            $qf02_data = json_decode($qf02_result['form_data'], true);
            $remarks_found = false;
            $remarks_text = "\n\n--- QF-02 FORM REMARKS ---\n";
            
            for ($i = 1; $i <= 20; $i++) {
                if (!empty($qf02_data["crit_{$i}_remarks"])) {
                    $remarks_found = true;
                    $remarks_text .= "Criterion {$i}: " . $qf02_data["crit_{$i}_remarks"] . "\n";
                }
            }
            
            if ($remarks_found) {
                $message_body .= $remarks_text;
            }
        }
    }
    
    $msg_stmt = $conn->prepare("INSERT INTO system_messages (queue_number, message_type, subject, message_body, created_at) VALUES (?, ?, ?, ?, NOW())");
    // Map action to valid message_type ENUM values
    $message_type_map = [
        'approve' => 'approval',
        'reject' => 'rejection',
        'revision' => 'update'
    ];
    $message_type = $message_type_map[$action] ?? 'update';
    $msg_stmt->bind_param('ssss', $queue_number, $message_type, $email_subject, $message_body);
    $msg_stmt->execute();
    
    header("Location: view-application.php?queue=" . urlencode($queue_number) . "&success=action_completed");
    
} catch (Exception $e) {
    if ($conn->connect_errno === 0) {
        $conn->rollback();
    }
    error_log("Error processing action: " . $e->getMessage());
    header("Location: view-application.php?queue=" . urlencode($queue_number) . "&error=action_failed");
} finally {
    closeDBConnection($conn);
}
exit();
?>
