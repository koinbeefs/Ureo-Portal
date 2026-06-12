<?php
declare(strict_types=1);
/**
 * Process Forwarding to UREC
 * TAU-UREO Portal
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/email-template-functions.php';

// Require staff login
requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $queue_number = $_POST['queue_number'] ?? '';
    $notes = $_POST['notes'] ?? '';
    $staff_id = $_SESSION['user_id'];

    if (empty($queue_number)) {
        header("Location: dashboard.php?error=missing_data");
        exit;
    }

    $conn = getDBConnection();

    // Verify current status is CHECKLIST_SUBMITTED
    $stmt = $conn->prepare("SELECT current_status, applicant_email, category FROM applications WHERE queue_number = ?");
    $stmt->bind_param("s", $queue_number);
    $stmt->execute();
    $result = $stmt->get_result();
    $application = $result->fetch_assoc();

    if (!$application) {
        header("Location: dashboard.php?error=application_not_found");
        exit;
    }

    if ($application['current_status'] !== STATUS_CHECKLIST_SUBMITTED && $application['current_status'] !== 'CATEGORY_FORMS_SUBMITTED') {
        header("Location: view-application.php?queue=" . urlencode($queue_number) . "&error=invalid_status");
        exit;
    }

    // Read AI classification from JSON file
    $ai_classification_file = __DIR__ . '/../uploads/' . $queue_number . '/ai_classification.json';
    if (!file_exists($ai_classification_file)) {
        header("Location: view-application.php?queue=" . urlencode($queue_number) . "&error=classification_not_found");
        exit;
    }
    
    $ai_data = json_decode(file_get_contents($ai_classification_file), true);
    if (!$ai_data || !isset($ai_data['staff_reviewed']) || !$ai_data['staff_reviewed']) {
        header("Location: view-application.php?queue=" . urlencode($queue_number) . "&error=classification_not_reviewed");
        exit;
    }
    
    $final_category = $ai_data['staff_feedback']['final_category'] ?? 'Human Use';
    
    // Map classification to committee
    $classification_to_committee = [
        'Human Use' => 'HUMAN_USE',
        'Animal Welfare' => 'ANIMAL_WELFARE', 
        'Plant Use' => 'PLANT_USE',
        'Microbiological/Biotechnological Use' => 'MICRO_BIO',
        'Engineering' => 'ENGINEERING',
        'Information Technology Use' => 'IT_USE',
        'Food Technology Use' => 'FOOD_TECH'
    ];

    $committee_code = $classification_to_committee[$final_category] ?? 'HUMAN_USE';
    
    // Get committee ID
    $committee_stmt = $conn->prepare("SELECT committee_id FROM urec_committees WHERE committee_code = ? AND is_active = 1");
    $committee_stmt->bind_param("s", $committee_code);
    $committee_stmt->execute();
    $committee_result = $committee_stmt->get_result()->fetch_assoc();
    
    if (!$committee_result) {
        header("Location: view-application.php?queue=" . urlencode($queue_number) . "&error=committee_not_found");
        exit;
    }
    
    $committee_id = $committee_result['committee_id'];

    try {
        // Set shorter lock timeout to prevent deadlocks
        $conn->query("SET SESSION innodb_lock_wait_timeout = 10");
        
        // Begin transaction with minimal scope
        $conn->begin_transaction();
    
    // 1. Update application status and committee assignment
        $previous_status = $application['current_status'];
        $finalStatus = STATUS_ASSIGNING_UREC_EVALUATOR;
        
        $update_stmt = $conn->prepare("UPDATE applications SET current_status = ?, urec_committee_id = ?, forwarded_to_urec_at = NOW(), forwarded_by_staff = ?, last_updated = NOW() WHERE queue_number = ?");
        $update_stmt->bind_param("siis", $finalStatus, $committee_id, $staff_id, $queue_number);
        $update_stmt->execute();
        
        // 2. Add single status history entry
        $history_stmt = $conn->prepare("INSERT INTO status_history (queue_number, previous_status, new_status, changed_by, changed_by_type, notes) VALUES (?, ?, ?, ?, 'staff', ?)");
        $history_notes = "Forwarded to UREC " . $committee_code . " committee. " . ($notes ? "Notes: " . $notes : "");
        $history_stmt->bind_param("sssis", $queue_number, $previous_status, $finalStatus, $staff_id, $history_notes);
        $history_stmt->execute();
        
        // Commit minimal transaction
        $conn->commit();
        
        // 3. Log activity outside transaction
        logStaffActivity($staff_id, $queue_number, 'other', 'Forwarded application to UREC ' . $committee_code . ' committee');
        
        // 4. Get UREC users to notify (outside transaction)
        $urec_stmt = $conn->prepare("SELECT email, full_name, committee_designation FROM users WHERE user_role = 'urec' AND committee_id = ? AND active_status = 1");
        $urec_stmt->bind_param("i", $committee_id);
        $urec_stmt->execute();
        $urec_users = $urec_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        // 5. Send notifications (non-blocking)
        foreach ($urec_users as $urec_user) {
            $urec_email = $urec_user['email'];
            $urec_name = $urec_user['full_name'];
            
            $urec_subject = "New Application for UREC Review - " . $queue_number;
            $urec_body = "Dear " . htmlspecialchars($urec_name) . ",\n\n";
            $urec_body .= "A new application has been forwarded to your UREC committee for ethical review.\n\n";
            $urec_body .= "Queue Number: " . htmlspecialchars($queue_number) . "\n";
            $urec_body .= "Applicant: " . htmlspecialchars($application['applicant_email']) . "\n";
            $urec_body .= "Classification: " . htmlspecialchars($final_category) . "\n";
            $urec_body .= "Committee: " . htmlspecialchars($committee_code) . "\n";
            $urec_body .= "Forwarded by: " . htmlspecialchars($_SESSION['full_name'] ?? 'Staff Member') . "\n";
            $urec_body .= "Forwarded on: " . date('Y-m-d H:i:s') . "\n";
            $urec_body .= ($notes ? "Notes: " . htmlspecialchars($notes) . "\n\n" : "\n");
            $urec_body .= "Please log in to the UREC portal to assign an evaluator.\n\n";
            $urec_body .= "Best regards,\nTAU-UREO Portal";
            
            sendEmail($urec_email, $urec_subject, $urec_body, $queue_number, 'forwarded_to_urec');
        }
        
        // 6. Create system messages (simple insert, no transaction needed)
        try {
            // UREC notification
            $urec_msg_stmt = $conn->prepare("INSERT INTO system_messages (queue_number, message_type, subject, message_body) VALUES (?, 'urec_assignment', ?, ?)");
            $urec_msg_subject = "New Application for UREC Review - " . $queue_number;
            $urec_msg_body = "Application " . $queue_number . " forwarded to " . $committee_code . " committee.\n\nApplicant: " . htmlspecialchars($application['applicant_email']) . "\nForwarded by: " . htmlspecialchars($_SESSION['full_name'] ?? 'Staff Member') . "\nClassification: " . htmlspecialchars($final_category);
            $urec_msg_stmt->bind_param("sss", $queue_number, $urec_msg_subject, $urec_msg_body);
            $urec_msg_stmt->execute();
            
            // Applicant notification
            $app_msg_stmt = $conn->prepare("INSERT INTO system_messages (queue_number, message_type, subject, message_body) VALUES (?, 'update', ?, ?)");
            $app_subject = "Application Forwarded to UREC - " . $queue_number;
            $app_body = "Your application has been forwarded to the University Research Ethics Committee (" . $committee_code . ") for ethical review. You will be notified once the committee completes their assessment.";
            $app_msg_stmt->bind_param("sss", $queue_number, $app_subject, $app_body);
            $app_msg_stmt->execute();
        } catch (Exception $msg_error) {
            // Non-critical error, log but don't fail the main operation
            error_log("System message creation failed: " . $msg_error->getMessage());
        }
        
        echo json_encode(['success' => true, 'message' => 'Application successfully forwarded to UREC ' . $committee_code . ' committee.']);
        exit;

    } catch (Exception $e) {
        if (isset($conn) && $conn->ping()) {
            $conn->rollback();
        }
        echo json_encode(['success' => false, 'message' => 'Error forwarding application: ' . $e->getMessage()]);
        exit;
    } finally {
        if (isset($conn)) {
            closeDBConnection($conn);
        }
    }
} else {
    header("Location: dashboard.php");
    exit;
}
