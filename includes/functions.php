<?php
/**
 * Common Functions
 * TAU-UREO Portal
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Generate queue number
 */
function generateQueueNumber($conn)
{
    $stmt = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'queue_counter'");
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $counter = intval($row['setting_value']) + 1;

    // Update counter
    $update_stmt = $conn->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = 'queue_counter'");
    $update_stmt->bind_param("i", $counter);
    $update_stmt->execute();

    return QUEUE_PREFIX . str_pad($counter, QUEUE_NUMBER_LENGTH, '0', STR_PAD_LEFT);
}

/**
 * Generate OTP code
 */
function generateOTP()
{
    return str_pad(random_int(0, 999999), OTP_LENGTH, '0', STR_PAD_LEFT);
}

/**
 * Send email using PHPMailer with Gmail SMTP
 */
function sendEmail($to, $subject, $body, $queue_number = null, $attachments = [])
{
    $mail = new PHPMailer(true);
    $success = false;
    $error_message = '';

    try {
        // Server settings
        $mail->SMTPDebug = 0; // Set to 2 for verbose debug output
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USER;
        $mail->Password = SMTP_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = SMTP_PORT;
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );

        // Recipients
        $mail->setFrom(SYSTEM_EMAIL, SYSTEM_NAME);
        $mail->addAddress($to);
        $mail->addReplyTo(SYSTEM_EMAIL, SYSTEM_NAME);

        // Content
        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';
        $mail->Subject = $subject;
        $mail->Body = $body;
        $mail->AltBody = strip_tags($body);

        // Add attachments if provided
        if (!empty($attachments) && is_array($attachments)) {
            foreach ($attachments as $attachment) {
                if (is_array($attachment)) {
                    // Array format: ['path' => '...', 'name' => '...']
                    $mail->addAttachment($attachment['path'], $attachment['name'] ?? '');
                }
                else {
                    // String format: just the file path
                    $mail->addAttachment($attachment);
                }
            }
        }

        $mail->send();
        $success = true;
        $error_message = null;

    }
    catch (Exception $e) {
        $success = false;
        $error_message = "Email could not be sent. Error: {$mail->ErrorInfo}";
        error_log("=== EMAIL ERROR ===");
        error_log("To: $to");
        error_log("Subject: $subject");
        error_log("Error: {$mail->ErrorInfo}");
        error_log("==================");
    }

    // Log email to database
    $conn = getDBConnection();
    $status = $success ? 'sent' : 'failed';
    // Use NULL if queue_number doesn't exist in applications table
    $queue_param = $queue_number;
    if ($queue_number && $queue_number !== '' && $queue_number !== 'TEST-001') {
        $check = $conn->query("SELECT queue_number FROM applications WHERE queue_number = '$queue_number'");
        if ($check->num_rows == 0) {
            $queue_param = null;
        }
    }
    else {
        $queue_param = null;
    }
    $stmt = $conn->prepare("INSERT INTO email_logs (queue_number, recipient_email, email_type, subject, body_html, status, error_message) VALUES (?, ?, 'general', ?, ?, ?, ?)");
    $stmt->bind_param("ssssss", $queue_param, $to, $subject, $body, $status, $error_message);
    $stmt->execute();
    closeDBConnection($conn);

    return $success;
}

/**
 * Log staff activity
 */
function logStaffActivity($staff_id, $queue_number, $action_type, $details = '')
{
    $conn = getDBConnection();
    $ip = $_SERVER['REMOTE_ADDR'];
    $stmt = $conn->prepare("INSERT INTO staff_logs (staff_id, queue_number, action_type, action_details, ip_address) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("issss", $staff_id, $queue_number, $action_type, $details, $ip);
    $stmt->execute();
    closeDBConnection($conn);
}

/**
 * Update application status
 */
function updateApplicationStatus($queue_number, $new_status, $changed_by = null, $changed_by_type = 'system', $notes = '')
{
    $conn = getDBConnection();

    // Get current status
    $stmt = $conn->prepare("SELECT current_status FROM applications WHERE queue_number = ?");
    $stmt->bind_param("s", $queue_number);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $previous_status = $row['current_status'];

    // Update application status
    $update_stmt = $conn->prepare("UPDATE applications SET current_status = ? WHERE queue_number = ?");
    $update_stmt->bind_param("ss", $new_status, $queue_number);
    $update_stmt->execute();

    // Log status change
    $history_stmt = $conn->prepare("INSERT INTO status_history (queue_number, previous_status, new_status, changed_by, changed_by_type, notes) VALUES (?, ?, ?, ?, ?, ?)");
    $history_stmt->bind_param("sssiss", $queue_number, $previous_status, $new_status, $changed_by, $changed_by_type, $notes);
    $history_stmt->execute();

    closeDBConnection($conn);
}

/**
 * Sanitize input
 */
function sanitizeInput($data)
{
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

/**
 * Validate file upload
 */
function validateFileUpload($file)
{
    $errors = [];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = "File upload error occurred.";
        return $errors;
    }

    if ($file['size'] > MAX_FILE_SIZE) {
        $errors[] = "File size exceeds maximum limit of " . (MAX_FILE_SIZE / 1024 / 1024) . "MB.";
    }

    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, ALLOWED_EXTENSIONS)) {
        $errors[] = "File type not allowed. Allowed types: " . implode(', ', ALLOWED_EXTENSIONS);
    }

    return $errors;
}

/**
 * Get status display name
 */
function getStatusDisplayName($status)
{
    $statuses = [
        'INTENT_RECEIVED' => 'Intent Received',
        'REQUIREMENTS_SENT' => 'Requirements Sent',
        'REQUIREMENTS_PENDING' => 'Requirements Pending',
        'UNDER_AUTO_REVIEW' => 'Under Automated Review',
        'STAFF_REVIEW_REQUIRED' => 'Staff Review Required',
        'REQUIREMENTS_INCOMPLETE' => 'Requirements Incomplete',
        'REGISTERED' => 'Application Registered',
        'UNDER_STAFF_REVIEW' => 'Application Under Review',
        'REVISIONS_REQUIRED' => 'Pending Revision',
        'CATEGORIZED' => 'Application Categorized',
        'CATEGORY_FORMS_REQUIRED' => 'Checklist Forms Required',
        'CATEGORY_FORMS_SUBMITTED' => 'Checklist Submitted',
        'CHECKLIST_SUBMITTED' => 'Checklist Submitted',
        'UREC_REVIEW_REQUIRED' => 'UREC Review Required',
        'FORWARDED_TO_UREC' => 'Forwarded to UREC',
        'ASSIGNING_UREC_EVALUATOR' => 'Assigning Evaluator',
        'UNDER_ETHICAL_REVIEW' => 'Under Ethical Review',
        'COMPLIANCE_PENDING' => 'Compliance Pending',
        'COMPLIANCE_REVIEW' => 'Compliance Under Review',
        'APPROVED' => 'Approved',
        'CERTIFICATE_ISSUED' => 'Certificate Issued',
        'REJECTED' => 'Rejected'
    ];

    return $statuses[$status] ?? $status;
}

/**
 * Check if user is logged in
 */
function isLoggedIn()
{
    return isset($_SESSION['user_id']) && isset($_SESSION['role']);
}

/**
 * Check if applicant is logged in via OTP
 */
function isApplicantLoggedIn()
{
    return isset($_SESSION['queue_number']) && isset($_SESSION['applicant_authenticated']);
}

/**
 * Require login
 */
function requireLogin($redirect = 'staff/login.php')
{
    if (!isLoggedIn()) {
        header("Location: " . BASE_URL . $redirect);
        exit();
    }
}

/**
 * Require applicant login
 */
function requireApplicantLogin($redirect = 'applicant/login.php')
{
    if (!isApplicantLoggedIn()) {
        header("Location: " . BASE_URL . $redirect);
        exit();
    }
}

/**
 * Check session timeout
 */
function checkSessionTimeout()
{
    if (isset($_SESSION['last_activity'])) {
        if (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT) {
            $is_applicant = isset($_SESSION['applicant_authenticated']);
            session_unset();
            session_destroy();

            if ($is_applicant) {
                header("Location: " . BASE_URL . "applicant/login.php?error=session_expired");
            }
            else {
                header("Location: " . BASE_URL . "staff/login.php?error=session_expired");
            }
            exit();
        }
    }
    $_SESSION['last_activity'] = time();
    return true;
}

/**
 * Format date for display
 */
function formatDate($date)
{
    if (empty($date))
        return '';
    return date('F j, Y', strtotime($date));
}

/**
 * Get status badge class
 */
function getStatusBadgeClass($status)
{
    $classes = [
        'INTENT_RECEIVED' => 'info',
        'REQUIREMENTS_SENT' => 'info',
        'REQUIREMENTS_PENDING' => 'warning',
        'UNDER_AUTO_REVIEW' => 'primary',
        'STAFF_REVIEW_REQUIRED' => 'warning',
        'REQUIREMENTS_INCOMPLETE' => 'danger',
        'REGISTERED' => 'success',
        'UNDER_STAFF_REVIEW' => 'primary',
        'REVISIONS_REQUIRED' => 'warning',
        'CATEGORIZED' => 'success',
        'CATEGORY_FORMS_REQUIRED' => 'warning',
        'CATEGORY_FORMS_SUBMITTED' => 'info',
        'CHECKLIST_SUBMITTED' => 'info',
        'UREC_REVIEW_REQUIRED' => 'warning',
        'FORWARDED_TO_UREC' => 'primary',
        'ASSIGNING_UREC_EVALUATOR' => 'warning',
        'UNDER_ETHICAL_REVIEW' => 'primary',
        'COMPLIANCE_PENDING' => 'warning',
        'COMPLIANCE_REVIEW' => 'primary',
        'APPROVED' => 'success',
        'CERTIFICATE_ISSUED' => 'success',
        'REJECTED' => 'danger'
    ];

    return $classes[$status] ?? 'secondary';
}

/**
 * Get time ago string
 */
function timeAgo($datetime)
{
    if (empty($datetime))
        return '';

    $timestamp = strtotime($datetime);
    $current_time = time();
    $time_difference = $current_time - $timestamp;

    if ($time_difference < 60) {
        return 'Just now';
    }
    elseif ($time_difference < 3600) {
        $minutes = floor($time_difference / 60);
        return $minutes . ' minute' . ($minutes > 1 ? 's' : '') . ' ago';
    }
    elseif ($time_difference < 86400) {
        $hours = floor($time_difference / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    }
    elseif ($time_difference < 604800) {
        $days = floor($time_difference / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    }
    elseif ($time_difference < 2592000) {
        $weeks = floor($time_difference / 604800);
        return $weeks . ' week' . ($weeks > 1 ? 's' : '') . ' ago';
    }
    elseif ($time_difference < 31536000) {
        $months = floor($time_difference / 2592000);
        return $months . ' month' . ($months > 1 ? 's' : '') . ' ago';
    }
    else {
        $years = floor($time_difference / 31536000);
        return $years . ' year' . ($years > 1 ? 's' : '') . ' ago';
    }
}
/**
 * Calculate duration between two dates in months (ceil)
 */
function calculateDateDuration($start_date, $end_date)
{
    if (empty($start_date) || empty($end_date)) return '';
    
    $start = new DateTime($start_date);
    $end = new DateTime($end_date);
    $interval = $start->diff($end);
    
    $months = ($interval->y * 12) + $interval->m;
    if ($interval->d > 0) $months++; // Partial month counts as full
    
    return $months . ($months > 1 ? ' months' : ' month');
}
/**
 * Render field-level remark indicator for applicant fillers
 */
function renderFieldRemark($fieldName, $savedData)
{
    $remarkKey = $fieldName . '_remarks';
    if (isset($savedData[$remarkKey]) && !empty($savedData[$remarkKey])) {
        $history = $savedData[$remarkKey];
        if (!is_array($history)) {
            $history = [['role' => 'staff', 'text' => $history, 'timestamp' => 'Legacy']];
        }
        
        $latest = end($history);
        $count = count($history);
        
        echo '<span class="remark-indicator" title="' . htmlspecialchars($latest['text']) . '">';
        echo '<i class="bi bi-exclamation-circle-fill"></i> REVISION REQUIRED (' . $count . ')';
        echo '</span>';
    }
}
?>
