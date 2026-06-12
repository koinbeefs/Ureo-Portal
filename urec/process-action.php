<?php
declare(strict_types=1);
/**
 * UREC Process Action
 * TAU-UREO Portal
 */

require_once '../config/config.php';
require_once '../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: dashboard.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$queue_number = $_POST['queue_number'] ?? '';
$action = $_POST['action'] ?? '';

if (empty($queue_number)) {
    header("Location: dashboard.php");
    exit();
}

$conn = getDBConnection();

// Fetch application to verify state
$stmt = $conn->prepare("SELECT * FROM applications WHERE queue_number = ?");
$stmt->bind_param("s", $queue_number);
$stmt->execute();
$application = $stmt->get_result()->fetch_assoc();

if (!$application) {
    die("Application not found.");
}

$success = false;
$message = "";

try {
    if ($action === 'assign_evaluator') {
        // Chairperson assigning evaluators (can be multiple)
        $evaluator_ids = $_POST['evaluator_ids'] ?? [];
        if (empty($evaluator_ids)) {
            throw new Exception("Please select at least one evaluator.");
        }

        // Use the first selected evaluator as primary (current system limitation)
        $primary_evaluator = $evaluator_ids[0];
        
        $update_query = "
            UPDATE applications 
            SET urec_reviewed_by = ?, 
                current_status = ?, 
                urec_assigned_at = NOW(),
                last_updated = NOW() 
            WHERE queue_number = ?
            AND urec_committee_id = (SELECT committee_id FROM users WHERE user_id = ?)
        ";
        $under_review = STATUS_UNDER_ETHICAL_REVIEW;
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param("issi", $primary_evaluator, $under_review, $queue_number, $user_id);
        
        if ($stmt->execute()) {
            $evaluator_list = implode(', ', $evaluator_ids);
            logStaffActivity($user_id, $queue_number, 'status_change', "Assigned evaluators (IDs: $evaluator_list) to application");
            $success = true;
            $message = count($evaluator_ids) . " evaluator(s) selected. Primary evaluator assigned: " . $primary_evaluator;
        }
    } 
    elseif ($action === 'assign_evaluators') {
        // Chairperson assigning multiple evaluators
        $evaluator_ids = $_POST['evaluator_ids'] ?? [];
        if (empty($evaluator_ids)) {
            throw new Exception("Please select at least one evaluator.");
        }

        // Use the first selected evaluator as primary (applications table limitation)
        $primary_evaluator = $evaluator_ids[0];
        
        // Store additional evaluators in a JSON field or notes for tracking
        $additional_evaluators = array_slice($evaluator_ids, 1); // All except primary
        $evaluator_json = json_encode($evaluator_ids);
        
        // Update application with primary evaluator and store all evaluator info
        $update_query = "
            UPDATE applications 
            SET urec_reviewed_by = ?, 
                current_status = ?, 
                urec_assigned_at = NOW(),
                last_updated = NOW(),
                urec_review_notes = ?
            WHERE queue_number = ?
            AND urec_committee_id = (SELECT committee_id FROM users WHERE user_id = ?)
        ";
        $under_review = STATUS_UNDER_ETHICAL_REVIEW;
        $evaluator_notes = "Multiple evaluators assigned: " . $evaluator_json;
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param("isssi", $primary_evaluator, $under_review, $evaluator_notes, $queue_number, $user_id);
        
        if ($stmt->execute()) {
            // Log all evaluator assignments
            foreach ($evaluator_ids as $index => $evaluator_id) {
                $eval_log_sql = "INSERT INTO staff_logs (staff_id, queue_number, action_type, action_details) VALUES (?, ?, 'other', ?)";
                $eval_log_stmt = $conn->prepare($eval_log_sql);
                $eval_details = "Assigned as evaluator (" . ($index === 0 ? 'Primary' : 'Secondary') . ")";
                $eval_log_stmt->bind_param("iss", $evaluator_id, $queue_number, $eval_details);
                $eval_log_stmt->execute();
            }
            
            $evaluator_list = implode(', ', $evaluator_ids);
            logStaffActivity($user_id, $queue_number, 'other', "Assigned " . count($evaluator_ids) . " evaluators (IDs: $evaluator_list) to application");
            $success = true;
            $message = count($evaluator_ids) . " evaluator(s) assigned successfully. Primary evaluator: " . $primary_evaluator;
        }
    }
    elseif (in_array($action, ['approve', 'minor_revision', 'major_revision', 'resubmit'])) {
        // Evaluator submitting a decision
        // Check if user is primary evaluator or any assigned evaluator
        $is_assigned_evaluator = false;
        
        if ($application['urec_reviewed_by'] == $user_id) {
            $is_assigned_evaluator = true; // Primary evaluator
        } elseif (!empty($application['urec_review_notes']) && strpos($application['urec_review_notes'], 'Multiple evaluators assigned:') === 0) {
            // Check if user is in the list of assigned evaluators
            $json_part = str_replace('Multiple evaluators assigned: ', '', $application['urec_review_notes']);
            $evaluator_ids = json_decode($json_part, true);
            if (is_array($evaluator_ids) && in_array($user_id, $evaluator_ids)) {
                $is_assigned_evaluator = true; // Secondary evaluator
            }
        }
        
        if (!$is_assigned_evaluator) {
            throw new Exception("You are not authorized to submit a decision for this application.");
        }

        // Check if application is in valid state for UREC approval
        $valid_approval_states = [
            STATUS_UREC_REVIEW_REQUIRED,
            STATUS_UNDER_ETHICAL_REVIEW,
            STATUS_FORWARDED_TO_UREC
        ];

        if (!in_array($application['current_status'], $valid_approval_states)) {
            throw new Exception("Application is not in a valid state for approval.");
        }

        $remarks = $_POST['remarks'] ?? '';
        $new_status = '';
        $log_action = '';

        switch ($action) {
            case 'approve':
                $new_status = STATUS_APPROVED; // Or COMPLIANCE_PENDING if further check needed
                $log_action = "Approved";
                break;
            case 'minor_revision':
                $new_status = STATUS_REVISIONS_REQUIRED;
                $log_action = "Approved with Minor Revision";
                break;
            case 'major_revision':
                $new_status = STATUS_REVISIONS_REQUIRED;
                $log_action = "Approved with Major Revision";
                break;
            case 'resubmit':
                $new_status = STATUS_REJECTED;
                $log_action = "Resubmit Application Required";
                break;
        }

        $update_query = "
            UPDATE applications 
            SET current_status = ?, 
                urec_review_notes = ?,
                urec_decision = ?,
                urec_decision_date = NOW(),
                urec_reviewed_at = NOW(),
                last_updated = NOW() 
            WHERE queue_number = ?
        ";
        $decision = $action === 'approve' ? 'approved' : ($action === 'resubmit' ? 'rejected' : 'revisions_required');
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param("ssss", $new_status, $remarks, $decision, $queue_number);
        
        if ($stmt->execute()) {
            logStaffActivity($user_id, $queue_number, 'status_change', "UREC Member $log_action: $remarks");
            $success = true;
            $message = "Decision successfully submitted.";
        }
    }

    if ($success) {
        $_SESSION['success_message'] = $message;
    } else {
        $_SESSION['error_message'] = "An error occurred while processing the action.";
    }

} catch (Exception $e) {
    $_SESSION['error_message'] = $e->getMessage();
}

closeDBConnection($conn);
header("Location: view-application.php?queue=" . urlencode($queue_number));
exit();
