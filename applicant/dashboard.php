<?php
/**
 * Applicant Dashboard
 * TAU-UREO Portal
 */

require_once '../config/config.php';
require_once '../includes/functions.php';

requireApplicantLogin();
checkSessionTimeout();

// Ensure session has queue_number
if (!isset($_SESSION['queue_number']) || empty($_SESSION['queue_number'])) {
    session_destroy();
    header("Location: login.php");
    exit();
}

$queue_number = $_SESSION['queue_number'];
$conn = getDBConnection();

// Get application details
$stmt = $conn->prepare("SELECT * FROM applications WHERE queue_number = ?");
$stmt->bind_param("s", $queue_number);
$stmt->execute();
$application = $stmt->get_result()->fetch_assoc();

// If application not found, logout and redirect
if (!$application) {
    session_destroy();
    closeDBConnection($conn);
    header("Location: login.php?error=application_not_found");
    exit();
}

// Get uploaded documents count and status
$doc_stmt = $conn->prepare("SELECT validation_status, COUNT(*) as count FROM documents WHERE queue_number = ? GROUP BY validation_status");
$doc_stmt->bind_param("s", $queue_number);
$doc_stmt->execute();
$doc_result = $doc_stmt->get_result();
$document_stats = ['total' => 0, 'validated' => 0, 'pending' => 0, 'rejected' => 0];
while ($row = $doc_result->fetch_assoc()) {
    $document_stats[$row['validation_status']] = $row['count'];
    $document_stats['total'] += $row['count'];
}

// Get recent messages (last 3)
$msg_stmt = $conn->prepare("SELECT * FROM messages WHERE queue_number = ? ORDER BY sent_at DESC LIMIT 3");
$msg_stmt->bind_param("s", $queue_number);
$msg_stmt->execute();
$recent_messages = $msg_stmt->get_result();

// Get recent status history (last 3)
$history_stmt = $conn->prepare("SELECT * FROM status_history WHERE queue_number = ? ORDER BY timestamp DESC LIMIT 3");
$history_stmt->bind_param("s", $queue_number);
$history_stmt->execute();
$recent_history = $history_stmt->get_result();

// Calculate progress percentage
$progress = 0;
switch ($application['current_status']) {
    case 'INTENT_RECEIVED': $progress = 15; break;
    case 'REQUIREMENTS_SENT':
    case 'REQUIREMENTS_PENDING':
    case 'REQUIREMENTS_INCOMPLETE': $progress = 30; break;
    case 'REGISTERED':
    case 'UNDER_AUTO_REVIEW':
    case 'STAFF_REVIEW_REQUIRED':
    case 'UNDER_STAFF_REVIEW':
    case 'REVISIONS_REQUIRED': $progress = 50; break;
    case 'CATEGORIZED': $progress = 70; break;
    case 'CATEGORY_FORMS_REQUIRED': $progress = 75; break;
    case 'CHECKLIST_SUBMITTED': $progress = 80; break;
    case 'UREC_REVIEW_REQUIRED':
    case 'FORWARDED_TO_UREC': $progress = 85; break;
    case 'UNDER_ETHICAL_REVIEW':
    case 'COMPLIANCE_PENDING':
    case 'COMPLIANCE_REVIEW': $progress = 90; break;
    case 'APPROVED':
    case 'CERTIFICATE_ISSUED': $progress = 100; break;
    case 'REJECTED': $progress = 100; break;
}

// Get category form token if needed
$category_token = '';
if ($application['current_status'] === 'CATEGORY_FORMS_REQUIRED' || $application['current_status'] === 'UNDER_ETHICAL_REVIEW' || $application['current_status'] === 'CATEGORIZED') {
    $token_stmt = $conn->prepare("SELECT form_data FROM fillable_forms WHERE queue_number = ? AND form_type = 'category_token'");
    $token_stmt->bind_param("s", $queue_number);
    $token_stmt->execute();
    $token_res = $token_stmt->get_result()->fetch_assoc();
    if ($token_res) {
        $token_data = json_decode($token_res['form_data'], true);
        $category_token = $token_data['token'] ?? '';
    }
}

// Check if there's a research proposal document for annotation viewer
$doc_stmt = $conn->prepare("SELECT document_id FROM documents WHERE queue_number = ? AND document_name LIKE '%research%' AND document_type = 'pdf'");
$doc_stmt->bind_param("s", $queue_number);
$doc_stmt->execute();
$research_doc = $doc_stmt->get_result()->fetch_assoc();

closeDBConnection($conn);

$page_title = 'Dashboard';
$base_url = '../';
$active_menu = 'overview';
include '../includes/auth_header.php';
?>

<style>
/* Dashboard specific styles */
.welcome-card {
    background: linear-gradient(135deg, #006400, #228B22);
    color: white;
    border: none;
    border-radius: 15px;
}

.status-card {
    background: #f8f9fa;
    border: 2px solid #006400;
    color: #006400;
    border-radius: 15px;
}

.progress-card {
    background: linear-gradient(135deg, #006400, #228B22);
    color: white;
    border: none;
    border-radius: 15px;
}

.stats-card {
    transition: all 0.3s ease;
    border-radius: 12px;
    border: 1px solid #dee2e6;
}

.stats-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15) !important;
    border-color: #006400;
}

.activity-item {
    border-left: 4px solid;
    padding-left: 15px;
    margin-bottom: 15px;
    border-radius: 0 8px 8px 0;
}

.activity-item.message {
    border-left-color: #006400;
    background: rgba(0, 100, 0, 0.05);
}

.activity-item.status {
    border-left-color: #228B22;
    background: rgba(34, 139, 34, 0.05);
}

.timeline-dot {
    width: 12px;
    height: 12px;
    border-radius: 50%;
    display: inline-block;
    margin-right: 8px;
}

.metric-value {
    font-size: 1.5rem;
    font-weight: 700;
    line-height: 1;
    color: #006400;
}

.metric-label {
    font-size: 0.8rem;
    opacity: 0.8;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #6c757d;
}

@media (max-width: 768px) {
    .welcome-card, .status-card, .progress-card {
        margin-bottom: 1rem;
    }

    .metric-value {
        font-size: 1.5rem;
    }
}
</style>

<div class="container-fluid py-4">
    <!-- Welcome Section -->
    <div class="row g-4 mb-4">
        <div class="col-12">
            <div class="card welcome-card shadow-lg">
                <div class="card-body p-4">
                    <div class="row align-items-center">
                        <div class="col-lg-8">
                            <h2 class="mb-2 text-white">
                                <i class="bi bi-person-circle me-3"></i>
                                Welcome back, <?php echo htmlspecialchars(explode(' ', $application['applicant_name'])[0]); ?>!
                            </h2>
                            <p class="mb-0 opacity-90 fs-6">
                                <i class="bi bi-file-earmark-text me-2"></i>
                                Application: <?php echo htmlspecialchars($application['queue_number']); ?>
                            </p>
                        </div>
                        <div class="col-lg-4 text-lg-end">
                            <div class="d-flex align-items-center justify-content-lg-end">
                                <i class="bi bi-calendar-event fs-2 me-3 opacity-75"></i>
                                <div>
                                    <div class="fw-bold fs-5"><?php echo formatDate($application['submission_timestamp']); ?></div>
                                    <small class="opacity-75">Application Submitted</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Status and Progress Row -->
    <div class="row g-4 mb-4">
        <div class="col-lg-4">
            <div class="card status-card shadow-lg h-100">
                <div class="card-body p-4 text-center">
                    <i class="bi bi-info-circle display-6 mb-3 opacity-75"></i>
                    <h4 class="mb-3 h5">Current Status</h4>
                    <span class="badge bg-white text-dark px-3 py-2 mb-3">
                        <?php echo getStatusDisplayName($application['current_status']); ?>
                    </span>
                    <p class="mb-0 small opacity-75">
                        Last updated: <?php echo formatDate($application['last_updated']); ?>
                    </p>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card progress-card shadow-lg h-100">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h4 class="mb-0">
                            <i class="bi bi-graph-up me-2"></i>
                            Application Progress
                        </h4>
                        <span class="badge bg-white text-dark"><?php echo $progress; ?>% Complete</span>
                    </div>
                    <div class="progress mb-3" style="height: 10px; border-radius: 5px;">
                        <div class="progress-bar bg-dark" role="progressbar" style="width: <?php echo $progress; ?>%; border-radius: 5px;" aria-valuenow="<?php echo $progress; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                    <div class="row text-center">
                        <div class="col-3">
                            <i class="bi bi-file-earmark-text fs-5 mb-1"></i>
                            <small class="d-block opacity-75">Intent</small>
                        </div>
                        <div class="col-3">
                            <i class="bi bi-clipboard-check fs-5 mb-1"></i>
                            <small class="d-block opacity-75">Requirements</small>
                        </div>
                        <div class="col-3">
                            <i class="bi bi-search fs-5 mb-1"></i>
                            <small class="d-block opacity-75">Review</small>
                        </div>
                        <div class="col-3">
                            <i class="bi bi-trophy fs-5 mb-1"></i>
                            <small class="d-block opacity-75">Approval</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Category Forms Required Alert -->
    <?php if ($application['current_status'] === 'CATEGORY_FORMS_REQUIRED' && !empty($category_token)): ?>
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-lg" style="border-radius: 15px; background: linear-gradient(135deg, #0d6efd, #0b5ed7); color: white;">
                <div class="card-body p-4">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h4 class="mb-2">
                                <i class="bi bi-exclamation-circle-fill me-2"></i>
                                Action Required: Category Checklist
                            </h4>
                            <p class="mb-md-0 opacity-90">
                                Your application has been classified and requires a category-specific checklist to proceed with the ethical review.
                            </p>
                        </div>
                        <div class="col-md-4 text-md-end">
                            <a href="category-form.php?queue=<?php echo urlencode($queue_number); ?>&token=<?php echo urlencode($category_token); ?>" class="btn btn-light btn-lg px-4 shadow-sm" style="color: #0d6efd; font-weight: 700; border-radius: 10px;">
                                <i class="bi bi-pencil-square me-2"></i>Fill Checklist Now
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>


    <!-- Statistics Row -->
    <div class="row g-4 mb-4">
        <div class="col-lg-3 col-md-6">
            <div class="card stats-card shadow-sm border-0 h-100">
                <div class="card-body p-4 text-center">
                    <div class="d-flex align-items-center justify-content-center mb-3">
                        <div class="rounded-circle bg-primary bg-opacity-10 p-3 me-3">
                            <i class="bi bi-file-earmark-text text-primary fs-2"></i>
                        </div>
                        <div class="text-start">
                            <div class="metric-value text-primary"><?php echo $document_stats['total']; ?></div>
                            <div class="metric-label">Total Documents</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6">
            <div class="card stats-card shadow-sm border-0 h-100">
                <div class="card-body p-4 text-center">
                    <div class="d-flex align-items-center justify-content-center mb-3">
                        <div class="rounded-circle bg-success bg-opacity-10 p-3 me-3">
                            <i class="bi bi-check-circle text-success fs-2"></i>
                        </div>
                        <div class="text-start">
                            <div class="metric-value text-success"><?php echo $document_stats['validated']; ?></div>
                            <div class="metric-label">Validated</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6">
            <div class="card stats-card shadow-sm border-0 h-100">
                <div class="card-body p-4 text-center">
                    <div class="d-flex align-items-center justify-content-center mb-3">
                        <div class="rounded-circle bg-warning bg-opacity-10 p-3 me-3">
                            <i class="bi bi-clock text-warning fs-2"></i>
                        </div>
                        <div class="text-start">
                            <div class="metric-value text-warning"><?php echo $document_stats['pending']; ?></div>
                            <div class="metric-label">Pending Review</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6">
            <div class="card stats-card shadow-sm border-0 h-100">
                <div class="card-body p-4 text-center">
                    <div class="d-flex align-items-center justify-content-center mb-3">
                        <div class="rounded-circle bg-danger bg-opacity-10 p-3 me-3">
                            <i class="bi bi-x-circle text-danger fs-2"></i>
                        </div>
                        <div class="text-start">
                            <div class="metric-value text-danger"><?php echo $document_stats['rejected']; ?></div>
                            <div class="metric-label">Rejected</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content Row -->
    <div class="row g-4">
        <!-- Application Details -->
        <div class="col-lg-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white border-0 py-3">
                    <h5 class="mb-0 text-primary">
                        <i class="bi bi-info-circle me-2"></i>
                        Application Details
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-sm-6">
                            <div class="p-3 bg-light rounded">
                                <small class="text-muted d-block">Queue Number</small>
                                <strong class="fs-6 text-primary"><?php echo htmlspecialchars($application['queue_number']); ?></strong>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="p-3 bg-light rounded">
                                <small class="text-muted d-block">Applicant Type</small>
                                <strong class="fs-6 text-success"><?php echo ucfirst(htmlspecialchars($application['applicant_type'])); ?></strong>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="p-3 bg-light rounded">
                                <small class="text-muted d-block">Full Name</small>
                                <strong class="fs-6"><?php echo htmlspecialchars($application['applicant_name']); ?></strong>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="p-3 bg-light rounded">
                                <small class="text-muted d-block">Research Title</small>
                                <strong><?php echo htmlspecialchars($application['research_title']); ?></strong>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="p-3 bg-light rounded">
                                <small class="text-muted d-block">Email Address</small>
                                <strong><?php echo htmlspecialchars($application['applicant_email']); ?></strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- UREC Review Comments -->
        <div class="col-lg-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white border-0 py-3">
                    <h5 class="mb-0 text-primary">
                        <i class="bi bi-chat-square-text me-2"></i>
                        UREC Review Comments
                    </h5>
                </div>
                <div class="card-body">
                    <?php if ($research_doc): ?>
                        <div class="text-center">
                            <div class="mb-3">
                                <i class="bi bi-eye text-primary fs-1"></i>
                            </div>
                            <h6 class="fw-bold">View Research Proposal Comments</h6>
                            <p class="text-muted small mb-4">
                                See the UREC committee's review comments and annotations on your research proposal.
                            </p>
                            <a href="view-annotations.php?queue=<?php echo urlencode($queue_number); ?>" class="btn btn-primary px-4">
                                <i class="bi bi-chat-square-text me-2"></i>View Comments
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="bi bi-file-earmark-text text-muted fs-1 mb-3"></i>
                            <p class="text-muted">No research proposal document found.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="col-lg-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white border-0 py-3">
                    <h5 class="mb-0 text-primary">
                        <i class="bi bi-activity me-2"></i>
                        Recent Activity
                    </h5>
                </div>
                <div class="card-body">
                    <?php if ($recent_messages->num_rows > 0 || $recent_history->num_rows > 0): ?>
                        <div class="activity-feed">
                            <?php while ($msg = $recent_messages->fetch_assoc()): ?>
                                <div class="activity-item message">
                                    <div class="d-flex align-items-start">
                                        <div class="timeline-dot bg-primary flex-shrink-0 mt-2"></div>
                                        <div class="flex-grow-1">
                                            <div class="d-flex justify-content-between align-items-start mb-1">
                                                <small class="fw-bold text-primary">
                                                    <i class="bi bi-chat-dots me-1"></i>
                                                    <?php echo $msg['sender_type'] === 'staff' ? 'Staff Message' : 'Your Message'; ?>
                                                </small>
                                                <small class="text-muted"><?php echo formatDate($msg['sent_at']); ?></small>
                                            </div>
                                            <p class="mb-0 small text-truncate" style="max-width: 100%;">
                                                <?php echo htmlspecialchars(substr($msg['message_content'], 0, 80) . (strlen($msg['message_content']) > 80 ? '...' : '')); ?>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>

                            <?php while ($history = $recent_history->fetch_assoc()): ?>
                                <div class="activity-item status">
                                    <div class="d-flex align-items-start">
                                        <div class="timeline-dot bg-success flex-shrink-0 mt-2"></div>
                                        <div class="flex-grow-1">
                                            <div class="d-flex justify-content-between align-items-start mb-1">
                                                <small class="fw-bold text-success">
                                                    <i class="bi bi-arrow-right-circle me-1"></i>
                                                    Status Updated
                                                </small>
                                                <small class="text-muted"><?php echo formatDate($history['timestamp']); ?></small>
                                            </div>
                                            <p class="mb-0 small">
                                                Changed to: <strong><?php echo getStatusDisplayName($history['new_status']); ?></strong>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="bi bi-activity display-6 text-muted mb-3"></i>
                            <p class="text-muted">No recent activity yet.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="../assets/js/dashboard.js"></script>

<?php include '../includes/auth_footer.php'; ?>
