<?php
declare(strict_types=1);
/**
 * UREC Chairperson Reports
 * TAU-UREO Portal
 */

require_once '../../config/config.php';
require_once '../../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$user_id = $_SESSION['user_id'];
$committee_id = $_SESSION['committee_id'] ?? null;
$committee_designation = $_SESSION['committee_designation'] ?? '';
$is_chairperson = (stripos($committee_designation, 'Chair') !== false || stripos($committee_designation, 'Head') !== false);

if (!$is_chairperson) {
    header("Location: ../dashboard.php");
    exit();
}

$conn = getDBConnection();

// Get committee statistics
$stats = [
    'total_applications' => 0,
    'pending_assignment' => 0,
    'under_review' => 0,
    'completed' => 0,
    'approved' => 0,
    'rejected' => 0,
    'revisions_requested' => 0
];

// Total applications for this committee
$total_stmt = $conn->prepare("SELECT COUNT(*) as count FROM applications WHERE urec_committee_id = ?");
$total_stmt->bind_param("i", $committee_id);
$total_stmt->execute();
$stats['total_applications'] = $total_stmt->get_result()->fetch_assoc()['count'];

// Pending assignment
$pending_stmt = $conn->prepare("SELECT COUNT(*) as count FROM applications WHERE urec_committee_id = ? AND current_status = ?");
$pending_status = STATUS_ASSIGNING_UREC_EVALUATOR;
$pending_stmt->bind_param("is", $committee_id, $pending_status);
$pending_stmt->execute();
$stats['pending_assignment'] = $pending_stmt->get_result()->fetch_assoc()['count'];

// Under review
$review_stmt = $conn->prepare("SELECT COUNT(*) as count FROM applications WHERE urec_committee_id = ? AND current_status = ?");
$review_status = STATUS_UNDER_ETHICAL_REVIEW;
$review_stmt->bind_param("is", $committee_id, $review_status);
$review_stmt->execute();
$stats['under_review'] = $review_stmt->get_result()->fetch_assoc()['count'];

// Completed applications
$completed_statuses = [STATUS_APPROVED, STATUS_REJECTED, STATUS_REVISION_REQUIRED];
$completed_placeholders = str_repeat('?,', count($completed_statuses) - 1) . '?';
$completed_stmt = $conn->prepare("SELECT COUNT(*) as count FROM applications WHERE urec_committee_id = ? AND current_status IN ($completed_placeholders)");
$completed_params = array_merge([$committee_id], $completed_statuses);
$completed_stmt->bind_param(str_repeat('i', count($completed_params)), ...$completed_params);
$completed_stmt->execute();
$stats['completed'] = $completed_stmt->get_result()->fetch_assoc()['count'];

// Individual status counts
$approved_stmt = $conn->prepare("SELECT COUNT(*) as count FROM applications WHERE urec_committee_id = ? AND current_status = ?");
$approved_stmt->bind_param("is", $committee_id, STATUS_APPROVED);
$approved_stmt->execute();
$stats['approved'] = $approved_stmt->get_result()->fetch_assoc()['count'];

$rejected_stmt = $conn->prepare("SELECT COUNT(*) as count FROM applications WHERE urec_committee_id = ? AND current_status = ?");
$rejected_stmt->bind_param("is", $committee_id, STATUS_REJECTED);
$rejected_stmt->execute();
$stats['rejected'] = $rejected_stmt->get_result()->fetch_assoc()['count'];

$revisions_stmt = $conn->prepare("SELECT COUNT(*) as count FROM applications WHERE urec_committee_id = ? AND current_status = ?");
$revisions_stmt->bind_param("is", $committee_id, STATUS_REVISION_REQUIRED);
$revisions_stmt->execute();
$stats['revisions_requested'] = $revisions_stmt->get_result()->fetch_assoc()['count'];

// Get recent applications
$recent_stmt = $conn->prepare("
    SELECT queue_number, applicant_name, research_title, current_status, urec_reviewed_by, 
           urec_decision_date, submission_timestamp
    FROM applications 
    WHERE urec_committee_id = ?
    ORDER BY urec_decision_date DESC, submission_timestamp DESC 
    LIMIT 10
");
$recent_stmt->bind_param("i", $committee_id);
$recent_stmt->execute();
$recent_applications = $recent_stmt->get_result();

// Get member performance
$member_stats = $conn->prepare("
    SELECT u.full_name, u.user_id,
           COUNT(a.queue_number) as total_assigned,
           SUM(CASE WHEN a.current_status = 'APPROVED' THEN 1 ELSE 0 END) as approved,
           SUM(CASE WHEN a.current_status = 'REJECTED' THEN 1 ELSE 0 END) as rejected,
           SUM(CASE WHEN a.current_status = 'REVISION_REQUIRED' THEN 1 ELSE 0 END) as revisions,
           AVG(DATEDIFF(a.urec_decision_date, a.urec_assigned_at)) as avg_review_days
    FROM users u
    LEFT JOIN applications a ON u.user_id = a.urec_reviewed_by AND u.committee_id = a.urec_committee_id
    WHERE u.committee_id = ? AND u.user_role = 'urec'
    GROUP BY u.user_id, u.full_name
    ORDER BY total_assigned DESC
");
$member_stats->bind_param("i", $committee_id);
$member_stats->execute();
$members_performance = $member_stats->get_result();

closeDBConnection($conn);

$page_title = 'Committee Reports';
$active_menu = 'reports';

require_once __DIR__ . '/../../includes_urec/header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold text-dark mb-1">
                <i class="bi bi-bar-chart-fill me-2 text-primary"></i>Committee Reports
            </h2>
            <p class="text-muted small mb-0">
                Performance overview for <?php echo htmlspecialchars($committee_designation); ?>
            </p>
        </div>
        <div class="text-end">
            <span class="badge bg-primary rounded-pill px-3 py-2">
                <?php echo $stats['total_applications']; ?> Total Applications
            </span>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <i class="bi bi-hourglass-split text-warning display-6 mb-2"></i>
                    <h3 class="fw-bold text-dark"><?php echo $stats['pending_assignment']; ?></h3>
                    <p class="text-muted small mb-0">Pending Assignment</p>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <i class="bi bi-eye text-info display-6 mb-2"></i>
                    <h3 class="fw-bold text-dark"><?php echo $stats['under_review']; ?></h3>
                    <p class="text-muted small mb-0">Under Review</p>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <i class="bi bi-check-circle text-success display-6 mb-2"></i>
                    <h3 class="fw-bold text-dark"><?php echo $stats['approved']; ?></h3>
                    <p class="text-muted small mb-0">Approved</p>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <i class="bi bi-x-circle text-danger display-6 mb-2"></i>
                    <h3 class="fw-bold text-dark"><?php echo $stats['rejected']; ?></h3>
                    <p class="text-muted small mb-0">Rejected</p>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Recent Applications -->
        <div class="col-lg-8 mb-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-bottom-0">
                    <h6 class="mb-0 fw-bold text-dark">
                        <i class="bi bi-clock-history text-primary me-2"></i>Recent Applications
                    </h6>
                </div>
                <div class="card-body p-0">
                    <?php if ($recent_applications->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="bg-light text-muted small text-uppercase">
                                    <tr>
                                        <th class="ps-4 py-3">Ref. No.</th>
                                        <th>Applicant</th>
                                        <th>Status</th>
                                        <th>Decision Date</th>
                                        <th class="pe-4 text-center">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($app = $recent_applications->fetch_assoc()): ?>
                                        <tr>
                                            <td class="ps-4">
                                                <div class="fw-bold text-dark"><?php echo htmlspecialchars($app['queue_number']); ?></div>
                                            </td>
                                            <td>
                                                <div class="small"><?php echo htmlspecialchars($app['applicant_name']); ?></div>
                                            </td>
                                            <td>
                                                <?php 
                                                $status_class = 'secondary';
                                                if (in_array($app['current_status'], ['APPROVED'])) $status_class = 'success';
                                                elseif (in_array($app['current_status'], ['REJECTED'])) $status_class = 'danger';
                                                elseif (in_array($app['current_status'], ['REVISION_REQUIRED'])) $status_class = 'warning';
                                                elseif (in_array($app['current_status'], ['UNDER_ETHICAL_REVIEW'])) $status_class = 'info';
                                                ?>
                                                <span class="badge bg-<?php echo $status_class; ?> small">
                                                    <?php echo getStatusDisplay($app['current_status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <small class="text-muted">
                                                    <?php echo $app['urec_decision_date'] ? formatDate($app['urec_decision_date']) : 'Pending'; ?>
                                                </small>
                                            </td>
                                            <td class="pe-4 text-center">
                                                <a href="../view-application.php?queue=<?php echo urlencode($app['queue_number']); ?>" class="btn btn-sm btn-light border">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="bi bi-inbox text-muted display-1 mb-3"></i>
                            <p class="text-muted">No applications found for this committee.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Member Performance -->
        <div class="col-lg-4 mb-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-bottom-0">
                    <h6 class="mb-0 fw-bold text-dark">
                        <i class="bi bi-people text-primary me-2"></i>Member Performance
                    </h6>
                </div>
                <div class="card-body">
                    <?php if ($members_performance->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead class="text-muted small">
                                    <tr>
                                        <th>Member</th>
                                        <th class="text-center">Assigned</th>
                                        <th class="text-center">Completed</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($member = $members_performance->fetch_assoc()): ?>
                                        <tr>
                                            <td>
                                                <div class="small fw-bold"><?php echo htmlspecialchars($member['full_name']); ?></div>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-info small"><?php echo $member['total_assigned']; ?></span>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-success small">
                                                    <?php echo ($member['approved'] + $member['rejected'] + $member['revisions']); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-muted text-center">No member data available.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes_urec/footer.php'; ?>
