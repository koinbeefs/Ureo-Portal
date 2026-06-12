<?php
/**
 * Document Validation Automation
 * TAU-UREO Portal - HITL System
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';

/**
 * Validate uploaded documents against checklist
 * This is the automation engine core
 */
function validateDocuments($queue_number) {
    $conn = getDBConnection();
    
    // Get application details
    $app_stmt = $conn->prepare("SELECT * FROM applications WHERE queue_number = ?");
    $app_stmt->bind_param("s", $queue_number);
    $app_stmt->execute();
    $application = $app_stmt->get_result()->fetch_assoc();
    
    if (!$application) {
        closeDBConnection($conn);
        return;
    }
    
    // Get required documents
    $req_stmt = $conn->query("SELECT * FROM required_documents WHERE active = 1 AND mandatory = 1");
    $required_docs = $req_stmt->fetch_all(MYSQLI_ASSOC);
    
    // Get uploaded documents
    $upload_stmt = $conn->prepare("SELECT DISTINCT document_type FROM documents WHERE queue_number = ?");
    $upload_stmt->bind_param("s", $queue_number);
    $upload_stmt->execute();
    $uploaded_result = $upload_stmt->get_result();
    $uploaded_types = [];
    while ($row = $uploaded_result->fetch_assoc()) {
        $uploaded_types[] = $row['document_type'];
    }
    
    // Check for missing documents
    $missing_documents = [];
    foreach ($required_docs as $req_doc) {
        if (!in_array($req_doc['document_type'], $uploaded_types)) {
            $missing_documents[] = $req_doc['display_name'];
        }
    }
    
    // Update status based on completeness
    if (!empty($missing_documents)) {
        // Documents incomplete
        updateApplicationStatus(
            $queue_number, 
            STATUS_REQUIREMENTS_INCOMPLETE, 
            null, 
            'system', 
            'Missing documents: ' . implode(', ', $missing_documents)
        );
        
        // Send notification email
        $email_subject = "TAU-UREO - Incomplete Documents for $queue_number";
        $email_body = "
        <html>
        <body style='font-family: Arial, sans-serif;'>
            <h2>Document Submission Incomplete</h2>
            <p>Your recent document submission for application $queue_number is incomplete.</p>
            <h3>Missing Documents:</h3>
            <ul>";
        foreach ($missing_documents as $doc) {
            $email_body .= "<li>$doc</li>";
        }
        $email_body .= "</ul>
            <p>Please login to the portal and upload the missing documents.</p>
            <p><a href='" . BASE_URL . "applicant/login.php'>Login to Portal</a></p>
        </body>
        </html>
        ";
        sendEmail($application['applicant_email'], $email_subject, $email_body, $queue_number);
        
        // Increment completion attempts
        $attempt_stmt = $conn->prepare("UPDATE applications SET completion_attempts = completion_attempts + 1 WHERE queue_number = ?");
        $attempt_stmt->bind_param("s", $queue_number);
        $attempt_stmt->execute();
        
    } else {
        // All mandatory documents present - check for additional requirements
        updateApplicationStatus($queue_number, STATUS_UNDER_AUTO_REVIEW, null, 'system', 'All mandatory documents received');
        
        // Check for conditional requirements
        $has_additional_requirements = checkAdditionalRequirements($queue_number, $conn);
        
        if ($has_additional_requirements) {
            // Flag for human review
            updateApplicationStatus(
                $queue_number, 
                STATUS_STAFF_REVIEW_REQUIRED, 
                null, 
                'system', 
                'Additional requirements detected - staff review required'
            );
            
            // Update flag in application
            $flag_stmt = $conn->prepare("UPDATE applications SET has_additional_requirements = 1 WHERE queue_number = ?");
            $flag_stmt->bind_param("s", $queue_number);
            $flag_stmt->execute();
            
            // Notify staff - assign to available staff member
            notifyStaff($queue_number, $conn);
            
        } else {
            // No additional requirements - auto-approve to registration
            updateApplicationStatus($queue_number, STATUS_REGISTERED, null, 'system', 'Automatically validated and registered');
            
            // Move to under review status for staff verification
            updateApplicationStatus($queue_number, 'UNDER_STAFF_REVIEW', null, 'system', 'Application under staff review');
            
            // Send success email
            $email_subject = "TAU-UREO - Documents Validated for $queue_number";
            $email_body = "
            <html>
            <body style='font-family: Arial, sans-serif;'>
                <h2>Documents Successfully Validated</h2>
                <p>Your documents for application $queue_number have been automatically validated.</p>
                <p>Your application has been registered and forwarded for ethical review.</p>
                <p><strong>Current Status:</strong> Registered</p>
                <p><a href='" . BASE_URL . "applicant/login.php'>View Application Status</a></p>
            </body>
            </html>
            ";
            sendEmail($application['applicant_email'], $email_subject, $email_body, $queue_number);
        }
    }
    
    closeDBConnection($conn);
}

/**
 * Check for additional requirements based on document content
 * This simulates checking for conditional fields
 */
function checkAdditionalRequirements($queue_number, $conn) {
    // In real implementation, this would parse documents and check for specific conditions
    // For now, we'll randomly determine if additional review is needed
    // This simulates checking checkboxes in forms that trigger additional requirements
    
    // Get conditional required documents
    $cond_stmt = $conn->query("SELECT * FROM required_documents WHERE active = 1 AND is_conditional = 1");
    $conditional_docs = $cond_stmt->fetch_all(MYSQLI_ASSOC);
    
    // Check if any conditional documents are present
    $upload_stmt = $conn->prepare("SELECT document_type FROM documents WHERE queue_number = ?");
    $upload_stmt->bind_param("s", $queue_number);
    $upload_stmt->execute();
    $uploaded = $upload_stmt->get_result();
    
    while ($doc = $uploaded->fetch_assoc()) {
        foreach ($conditional_docs as $cond_doc) {
            if ($doc['document_type'] === $cond_doc['document_type']) {
                return true; // Found conditional document
            }
        }
    }
    
    return false; // No additional requirements
}

/**
 * Notify available staff member
 */
function notifyStaff($queue_number, $conn) {
    // Find available staff member (least busy)
    $staff_stmt = $conn->query("
        SELECT u.user_id, u.email, u.full_name, COUNT(a.queue_number) as assigned_count
        FROM users u
        LEFT JOIN applications a ON u.user_id = a.assigned_staff_id AND a.current_status NOT IN ('APPROVED', 'REJECTED', 'CERTIFICATE_ISSUED')
        WHERE u.role = 'staff' AND u.active_status = 1
        GROUP BY u.user_id
        ORDER BY assigned_count ASC
        LIMIT 1
    ");
    
    if ($staff_stmt->num_rows > 0) {
        $staff = $staff_stmt->fetch_assoc();
        
        // Assign application to staff
        $assign_stmt = $conn->prepare("UPDATE applications SET assigned_staff_id = ? WHERE queue_number = ?");
        $assign_stmt->bind_param("is", $staff['user_id'], $queue_number);
        $assign_stmt->execute();
        
        // Send notification email to staff
        $email_subject = "TAU-UREO - New Application Requires Review: $queue_number";
        $email_body = "
        <html>
        <body style='font-family: Arial, sans-serif;'>
            <h2>New Application Assignment</h2>
            <p>Dear " . htmlspecialchars($staff['full_name']) . ",</p>
            <p>Application <strong>$queue_number</strong> has been assigned to you for review.</p>
            <p><strong>Reason:</strong> Additional requirements detected by automation system</p>
            <p>Please login to the staff portal to review this application.</p>
            <p><a href='" . BASE_URL . "staff/login.php'>Login to Staff Portal</a></p>
        </body>
        </html>
        ";
        sendEmail($staff['email'], $email_subject, $email_body, $queue_number);
    }
}

/**
 * Auto-categorize application
 */
function categorizeApplication($queue_number, $conn) {
    // Simple categorization logic
    // In real implementation, this would analyze research type, risk factors, etc.
    
    $category = 'full'; // Default to full review
    
    // Update application category
    $cat_stmt = $conn->prepare("UPDATE applications SET category = ? WHERE queue_number = ?");
    $cat_stmt->bind_param("ss", $category, $queue_number);
    $cat_stmt->execute();
    
    updateApplicationStatus($queue_number, STATUS_CATEGORIZED, null, 'system', "Categorized as: $category review");
    
    // Forward to UREC
    updateApplicationStatus($queue_number, STATUS_FORWARDED_TO_UREC, null, 'system', 'Application forwarded to UREC for ethical review');
}
?>
