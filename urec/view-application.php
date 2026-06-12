<?php
declare(strict_types=1);
/**
 * UREC View Application Detailed
 * TAU-UREO Portal
 */

require_once '../config/config.php';
require_once '../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$queue_number = $_GET['queue'] ?? '';
if (empty($queue_number)) {
    header("Location: applications.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$committee_id = $_SESSION['committee_id'] ?? null;
$committee_designation = $_SESSION['committee_designation'] ?? '';
$is_chairperson = (stripos($committee_designation, 'Chair') !== false || stripos($committee_designation, 'Head') !== false);

$conn = getDBConnection();

// Fetch application details with comprehensive information
$stmt = $conn->prepare("
    SELECT a.*, 
           u.full_name as evaluator_name,
           u.email as evaluator_email,
           c.committee_name,
           c.committee_code,
           staff.full_name as staff_forwarded_name,
           assigned_staff.full_name as assigned_staff_name
    FROM applications a
    LEFT JOIN users u ON a.urec_reviewed_by = u.user_id
    LEFT JOIN urec_committees c ON a.urec_committee_id = c.committee_id
    LEFT JOIN users staff ON a.forwarded_by_staff = staff.user_id
    LEFT JOIN users assigned_staff ON a.assigned_staff_id = assigned_staff.user_id
    WHERE a.queue_number = ?
");
$stmt->bind_param("s", $queue_number);
$stmt->execute();
$application = $stmt->get_result()->fetch_assoc();

if (!$application) {
    header("Location: applications.php");
    exit();
}

// Security: Check if user belongs to the committee that was assigned this application
// Or if it's assigned to them specifically
$is_assigned_to_me = ($application['urec_reviewed_by'] == $user_id);
$is_my_committee = ($application['urec_committee_id'] == $committee_id);

// Check if user is any assigned evaluator (including secondary evaluators)
$is_any_assigned_evaluator = false;
if ($is_assigned_to_me) {
    $is_any_assigned_evaluator = true;
} elseif (!empty($application['urec_review_notes']) && strpos($application['urec_review_notes'], 'Multiple evaluators assigned:') === 0) {
    $json_part = str_replace('Multiple evaluators assigned: ', '', $application['urec_review_notes']);
    $evaluator_ids = json_decode($json_part, true);
    if (is_array($evaluator_ids) && in_array($user_id, $evaluator_ids)) {
        $is_any_assigned_evaluator = true;
    }
}

if (!$is_assigned_to_me && !$is_my_committee) {
    // If not chairperson and not assigned to them, check if they have general urec access 
    // Usually members should see committee apps
    if (!$is_chairperson && $application['urec_committee_id'] != $committee_id) {
         echo "Access Denied. You are not authorized to view this application.";
         exit();
    }
}

// Fetch documents
$doc_stmt = $conn->prepare("SELECT * FROM documents WHERE queue_number = ? ORDER BY upload_timestamp DESC");
$doc_stmt->bind_param("s", $queue_number);
$doc_stmt->execute();
$documents = $doc_stmt->get_result();

// Fetch system documents (checklists, etc.)
$sys_doc_stmt = $conn->prepare("SELECT * FROM system_documents WHERE queue_number = ? ORDER BY provided_at DESC");
$sys_doc_stmt->bind_param("s", $queue_number);
$sys_doc_stmt->execute();
$system_documents = $sys_doc_stmt->get_result();

// Fetch form data for additional details
$form_data_stmt = $conn->prepare("SELECT form_type, COUNT(*) as form_count, MAX(completed_at) as last_updated FROM fillable_forms WHERE queue_number = ? GROUP BY form_type");
$form_data_stmt->bind_param("s", $queue_number);
$form_data_stmt->execute();
$form_data = $form_data_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch UREC activity logs
$activity_stmt = $conn->prepare("SELECT activity_type, activity_description, created_at, committee_id FROM urec_activity_log WHERE queue_number = ? ORDER BY created_at DESC LIMIT 10");
$activity_stmt->bind_param("s", $queue_number);
$activity_stmt->execute();
$activities = $activity_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch staff activity logs
$staff_activity_stmt = $conn->prepare("SELECT action_type as activity_type, action_details as activity_description, timestamp as created_at FROM staff_logs WHERE queue_number = ? ORDER BY timestamp DESC LIMIT 5");
$staff_activity_stmt->bind_param("s", $queue_number);
$staff_activity_stmt->execute();
$staff_activities = $staff_activity_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch assigned evaluators for this application
$assigned_evaluators = [];
if ($application['urec_reviewed_by']) {
    // Check if we have multiple evaluators stored in JSON format
    $evaluator_json = null;
    if (!empty($application['urec_review_notes']) && strpos($application['urec_review_notes'], 'Multiple evaluators assigned:') === 0) {
        $json_part = str_replace('Multiple evaluators assigned: ', '', $application['urec_review_notes']);
        $evaluator_ids = json_decode($json_part, true);
        
        if (is_array($evaluator_ids)) {
            // Fetch all evaluators from the JSON array
            $placeholders = str_repeat('?,', count($evaluator_ids) - 1) . '?';
            $eval_stmt = $conn->prepare("
                SELECT u.user_id, u.full_name, u.committee_designation, 
                       CASE WHEN u.user_id = ? THEN a.urec_assigned_at ELSE NOW() END as assigned_at
                FROM users u
                LEFT JOIN applications a ON a.queue_number = ?
                WHERE u.user_id IN ($placeholders)
            ");
            $params = array_merge([$application['urec_reviewed_by'], $queue_number], $evaluator_ids);
            $eval_stmt->bind_param(str_repeat('i', count($params)), ...$params);
            $eval_stmt->execute();
            $assigned_evaluators = $eval_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        }
    } else {
        // Single evaluator (old format)
        $eval_stmt = $conn->prepare("
            SELECT u.user_id, u.full_name, u.committee_designation, a.urec_assigned_at as assigned_at
            FROM users u
            JOIN applications a ON u.user_id = a.urec_reviewed_by
            WHERE a.queue_number = ?
        ");
        $eval_stmt->bind_param("s", $queue_number);
        $eval_stmt->execute();
        $assigned_evaluators = $eval_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}

// Fetch members for assignment (if Chairperson)
$members = null;
if ($is_chairperson && $committee_id) {
    $mem_stmt = $conn->prepare("SELECT user_id, full_name, committee_designation FROM users WHERE committee_id = ? AND active_status = 1");
    $mem_stmt->bind_param("i", $committee_id);
    $mem_stmt->execute();
    $members = $mem_stmt->get_result();
}

closeDBConnection($conn);

$page_title = 'View Application - ' . $queue_number;
$active_menu = 'applications';
require_once __DIR__ . '/../includes_urec/header.php';
?>

<div class="container-fluid py-4">
    <!-- Breadcrumb & Navigation -->
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb bg-white p-3 shadow-sm rounded-pill">
            <li class="breadcrumb-item"><a href="dashboard.php" class="text-decoration-none text-success">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="applications.php" class="text-decoration-none text-success">Applications</a></li>
            <li class="breadcrumb-item active" aria-current="page"><?php echo $queue_number; ?></li>
        </ol>
    </nav>

    <!-- Header Section -->
    <div class="card border-0 shadow-sm mb-4 overflow-hidden" style="border-radius: 20px;">
        <div class="card-body p-4">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <span class="badge bg-<?php echo getStatusBadgeClass($application['current_status']); ?> px-3 py-2">
                             <i class="bi bi-info-circle me-1"></i> <?php echo getStatusDisplayName($application['current_status']); ?>
                        </span>
                        <span class="text-muted small"><i class="bi bi-clock me-1"></i> Last updated: <?php echo formatDate($application['last_updated']); ?></span>
                    </div>
                    <h2 class="fw-bold text-dark mb-2"><?php echo htmlspecialchars($application['research_title']); ?></h2>
                    <div class="d-flex flex-wrap gap-4 mt-3">
                        <div class="d-flex align-items-center gap-2">
                            <div class="bg-light p-2 rounded-circle"><i class="bi bi-person text-primary"></i></div>
                            <div>
                                <small class="text-muted d-block">Lead Applicant</small>
                                <strong class="text-dark"><?php echo htmlspecialchars($application['applicant_name']); ?></strong>
                            </div>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <div class="bg-light p-2 rounded-circle"><i class="bi bi-shield-check text-success"></i></div>
                            <div>
                                <small class="text-muted d-block">Committee</small>
                                <strong class="text-dark"><?php echo htmlspecialchars($application['committee_name'] ?: 'N/A'); ?></strong>
                            </div>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <div class="bg-light p-2 rounded-circle"><i class="bi bi-person-workspace text-info"></i></div>
                            <div>
                                <small class="text-muted d-block">Evaluator</small>
                                <strong class="text-dark"><?php echo htmlspecialchars($application['evaluator_name'] ?: 'Pending Assignment'); ?></strong>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4 text-lg-end mt-4 mt-lg-0">
                    <div class="bg-light p-4 rounded-4 text-center border">
                        <small class="text-muted d-block mb-1 text-uppercase fw-bold">Reference Number</small>
                        <h1 class="fw-bold mb-0 text-success" style="letter-spacing: 1px;"><?php echo $queue_number; ?></h1>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Main Details Tabbed Area -->
        <div class="col-xl-8" style="position: sticky; top: 80px; height: calc(100vh - 100px);">
            <div class="card border-0 shadow-sm h-100" style="border-radius: 20px;">
                <div class="card-header bg-white border-0 pt-4 px-4">
                    <ul class="nav nav-pills custom-pills" id="detailTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="details-tab" data-bs-toggle="tab" data-bs-target="#details" type="button" role="tab"><i class="bi bi-info-circle me-1"></i> Details</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="docs-tab" data-bs-toggle="tab" data-bs-target="#docs" type="button" role="tab"><i class="bi bi-file-earmark-text me-1"></i> Documents</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="forms-tab" data-bs-toggle="tab" data-bs-target="#forms" type="button" role="tab"><i class="bi bi-clipboard-check me-1"></i> Forms</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="history-tab" data-bs-toggle="tab" data-bs-target="#history" type="button" role="tab"><i class="bi bi-clock-history me-1"></i> History</button>
                        </li>
                    </ul>
                </div>
                <div class="card-body p-4" style="height: calc(100% - 80px); overflow-y: auto;">
                    <div class="tab-content" id="detailTabsContent">
                        <!-- Details Tab -->
                        <div class="tab-pane fade show active" id="details" role="tabpanel">
                            <!-- Applicant & Research Section -->
                            <div class="row g-4 mb-4">
                                <div class="col-md-6">
                                    <h6 class="fw-bold text-muted text-uppercase small mb-3"><i class="bi bi-person-circle me-2"></i>Applicant Information</h6>
                                    <div class="card border-0 shadow-sm">
                                        <div class="card-body">
                                            <div class="mb-3">
                                                <label class="text-muted small d-block">Full Name</label>
                                                <div class="fw-bold"><?php echo htmlspecialchars($application['applicant_name'] ?? 'N/A'); ?></div>
                                            </div>
                                            <div class="mb-3">
                                                <label class="text-muted small d-block">Email Address</label>
                                                <div class="fw-bold"><?php echo htmlspecialchars($application['applicant_email'] ?? 'N/A'); ?></div>
                                            </div>
                                            <div class="mb-3">
                                                <label class="text-muted small d-block">Applicant Type</label>
                                                <div class="fw-bold">
                                                    <?php 
                                                    $applicant_type = $application['applicant_type'] ?? 'N/A';
                                                    $type_icon = match($applicant_type) {
                                                        'student' => 'bi-mortarboard',
                                                        'researcher' => 'bi-search',
                                                        'faculty' => 'bi-person-badge',
                                                        default => 'bi-person'
                                                    };
                                                    ?>
                                                    <span class="badge bg-primary"><i class="bi <?php echo $type_icon; ?> me-1"></i><?php echo htmlspecialchars(ucfirst($applicant_type)); ?></span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <h6 class="fw-bold text-muted text-uppercase small mb-3"><i class="bi bi-journal-text me-2"></i>Research Information</h6>
                                    <div class="card border-0 shadow-sm">
                                        <div class="card-body">
                                            <div class="mb-3">
                                                <label class="text-muted small d-block">Research Title</label>
                                                <div class="fw-bold" style="font-size: 0.9rem;"><?php echo htmlspecialchars($application['research_title'] ?? 'N/A'); ?></div>
                                            </div>
                                            <div class="mb-3">
                                                <label class="text-muted small d-block">Review Category</label>
                                                <div class="fw-bold">
                                                    <?php 
                                                    $category = $application['category'] ?? 'N/A';
                                                    $category_class = match($category) {
                                                        'exempt' => 'success',
                                                        'expedited' => 'warning', 
                                                        'full' => 'danger',
                                                        default => 'secondary'
                                                    };
                                                    ?>
                                                    <span class="badge bg-<?php echo $category_class; ?>"><?php echo htmlspecialchars(ucfirst($category)); ?></span>
                                                </div>
                                            </div>
                                            <div class="mb-3">
                                                <label class="text-muted small d-block">Submission Date</label>
                                                <div class="fw-bold"><?php echo formatDate($application['submission_timestamp'] ?? $application['last_updated']); ?></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- UREC Assignment Section -->
                            <div class="row g-4 mb-4">
                                <div class="col-md-6">
                                    <h6 class="fw-bold text-muted text-uppercase small mb-3"><i class="bi bi-people me-2"></i>UREC Assignment</h6>
                                    <div class="card border-0 shadow-sm">
                                        <div class="card-body">
                                            <div class="mb-3">
                                                <label class="text-muted small d-block">Assigned Committee</label>
                                                <div class="fw-bold">
                                                    <?php if ($application['committee_name']): ?>
                                                        <span class="badge bg-info"><?php echo htmlspecialchars($application['committee_name']); ?></span>
                                                        <?php if ($application['committee_code']): ?>
                                                            <small class="text-muted d-block">(<?php echo htmlspecialchars($application['committee_code']); ?>)</small>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">Not Assigned</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="mb-3">
                                                <label class="text-muted small d-block">Assigned Evaluator</label>
                                                <div class="fw-bold">
                                                    <?php if ($application['evaluator_name']): ?>
                                                        <div><?php echo htmlspecialchars($application['evaluator_name']); ?></div>
                                                        <?php if ($application['evaluator_email']): ?>
                                                            <small class="text-muted"><?php echo htmlspecialchars($application['evaluator_email']); ?></small>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">Not Assigned</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="mb-3">
                                                <label class="text-muted small d-block">UREC Decision</label>
                                                <div class="fw-bold">
                                                    <?php 
                                                    $decision = $application['urec_decision'] ?? 'pending';
                                                    $decision_class = match($decision) {
                                                        'approved' => 'success',
                                                        'revisions_required' => 'warning',
                                                        'rejected' => 'danger',
                                                        'pending' => 'secondary',
                                                        default => 'secondary'
                                                    };
                                                    ?>
                                                    <span class="badge bg-<?php echo $decision_class; ?>"><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $decision))); ?></span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <h6 class="fw-bold text-muted text-uppercase small mb-3"><i class="bi bi-clock-history me-2"></i>Processing Timeline</h6>
                                    <div class="card border-0 shadow-sm">
                                        <div class="card-body">
                                            <div class="mb-3">
                                                <label class="text-muted small d-block">Forwarded to UREC</label>
                                                <div class="fw-bold">
                                                    <?php echo $application['forwarded_to_urec_at'] ? formatDate($application['forwarded_to_urec_at']) : '<span class="text-muted">Not Forwarded</span>'; ?>
                                                    <?php if ($application['staff_forwarded_name']): ?>
                                                        <small class="text-muted d-block">by <?php echo htmlspecialchars($application['staff_forwarded_name']); ?></small>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="mb-3">
                                                <label class="text-muted small d-block">UREC Assigned</label>
                                                <div class="fw-bold"><?php echo $application['urec_assigned_at'] ? formatDate($application['urec_assigned_at']) : '<span class="text-muted">Not Assigned</span>'; ?></div>
                                            </div>
                                            <div class="mb-3">
                                                <label class="text-muted small d-block">Decision Date</label>
                                                <div class="fw-bold"><?php echo $application['urec_decision_date'] ? formatDate($application['urec_decision_date']) : '<span class="text-muted">No Decision</span>'; ?></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Form Statistics Section -->
                            <div class="row g-4 mb-4">
                                <div class="col-md-6">
                                    <h6 class="fw-bold text-muted text-uppercase small mb-3"><i class="bi bi-clipboard-check me-2"></i>Form Statistics</h6>
                                    <div class="card border-0 shadow-sm">
                                        <div class="card-body">
                                            <?php if (!empty($form_data)): ?>
                                                <?php foreach ($form_data as $form): ?>
                                                    <div class="mb-3">
                                                        <label class="text-muted small d-block"><?php echo htmlspecialchars(ucfirst(str_replace('-', ' ', $form['form_type']))); ?></label>
                                                        <div class="fw-bold">
                                                            <span class="badge bg-secondary"><?php echo $form['form_count']; ?> submission(s)</span>
                                                            <small class="text-muted d-block">Last: <?php echo formatDate($form['last_updated']); ?></small>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <div class="text-muted">No form data available</div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <h6 class="fw-bold text-muted text-uppercase small mb-3"><i class="bi bi-graph-up me-2"></i>Application Metrics</h6>
                                    <div class="card border-0 shadow-sm">
                                        <div class="card-body">
                                            <div class="mb-3">
                                                <label class="text-muted small d-block">Completion Attempts</label>
                                                <div class="fw-bold">
                                                    <span class="badge bg-info"><?php echo $application['completion_attempts'] ?? 0; ?></span>
                                                </div>
                                            </div>
                                            <div class="mb-3">
                                                <label class="text-muted small d-block">Additional Requirements</label>
                                                <div class="fw-bold">
                                                    <?php if ($application['has_additional_requirements']): ?>
                                                        <span class="badge bg-warning">Yes</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-success">No</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="mb-3">
                                                <label class="text-muted small d-block">Last Updated</label>
                                                <div class="fw-bold"><?php echo formatDate($application['last_updated']); ?></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Recent Activity Section -->
                            <div class="row g-4 mb-4">
                                <div class="col-12">
                                    <h6 class="fw-bold text-muted text-uppercase small mb-3"><i class="bi bi-activity me-2"></i>Recent Activity</h6>
                                    <div class="card border-0 shadow-sm">
                                        <div class="card-body">
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <h6 class="fw-bold text-dark mb-3">UREC Activity</h6>
                                                    <?php if (!empty($activities)): ?>
                                                        <?php foreach (array_slice($activities, 0, 3) as $activity): ?>
                                                            <div class="mb-2 pb-2 border-bottom">
                                                                <small class="text-muted d-block"><?php echo formatDate($activity['created_at'], true); ?></small>
                                                                <div class="small"><?php echo htmlspecialchars($activity['activity_description'] ?? $activity['activity_type']); ?></div>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    <?php else: ?>
                                                        <div class="text-muted small">No UREC activity recorded</div>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="col-md-6">
                                                    <h6 class="fw-bold text-dark mb-3">Staff Activity</h6>
                                                    <?php if (!empty($staff_activities)): ?>
                                                        <?php foreach (array_slice($staff_activities, 0, 3) as $activity): ?>
                                                            <div class="mb-2 pb-2 border-bottom">
                                                                <small class="text-muted d-block"><?php echo formatDate($activity['created_at'], true); ?></small>
                                                                <div class="small"><?php echo htmlspecialchars($activity['activity_description'] ?? $activity['activity_type']); ?></div>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    <?php else: ?>
                                                        <div class="text-muted small">No staff activity recorded</div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Review Notes Section -->
                            <?php if (!empty($application['urec_review_notes'])): ?>
                            <div class="row g-4">
                                <div class="col-12">
                                    <h6 class="fw-bold text-muted text-uppercase small mb-3"><i class="bi bi-journal-medical me-2"></i>UREC Review Notes</h6>
                                    <div class="card border-0 shadow-sm">
                                        <div class="card-body">
                                            <div class="p-3 bg-light rounded-3" style="font-size: 0.95rem; line-height: 1.6;">
                                                <?php echo nl2br(htmlspecialchars($application['urec_review_notes'])); ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- Documents Tab -->
                        <div class="tab-pane fade" id="docs" role="tabpanel">
                            <h6 class="fw-bold text-dark mb-3">System Generated Documents (Checklists)</h6>
                            <div class="row g-3 mb-4">
                                <?php if ($system_documents->num_rows > 0): ?>
                                    <?php while ($sdoc = $system_documents->fetch_assoc()): ?>
                                        <div class="col-md-6">
                                            <div class="d-flex align-items-center p-3 border rounded shadow-sm hover-shadow transition-all bg-white">
                                                <div class="bg-success bg-opacity-10 p-2 rounded text-success me-3">
                                                    <i class="bi bi-file-earmark-pdf fs-4"></i>
                                                </div>
                                                <div class="flex-grow-1 overflow-hidden">
                                                    <div class="fw-bold text-dark text-truncate small"><?php echo htmlspecialchars($sdoc['document_type']); ?></div>
                                                    <small class="text-muted d-block"><?php echo formatDate($sdoc['provided_at'] ?? $sdoc['created_at'] ?? date('Y-m-d H:i:s')); ?></small>
                                                </div>
                                                <a href="view-document.php?id=<?php echo $sdoc['system_doc_id']; ?>&type=system" target="_blank" class="btn btn-sm btn-light border ms-2">
                                                    <i class="bi bi-download"></i>
                                                </a>
                                            </div>
                                        </div>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <div class="col-12 text-center text-muted py-3">No system documents found.</div>
                                <?php endif; ?>
                            </div>

                            <h6 class="fw-bold text-dark mb-3">Applicant Uploads</h6>
                            <div class="row g-3">
                                <?php if ($documents->num_rows > 0): ?>
                                    <?php while ($doc = $documents->fetch_assoc()): ?>
                                        <div class="col-md-6">
                                            <div class="d-flex align-items-center p-3 border rounded shadow-sm hover-shadow transition-all bg-white">
                                                <div class="bg-primary bg-opacity-10 p-2 rounded text-primary me-3">
                                                    <i class="bi bi-file-earmark-text fs-4"></i>
                                                </div>
                                                <div class="flex-grow-1 overflow-hidden">
                                                    <div class="fw-bold text-dark text-truncate small"><?php echo htmlspecialchars($doc['document_name']); ?></div>
                                                    <small class="text-muted d-block"><?php echo formatDate($doc['upload_timestamp']); ?></small>
                                                </div>
                                                <?php if (stripos($doc['document_name'], 'proposal') !== false || stripos($doc['document_name'], 'thesis') !== false): ?>
                                                    <a href="review-proposal.php?queue=<?php echo $queue_number; ?>&doc_id=<?php echo $doc['document_id']; ?>" target="_blank" class="btn btn-sm btn-secondary ms-2" title="Annotate Proposal">
                                                        <i class="bi bi-pin-angle-fill"></i>
                                                    </a>
                                                <?php else: ?>
                                                    <button type="button" class="btn btn-sm btn-info ms-2" onclick="previewDocument('<?php echo addslashes($doc['file_path']); ?>', '<?php echo addslashes($doc['document_name']); ?>', <?php echo $doc['document_id']; ?>)" title="View Document">
                                                        <i class="bi bi-eye"></i>
                                                    </button>
                                                <?php endif; ?>
                                                <a href="view-document.php?id=<?php echo $doc['document_id']; ?>" target="_blank" class="btn btn-sm btn-light border ms-2" title="Download Document">
                                                    <i class="bi bi-download"></i>
                                                </a>
                                            </div>
                                        </div>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <div class="col-12 text-center text-muted py-3">No uploaded documents found.</div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Forms Tab -->
                        <div class="tab-pane fade" id="forms" role="tabpanel">
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <h6 class="fw-bold text-dark mb-0">Application Forms</h6>
                                <div class="btn-group" role="group">
                                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="loadForm('qf01')">QF-01</button>
                                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="loadForm('qf02')">QF-02</button>
                                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="loadForm('category')">Category Checklist</button>
                                    <button type="button" class="btn btn-sm btn-success" onclick="expandForm()" id="expandBtn" style="display: none;">
                                        <i class="bi bi-arrows-fullscreen me-1"></i> Expand
                                    </button>
                                </div>
                            </div>
                            
                            <div id="formViewer" class="border rounded bg-light" style="height: 600px; position: relative;">
                                <div class="d-flex align-items-center justify-content-center h-100 text-muted">
                                    <div class="text-center">
                                        <i class="bi bi-clipboard-check display-1 mb-3"></i>
                                        <h5>Select a form to view</h5>
                                        <p class="small">Click on the form buttons above to load the application form for review.</p>
                                        <button type="button" class="btn btn-sm btn-outline-primary mt-2" onclick="debugShowIframe()">Debug: Show Iframe</button>
                                    </div>
                                </div>
                                <iframe id="formIframe" style="display: none; width: 100%; height: 100%; border: none;" sandbox="allow-same-origin allow-scripts allow-forms allow-popups"></iframe>
                            </div>
                        </div>

                        <!-- History Tab -->
                        <div class="tab-pane fade" id="history" role="tabpanel">
                            <div class="timeline p-3">
                                <div class="alert alert-light border small text-muted">
                                    <i class="bi bi-info-circle me-1"></i> Full audit trail will be available in the upcoming version.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sidebar Actions Card -->
        <div class="col-xl-4">
            <div class="card border-0 shadow-sm mb-4" style="border-radius: 20px; overflow: hidden; position: sticky; top: 80px; align-self: flex-start;">
                <div class="card-header bg-success text-white py-3 px-4 border-0">
                    <h5 class="fw-bold mb-0 text-center"><i class="bi bi-shield-lock me-2"></i> Review Actions</h5>
                </div>
                <div class="card-body p-4">
                    <?php if ($is_chairperson && !empty($application['urec_reviewed_by'])): ?>
                    <!-- Reset Evaluator Assignment -->
                    <div class="text-center mb-4">
                        <div class="alert alert-warning border-0 small mb-3">
                            <i class="bi bi-info-circle me-2"></i>
                            <strong>Evaluator Assigned:</strong> This application has been assigned to an evaluator.
                        </div>
                        <a href="urec_admin/reset-evaluator-assignment.php?queue=<?php echo urlencode($queue_number); ?>" 
                           class="btn btn-warning btn-sm rounded-pill px-4">
                            <i class="bi bi-arrow-clockwise me-2"></i>Reset Evaluator Assignment
                        </a>
                        <p class="text-muted small mt-2 mb-0">Clear current assignment to reassign to different evaluators.</p>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($is_chairperson && empty($application['urec_reviewed_by'])): ?>
                        <!-- Chairperson Assignment Section -->
                        <div class="text-center mb-4">
                            <div class="bg-warning bg-opacity-10 p-3 rounded-4 mb-3">
                                <i class="bi bi-person-plus text-warning fs-1"></i>
                                <h6 class="fw-bold mt-2">Needs Evaluation Assignment</h6>
                                <p class="small text-muted mb-0">Assign a UREC member to conduct the ethical review for this application.</p>
                            </div>
                            
                            <form action="process-action.php" method="POST">
                                <input type="hidden" name="queue_number" value="<?php echo $queue_number; ?>">
                                <input type="hidden" name="action" value="assign_evaluators">
                                <div class="mb-3 text-start">
                                    <label class="form-label small fw-bold">Select Evaluator(s)</label>
                                    <div class="border rounded-3 p-3 bg-light" style="max-height: 200px; overflow-y: auto;">
                                        <?php if ($members): mysqli_data_seek($members, 0); ?>
                                            <?php while ($member = $members->fetch_assoc()): ?>
                                                <div class="form-check mb-2">
                                                    <input class="form-check-input" type="checkbox" name="evaluator_ids[]" value="<?php echo $member['user_id']; ?>" id="eval_<?php echo $member['user_id']; ?>">
                                                    <label class="form-check-label d-flex justify-content-between" for="eval_<?php echo $member['user_id']; ?>">
                                                        <span>
                                                            <strong><?php echo htmlspecialchars($member['full_name']); ?></strong>
                                                            <small class="text-muted">(<?php echo htmlspecialchars($member['committee_designation']); ?>)</small>
                                                        </span>
                                                    </label>
                                                </div>
                                            <?php endwhile; ?>
                                        <?php endif; ?>
                                    </div>
                                    <small class="text-muted">Select one or more UREC members to evaluate this application. You can include yourself.</small>
                                </div>
                                <button type="submit" class="btn btn-success w-100 py-3 rounded-pill fw-bold shadow-sm">
                                    <i class="bi bi-person-check me-2"></i> Confirm Assignment
                                </button>
                            </form>
                        </div>
                    <?php elseif ($is_any_assigned_evaluator && in_array($application['current_status'], [STATUS_UNDER_ETHICAL_REVIEW, STATUS_ASSIGNING_UREC_EVALUATOR])): ?>
                        <!-- Evaluator Decision Section -->
                        <div class="text-center">
                            <div class="bg-primary bg-opacity-10 p-3 rounded-4 mb-4">
                                <i class="bi bi-clipboard-check text-primary fs-1"></i>
                                <h6 class="fw-bold mt-2">Ethical Evaluation</h6>
                                <p class="small text-muted mb-0">Submit your final ethical review decision for this research project.</p>
                            </div>
                            
                            <div class="d-grid gap-3">
                                <button class="btn btn-success py-3 rounded-4 fw-bold shadow-sm" onclick="openReviewModal('approve')">
                                    <i class="bi bi-check-circle me-2"></i> Approved
                                </button>
                                <button class="btn btn-info py-3 rounded-4 fw-bold shadow-sm" onclick="openReviewModal('minor_revision')">
                                    <i class="bi bi-pencil me-2"></i> Approved with Minor Revision
                                </button>
                                <button class="btn btn-warning py-3 rounded-4 fw-bold shadow-sm" onclick="openReviewModal('major_revision')">
                                    <i class="bi bi-arrow-repeat me-2"></i> Approved with Major Revision
                                </button>
                                <button class="btn btn-danger py-3 rounded-4 fw-bold shadow-sm" onclick="openReviewModal('resubmit')">
                                    <i class="bi bi-x-circle me-2"></i> Resubmit Application
                                </button>
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- Read Only / Waiting State -->
                        <div class="text-center py-4">
                            <i class="bi bi-hourglass-split text-muted display-4 mb-3"></i>
                            <h6 class="fw-bold text-muted">Currently in Review</h6>
                            <p class="small text-muted">This application is under evaluation. You will be notified once a decision is made.</p>
                            <?php if (!empty($assigned_evaluators)): ?>
                                <div class="mt-3 p-3 bg-light rounded-3 text-start small">
                                    <div class="fw-bold"><i class="bi bi-people me-1"></i> Assigned Evaluators:</div>
                                    <?php foreach ($assigned_evaluators as $evaluator): ?>
                                        <div class="mb-2 pb-2 border-bottom">
                                            <div><strong><?php echo htmlspecialchars($evaluator['full_name']); ?></strong></div>
                                            <small class="text-muted"><?php echo htmlspecialchars($evaluator['committee_designation']); ?></small>
                                            <div class="mt-1 text-muted">Assigned: <?php echo formatDate($evaluator['assigned_at']); ?></div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                
                <!-- Communication Log Section -->
                <hr class="my-4">
                <div class="text-center">
                    <h6 class="fw-bold text-muted mb-3"><i class="bi bi-chat-dots me-2"></i>Communication Log</h6>
                    <p class="small text-muted mb-3">Check the Messages tab for full communication history with the applicant.</p>
                    <a href="#" class="btn btn-sm btn-outline-primary rounded-pill px-4">Go to Messages</a>
                </div>
                </div>
            </div>

                    </div>
    </div>
</div>

<!-- Review Modal -->
<div class="modal fade" id="reviewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 20px;">
            <form action="process-action.php" method="POST">
                <div class="modal-header border-0 pb-0 ps-4">
                    <h5 class="modal-title fw-bold" id="modalTitle">Evaluation Decision</h5>
                    <button type="button" class="btn-close" data-bs-toggle="modal" data-bs-target="#reviewModal"></button>
                </div>
                <div class="modal-body p-4">
                    <input type="hidden" name="queue_number" value="<?php echo $queue_number; ?>">
                    <input type="hidden" name="action" id="modalAction">
                    
                    <div class="alert alert-info border-0 small" id="actionNote">
                        <!-- Dynamic text -->
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Review Notes / Remarks</label>
                        <textarea name="remarks" class="form-control" rows="4" placeholder="Enter your evaluation notes here..." required></textarea>
                        <div class="form-text small">These notes will be forwarded to the primary office staff and may be shared with the applicant.</div>
                    </div>
                </div>
                <div class="modal-footer border-0 p-4 pt-0">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success rounded-pill px-4 fw-bold" id="submitBtn">Submit Decision</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openReviewModal(type) {
    const modal = new bootstrap.Modal(document.getElementById('reviewModal'));
    const title = document.getElementById('modalTitle');
    const note = document.getElementById('actionNote');
    const action = document.getElementById('modalAction');
    const submitBtn = document.getElementById('submitBtn');

    action.value = type;
    
    if (type === 'approve') {
        title.innerText = 'Ethical Approval';
        note.innerHTML = '<i class="bi bi-check-circle me-2"></i> This research meets all ethical requirements and is approved for implementation.';
        submitBtn.className = 'btn btn-success rounded-pill px-4 fw-bold';
    } else if (type === 'minor_revision') {
        title.innerText = 'Approved with Minor Revision';
        note.innerHTML = '<i class="bi bi-pencil me-2"></i> This research is approved but requires minor revisions. Please specify the changes needed.';
        submitBtn.className = 'btn btn-info rounded-pill px-4 fw-bold';
    } else if (type === 'major_revision') {
        title.innerText = 'Approved with Major Revision';
        note.innerHTML = '<i class="bi bi-arrow-repeat me-2"></i> This research requires significant revisions before approval. Please detail the major modifications required.';
        submitBtn.className = 'btn btn-warning rounded-pill px-4 fw-bold text-dark';
    } else if (type === 'resubmit') {
        title.innerText = 'Resubmit Application';
        note.innerHTML = '<i class="bi bi-x-circle me-2 text-danger"></i> This application must be resubmitted with substantial changes. Please provide clear justification for resubmission requirements.';
        submitBtn.className = 'btn btn-danger rounded-pill px-4 fw-bold';
    }

    modal.show();
}

function loadForm(formType) {
    const queueNumber = '<?php echo $queue_number; ?>';
    const formViewer = document.getElementById('formViewer');
    const iframe = document.getElementById('formIframe');
    const placeholder = formViewer.querySelector('.d-flex.align-items-center');
    
    // Map form types to actual files (from urec/ folder, go up one level to root, then to applicant/)
    const formMap = {
        'qf01': '../applicant/fill-qf01-form.php',
        'qf02': '../applicant/fill-qf02-form.php',
        'category': '../applicant/fill-category-form.php'
    };
    
    // Get the appropriate category checklist based on application category
    if (formType === 'category') {
        const category = '<?php echo $application['category'] ?? ''; ?>';
        if (category === 'human') {
            formMap['category'] = '../applicant/fill-Human-checklist.php';
        } else if (category === 'animal') {
            formMap['category'] = '../applicant/fill-Animal-checklist.php';
        } else if (category === 'engineering') {
            formMap['category'] = '../applicant/fill-Engineering-checklist.php';
        } else if (category === 'food') {
            formMap['category'] = '../applicant/fill-Food-checklist.php';
        } else if (category === 'plant') {
            formMap['category'] = '../applicant/fill-Plant-checklist.php';
        } else {
            formMap['category'] = '../applicant/fill-category-form.php';
        }
    }
    
    const formUrl = (formMap[formType] || '../applicant/fill-category-form.php') + "?review=1&queue=" + encodeURIComponent(queueNumber);
    
    // Debug: log the URL being loaded
    console.log('Loading form URL:', formUrl);
    
    // Show loading state
    if (placeholder) {
        placeholder.innerHTML = `
            <div class="text-center">
                <div class="spinner-border text-primary mb-3" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <h5>Loading form...</h5>
                <p class="small">Please wait while we load the application form.</p>
                <p class="small text-muted"><code>${formUrl}</code></p>
            </div>
        `;
    }
    
    // Load the form in iframe
    iframe.src = formUrl;
    iframe.style.display = 'block';
    
    // Hide placeholder when iframe loads
    iframe.onload = function() {
        console.log('Iframe loaded successfully');
        if (placeholder) {
            placeholder.remove(); // Completely remove placeholder
        }
        iframe.style.display = 'block';
        iframe.style.height = '100%';
        iframe.style.width = '100%';
        iframe.style.border = 'none';
        iframe.style.background = 'white';
        
        // Show expand button
        const expandBtn = document.getElementById('expandBtn');
        if (expandBtn) {
            expandBtn.style.display = 'inline-block';
        }
    };
    
    // Additional check after a delay
    setTimeout(() => {
        try {
            if (iframe.contentDocument && iframe.contentDocument.readyState === 'complete') {
                console.log('Iframe content fully loaded');
                const placeholder = document.getElementById('formViewer').querySelector('.d-flex.align-items-center');
                if (placeholder) {
                    placeholder.remove();
                }
                iframe.style.display = 'block';
                iframe.style.height = '100%';
                iframe.style.width = '100%';
            }
        } catch (e) {
            console.log('Cross-origin iframe, but should still be visible');
        }
    }, 1000);
    
    // Handle iframe errors
    iframe.onerror = function() {
        if (placeholder) {
            placeholder.style.display = 'flex';
            placeholder.innerHTML = `
                <div class="text-center text-danger">
                    <i class="bi bi-exclamation-triangle display-1 mb-3"></i>
                    <h5>Error loading form</h5>
                    <p class="small">Unable to load the selected form. Please try again.</p>
                    <button class="btn btn-sm btn-primary" onclick="loadForm('${formType}')">Retry</button>
                </div>
            `;
        }
        iframe.style.display = 'none';
    };
}

function debugShowIframe() {
    const iframe = document.getElementById('formIframe');
    const placeholder = document.getElementById('formViewer').querySelector('.d-flex.align-items-center');
    
    console.log('Debug: Force showing iframe');
    console.log('Iframe src:', iframe.src);
    console.log('Iframe display:', iframe.style.display);
    
    if (placeholder) {
        placeholder.remove();
    }
    
    iframe.style.display = 'block';
    iframe.style.height = '100%';
    iframe.style.width = '100%';
    iframe.style.border = '2px solid red';
    iframe.style.background = 'white';
}

function previewDocument(path, name, documentId) {
    if (!path || path === '') {
        alert('Document path is not available.');
        return;
    }
    
    // Set modal title
    document.getElementById('previewModalTitle').innerHTML = '<i class="bi bi-files"></i> ' + (name || 'Document Preview');
    
    // Clear iframe first
    const iframe = document.getElementById('documentFrame');
    iframe.src = '';
    
    // Show modal first
    const modal = new bootstrap.Modal(document.getElementById('previewModal'));
    modal.show();
    
    // Set iframe source after modal is shown
    setTimeout(() => {
        iframe.src = 'view-document.php?id=' + documentId;
        
        // Add error handling
        iframe.onerror = function() {
            console.error('Failed to load document');
            alert('Failed to load document. Please try downloading instead.');
        };
        
        iframe.onload = function() {
            console.log('Document loaded successfully');
        };
    }, 100);
}

// Expand form to full screen modal
function expandForm() {
    const currentFormUrl = document.getElementById('formIframe').src;
    const expandModal = new bootstrap.Modal(document.getElementById('expandFormModal'));
    const expandIframe = document.getElementById('expandFormIframe');
    
    // Set the iframe source to current form URL
    expandIframe.src = currentFormUrl;
    
    // Show the modal
    expandModal.show();
    
    // Update modal title based on current form
    const formType = getCurrentFormType();
    const titleElement = document.getElementById('expandFormTitle');
    
    const formNames = {
        'qf01': 'QF-01 Application Form',
        'qf02': 'QF-02 Application Form', 
        'category': 'Category Checklist'
    };
    
    titleElement.innerHTML = `<i class="bi bi-arrows-fullscreen me-2"></i>${formNames[formType] || 'Application Form'} - Full View`;
}

// Get current form type from URL
function getCurrentFormType() {
    const currentSrc = document.getElementById('formIframe').src;
    if (currentSrc.includes('qf01')) return 'qf01';
    if (currentSrc.includes('qf02')) return 'qf02';
    if (currentSrc.includes('category')) return 'category';
    return 'unknown';
}

</script>

<!-- Expand Form Modal -->
<div class="modal fade" id="expandFormModal" tabindex="-1">
    <div class="modal-dialog modal-fullscreen">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #0f2942, #1a3a52); color: white;">
                <h5 class="modal-title" id="expandFormTitle"><i class="bi bi-arrows-fullscreen me-2"></i>Application Form - Full View</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0" style="height: calc(100vh - 60px);">
                <iframe id="expandFormIframe" style="width: 100%; height: 100%; border: none;" sandbox="allow-same-origin allow-scripts allow-forms allow-popups"></iframe>
            </div>
        </div>
    </div>
</div>

<!-- Document Preview Modal -->
<div class="modal fade" id="previewModal" tabindex="-1">
    <div class="modal-dialog modal-fullscreen">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #0f2942, #1a3a52); color: white;">
                <h5 class="modal-title" id="previewModalTitle"><i class="bi bi-files"></i> Document Preview</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0" style="height: calc(100vh - 60px);">
                <iframe id="documentFrame" style="width: 100%; height: 100%; border: none;"></iframe>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes_urec/footer.php'; ?>
