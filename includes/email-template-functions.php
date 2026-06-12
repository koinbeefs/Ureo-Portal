<?php
/**
 * Email Template Helper Functions
 * TAU-UREO Portal
 */

/**
 * Get all active email templates
 */
function getEmailTemplates($category = null) {
    $conn = getDBConnection();
    
    if ($category) {
        $stmt = $conn->prepare("SELECT * FROM email_templates WHERE is_active = 1 AND category = ? ORDER BY template_name");
        $stmt->bind_param("s", $category);
    } else {
        $stmt = $conn->prepare("SELECT * FROM email_templates WHERE is_active = 1 ORDER BY category, template_name");
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $templates = $result->fetch_all(MYSQLI_ASSOC);
    
    closeDBConnection($conn);
    return $templates;
}

/**
 * Get a specific email template by code
 */
function getEmailTemplate($template_code) {
    $conn = getDBConnection();
    
    $stmt = $conn->prepare("SELECT * FROM email_templates WHERE template_code = ? AND is_active = 1");
    $stmt->bind_param("s", $template_code);
    $stmt->execute();
    $result = $stmt->get_result();
    $template = $result->fetch_assoc();
    
    closeDBConnection($conn);
    return $template;
}

/**
 * Replace placeholders in template with actual values
 */
function processEmailTemplate($template_body, $placeholders) {
    $processed = $template_body;
    
    foreach ($placeholders as $key => $value) {
        $processed = str_replace("{{" . $key . "}}", $value, $processed);
    }
    
    return $processed;
}

/**
 * Send email using template
 */
function sendTemplatedEmail($recipient, $template_code, $placeholders = [], $queue_number = null, $attachments = []) {
    $template = getEmailTemplate($template_code);
    
    if (!$template) {
        return false;
    }
    
    $subject = processEmailTemplate($template['subject'], $placeholders);
    $body = processEmailTemplate($template['body'], $placeholders);
    
    return sendEmail($recipient, $subject, $body, $queue_number, $attachments);
}

/**
 * Get template attachments based on template code
 */
function getTemplateAttachments($template_code) {
    $attachments = [];
    
    // Reply to Letter of Intent includes supplementary files
    if ($template_code === 'REPLY_INTENT') {
        $base_path = __DIR__ . '/../assets/to_send/for_reply_to_letter_of_intent/';
        
        $files = [
            ['path' => $base_path . 'TAU-REO-QF-01 Application for Research Ethics Review Form Rev01.docx', 
             'name' => 'TAU-REO-QF-01 Application Form.docx'],
            ['path' => $base_path . 'TAU-REO-QF-02 Research Ethics Review Category.docx', 
             'name' => 'TAU-REO-QF-02 Review Category Form.docx'],
            ['path' => $base_path . 'General Guidelines.pdf', 
             'name' => 'General Guidelines.pdf']
        ];
        
        // Only add files that exist
        foreach ($files as $file) {
            if (file_exists($file['path'])) {
                $attachments[] = $file;
            }
        }
    }
    
    return $attachments;
}

/**
 * Get template categories for filtering
 */
function getTemplateCategories() {
    return [
        'general' => 'General',
        'review_process' => 'Review Process',
        'decision' => 'Decision',
        'certificate' => 'Certificate'
    ];
}

/**
 * Get available placeholders for application context
 */
function getApplicationPlaceholders($queue_number) {
    $conn = getDBConnection();
    
    $stmt = $conn->prepare("
        SELECT 
            a.queue_number,
            a.applicant_name,
            a.applicant_email,
            a.research_title,
            a.current_status,
            a.category,
            a.submission_timestamp,
            u.full_name as assigned_staff_name
        FROM applications a
        LEFT JOIN users u ON a.assigned_staff_id = u.user_id
        WHERE a.queue_number = ?
    ");
    $stmt->bind_param("s", $queue_number);
    $stmt->execute();
    $result = $stmt->get_result();
    $app = $result->fetch_assoc();
    
    closeDBConnection($conn);
    
    if (!$app) {
        return [];
    }
    
    return [
        'queue_number' => $app['queue_number'],
        'applicant_name' => $app['applicant_name'] ?? 'Applicant',
        'applicant_email' => $app['applicant_email'],
        'research_title' => $app['research_title'] ?? '',
        'current_status' => $app['current_status'],
        'category' => ucfirst($app['category'] ?? 'Pending'),
        'submission_date' => date('F d, Y', strtotime($app['submission_timestamp'])),
        'assigned_staff' => $app['assigned_staff_name'] ?? 'Not assigned',
        'approval_date' => date('F d, Y'),
        'current_date' => date('F d, Y')
    ];
}
