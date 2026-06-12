<?php
/**
 * View Application Details (Staff)
 * TAU-UREO Portal
 */

require_once '../config/config.php';
require_once '../includes/functions.php';
require_once '../includes/email-template-functions.php';

requireLogin();
checkSessionTimeout();

$queue_number = $_GET['queue'] ?? '';
$staff_id = $_SESSION['user_id'];

if (empty($queue_number)) {
    header("Location: dashboard.php");
    exit();
}

// Handle file download if requested
if (isset($_GET['download']) && isset($_SESSION['download_file'])) {
    $file = $_SESSION['download_file'];
    $filename = $_SESSION['download_filename'];

    if (file_exists($file) && !empty($filename)) {
        header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        header('Content-Disposition: attachment; filename="' . basename($filename) . '"');
        header('Content-Length: ' . filesize($file));
        readfile($file);
        unlink($file);
    }

    unset($_SESSION['download_file']);
    unset($_SESSION['download_filename']);
    exit;
}

$conn = getDBConnection();

// Get application details
$app_stmt = $conn->prepare("SELECT * FROM applications WHERE queue_number = ?");
$app_stmt->bind_param("s", $queue_number);
$app_stmt->execute();
$application = $app_stmt->get_result()->fetch_assoc();

if (!$application) {
    header("Location: dashboard.php?error=notfound");
    exit();
}

// Auto-claim unassigned applications
$just_claimed = false;
if (!$application['assigned_staff_id'] && !in_array($application['current_status'], ['APPROVED', 'REJECTED', 'CERTIFICATE_ISSUED'])) {
    $claim_stmt = $conn->prepare("UPDATE applications SET assigned_staff_id = ? WHERE queue_number = ? AND assigned_staff_id IS NULL");
    $claim_stmt->bind_param("is", $staff_id, $queue_number);
    $claim_stmt->execute();

    if ($claim_stmt->affected_rows > 0) {
        $just_claimed = true;
        $application['assigned_staff_id'] = $staff_id; // Update the local copy

        // Log the auto-claim activity
        logStaffActivity($staff_id, $queue_number, 'other', 'Auto-claimed application for review');
    }
}

// Get assigned staff name if application is assigned
$assigned_staff_name = null;
if ($application['assigned_staff_id']) {
    $assigned_stmt = $conn->prepare("SELECT full_name FROM users WHERE user_id = ?");
    $assigned_stmt->bind_param("i", $application['assigned_staff_id']);
    $assigned_stmt->execute();
    $assigned_result = $assigned_stmt->get_result()->fetch_assoc();
    $assigned_staff_name = $assigned_result['full_name'];
}

// Check if current user can edit this application
$can_edit = ($application['assigned_staff_id'] == $staff_id || !$application['assigned_staff_id']);

// Log activity
logStaffActivity($staff_id, $queue_number, 'viewed_application', 'Viewed application details');

// Get system messages (staff-to-applicant messages)
$sys_msg_stmt = $conn->prepare("SELECT * FROM system_messages WHERE queue_number = ? ORDER BY created_at DESC");
$sys_msg_stmt->bind_param("s", $queue_number);
$sys_msg_stmt->execute();
$system_messages = $sys_msg_stmt->get_result();

// Get system documents (provided templates/guidelines)
$sys_doc_stmt = $conn->prepare("SELECT * FROM system_documents WHERE queue_number = ? ORDER BY provided_at DESC");
$sys_doc_stmt->bind_param("s", $queue_number);
$sys_doc_stmt->execute();
$system_documents = $sys_doc_stmt->get_result();

// Get fillable forms status
$forms_stmt = $conn->prepare("SELECT form_type, form_data, file_generated, completed_at FROM fillable_forms WHERE queue_number = ?");
$forms_stmt->bind_param("s", $queue_number);
$forms_stmt->execute();
$forms_result = $forms_stmt->get_result();
$fillable_forms_status = [];
while ($row = $forms_result->fetch_assoc()) {
    $fillable_forms_status[$row['form_type']] = [
        'completed' => (bool)$row['file_generated'],
        'completed_at' => $row['completed_at'],
        'data' => json_decode($row['form_data'], true)
    ];
}

// Check for AI classification
$ai_classification = null;
$ai_file_path = '../uploads/' . $queue_number . '/ai_classification.json';
if (file_exists($ai_file_path)) {
    $ai_classification = json_decode(file_get_contents($ai_file_path), true);
}

// Get documents
$docs_stmt = $conn->prepare("SELECT * FROM documents WHERE queue_number = ? ORDER BY upload_timestamp DESC");
$docs_stmt->bind_param("s", $queue_number);
$docs_stmt->execute();
$documents = $docs_stmt->get_result();

// Get messages
$msgs_stmt = $conn->prepare("
    SELECT m.*, u.full_name as staff_name 
    FROM messages m 
    LEFT JOIN users u ON m.sender_id = u.user_id 
    WHERE m.queue_number = ? 
    ORDER BY sent_at ASC
");
$msgs_stmt->bind_param("s", $queue_number);
$msgs_stmt->execute();
$messages = $msgs_stmt->get_result();

// Mark messages as read
$read_stmt = $conn->prepare("UPDATE messages SET read_status = 1, read_at = NOW(), read_by = ? WHERE queue_number = ? AND sender_type = 'applicant' AND read_status = 0");
$read_stmt->bind_param("is", $staff_id, $queue_number);
$read_stmt->execute();

// Get status history
$history_stmt = $conn->prepare("SELECT * FROM status_history WHERE queue_number = ? ORDER BY timestamp DESC");
$history_stmt->bind_param("s", $queue_number);
$history_stmt->execute();
$history = $history_stmt->get_result();

closeDBConnection($conn);

$page_title = 'View Application';
$base_url = '../';
$active_menu = 'dashboard';
include '../includes/auth_header.php';
?>

<style>
    :root {
        --tau-green-dark: #006400;
        --tau-green-primary: #228B22;
        --tau-green-light: #e8f5e9;
        --tau-accent: #ffd700;
    }

    .app-header {
        background: white;
        padding: 2rem 2.5rem;
        border-radius: 16px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        margin-bottom: 2rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 2rem;
        border: 1px solid rgba(0,0,0,0.05);
    }

    .queue-badge {
        background: linear-gradient(135deg, var(--tau-green-dark), var(--tau-green-primary));
        color: white;
        padding: 0.6rem 1.2rem;
        border-radius: 8px;
        font-weight: 700;
        font-family: 'Monaco', 'Consolas', monospace;
        letter-spacing: 1px;
        box-shadow: 0 4px 10px rgba(0, 100, 0, 0.2);
    }

    .section-card {
        border: none;
        border-radius: 16px;
        background: white;
        box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        margin-bottom: 2rem;
        overflow: hidden;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }

    .section-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 30px rgba(0,0,0,0.12);
    }

    .section-card .card-header {
        background: white;
        border-bottom: 2px solid #f8f9fa;
        padding: 1.5rem 2rem;
        font-weight: 700;
        color: var(--tau-green-dark);
        display: flex;
        align-items: center;
        gap: 12px;
        font-size: 1.1rem;
    }

    .info-label {
        font-size: 0.75rem;
        font-weight: 700;
        color: #888;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 0.25rem;
    }

    .info-value {
        font-weight: 600;
        color: #333;
        margin-bottom: 0;
    }

    .status-pill {
        padding: 0.5rem 1rem;
        border-radius: 50px;
        font-weight: 700;
        font-size: 0.85rem;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    .document-item {
        padding: 1rem;
        border-radius: 10px;
        background: #f8f9fa;
        border: 1px solid #eee;
        transition: all 0.2s;
        margin-bottom: 0.75rem;
    }

    .document-item:hover {
        background: white;
        border-color: var(--tau-green-primary);
        box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        transform: translateY(-2px);
    }

    .chat-container {
        height: 300px;
        overflow-y: auto;
        padding: 1rem;
        background: #fcfcfc;
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }

    .msg-bubble {
        max-width: 85%;
        padding: 0.8rem 1.2rem;
        border-radius: 15px;
        font-size: 0.95rem;
        position: relative;
        box-shadow: 0 2px 5px rgba(0,0,0,0.03);
    }

    .msg-sent {
        align-self: flex-end;
        background: linear-gradient(135deg, var(--tau-green-dark), var(--tau-green-primary));
        color: white;
        border-bottom-right-radius: 2px;
    }

    .msg-received {
        align-self: flex-start;
        background: white;
        border: 1px solid #e9ecef;
        color: #333;
        border-bottom-left-radius: 2px;
    }

    .msg-meta {
        font-size: 0.7rem;
        margin-top: 0.4rem;
        opacity: 0.8;
    }

    .timeline-wrapper {
        padding-left: 1.5rem;
        border-left: 2px solid #f0f0f0;
        margin-left: 0.75rem;
        position: relative;
    }

    .timeline-point {
        position: absolute;
        left: -9px;
        width: 16px;
        height: 16px;
        color: var(--tau-green-dark);
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .info-label {
        font-size: 0.75rem;
        font-weight: 700;
        color: #888;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 0.25rem;
    }

    .info-value {
        font-weight: 600;
        color: #333;
        margin-bottom: 0;
    }

    .status-pill {
        padding: 0.5rem 1rem;
        border-radius: 50px;
        font-weight: 700;
        font-size: 0.85rem;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    .document-item {
        padding: 1rem;
        border-radius: 10px;
        background: #f8f9fa;
        border: 1px solid #eee;
        transition: all 0.2s;
        margin-bottom: 0.75rem;
    }

    .document-item:hover {
        background: white;
        border-color: var(--tau-green-primary);
        box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        transform: translateY(-2px);
    }

    .chat-container {
        height: 300px;
        overflow-y: auto;
        padding: 1rem;
        background: #fcfcfc;
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }

    .msg-bubble {
        max-width: 85%;
        padding: 0.8rem 1.2rem;
        border-radius: 15px;
        font-size: 0.95rem;
        position: relative;
        box-shadow: 0 2px 5px rgba(0,0,0,0.03);
    }

    .msg-sent {
        align-self: flex-end;
        background: linear-gradient(135deg, var(--tau-green-dark), var(--tau-green-primary));
        color: white;
        border-bottom-right-radius: 2px;
    }

    .msg-received {
        align-self: flex-start;
        background: white;
        border: 1px solid #e9ecef;
        color: #333;
        border-bottom-left-radius: 2px;
    }

    .msg-meta {
        font-size: 0.7rem;
        margin-top: 0.4rem;
        opacity: 0.8;
    }

    .timeline-wrapper {
        padding-left: 1.5rem;
        border-left: 2px solid #f0f0f0;
        margin-left: 0.75rem;
        position: relative;
    }

    .timeline-point {
        position: absolute;
        left: -9px;
        width: 16px;
        height: 16px;
        border-radius: 50%;
        background: white;
        border: 3px solid var(--tau-green-primary);
    }

    .action-btn {
        border-radius: 8px;
        padding: 0.75rem 1.25rem;
        font-weight: 600;
        transition: all 0.2s;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
    }

    .action-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }

    .nav-tabs-custom {
        border-bottom: 3px solid #f8f9fa;
        gap: 2rem;
        padding: 0 1rem;
    }

    .nav-tabs-custom .nav-link {
        border: none;
        color: #6c757d;
        font-weight: 600;
        padding: 1.25rem 1rem;
        position: relative;
        background: transparent;
        border-radius: 8px 8px 0 0;
        transition: all 0.3s ease;
        font-size: 1rem;
    }

    .nav-tabs-custom .nav-link:hover {
        color: var(--tau-green-primary);
        background: rgba(0, 100, 0, 0.05);
    }

    .nav-tabs-custom .nav-link.active {
        color: white;
        background: var(--tau-green-primary);
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }

    .nav-tabs-custom .nav-link.active::after {
        display: none;
    }

    .ai-badge {
        background: #6f42c1;
        color: white;
        font-size: 0.7rem;
        padding: 2px 8px;
        border-radius: 4px;
        font-weight: 700;
        text-transform: uppercase;
    }

    .document-item {
        padding: 1rem;
        border: 1px solid #e9ecef;
        border-radius: 8px;
        background: white;
        transition: all 0.2s;
        margin-bottom: 0.5rem;
    }

    .document-item:hover {
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        border-color: #dee2e6;
    }

    .message-content {
        transition: all 0.2s ease;
    }

    .message-content:hover {
        background-color: #f8f9fa !important;
        border-color: var(--tau-green-primary) !important;
        transform: scale(1.01);
    }
</style>

<div class="container-fluid py-4">
    <?php if ($just_claimed): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-hand-index-thumb"></i> Application has been automatically assigned to you for review.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php
endif; ?>

    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php if ($_GET['success'] === 'message_sent'): ?>
                <i class="bi bi-check-circle"></i> System message sent successfully!
            <?php
    elseif ($_GET['success'] === 'action_completed'): ?>
                <i class="bi bi-check-circle"></i> Action completed successfully!
            <?php
    elseif ($_GET['success'] === 'remarks_saved'): ?>
                <i class="bi bi-check-circle"></i> QF-02 remarks saved successfully! Document will download automatically.
            <?php
    endif; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php
endif; ?>
    
    <?php if (isset($_GET['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php if ($_GET['error'] === 'empty_message'): ?>
                <i class="bi bi-exclamation-triangle"></i> Please enter a message.
            <?php
    elseif ($_GET['error'] === 'send_failed'): ?>
                <i class="bi bi-exclamation-triangle"></i> Failed to send message. Please try again.
            <?php
    elseif ($_GET['error'] === 'action_failed'): ?>
                <i class="bi bi-exclamation-triangle"></i> Failed to process action. Please try again.
            <?php
    elseif ($_GET['error'] === 'message_failed'): ?>
                <i class="bi bi-exclamation-triangle"></i> Failed to send system message. Please try again.
            <?php
    elseif ($_GET['error'] === 'invalid_request'): ?>
                <i class="bi bi-exclamation-triangle"></i> Invalid request parameters.
            <?php
    endif; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php
endif; ?>
    
    <!-- Header -->
    <div class="app-header">
        <div class="d-flex align-items-center gap-3">
            <a href="applications.php" class="btn btn-light border btn-sm rounded-pill px-3">
                <i class="bi bi-arrow-left me-1"></i> Back
            </a>
            <div>
                <h4 class="mb-0 fw-bold text-dark">Application Details</h4>
                <small class="text-muted">Review and manage research ethics application</small>
            </div>
        </div>
        <div class="d-flex align-items-center gap-3">
            <div class="text-end">
                <div class="queue-badge">
                    <i class="bi bi-ticket-detailed me-1"></i> <?php echo htmlspecialchars($queue_number); ?>
                </div>
            </div>
            <div class="status-pill bg-light border text-dark px-3 py-2 rounded-pill">
                <i class="bi bi-info-circle me-1"></i> <?php echo getStatusDisplayName($application['current_status']); ?>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Main Content -->
        <div class="col-lg-8">
            <!-- Overview Card -->
            <div class="section-card">
                <div class="card-header">
                    <i class="bi bi-file-earmark-person"></i> Applicant & Research Overview
                </div>
                <div class="card-body p-4">
                    <div class="row g-4">
                        <div class="col-md-6">
                            <div class="mb-4">
                                <div class="info-label">Applicant Name</div>
                                <div class="info-value fs-5"><?php echo htmlspecialchars($application['applicant_name']); ?></div>
                            </div>
                            <div class="mb-4">
                                <div class="info-label">Email Address</div>
                                <div class="info-value"><?php echo htmlspecialchars($application['applicant_email']); ?></div>
                            </div>
                            <div class="mb-4">
                                <div class="info-label">Applicant Type</div>
                                <div class="info-value">
                                    <span class="badge bg-light text-dark border"><?php echo ucfirst($application['applicant_type']); ?></span>
                                </div>
                            </div>
                            <div>
                                <div class="info-label">Assignment</div>
                                <div class="info-value">
                                    <?php if ($application['assigned_staff_id']): ?>
                                        <?php if ($application['assigned_staff_id'] == $staff_id): ?>
                                            <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">Assigned to you</span>
                                        <?php
    else: ?>
                                            <span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25"><?php echo htmlspecialchars($assigned_staff_name); ?></span>
                                        <?php
    endif; ?>
                                    <?php
else: ?>
                                        <span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25">Unassigned</span>
                                    <?php
endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-4">
                                <div class="info-label">Research Title</div>
                                <div class="info-value" style="line-height: 1.4;"><?php echo htmlspecialchars($application['research_title']); ?></div>
                            </div>
                            <div class="mb-4">
                                <div class="info-label">Submission Date</div>
                                <div class="info-value"><?php echo formatDate($application['submission_timestamp']); ?></div>
                            </div>
                            <div class="mb-4">
                                <div class="info-label">Review Category</div>
                                <div class="info-value">
                                    <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">
                                        <?php echo ucfirst($application['category'] ?? 'Pending'); ?> Review
                                    </span>
                                </div>
                            </div>
                            <div>
                                <div class="info-label">Overall Progress</div>
                                <div class="d-flex align-items-center gap-2 mt-1">
                                    <div class="progress flex-grow-1" style="height: 8px;">
                                        <?php
$progress = 0;
$total_steps = 5;
switch ($application['current_status']) {
    case 'INTENT_RECEIVED':
        $progress = 15;
        break;
    case 'REQUIREMENTS_SENT':
    case 'REQUIREMENTS_PENDING':
    case 'REQUIREMENTS_INCOMPLETE':
        $progress = 30;
        break;
    case 'REGISTERED':
    case 'UNDER_AUTO_REVIEW':
    case 'STAFF_REVIEW_REQUIRED':
    case 'UNDER_STAFF_REVIEW':
    case 'REVISIONS_REQUIRED':
        $progress = 50;
        break;
    case 'CATEGORIZED':
        $progress = 70;
        break;
    case 'CATEGORY_FORMS_REQUIRED':
        $progress = 75;
        break;
    case 'CHECKLIST_SUBMITTED':
    case 'CATEGORY_FORMS_SUBMITTED':
        $progress = 80;
        break;
    case 'UREC_REVIEW_REQUIRED':
    case 'FORWARDED_TO_UREC':
        $progress = 85;
        break;
    case 'UNDER_ETHICAL_REVIEW':
    case 'COMPLIANCE_PENDING':
    case 'COMPLIANCE_REVIEW':
        $progress = 90;
        break;
    case 'APPROVED':
    case 'CERTIFICATE_ISSUED':
        $progress = 100;
        break;
    case 'REJECTED':
        $progress = 100;
        break;
}
?>
                                        <div class="progress-bar" role="progressbar" style="width: <?php echo $progress; ?>%; background: linear-gradient(90deg, #006400, #228B22);" aria-valuenow="<?php echo $progress; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                    </div>
                                    <small class="text-muted fw-bold"><?php echo $progress; ?>%</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Application Tabs -->
            <div class="section-card">
                <div class="card-body p-0">
                    <ul class="nav nav-tabs nav-tabs-custom px-4" id="appTabs" role="tablist">
                        <li class="nav-item">
                            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-sys-msgs">
                                <i class="bi bi-megaphone me-2"></i> System Message
                            </button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-provided-docs">
                                <i class="bi bi-file-earmark-medical me-2"></i> Provided Documents
                            </button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-docs">
                                <i class="bi bi-files me-2"></i> Documents
                            </button>
                        </li>
                    </ul>

                    <div class="tab-content p-4">
                        <!-- System Messages Tab -->
                        <div class="tab-pane fade show active" id="tab-sys-msgs">
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <h6 class="fw-bold mb-0">Sent Notifications</h6>
                                <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#emailTemplateModal" <?php echo !$can_edit ? 'disabled' : ''; ?>>
                                    <i class="bi bi-plus-circle me-1"></i> Send New
                                </button>
                            </div>

                            <?php if ($system_messages->num_rows > 0): ?>
                                <script>
                                // Store message data in JavaScript array
                                window.messageData = [];
                                </script>
                                <div class="row g-3">
                                    <?php
    // Reset the result pointer
    $system_messages->data_seek(0);
    $messageIndex = 0;
    while ($sys_msg = $system_messages->fetch_assoc()):
?>
                                        <script>
                                        window.messageData.push({
                                            subject: <?php echo json_encode($sys_msg['subject'] ?? 'No Subject'); ?>,
                                            message: <?php echo json_encode($sys_msg['message_body']); ?>,
                                            date: <?php echo json_encode(formatDate($sys_msg['created_at'])); ?>,
                                            type: <?php echo json_encode($sys_msg['message_type']); ?>
                                        });
                                        </script>
                                        <div class="col-12">
                                            <div class="card border-0 bg-light p-3 rounded-3">
                                                <div class="d-flex justify-content-between align-items-start mb-2">
                                                    <h6 class="mb-0 fw-bold text-primary"><?php echo htmlspecialchars($sys_msg['subject'] ?? 'No Subject'); ?></h6>
                                                    <span class="badge bg-<?php
        echo $sys_msg['message_type'] === 'approval' ? 'success' :
            ($sys_msg['message_type'] === 'rejection' ? 'danger' :
            ($sys_msg['message_type'] === 'update' ? 'warning' : 'info'));
?>">
                                                        <?php echo ucfirst($sys_msg['message_type']); ?>
                                                    </span>
                                                </div>
                                                <div class="text-muted small mb-2">
                                                    <i class="bi bi-calendar-event"></i> <?php echo formatDate($sys_msg['created_at']); ?>
                                                    <?php if ($sys_msg['is_read']): ?>
                                                        <span class="ms-2 text-success"><i class="bi bi-check2-all"></i> Read</span>
                                                    <?php
        endif; ?>
                                                </div>
                                                <div class="bg-white p-3 rounded border small message-content position-relative"
                                                     style="max-height: 150px; overflow-y: auto; cursor: pointer;"
                                                     onclick="showFullMessage(<?php echo $messageIndex; ?>)"
                                                     title="Click to view full message">
                                                    <?php echo nl2br(htmlspecialchars($sys_msg['message_body'])); ?>
                                                    <div class="position-absolute bottom-0 end-0 p-2 bg-white bg-opacity-75 rounded">
                                                        <small class="text-muted"><i class="bi bi-arrows-fullscreen"></i> Click to expand</small>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php
        $messageIndex++;
    endwhile;
?>
                                </div>
                            <?php
else: ?>
                                <div class="text-center py-5 text-muted">
                                    <i class="bi bi-megaphone display-4 d-block mb-3"></i>
                                    No system messages sent yet.
                                </div>
                            <?php
endif; ?>
                        </div>

                        <!-- Provided Documents Tab -->
                        <div class="tab-pane fade" id="tab-provided-docs">
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <h6 class="fw-bold mb-0">System Documents</h6>
                                <span class="badge bg-light text-dark border"><?php echo $system_documents->num_rows; ?> Documents</span>
                            </div>

                            <?php if ($system_documents->num_rows > 0): ?>
                                <div class="row g-3">
                                    <?php
    // Reset the result pointer
    $system_documents->data_seek(0);
    while ($sdoc = $system_documents->fetch_assoc()):
?>
                                        <div class="col-md-6">
                                            <div class="document-item">
                                                <div class="d-flex align-items-center gap-3">
                                                    <div class="fs-2 text-primary">
                                                        <i class="bi bi-file-earmark-medical"></i>
                                                    </div>
                                                    <div class="flex-grow-1 overflow-hidden">
                                                        <div class="fw-bold text-truncate small"><?php echo htmlspecialchars($sdoc['document_name']); ?></div>
                                                        <div class="text-muted" style="font-size: 0.7rem;"><?php echo formatDate($sdoc['provided_at']); ?></div>
                                                    </div>
                                                    <div class="d-flex gap-1">
                                                        <?php if (!empty($sdoc['file_path'])): ?>
                                                            <a href="../uploads/system/<?php echo basename($sdoc['file_path']); ?>" class="btn btn-sm btn-light border" target="_blank">
                                                                <i class="bi bi-download"></i>
                                                            </a>
                                                        <?php
        else: ?>
                                                            <button class="btn btn-sm btn-light border" disabled title="File not available">
                                                                <i class="bi bi-download"></i>
                                                            </button>
                                                        <?php
        endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php
    endwhile; ?>
                                </div>
                            <?php
else: ?>
                                <div class="text-center py-5 text-muted">
                                    <i class="bi bi-file-earmark-medical display-4 d-block mb-3"></i>
                                    No system documents provided yet.
                                </div>
                            <?php
endif; ?>
                        </div>

                        <!-- Documents Tab -->
                        <div class="tab-pane fade" id="tab-docs">
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <h6 class="fw-bold mb-0">Submitted Files</h6>
                                <span class="badge bg-light text-dark border"><?php echo $documents->num_rows; ?> Files</span>
                            </div>

                            <div class="row g-3">
                                <?php
// Reset the result pointer
$documents->data_seek(0);
while ($doc = $documents->fetch_assoc()):
?>
                                    <div class="col-md-6">
                                        <div class="document-item">
                                            <div class="d-flex align-items-center gap-3">
                                                <div class="fs-2 text-primary">
                                                    <?php
    $ext = pathinfo($doc['file_path'], PATHINFO_EXTENSION);
    if (in_array($ext, ['pdf']))
        echo '<i class="bi bi-file-earmark-pdf"></i>';
    elseif (in_array($ext, ['doc', 'docx']))
        echo '<i class="bi bi-file-earmark-word"></i>';
    else
        echo '<i class="bi bi-file-earmark"></i>';
?>
                                                </div>
                                                <div class="flex-grow-1 overflow-hidden">
                                                    <div class="fw-bold text-truncate small"><?php echo htmlspecialchars($doc['document_type']); ?></div>
                                                    <div class="text-muted" style="font-size: 0.7rem;"><?php echo formatDate($doc['upload_timestamp']); ?></div>
                                                    <?php if ($doc['validation_status'] === 'validated'): ?>
                                                        <span class="badge bg-success badge-sm"><i class="bi bi-check-circle"></i> Validated</span>
                                                    <?php
    elseif ($doc['validation_status'] === 'rejected'): ?>
                                                        <span class="badge bg-danger badge-sm"><i class="bi bi-x-circle"></i> Rejected</span>
                                                    <?php
    endif; ?>
                                                </div>
                                                <div class="d-flex gap-1">
                                                    <?php if ($doc['document_type'] === 'Research proposal/Thesis/Dissertation Outline' || $doc['document_type'] === 'proposal'): ?>
                                                        <a href="review-proposal.php?queue=<?php echo $queue_number; ?>&doc_id=<?php echo $doc['document_id']; ?>" class="btn btn-sm btn-primary" target="_blank" title="Review & Annotate">
                                                            <i class="bi bi-pin-angle-fill"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                    <button type="button" class="btn btn-sm btn-light border" onclick="previewDocument('<?php echo addslashes($doc['file_path']); ?>', '<?php echo addslashes($doc['document_type']); ?>', <?php echo $doc['document_id']; ?>, '<?php echo $doc['validation_status']; ?>')">
                                                        <i class="bi bi-eye"></i>
                                                    </button>
                                                    <?php if (!empty($doc['file_path'])): ?>
                                                        <a href="../uploads/<?php echo $queue_number; ?>/<?php echo basename($doc['file_path']); ?>" class="btn btn-sm btn-light border" target="_blank">
                                                            <i class="bi bi-download"></i>
                                                        </a>
                                                    <?php
    else: ?>
                                                        <button class="btn btn-sm btn-light border" disabled title="File not available">
                                                            <i class="bi bi-download"></i>
                                                        </button>
                                                    <?php
    endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php
endwhile; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Communication Section -->
            <div class="section-card mt-4">
                <div class="card-header">
                    <i class="bi bi-chat-dots"></i> Communication
                </div>
                <div class="card-body p-3">
                    <div class="chat-container rounded-3 border mb-3">
                        <?php if ($messages->num_rows > 0): ?>
                            <?php
    // Reset the result pointer
    $messages->data_seek(0);
    while ($msg = $messages->fetch_assoc()):
?>
                                <div class="msg-bubble <?php echo $msg['sender_type'] === 'staff' ? 'msg-sent' : 'msg-received'; ?>">
                                    <div class="fw-bold small mb-1">
                                        <?php echo $msg['sender_type'] === 'staff' ? 'You' : htmlspecialchars($application['applicant_name']); ?>
                                    </div>
                                    <div><?php echo nl2br(htmlspecialchars($msg['message_content'])); ?></div>
                                    <div class="msg-meta">
                                        <?php echo date('M d, h:i A', strtotime($msg['sent_at'])); ?>
                                        <?php if ($msg['sender_type'] === 'staff' && $msg['read_status']): ?>
                                            <i class="bi bi-check2-all ms-1"></i>
                                        <?php
        endif; ?>
                                    </div>
                                </div>
                            <?php
    endwhile; ?>
                        <?php
else: ?>
                            <div class="text-center py-3 text-muted">
                                <i class="bi bi-chat-left display-4 d-block mb-2"></i>
                                No messages yet. Start a conversation below.
                            </div>
                        <?php
endif; ?>
                    </div>

                    <form action="process-action.php" method="POST">
                        <input type="hidden" name="queue_number" value="<?php echo $queue_number; ?>">
                        <input type="hidden" name="action" value="send_message">
                        <div class="input-group">
                            <textarea name="message" class="form-control" rows="1" placeholder="Type your message to the applicant..." required></textarea>
                            <button type="submit" class="btn btn-success px-3" <?php echo !$can_edit ? 'disabled' : ''; ?>>
                                <i class="bi bi-send"></i>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="col-lg-4">
            <!-- Status Action Card -->
            <div class="section-card">
                <div class="card-header">
                    <i class="bi bi-lightning-charge"></i> Review Actions
                </div>
                <div class="card-body p-4">
                    <?php if ($can_edit): ?>
                        <div class="d-grid gap-3">
                            <?php if ($application['current_status'] === STATUS_CHECKLIST_SUBMITTED || $application['current_status'] === 'CATEGORY_FORMS_SUBMITTED'): ?>
                                <button class="action-btn btn btn-warning" onclick="openForwardModal()">
                                    <i class="bi bi-send-check"></i> Forward to UREC
                                </button>
                            <?php
    elseif ($application['current_status'] === STATUS_CATEGORY_FORMS_REQUIRED): ?>
                                <div class="alert alert-info border-0 mb-0 shadow-sm">
                                    <div class="d-flex align-items-center gap-2 mb-2">
                                        <div class="spinner-border spinner-border-sm text-primary" role="status">
                                            <span class="visually-hidden">Loading...</span>
                                        </div>
                                        <span class="fw-bold small">Waiting for Checklist Submission</span>
                                    </div>
                                    <p class="small text-muted mb-0">The application has been categorized. Waiting for the applicant to generate and submit the required categorical checklist.</p>
                                </div>
                            <?php
    elseif ($application['current_status'] === STATUS_UREC_REVIEW_REQUIRED): ?>
                                <div class="alert alert-info border-0 mb-0 shadow-sm">
                                    <div class="d-flex align-items-center gap-2 mb-2">
                                        <div class="spinner-border spinner-border-sm text-primary" role="status">
                                            <span class="visually-hidden">Loading...</span>
                                        </div>
                                        <span class="fw-bold small">Waiting for UREC Manager</span>
                                    </div>
                                    <p class="small text-muted mb-0">This exempt application is ready for UREC Manager (Head) assignment to a committee member.</p>
                                </div>
                            <?php
    elseif (in_array($application['current_status'], [STATUS_FORWARDED_TO_UREC, STATUS_ASSIGNING_UREC_EVALUATOR, STATUS_UNDER_ETHICAL_REVIEW])): ?>
                                <div class="alert alert-primary border-0 mb-0 shadow-sm">
                                    <div class="d-flex align-items-center gap-2 mb-2">
                                        <i class="bi bi-people-fill text-primary"></i>
                                        <span class="fw-bold small">Waiting for UREC Decision</span>
                                    </div>
                                    <p class="small text-muted mb-0">The application has been forwarded to the UREC Committee. Results will be posted once the ethical review is complete.</p>
                                </div>
                            <?php
    elseif (in_array($application['current_status'], [STATUS_APPROVED, STATUS_REJECTED, STATUS_CERTIFICATE_ISSUED])): ?>
                                <div class="alert alert-light border mb-0">
                                    <i class="bi bi-info-circle me-1"></i> Review process completed for this application.
                                </div>
                            <?php
    else: ?>
                                <button class="action-btn btn btn-success" onclick="openApproveModal()">
                                    <i class="bi bi-check-circle"></i> Approve Application
                                </button>
                                <button class="action-btn btn btn-warning text-dark" onclick="openRevisionModal()">
                                    <i class="bi bi-arrow-clockwise"></i> Request Revision
                                </button>
                                <button class="action-btn btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#rejectModal">
                                    <i class="bi bi-x-circle"></i> Reject Application
                                </button>
                            <?php
    endif; ?>
                        </div>
                    <?php
else: ?>
                        <div class="alert alert-warning border-0 small mb-0">
                            <i class="bi bi-lock-fill me-2"></i> This application is currently assigned to <strong><?php echo htmlspecialchars($assigned_staff_name); ?></strong>.
                        </div>
                    <?php
endif; ?>
                </div>
            </div>

            <!-- Fillable Forms Status -->
            <div class="section-card">
                <div class="card-header">
                    <i class="bi bi-clipboard-check"></i> Fillable Forms
                </div>
                <div class="card-body p-4">
                    <?php if (empty($fillable_forms_status)): ?>
                        <div class="text-center py-3 text-muted">
                            <i class="bi bi-file-earmark-x d-block mb-2"></i> No forms submitted yet.
                        </div>
                    <?php else: ?>
                        <?php foreach ($fillable_forms_status as $ftype => $fstatus): 
                            // Skip non-form metadata types and wrappers
                            if (in_array($ftype, ['category_form', 'category_token', 'category'])) continue;
                            
                            $badge_class = $fstatus['completed'] ? 'bg-success' : 'bg-warning text-dark';
                            $badge_text = $fstatus['completed'] ? 'Completed' : 'Draft';
                            $display_name = strtoupper(str_replace(['_', 'form', 'checklist'], [' ', '', ''], $ftype));
                        ?>
                            <div class="mb-3 p-3 border rounded bg-light bg-opacity-50">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="small fw-bold"><?php echo $display_name; ?></span>
                                    <span class="badge <?php echo $badge_class; ?>"><?php echo $badge_text; ?></span>
                                </div>
                                <div class="d-flex gap-2">
                                    <button class="btn btn-sm btn-outline-primary flex-grow-1" onclick="openReviewForm('<?php echo $ftype; ?>')">
                                        <i class="bi bi-eye me-1"></i> Review Data
                                    </button>
                                    <?php if ($ftype === 'qf02' && $fstatus['completed']): ?>
                                        <button class="btn btn-sm btn-light border" onclick="openQf02Remarks()" title="Legacy Editor">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                                <?php if ($fstatus['completed'] && isset($fstatus['completed_at'])): ?>
                                    <div class="mt-2" style="font-size: 0.65rem; color: #888;">
                                        Submitted: <?php echo date('M d, Y h:i A', strtotime($fstatus['completed_at'])); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- AI Insights Card -->
            <?php
$ai_data = null;
$ai_file_path = '../uploads/' . $queue_number . '/ai_classification.json';
if (file_exists($ai_file_path)) {
    $ai_data = json_decode(file_get_contents($ai_file_path), true);
}
if ($ai_data):
?>
                <div class="section-card border-primary-subtle">
                    <div class="card-header bg-primary bg-opacity-10">
                        <i class="bi bi-robot"></i> AI Classification <span class="ai-badge ms-auto">Beta</span>
                    </div>
                    <div class="card-body p-4">
                        <div class="mb-3">
                            <div class="info-label">Predicted Category</div>
                            <div class="fw-bold text-primary"><?php echo $ai_data['ai_prediction']['predicted'] ?? 'Unknown'; ?></div>
                        </div>
                        <div class="mb-3">
                            <div class="info-label">Confidence Score</div>
                            <div class="progress" style="height: 8px;">
                                <div class="progress-bar bg-primary" style="width: <?php echo($ai_data['ai_prediction']['max_score'] ?? 0) * 100; ?>%"></div>
                            </div>
                            <div class="text-end small mt-1"><?php echo round(($ai_data['ai_prediction']['max_score'] ?? 0) * 100); ?>%</div>
                        </div>
                        <button class="btn btn-sm btn-light border w-100" onclick="openAiClassification()">
                            <i class="bi bi-eye me-1"></i> Review AI Analysis
                        </button>
                    </div>
                </div>
            <?php
endif; ?>

            <!-- System Documents Card -->
            <div class="section-card">
                <div class="card-header">
                    <i class="bi bi-clock-history"></i> Status History
                </div>
                <div class="card-body p-4">
                    <?php if ($history->num_rows > 0): ?>
                        <div class="timeline p-3" style="max-height: 400px; overflow-y: auto;">
                            <?php
    $history_items = [];
    while ($h = $history->fetch_assoc()) {
        $history_items[] = $h;
    }
    $history_items = array_reverse($history_items);
    foreach ($history_items as $index => $h):
?>
                                <div class="timeline-item d-flex gap-3 mb-3 <?php echo $index < count($history_items) - 1 ? 'border-bottom pb-3' : ''; ?>">
                                    <div class="timeline-marker flex-shrink-0 <?php echo $h['new_status'] === $application['current_status'] ? 'current' : 'completed'; ?>" style="width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center; background: <?php echo $h['new_status'] === $application['current_status'] ? 'linear-gradient(135deg, #006400, #228B22)' : '#e9ecef'; ?>; color: <?php echo $h['new_status'] === $application['current_status'] ? 'white' : '#6c757d'; ?>; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                                        <?php if ($h['new_status'] === $application['current_status']): ?>
                                            <i class="bi bi-circle-fill" style="font-size: 0.9rem;"></i>
                                        <?php
        else: ?>
                                            <i class="bi bi-check-circle-fill" style="font-size: 0.9rem;"></i>
                                        <?php
        endif; ?>
                                    </div>
                                    <div class="timeline-content flex-grow-1">
                                        <div class="d-flex flex-column gap-1 mb-2">
                                            <span class="badge bg-<?php echo getStatusBadgeClass($h['new_status']); ?> px-2 py-1" style="font-size: 0.75rem; font-weight: 600; width: fit-content;">
                                                <?php echo getStatusDisplayName($h['new_status']); ?>
                                            </span>
                                            <?php if ($h['new_status'] === $application['current_status']): ?>
                                                <span class="badge" style="background: #006400; color: white; font-size: 0.7rem; width: fit-content;">Current</span>
                                            <?php
        endif; ?>
                                        </div>
                                        <div class="d-flex align-items-center gap-2 mb-2">
                                            <small class="text-muted" style="font-size: 0.75rem;">
                                                <i class="bi bi-calendar-event"></i> <?php echo formatDate($h['timestamp']); ?>
                                            </small>
                                            <?php if ($h['changed_by_type'] === 'staff' && $h['changed_by']): ?>
                                                <small class="text-muted" style="font-size: 0.75rem;">
                                                    <i class="bi bi-person"></i> by Staff
                                                </small>
                                            <?php
        elseif ($h['changed_by_type'] === 'system'): ?>
                                                <small class="text-muted" style="font-size: 0.75rem;">
                                                    <i class="bi bi-robot"></i> System
                                                </small>
                                            <?php
        endif; ?>
                                        </div>
                                        <?php if (!empty($h['notes'])): ?>
                                            <div class="alert alert-light p-2 mb-0 mt-2 border" style="background: #F8F9FA; border-color: #E9ECEF; border-radius: 6px; font-size: 0.8rem; line-height: 1.4;">
                                                <div class="text-dark"><?php echo nl2br(htmlspecialchars($h['notes'])); ?></div>
                                            </div>
                                        <?php
        endif; ?>
                                    </div>
                                </div>
                            <?php
    endforeach; ?>
                        </div>
                    <?php
else: ?>
                        <div class="text-center py-4">
                            <i class="bi bi-clock-history display-4 text-muted"></i>
                            <p class="text-muted mt-2 mb-0" style="font-size: 0.9rem;">No status history yet.</p>
                        </div>
                    <?php
endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Email Template Modal -->
    <div class="modal fade" id="emailTemplateModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #006400, #228B22); color: white;">
                    <h5 class="modal-title"><i class="bi bi-megaphone"></i> Send System Message</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="emailTemplateForm" method="POST" action="send-template-email.php">
                        <input type="hidden" name="queue_number" value="<?php echo htmlspecialchars($queue_number); ?>">
                        
                        <!-- Template Selection -->
                        <div class="mb-3">
                            <label class="form-label fw-bold">Select Message Template</label>
                            <select class="form-select" id="templateSelect" name="template_code" required>
                                <option value="">-- Choose a template --</option>
                                <?php
$categories = getTemplateCategories();
foreach ($categories as $cat_code => $cat_name):
    $templates = getEmailTemplates($cat_code);
    if (count($templates) > 0):
?>
                                    <optgroup label="<?php echo htmlspecialchars($cat_name); ?>">
                                        <?php foreach ($templates as $template): ?>
                                            <option value="<?php echo htmlspecialchars($template['template_code']); ?>"
                                                    data-subject="<?php echo htmlspecialchars($template['subject']); ?>"
                                                    data-body="<?php echo htmlspecialchars($template['body']); ?>"
                                                    data-description="<?php echo htmlspecialchars($template['description'] ?? ''); ?>">
                                                <?php echo htmlspecialchars($template['template_name']); ?>
                                            </option>
                                        <?php
        endforeach; ?>
                                    </optgroup>
                                <?php
    endif;
endforeach;
?>
                            </select>
                            <small class="text-muted" id="templateDescription"></small>
                        </div>

                        <!-- Preview Area -->
                        <div id="templatePreview" style="display: none;">
                            <!-- Documents Notice -->
                            <div id="attachmentsNotice" class="alert alert-success" style="display: none;">
                                <i class="bi bi-paperclip"></i> <strong>Documents to Provide:</strong>
                                <ul id="attachmentsList" class="mb-0 mt-2"></ul>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold">Subject</label>
                                <input type="text" class="form-control" id="emailSubject" name="subject" readonly style="background-color: #f8f9fa;">
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold">Message Body</label>
                                <div class="alert alert-info">
                                    <small><i class="bi bi-info-circle"></i> You can edit the message below. Placeholders like {{applicant_name}} will be automatically replaced.</small>
                                </div>
                                <textarea class="form-control" id="emailBody" name="body" rows="12" style="font-family: monospace; font-size: 13px;"></textarea>
                            </div>

                            <!-- Custom Fields for Specific Templates -->
                            <div id="customFields"></div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" form="emailTemplateForm" class="btn text-white" style="background: linear-gradient(135deg, #006400, #228B22);" id="sendEmailBtn" disabled <?php echo !$can_edit ? 'style="pointer-events: none; opacity: 0.5;"' : ''; ?></button>>
                        <i class="bi bi-send"></i> Send Message
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Approve Application Modal -->
    <div class="modal fade" id="approveModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #198754, #28a745); color: white;">
                    <h5 class="modal-title"><i class="bi bi-check-circle"></i> Approve Application</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-0" style="overflow: hidden;">
                    <iframe id="approveFrame" style="width: 100%; height: 60vh; border: none;"></iframe>
                </div>
            </div>
        </div>
    </div>

    <!-- Revision Modal -->
    <div class="modal fade" id="revisionModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title"><i class="bi bi-arrow-clockwise"></i> Request Revision</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-0" style="overflow: hidden;">
                    <iframe id="revisionFrame" style="width: 100%; height: 70vh; border: none;"></iframe>
                </div>
            </div>
        </div>
    </div>

    <!-- AI Classification Modal -->
    <?php if ($ai_classification): ?>
    <div class="modal fade" id="aiClassificationModal" tabindex="-1">
        <div class="modal-dialog modal-fullscreen">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #667eea, #764ba2); color: white;">
                    <h5 class="modal-title"><i class="bi bi-robot"></i> AI Classification Review</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-0" style="overflow: hidden;">
                    <iframe id="aiClassificationFrame" style="width: 100%; height: calc(100vh - 56px); border: none;"></iframe>
                </div>
            </div>
        </div>
    </div>
    <?php
endif; ?>

    <!-- QF02 Remarks Modal -->
    <div class="modal fade" id="qf02RemarksModal" tabindex="-1">
        <div class="modal-dialog modal-fullscreen">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #006400, #228B22); color: white;">
                    <h5 class="modal-title"><i class="bi bi-pencil-square"></i> Edit QF-02 Remarks</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-0" style="overflow: hidden;">
                    <iframe id="qf02RemarksFrame" style="width: 100%; height: calc(100vh - 56px); border: none;"></iframe>
                </div>
            </div>
        </div>
    </div>

    <!-- Unified Form Reviewer Modal -->
    <div class="modal fade" id="reviewFormModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1">
        <div class="modal-dialog modal-fullscreen">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #006400, #228B22); color: white;">
                    <h5 class="modal-title"><i class="bi bi-clipboard-check"></i> Unified Form Reviewer</h5>
                    <button type="button" class="btn-close btn-close-white" onclick="closeReviewForm()"></button>
                </div>
                <div class="modal-body p-0" style="background: #f4f7f6; overflow: hidden;">
                    <iframe id="reviewFormFrame" src="" style="width: 100%; height: calc(100vh - 56px); border: none;"></iframe>
                </div>
            </div>
        </div>
    </div>

    <!-- Document Preview Modal -->
    <div class="modal fade" id="previewModal" tabindex="-1">
        <div class="modal-dialog modal-fullscreen">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #006400, #228B22); color: white;">
                    <h5 class="modal-title" id="previewModalTitle"><i class="bi bi-files"></i> Document Preview</h5>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-success btn-sm" id="validateDocBtn" onclick="validateCurrentDocument()" style="display: none;">
                            <i class="bi bi-check-circle"></i> Validate
                        </button>
                        <button type="button" class="btn btn-danger btn-sm" id="rejectDocBtn" onclick="rejectCurrentDocument()" style="display: none;">
                            <i class="bi bi-x-circle"></i> Reject
                        </button>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                </div>
                <div class="modal-body p-0" style="overflow: hidden;">
                    <iframe id="documentFrame" style="width: 100%; height: calc(100vh - 120px); border: none;"></iframe>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="rejectModal" tabindex="-1">
        <div class="modal-dialog">
            <form method="POST" action="process-action.php">
                <input type="hidden" name="queue_number" value="<?php echo htmlspecialchars($queue_number); ?>">
                <input type="hidden" name="action" value="reject">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Reject Application</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-danger">
                            <i class="bi bi-exclamation-triangle"></i> <strong>Warning:</strong> This action will reject the application.
                        </div>
                        <p>Please provide a reason for rejection:</p>
                        <div class="mb-3">
                            <label class="form-label">Rejection Reason <span class="text-danger">*</span></label>
                            <textarea class="form-control" name="notes" rows="5" required placeholder="Explain the reason for rejection..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger" <?php echo !$can_edit ? 'disabled' : ''; ?>>Reject Application</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="forwardModal" tabindex="-1">
        <div class="modal-dialog">
            <form id="forwardForm" method="POST" action="process-forward-urec.php">
                <input type="hidden" name="queue_number" value="<?php echo htmlspecialchars($queue_number); ?>">
                <div class="modal-content">
                    <div class="modal-header" style="background: linear-gradient(135deg, #0d6efd, #0b5ed7); color: white;">
                        <h5 class="modal-title"><i class="bi bi-send-check"></i> Forward to UREC Committee</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-primary border-0 bg-primary bg-opacity-10">
                            <div class="d-flex gap-3">
                                <i class="bi bi-info-circle fs-4 text-primary"></i>
                                <div>
                                    <span class="fw-bold d-block">Ethical Review Transition</span>
                                    <span class="small">This will transition the application status to <strong>Under Ethical Review</strong>. The UREC Committee will now be able to review all provided checklists and documents.</span>
                                </div>
                            </div>
                        </div>
                        <p class="mb-3">Are you sure the categorical checklist is satisfactory and ready for official committee review?</p>
                        <div class="mb-0">
                            <label class="form-label fw-bold small">Internal Processing Notes (Optional)</label>
                            <textarea class="form-control" name="notes" rows="3" placeholder="Add any specific instructions or observations for the UREC committee..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer bg-light">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel Action</button>
                        <button type="submit" class="btn btn-warning px-4">Confirm & Forward</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

<!-- Full Message Modal -->
<div class="modal fade" id="fullMessageModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #006400, #228B22); color: white;">
                <h5 class="modal-title"><i class="bi bi-envelope-open"></i> <span id="modalSubject"></span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="badge bg-info" id="modalType"></span>
                        <small class="text-muted"><i class="bi bi-calendar-event"></i> <span id="modalDate"></span></small>
                    </div>
                    <div class="bg-light p-4 rounded border" id="modalMessage"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
// System Message Template Handler
document.getElementById('templateSelect').addEventListener('change', function() {
    const selectedOption = this.options[this.selectedIndex];
    const preview = document.getElementById('templatePreview');
    const sendBtn = document.getElementById('sendEmailBtn');
    const description = document.getElementById('templateDescription');
    const customFields = document.getElementById('customFields');
    const attachmentsNotice = document.getElementById('attachmentsNotice');
    const attachmentsList = document.getElementById('attachmentsList');
    
    if (this.value) {
        // Show preview
        preview.style.display = 'block';
        sendBtn.disabled = false;
        
        // Set subject and body
        document.getElementById('emailSubject').value = selectedOption.dataset.subject;
        document.getElementById('emailBody').value = selectedOption.dataset.body;
        description.textContent = selectedOption.dataset.description;
        
        // Clear custom fields
        customFields.innerHTML = '';
        
        // Show documents notice for templates with documents to provide
        const templateCode = this.value;
        
        if (templateCode === 'REPLY_INTENT') {
            attachmentsNotice.style.display = 'block';
            attachmentsList.innerHTML = `
                <li>TAU-REO-QF-01 Application Form.docx</li>
                <li>TAU-REO-QF-02 Review Category Form.docx</li>
                <li>General Guidelines.pdf</li>
            `;
        } else {
            attachmentsNotice.style.display = 'none';
            attachmentsList.innerHTML = '';
        }
        
        // Add custom fields based on template
        
        if (templateCode === 'INCOMPLETE_DOCS') {
            customFields.innerHTML = `
                <div class="mb-3">
                    <label class="form-label fw-bold text-danger">Missing Documents <span class="text-danger">*</span></label>
                    <textarea class="form-control" name="missing_documents" rows="4" placeholder="• Document 1\n• Document 2\n• Document 3" required></textarea>
                    <small class="text-muted">List each missing document on a new line</small>
                </div>
            `;
        } else if (templateCode === 'MISSING_SIGNATURES') {
            customFields.innerHTML = `
                <div class="mb-3">
                    <label class="form-label fw-bold text-danger">Documents Missing Signatures <span class="text-danger">*</span></label>
                    <textarea class="form-control" name="unsigned_documents" rows="4" placeholder="• Application Form\n• Consent Form\n• Research Proposal" required></textarea>
                    <small class="text-muted">List each document that needs signatures</small>
                </div>
            `;
        } else if (templateCode === 'CONDITIONAL_APPROVAL') {
            customFields.innerHTML = `
                <div class="mb-3">
                    <label class="form-label fw-bold text-warning">Conditions <span class="text-danger">*</span></label>
                    <textarea class="form-control" name="conditions" rows="4" placeholder="• Condition 1\n• Condition 2" required></textarea>
                    <small class="text-muted">List conditions that must be met</small>
                </div>
            `;
        } else if (templateCode === 'REJECTED') {
            customFields.innerHTML = `
                <div class="mb-3">
                    <label class="form-label fw-bold text-danger">Rejection Reason <span class="text-danger">*</span></label>
                    <textarea class="form-control" name="rejection_reason" rows="4" placeholder="Explain the reason for rejection..." required></textarea>
                </div>
            `;
        } else if (templateCode === 'REVISIONS_NEEDED') {
            customFields.innerHTML = `
                <div class="mb-3">
                    <label class="form-label fw-bold text-warning">Required Revisions <span class="text-danger">*</span></label>
                    <textarea class="form-control" name="revisions_list" rows="4" placeholder="• Revision 1\n• Revision 2" required></textarea>
                    <small class="text-muted">List all required revisions</small>
                </div>
            `;
        } else if (templateCode === 'GENERAL_UPDATE') {
            customFields.innerHTML = `
                <div class="mb-3">
                    <label class="form-label fw-bold">Message Content <span class="text-danger">*</span></label>
                    <textarea class="form-control" name="message_content" rows="4" placeholder="Enter your custom message here..." required></textarea>
                </div>
            `;
        }
    } else {
        preview.style.display = 'none';
        sendBtn.disabled = true;
        description.textContent = '';
    }
});

// Reset form when modal closes
document.getElementById('emailTemplateModal').addEventListener('hidden.bs.modal', function() {
    document.getElementById('emailTemplateForm').reset();
    document.getElementById('templatePreview').style.display = 'none';
    document.getElementById('sendEmailBtn').disabled = true;
    document.getElementById('templateDescription').textContent = '';
    document.getElementById('customFields').innerHTML = '';
    document.getElementById('attachmentsNotice').style.display = 'none';
    document.getElementById('attachmentsList').innerHTML = '';
});

// Auto-trigger download if download parameter is present
<?php if (isset($_GET['download']) && $_GET['download'] === 'qf02'): ?>
window.addEventListener('DOMContentLoaded', function() {
    // Trigger download
    window.location.href = 'view-application.php?queue=<?php echo urlencode($queue_number); ?>&download=qf02';
});
<?php
endif; ?>

// AI Classification Feedback Handler
function openDocumentViewer() {
    const iframe = document.getElementById('documentViewerFrame');
    iframe.src = 'document-viewer.php?queue=<?php echo urlencode($queue_number); ?>';
    
    new bootstrap.Modal(document.getElementById('documentViewerModal')).show();
}

// Listen for messages from document viewer iframe
window.addEventListener('message', function(event) {
    if (event.data && event.data.type === 'documentValidated') {
        if (event.data.success) {
            // Close modal and reload page
            const modal = bootstrap.Modal.getInstance(document.getElementById('documentViewerModal'));
            if (modal) modal.hide();
            window.location.reload();
        }
    }
});

let currentDocumentId = null;

function previewDocument(path, name, documentId, validationStatus) {
    if (!path || path === '') {
        alert('Document path is not available.');
        return;
    }
    
    currentDocumentId = documentId;
    const modal = new bootstrap.Modal(document.getElementById('previewModal'));
    document.getElementById('previewModalTitle').innerHTML = '<i class="bi bi-files"></i> ' + (name || 'Document Preview');
    
    const iframe = document.getElementById('documentFrame');
    
    // Clear previous content
    iframe.src = '';
    
    modal.show();
    
    // Set iframe source
    iframe.src = 'view-document.php?path=' + encodeURIComponent(path);
    
    // Update button visibility based on validation status and user permissions
    const validateBtn = document.getElementById('validateDocBtn');
    const rejectBtn = document.getElementById('rejectDocBtn');
    
    if (validateBtn && rejectBtn) {
        // Only show buttons if user can edit AND document is not already validated
        if (<?php echo $can_edit ? 'true' : 'false'; ?> && validationStatus !== 'validated') {
            validateBtn.style.display = 'inline-block';
            rejectBtn.style.display = 'inline-block';
        } else {
            validateBtn.style.display = 'none';
            rejectBtn.style.display = 'none';
        }
    }
}

function validateCurrentDocument() {
    if (!currentDocumentId) return;
    
    if (confirm('Are you sure you want to validate this document?')) {
        performDocumentAction('validate', currentDocumentId);
    }
}

function rejectCurrentDocument() {
    if (!currentDocumentId) return;
    
    const notes = prompt('Please provide a reason for rejection (optional):');
    if (notes !== null) {
        performDocumentAction('reject', currentDocumentId, notes);
    }
}

function performDocumentAction(action, documentId, notes = '') {
    const formData = new FormData();
    formData.append('document_id', documentId);
    formData.append('action', action);
    formData.append('notes', notes);
    
    fetch('validate-document.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            bootstrap.Modal.getInstance(document.getElementById('previewModal')).hide();
            window.location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        alert('Error processing document: ' + error.message);
    });
}

function openAiClassification() {
    const iframe = document.getElementById('aiClassificationFrame');
    iframe.src = 'ai-classification.php?queue=<?php echo urlencode($queue_number); ?>';
    
    new bootstrap.Modal(document.getElementById('aiClassificationModal')).show();
}

function openQf02Remarks() {
    const iframe = document.getElementById('qf02RemarksFrame');
    iframe.src = 'edit-qf02-remarks.php?queue=<?php echo urlencode($queue_number); ?>&modal=1';
    
    new bootstrap.Modal(document.getElementById('qf02RemarksModal')).show();
}

function openReviewForm(type) {
    const iframe = document.getElementById('reviewFormFrame');
    iframe.src = 'review-form.php?queue=<?php echo urlencode($queue_number); ?>&type=' + type;
    
    new bootstrap.Modal(document.getElementById('reviewFormModal')).show();
}

function closeReviewForm() {
    if (confirm('Make sure you have saved your remarks. Close the reviewer?')) {
        bootstrap.Modal.getInstance(document.getElementById('reviewFormModal')).hide();
        window.location.reload();
    }
}

function showFullMessage(index) {
    const data = window.messageData[index];
    if (!data) return;
    
    document.getElementById('modalSubject').textContent = data.subject;
    document.getElementById('modalMessage').innerHTML = data.message.replace(/\n/g, '<br>');
    document.getElementById('modalDate').textContent = data.date;
    
    const typeBadge = document.getElementById('modalType');
    typeBadge.textContent = data.type.charAt(0).toUpperCase() + data.type.slice(1);
    
    // Set badge color based on type
    typeBadge.className = 'badge bg-' + 
        (data.type === 'approval' ? 'success' : 
         data.type === 'rejection' ? 'danger' : 
         data.type === 'update' ? 'warning' : 'info');
    
    new bootstrap.Modal(document.getElementById('fullMessageModal')).show();
}

function openApproveModal() {
    const iframe = document.getElementById('approveFrame');
    iframe.src = 'approve-application.php?queue=<?php echo urlencode($queue_number); ?>';
    
    new bootstrap.Modal(document.getElementById('approveModal')).show();
}

// Listen for messages from AI classification iframe
window.addEventListener('message', function(event) {
    if (event.data && event.data.type === 'aiReviewCompleted') {
        if (event.data.success) {
            // Close modal and reload page
            const modal = bootstrap.Modal.getInstance(document.getElementById('aiClassificationModal'));
            if (modal) modal.hide();
            window.location.reload();
        }
    }
    
    // Handle QF02 remarks modal messages
    if (event.data && event.data.type === 'qf02RemarksCompleted') {
        if (event.data.success) {
            // Trigger download if URL provided
            if (event.data.download_url) {
                const link = document.createElement('a');
                link.href = 'edit-qf02-remarks.php?download=' + encodeURIComponent(event.data.download_url);
                link.download = '';
                link.target = '_blank';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            }
            
            // Close modal and reload page
            const modal = bootstrap.Modal.getInstance(document.getElementById('qf02RemarksModal'));
            if (modal) modal.hide();
            window.location.reload();
        }
    }
    
    // Handle revision modal messages
    if (event.data && event.data.type === 'closeRevisionModal') {
        const modal = bootstrap.Modal.getInstance(document.getElementById('revisionModal'));
        if (modal) modal.hide();
    }
    
    if (event.data && event.data.type === 'revisionRequestSent') {
        if (event.data.success) {
            const modal = bootstrap.Modal.getInstance(document.getElementById('revisionModal'));
            if (modal) modal.hide();
            window.location.reload();
        }
    }
    
    // Handle approve modal messages
    if (event.data && event.data.type === 'closeApproveModal') {
        const modal = bootstrap.Modal.getInstance(document.getElementById('approveModal'));
        if (modal) modal.hide();
    }
    
    if (event.data && event.data.type === 'applicationApproved') {
        if (event.data.success) {
            const modal = bootstrap.Modal.getInstance(document.getElementById('approveModal'));
            if (modal) modal.hide();
            window.location.reload();
        }
    }
});

function openForwardModal() {
    new bootstrap.Modal(document.getElementById('forwardModal')).show();
}

function openRevisionModal() {
    const iframe = document.getElementById('revisionFrame');
    iframe.src = 'request-revision.php?queue=<?php echo urlencode($queue_number); ?>';
    
    new bootstrap.Modal(document.getElementById('revisionModal')).show();
}

// Handle forward form submission
document.getElementById('forwardForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    
    const submitBtn = this.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    
    // Show loading state
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="bi bi-hourglass-split me-2"></i>Processing...';
    
    // Create form data
    const formData = new FormData(this);
    
    // Submit via AJAX
    fetch('process-forward-urec.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Close modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('forwardModal'));
            if (modal) modal.hide();
            
            // Show success message
            alert(data.message);
            
            // Reload page to show new status
            window.location.reload();
        } else {
            // Show error
            alert('Error: ' + data.message);
            
            // Reset button
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Communication error. Please try again.');
        
        // Reset button
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
    });
});

</script>

<?php include '../includes/auth_footer.php'; ?>
