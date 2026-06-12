<?php
/**
 * View Application Details (Staff)
 * TAU-UREO Portal
 * Improved UI Version - FULL COMPLETE
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
    
    if (file_exists($file)) {
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
        padding: 1.25rem 1.5rem;
        border-radius: 12px;
        box-shadow: 0 2px 12px rgba(0,0,0,0.05);
        margin-bottom: 1.5rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 1rem;
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
        border-radius: 12px;
        background: white;
        box-shadow: 0 2px 15px rgba(0,0,0,0.04);
        margin-bottom: 1.5rem;
        overflow: hidden;
    }

    .section-card .card-header {
        background: white;
        border-bottom: 1px solid #f0f0f0;
        padding: 1.25rem 1.5rem;
        font-weight: 700;
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
        height: 450px;
        overflow-y: auto;
        padding: 1.5rem;
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
        border-bottom: 2px solid #f0f0f0;
        gap: 1.5rem;
    }

    .nav-tabs-custom .nav-link {
        border: none;
        color: #888;
        font-weight: 600;
        padding: 1rem 0.5rem;
        position: relative;
        background: transparent;
    }

    .nav-tabs-custom .nav-link.active {
        color: var(--tau-green-dark);
        background: transparent;
    }

    .nav-tabs-custom .nav-link.active::after {
        content: '';
        position: absolute;
        bottom: -2px;
        left: 0;
        width: 100%;
        height: 2px;
        background: var(--tau-green-dark);
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
</style>

<div class="container-fluid py-4">
    <!-- Alerts -->
    <?php if ($just_claimed): ?>
        <div class="alert alert-success border-0 shadow-sm d-flex align-items-center mb-4">
            <i class="bi bi-check-circle-fill fs-4 me-3"></i>
            <div><strong>Success!</strong> This application has been automatically assigned to you for review.</div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
            <div class="d-flex align-items-center">
                <i class="bi bi-check-circle-fill fs-4 me-3"></i>
                <div>
                    <?php if ($_GET['success'] === 'message_sent'): ?>
                        System message sent successfully!
                    <?php elseif ($_GET['success'] === 'action_completed'): ?>
                        Action completed successfully!
                    <?php elseif ($_GET['success'] === 'remarks_saved'): ?>
                        QF-02 remarks saved successfully! Document will download automatically.
                    <?php endif; ?>
                </div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_GET['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
            <div class="d-flex align-items-center">
                <i class="bi bi-exclamation-triangle-fill fs-4 me-3"></i>
                <div>
                    <?php if ($_GET['error'] === 'empty_message'): ?>
                        Please enter a message.
                    <?php elseif ($_GET['error'] === 'send_failed'): ?>
                        Failed to send message. Please try again.
                    <?php elseif ($_GET['error'] === 'action_failed'): ?>
                        Failed to process action. Please try again.
                    <?php elseif ($_GET['error'] === 'message_failed'): ?>
                        Failed to send system message. Please try again.
                    <?php elseif ($_GET['error'] === 'invalid_request'): ?>
                        Invalid request parameters.
                    <?php endif; ?>
                </div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Header -->
    <div class="app-header">
        <div class="d-flex align-items-center gap-3">
            <a href="dashboard.php" class="btn btn-light border btn-sm">
                <i class="bi bi-arrow-left"></i>
            </a>
            <h4 class="mb-0 fw-bold">Application Details</h4>
        </div>
        <div class="d-flex align-items-center gap-3">
            <div class="queue-badge">
                <i class="bi bi-hash"></i> <?php echo htmlspecialchars($queue_number); ?>
            </div>
            <div class="status-pill bg-light border text-dark">
                <i class="bi bi-info-circle"></i> <?php echo getStatusDisplayName($application['current_status']); ?>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Left Column: Main Info -->
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
                                        <?php else: ?>
                                            <span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25"><?php echo htmlspecialchars($assigned_staff_name); ?></span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25">Unassigned</span>
                                    <?php endif; ?>
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
                                            case 'INTENT_RECEIVED': $progress = 20; break;
                                            case 'REQUIREMENTS_SENT':
                                            case 'REQUIREMENTS_PENDING':
                                            case 'REQUIREMENTS_INCOMPLETE': $progress = 40; break;
                                            case 'REGISTERED':
                                            case 'UNDER_AUTO_REVIEW':
                                            case 'STAFF_REVIEW_REQUIRED':
                                            case 'UNDER_STAFF_REVIEW':
                                            case 'REVISIONS_REQUIRED':
                                            case 'CATEGORIZED': $progress = 60; break;
                                            case 'FORWARDED_TO_UREC':
                                            case 'UNDER_ETHICAL_REVIEW':
                                            case 'COMPLIANCE_PENDING':
                                            case 'COMPLIANCE_REVIEW': $progress = 80; break;
                                            case 'APPROVED':
                                            case 'CERTIFICATE_ISSUED': $progress = 100; break;
                                            case 'REJECTED': $progress = 100; break;
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

            <!-- Tabs for Documents, Messages, History -->
            <div class="section-card">
                <div class="card-body p-0">
                    <ul class="nav nav-tabs nav-tabs-custom px-4" id="appTabs" role="tablist">
                        <li class="nav-item">
                            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-docs">
                                <i class="bi bi-files me-2"></i> Documents
                            </button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-msgs">
                                <i class="bi bi-chat-dots me-2"></i> Messages
                            </button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-sys-msgs">
                                <i class="bi bi-megaphone me-2"></i> System Messages
                            </button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-history">
                                <i class="bi bi-clock-history me-2"></i> History
                            </button>
                        </li>
                    </ul>

                    <div class="tab-content p-4">
                        <!-- Documents Tab -->
                        <div class="tab-pane fade show active" id="tab-docs">
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <h6 class="fw-bold mb-0">Submitted Files</h6>
                                <span class="badge bg-light text-dark border"><?php echo $documents->num_rows; ?> Files</span>
                            </div>
                            
                            <div class="row g-3">
                                <?php while ($doc = $documents->fetch_assoc()): ?>
                                    <div class="col-md-6">
                                        <div class="document-item">
                                            <div class="d-flex align-items-center gap-3">
                                                <div class="fs-2 text-primary">
                                                    <?php 
                                                        $ext = pathinfo($doc['file_path'], PATHINFO_EXTENSION);
                                                        if (in_array($ext, ['pdf'])) echo '<i class="bi bi-file-earmark-pdf"></i>';
                                                        elseif (in_array($ext, ['doc', 'docx'])) echo '<i class="bi bi-file-earmark-word"></i>';
                                                        else echo '<i class="bi bi-file-earmark"></i>';
                                                    ?>
                                                </div>
                                                <div class="flex-grow-1 overflow-hidden">
                                                    <div class="fw-bold text-truncate small"><?php echo htmlspecialchars($doc['document_type']); ?></div>
                                                    <div class="text-muted" style="font-size: 0.7rem;"><?php echo formatDate($doc['upload_timestamp']); ?></div>
                                                    <?php if ($doc['validation_status'] === 'validated'): ?>
                                                        <span class="badge bg-success badge-sm"><i class="bi bi-check-circle"></i> Validated</span>
                                                    <?php elseif ($doc['validation_status'] === 'rejected'): ?>
                                                        <span class="badge bg-danger badge-sm"><i class="bi bi-x-circle"></i> Rejected</span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="d-flex gap-1">
                                                    <button type="button" class="btn btn-sm btn-light border" onclick="previewDocument('<?php echo addslashes($doc['file_path']); ?>', '<?php echo addslashes($doc['document_type']); ?>', <?php echo $doc['document_id']; ?>, '<?php echo $doc['validation_status']; ?>')">
                                                        <i class="bi bi-eye"></i>
                                                    </button>
                                                    <a href="../uploads/<?php echo $queue_number; ?>/<?php echo basename($doc['file_path']); ?>" class="btn btn-sm btn-light border" target="_blank">
                                                        <i class="bi bi-download"></i>
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        </div>

                        <!-- Messages Tab -->
                        <div class="tab-pane fade" id="tab-msgs">
                            <div class="chat-container rounded-3 border mb-3">
                                <?php if ($messages->num_rows > 0): ?>
                                    <?php while ($msg = $messages->fetch_assoc()): ?>
                                        <div class="msg-bubble <?php echo $msg['sender_type'] === 'staff' ? 'msg-sent' : 'msg-received'; ?>">
                                            <div class="fw-bold small mb-1">
                                                <?php echo $msg['sender_type'] === 'staff' ? 'You' : htmlspecialchars($application['applicant_name']); ?>
                                            </div>
                                            <div><?php echo nl2br(htmlspecialchars($msg['message_text'])); ?></div>
                                            <div class="msg-meta">
                                                <?php echo date('M d, h:i A', strtotime($msg['sent_at'])); ?>
                                                <?php if ($msg['sender_type'] === 'staff' && $msg['read_status']): ?>
                                                    <i class="bi bi-check2-all ms-1"></i>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <div class="text-center py-5 text-muted">
                                        <i class="bi bi-chat-left-dots display-4 d-block mb-3"></i>
                                        No messages yet. Start a conversation below.
                                    </div>
                                <?php endif; ?>
                            </div>

                            <form action="process-action.php" method="POST">
                                <input type="hidden" name="queue_number" value="<?php echo $queue_number; ?>">
                                <input type="hidden" name="action" value="send_message">
                                <div class="input-group">
                                    <textarea name="message" class="form-control" rows="2" placeholder="Type your message to the applicant..." required></textarea>
                                    <button type="submit" class="btn btn-success px-4" <?php echo !$can_edit ? 'disabled' : ''; ?>>
                                        <i class="bi bi-send"></i>
                                    </button>
                                </div>
                            </form>
                        </div>

                        <!-- System Messages Tab -->
                        <div class="tab-pane fade" id="tab-sys-msgs">
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <h6 class="fw-bold mb-0">Sent Notifications</h6>
                                <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#emailTemplateModal" <?php echo !$can_edit ? 'disabled' : ''; ?>>
                                    <i class="bi bi-plus-circle me-1"></i> Send New
                                </button>
                            </div>
                            
                            <?php if ($system_messages->num_rows > 0): ?>
                                <div class="row g-3">
                                    <?php while ($sys_msg = $system_messages->fetch_assoc()): ?>
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
                                                    <?php endif; ?>
                                                </div>
                                                <div class="bg-white p-3 rounded border small" style="max-height: 150px; overflow-y: auto;">
                                                    <?php echo nl2br(htmlspecialchars($sys_msg['message_body'])); ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endwhile; ?>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-5 text-muted">
                                    <i class="bi bi-megaphone display-4 d-block mb-3"></i>
                                    No system messages sent yet.
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- History Tab -->
                        <div class="tab-pane fade" id="tab-history">
                            <div class="timeline-wrapper">
                                <?php while ($h = $history->fetch_assoc()): ?>
                                    <div class="mb-4 position-relative">
                                        <div class="timeline-point"></div>
                                        <div class="ms-3">
                                            <div class="fw-bold small"><?php echo getStatusDisplayName($h['status']); ?></div>
                                            <div class="text-muted small mb-1"><?php echo formatDate($h['timestamp']); ?></div>
                                            <?php if ($h['remarks']): ?>
                                                <div class="bg-light p-2 rounded small border-start border-4 border-success">
                                                    <?php echo htmlspecialchars($h['remarks']); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column: Actions & Sidebar -->
        <div class="col-lg-4">
            <!-- Status Action Card -->
            <div class="section-card">
                <div class="card-header">
                    <i class="bi bi-lightning-charge"></i> Review Actions
                </div>
                <div class="card-body p-4">
                    <?php if ($can_edit): ?>
                        <div class="d-grid gap-3">
                            <button class="action-btn btn btn-success" onclick="openApproveModal()">
                                <i class="bi bi-check-circle"></i> Approve Application
                            </button>
                            <button class="action-btn btn btn-warning text-dark" onclick="openRevisionModal()">
                                <i class="bi bi-arrow-clockwise"></i> Request Revision
                            </button>
                            <button class="action-btn btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#rejectModal">
                                <i class="bi bi-x-circle"></i> Reject Application
                            </button>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-warning border-0 small mb-0">
                            <i class="bi bi-lock-fill me-2"></i> This application is currently assigned to <strong><?php echo htmlspecialchars($assigned_staff_name); ?></strong>.
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Fillable Forms Status -->
            <div class="section-card">
                <div class="card-header">
                    <i class="bi bi-clipboard-check"></i> Fillable Forms
                </div>
                <div class="card-body p-4">
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="small fw-bold">TAU-REO-QF-02</span>
                            <?php if (isset($fillable_forms_status['qf02']) && $fillable_forms_status['qf02']['completed']): ?>
                                <span class="badge bg-success">Completed</span>
                            <?php else: ?>
                                <span class="badge bg-warning text-dark">Pending</span>
                            <?php endif; ?>
                        </div>
                        <?php if (isset($fillable_forms_status['qf02']) && $fillable_forms_status['qf02']['completed']): ?>
                            <a href="edit-qf02-remarks.php?queue=<?php echo urlencode($queue_number); ?>" class="btn btn-sm btn-outline-primary w-100">
                                <i class="bi bi-pencil-square me-1"></i> Edit Remarks
                            </a>
                        <?php endif; ?>
                    </div>
                    <hr>
                    <div class="mb-0">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="small fw-bold">TAU-REO-QF-01</span>
                            <?php if (isset($fillable_forms_status['qf01']) && $fillable_forms_status['qf01']['completed']): ?>
                                <span class="badge bg-success">Completed</span>
                            <?php else: ?>
                                <span class="badge bg-warning text-dark">Pending</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- AI Insights Card -->
            <?php if ($ai_classification): ?>
                <div class="section-card border-primary-subtle">
                    <div class="card-header bg-primary bg-opacity-10">
                        <i class="bi bi-robot"></i> AI Classification <span class="ai-badge ms-auto">Beta</span>
                    </div>
                    <div class="card-body p-4">
                        <div class="mb-3">
                            <div class="info-label">Predicted Category</div>
                            <div class="fw-bold text-primary"><?php echo $ai_classification['category'] ?? 'Unknown'; ?></div>
                        </div>
                        <div class="mb-3">
                            <div class="info-label">Confidence Score</div>
                            <div class="progress" style="height: 8px;">
                                <div class="progress-bar bg-primary" style="width: <?php echo ($ai_classification['confidence'] ?? 0) * 100; ?>%"></div>
                            </div>
                            <div class="text-end small mt-1"><?php echo round(($ai_classification['confidence'] ?? 0) * 100); ?>%</div>
                        </div>
                        <button class="btn btn-sm btn-primary w-100" onclick="openAiClassification()">
                            <i class="bi bi-eye me-1"></i> Review AI Analysis
                        </button>
                    </div>
                </div>
            <?php endif; ?>

            <!-- System Documents Card -->
            <div class="section-card">
                <div class="card-header">
                    <i class="bi bi-file-earmark-medical"></i> Provided Documents
                </div>
                <div class="card-body p-4">
                    <?php if ($system_documents->num_rows > 0): ?>
                        <div class="list-group list-group-flush">
                            <?php while ($sdoc = $system_documents->fetch_assoc()): ?>
                                <div class="list-group-item px-0 py-2 border-0 border-bottom">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div class="overflow-hidden">
                                            <div class="fw-bold small text-truncate"><?php echo htmlspecialchars($sdoc['document_name']); ?></div>
                                            <div class="text-muted small" style="font-size: 0.7rem;"><?php echo formatDate($sdoc['provided_at']); ?></div>
                                        </div>
                                        <a href="../uploads/system/<?php echo basename($sdoc['file_path']); ?>" class="btn btn-xs btn-light border" target="_blank">
                                            <i class="bi bi-download"></i>
                                        </a>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-3 text-muted small">
                            No system documents provided yet.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modals -->
<!-- Email Template Modal -->
<div class="modal fade" id="emailTemplateModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-envelope-paper"></i> Send System Message</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <form id="emailTemplateForm" action="process-action.php" method="POST">
                    <input type="hidden" name="queue_number" value="<?php echo htmlspecialchars($queue_number); ?>">
                    <input type="hidden" name="action" value="send_system_message">
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Select Template</label>
                        <select class="form-select" id="templateSelect" name="template_code" required>
                            <option value="">-- Choose a template --</option>
                            <?php 
                            $templates = getEmailTemplates();
                            foreach ($templates as $code => $template): 
                                if ($template['active']):
                            ?>
                                <option value="<?php echo $code; ?>" 
                                        data-subject="<?php echo htmlspecialchars($template['subject']); ?>" 
                                        data-body="<?php echo htmlspecialchars($template['body']); ?>"
                                        data-description="<?php echo htmlspecialchars($template['description']); ?>">
                                    <?php echo htmlspecialchars($template['name']); ?>
                                </option>
                            <?php 
                                endif;
                            endforeach; 
                            ?>
                        </select>
                        <small class="text-muted" id="templateDescription"></small>
                    </div>

                    <div id="templatePreview" style="display: none;">
                        <div id="attachmentsNotice" class="alert alert-success bg-opacity-10 border-success text-success py-2" style="display: none;">
                            <i class="bi bi-paperclip"></i> <strong>Documents to Provide:</strong>
                            <ul id="attachmentsList" class="mb-0 mt-1 small"></ul>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Subject</label>
                            <input type="text" class="form-control bg-light" id="emailSubject" name="subject" readonly>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Message Body</label>
                            <div class="alert alert-info py-2 small mb-2">
                                <i class="bi bi-info-circle"></i> You can edit the message below. Placeholders like <code>{{applicant_name}}</code> will be replaced.
                            </div>
                            <textarea class="form-control" id="emailBody" name="body" rows="10" style="font-family: monospace; font-size: 13px;"></textarea>
                        </div>

                        <div id="customFields"></div>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="emailTemplateForm" class="btn btn-success px-4" id="sendEmailBtn" disabled>
                    <i class="bi bi-send"></i> Send Message
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Approve Application Modal -->
<div class="modal fade" id="approveModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-check-circle"></i> Approve Application</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <iframe id="approveFrame" style="width: 100%; height: 60vh; border: none;"></iframe>
            </div>
        </div>
    </div>
</div>

<!-- Revision Modal -->
<div class="modal fade" id="revisionModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-warning">
                <h5 class="modal-title fw-bold"><i class="bi bi-arrow-clockwise"></i> Request Revision</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <iframe id="revisionFrame" style="width: 100%; height: 70vh; border: none;"></iframe>
            </div>
        </div>
    </div>
</div>

<!-- AI Classification Modal -->
<?php if ($ai_classification): ?>
<div class="modal fade" id="aiClassificationModal" tabindex="-1">
    <div class="modal-dialog modal-fullscreen">
        <div class="modal-content border-0">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-robot"></i> AI Classification Review</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <iframe id="aiClassificationFrame" style="width: 100%; height: calc(100vh - 56px); border: none;"></iframe>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Document Preview Modal -->
<div class="modal fade" id="previewModal" tabindex="-1">
    <div class="modal-dialog modal-fullscreen">
        <div class="modal-content border-0">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title fw-bold" id="previewModalTitle"><i class="bi bi-files"></i> Document Preview</h5>
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
            <div class="modal-body p-0 bg-secondary">
                <iframe id="documentFrame" style="width: 100%; height: calc(100vh - 56px); border: none;"></iframe>
            </div>
        </div>
    </div>
</div>

<!-- Reject Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" action="process-action.php">
            <input type="hidden" name="queue_number" value="<?php echo htmlspecialchars($queue_number); ?>">
            <input type="hidden" name="action" value="reject">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title fw-bold">Reject Application</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="alert alert-danger border-0 bg-danger bg-opacity-10 text-danger mb-4">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i> <strong>Warning:</strong> This action will reject the application.
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Rejection Reason <span class="text-danger">*</span></label>
                        <textarea class="form-control" name="notes" rows="5" required placeholder="Explain the reason for rejection..."></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger px-4" <?php echo !$can_edit ? 'disabled' : ''; ?>>Reject Application</button>
                </div>
            </div>
        </form>
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
        preview.style.display = 'block';
        sendBtn.disabled = false;
        document.getElementById('emailSubject').value = selectedOption.dataset.subject;
        document.getElementById('emailBody').value = selectedOption.dataset.body;
        description.textContent = selectedOption.dataset.description;
        customFields.innerHTML = '';
        
        const templateCode = this.value;
        if (templateCode === 'REPLY_INTENT') {
            attachmentsNotice.style.display = 'block';
            attachmentsList.innerHTML = '<li>TAU-REO-QF-01 Application Form.docx</li><li>TAU-REO-QF-02 Review Category Form.docx</li><li>General Guidelines.pdf</li>';
        } else {
            attachmentsNotice.style.display = 'none';
        }
        
        if (templateCode === 'INCOMPLETE_DOCS') {
            customFields.innerHTML = '<div class="mb-3"><label class="form-label fw-bold text-danger">Missing Documents *</label><textarea class="form-control" name="missing_documents" rows="4" placeholder="• Document 1\n• Document 2" required></textarea></div>';
        } else if (templateCode === 'MISSING_SIGNATURES') {
            customFields.innerHTML = '<div class="mb-3"><label class="form-label fw-bold text-danger">Documents Missing Signatures *</label><textarea class="form-control" name="unsigned_documents" rows="4" placeholder="• Application Form" required></textarea></div>';
        } else if (templateCode === 'CONDITIONAL_APPROVAL') {
            customFields.innerHTML = '<div class="mb-3"><label class="form-label fw-bold text-warning">Conditions *</label><textarea class="form-control" name="conditions" rows="4" placeholder="• Condition 1" required></textarea></div>';
        } else if (templateCode === 'REJECTION_NOTICE') {
            customFields.innerHTML = '<div class="mb-3"><label class="form-label fw-bold text-danger">Rejection Reason *</label><textarea class="form-control" name="rejection_reason" rows="4" placeholder="Explain the reason..." required></textarea></div>';
        } else if (templateCode === 'REVISIONS_NEEDED') {
            customFields.innerHTML = '<div class="mb-3"><label class="form-label fw-bold text-warning">Required Revisions *</label><textarea class="form-control" name="revisions_list" rows="4" placeholder="• Revision 1" required></textarea></div>';
        } else if (templateCode === 'GENERAL_UPDATE') {
            customFields.innerHTML = '<div class="mb-3"><label class="form-label fw-bold">Message Content *</label><textarea class="form-control" name="message_content" rows="4" placeholder="Enter custom message..." required></textarea></div>';
        }
    } else {
        preview.style.display = 'none';
        sendBtn.disabled = true;
    }
});

// Reset form when modal closes
document.getElementById('emailTemplateModal').addEventListener('hidden.bs.modal', function() {
    document.getElementById('emailTemplateForm').reset();
    document.getElementById('templatePreview').style.display = 'none';
    document.getElementById('sendEmailBtn').disabled = true;
});

// Auto-trigger download if download parameter is present
<?php if (isset($_GET['download']) && $_GET['download'] === 'qf02'): ?>
window.addEventListener('DOMContentLoaded', function() {
    window.location.href = 'view-application.php?queue=<?php echo urlencode($queue_number); ?>&download=qf02';
});
<?php endif; ?>

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
    iframe.src = '';
    modal.show();
    iframe.src = 'view-document.php?path=' + encodeURIComponent(path);
    
    const validateBtn = document.getElementById('validateDocBtn');
    const rejectBtn = document.getElementById('rejectDocBtn');
    if (validateBtn && rejectBtn) {
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
    fetch('validate-document.php', { method: 'POST', body: formData })
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
    .catch(error => alert('Error processing document: ' + error.message));
}

function openAiClassification() {
    const iframe = document.getElementById('aiClassificationFrame');
    iframe.src = 'ai-classification.php?queue=<?php echo urlencode($queue_number); ?>';
    new bootstrap.Modal(document.getElementById('aiClassificationModal')).show();
}

function openApproveModal() {
    const iframe = document.getElementById('approveFrame');
    iframe.src = 'approve-application.php?queue=<?php echo urlencode($queue_number); ?>';
    new bootstrap.Modal(document.getElementById('approveModal')).show();
}

function openRevisionModal() {
    const iframe = document.getElementById('revisionFrame');
    iframe.src = 'request-revision.php?queue=<?php echo urlencode($queue_number); ?>';
    new bootstrap.Modal(document.getElementById('revisionModal')).show();
}

window.addEventListener('message', function(event) {
    if (event.data && event.data.type === 'aiReviewCompleted' && event.data.success) window.location.reload();
    if (event.data && event.data.type === 'closeRevisionModal') bootstrap.Modal.getInstance(document.getElementById('revisionModal')).hide();
    if (event.data && event.data.type === 'revisionRequestSent' && event.data.success) window.location.reload();
    if (event.data && event.data.type === 'closeApproveModal') bootstrap.Modal.getInstance(document.getElementById('approveModal')).hide();
    if (event.data && event.data.type === 'applicationApproved' && event.data.success) window.location.reload();
});

document.addEventListener('DOMContentLoaded', function() {
    const chatContainer = document.querySelector('.chat-container');
    if (chatContainer) chatContainer.scrollTop = chatContainer.scrollHeight;
});
</script>

<?php include '../includes/auth_footer.php'; ?>