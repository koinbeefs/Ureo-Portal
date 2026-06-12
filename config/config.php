<?php
/**
 * System Configuration
 * TAU-UREO Portal
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Base URL
define('BASE_URL', 'http://localhost/ureo-portal/');

// File upload settings
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('MAX_FILE_SIZE', 10 * 1024 * 1024);
define('ALLOWED_EXTENSIONS', ['pdf', 'doc', 'docx', 'xlsx', 'xls', 'jpg', 'jpeg', 'png']);

// Email settings
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'djdtabuan@gmail.com');
define('SMTP_PASS', 'nhhh clng hxiw grca');
define('SYSTEM_EMAIL', 'ureo@tau.edu.ph');
define('SYSTEM_NAME', 'TAU-UREO Portal');

// OTP settings
define('OTP_LENGTH', 6);
define('OTP_EXPIRY_MINUTES', 10);
define('OTP_MAX_ATTEMPTS', 3);

// Session settings
define('SESSION_TIMEOUT', 1800); // 30 minutes in seconds

// Queue number format
define('QUEUE_PREFIX', 'UREO-');
define('QUEUE_NUMBER_LENGTH', 4);

// Application statuses
define('STATUS_INTENT_RECEIVED', 'INTENT_RECEIVED');
define('STATUS_REQUIREMENTS_SENT', 'REQUIREMENTS_SENT');
define('STATUS_REQUIREMENTS_PENDING', 'REQUIREMENTS_PENDING');
define('STATUS_UNDER_AUTO_REVIEW', 'UNDER_AUTO_REVIEW');
define('STATUS_STAFF_REVIEW_REQUIRED', 'STAFF_REVIEW_REQUIRED');
define('STATUS_REQUIREMENTS_INCOMPLETE', 'REQUIREMENTS_INCOMPLETE');
define('STATUS_REGISTERED', 'REGISTERED');
define('STATUS_UNDER_STAFF_REVIEW', 'UNDER_STAFF_REVIEW');
define('STATUS_REVISIONS_REQUIRED', 'REVISIONS_REQUIRED');
define('STATUS_CATEGORIZED', 'CATEGORIZED');
define('STATUS_CATEGORY_FORMS_REQUIRED', 'CATEGORY_FORMS_REQUIRED');
define('STATUS_CHECKLIST_SUBMITTED', 'CHECKLIST_SUBMITTED');
define('STATUS_UREC_REVIEW_REQUIRED', 'UREC_REVIEW_REQUIRED');
define('STATUS_FORWARDED_TO_UREC', 'FORWARDED_TO_UREC');
define('STATUS_ASSIGNING_UREC_EVALUATOR', 'ASSIGNING_UREC_EVALUATOR');
define('STATUS_UNDER_ETHICAL_REVIEW', 'UNDER_ETHICAL_REVIEW');
define('STATUS_COMPLIANCE_PENDING', 'COMPLIANCE_PENDING');
define('STATUS_COMPLIANCE_REVIEW', 'COMPLIANCE_REVIEW');
define('STATUS_APPROVED', 'APPROVED');
define('STATUS_CERTIFICATE_ISSUED', 'CERTIFICATE_ISSUED');
define('STATUS_REJECTED', 'REJECTED');

// Timezone
date_default_timezone_set('Asia/Manila');

// Error reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include database config
require_once __DIR__ . '/database.php';
?>
