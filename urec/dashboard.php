<?php
declare(strict_types=1);
/**
 * UREC Dashboard
 * TAU-UREO Portal
 */

require_once '../config/config.php';
require_once '../includes/functions.php';

// Auth check (handled by header, but we need variables here)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$user_id = $_SESSION['user_id'];
$committee_id = $_SESSION['committee_id'] ?? null;
$committee_designation = $_SESSION['committee_designation'] ?? '';
$is_chairperson = (stripos($committee_designation, 'Chair') !== false || stripos($committee_designation, 'Head') !== false);

$conn = getDBConnection();

// Initialize stats
$stats = [
    'assigned_to_me' => 0,
    'pending_review' => 0,
    'completed_by_me' => 0,
    'total_committee_pending' => 0
];

// 1. Get stats for individual member
$member_stats_query = "
    SELECT 
        current_status,
        COUNT(*) as count
    FROM applications
    WHERE urec_reviewed_by = ?
    GROUP BY current_status
";
$stmt = $conn->prepare($member_stats_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $stats['assigned_to_me'] += $row['count'];
    if ($row['current_status'] === STATUS_UNDER_ETHICAL_REVIEW) {
        $stats['pending_review'] = $row['count'];
    }
    if (in_array($row['current_status'], [STATUS_APPROVED, STATUS_REJECTED, STATUS_COMPLIANCE_PENDING])) {
        $stats['completed_by_me'] += $row['count'];
    }
}

// 2. If Chairperson, get committee-wide stats
if ($is_chairperson && $committee_id) {
    $comm_stats_query = "SELECT COUNT(*) as count FROM applications WHERE urec_committee_id = ? AND current_status = ?";
    $stmt = $conn->prepare($comm_stats_query);
    $forwarded = STATUS_FORWARDED_TO_UREC;
    $stmt->bind_param("is", $committee_id, $forwarded);
    $stmt->execute();
    $stats['total_committee_pending'] = $stmt->get_result()->fetch_assoc()['count'];
}

// 3. Get Recent Applications (Assigned to me)
$recent_query = "
    SELECT a.*, 
           DATEDIFF(NOW(), a.last_updated) as days_since_update
    FROM applications a
    WHERE a.urec_reviewed_by = ?
    ORDER BY a.last_updated DESC
    LIMIT 10
";
$stmt = $conn->prepare($recent_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$recent_applications = $stmt->get_result();

// 4. Get New Assignments (Forwarded to committee, if Chairperson)
$new_assignments = null;
if ($is_chairperson && $committee_id) {
    $new_query = "
        SELECT * FROM applications 
        WHERE urec_committee_id = ? AND current_status = ? 
        ORDER BY submission_timestamp DESC LIMIT 5
    ";
    $stmt = $conn->prepare($new_query);
    $forwarded = STATUS_FORWARDED_TO_UREC;
    $stmt->bind_param("is", $committee_id, $forwarded);
    $stmt->execute();
    $new_assignments = $stmt->get_result();
}

closeDBConnection($conn);

$page_title = 'UREC Dashboard';
$active_menu = 'dashboard';
require_once __DIR__ . '/../includes_urec/header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold text-dark mb-1">UREC Dashboard</h2>
            <p class="text-muted small mb-0">Welcome back, evaluator. Here's an overview of your ethical reviews.</p>
        </div>
        <div class="text-end">
            <span class="badge bg-light text-dark border p-2">
                <i class="bi bi-calendar3 me-1"></i> <?php echo date('F j, Y'); ?>
            </span>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100" style="border-left: 4px solid #006400 !important;">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <p class="text-muted small text-uppercase fw-bold mb-1">Assigned to Me</p>
                            <h3 class="fw-bold mb-0"><?php echo $stats['assigned_to_me']; ?></h3>
                        </div>
                        <div class="bg-light p-3 rounded-circle">
                            <i class="bi bi-person-check text-success fs-4"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100" style="border-left: 4px solid #ffc107 !important;">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <p class="text-muted small text-uppercase fw-bold mb-1">Pending Review</p>
                            <h3 class="fw-bold mb-0"><?php echo $stats['pending_review']; ?></h3>
                        </div>
                        <div class="bg-light p-3 rounded-circle">
                            <i class="bi bi-clock-history text-warning fs-4"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100" style="border-left: 4px solid #28a745 !important;">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <p class="text-muted small text-uppercase fw-bold mb-1">Completed Reviews</p>
                            <h3 class="fw-bold mb-0"><?php echo $stats['completed_by_me']; ?></h3>
                        </div>
                        <div class="bg-light p-3 rounded-circle">
                            <i class="bi bi-check2-all text-success fs-4"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php if ($is_chairperson): ?>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100" style="border-left: 4px solid #0d6efd !important;">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <p class="text-muted small text-uppercase fw-bold mb-1">Pending Assignment</p>
                            <h3 class="fw-bold mb-0"><?php echo $stats['total_committee_pending']; ?></h3>
                        </div>
                        <div class="bg-light p-3 rounded-circle">
                            <i class="bi bi-people text-primary fs-4"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <div class="row g-4">
        <!-- Main Work Area -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3 border-0">
                    <div class="d-flex align-items-center justify-content-between">
                        <h5 class="fw-bold mb-0"><i class="bi bi-journals me-2 text-success"></i> My Recent Reviews</h5>
                        <a href="review-queue.php" class="btn btn-sm btn-outline-success">View All</a>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light text-muted small text-uppercase">
                                <tr>
                                    <th class="ps-4">Application</th>
                                    <th>Applicant</th>
                                    <th>Status</th>
                                    <th>Update</th>
                                    <th class="pe-4 text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($recent_applications->num_rows > 0): ?>
                                    <?php while ($app = $recent_applications->fetch_assoc()): ?>
                                        <tr>
                                            <td class="ps-4">
                                                <div class="fw-bold text-dark"><?php echo htmlspecialchars($app['queue_number']); ?></div>
                                                <div class="small text-muted text-truncate" style="max-width: 200px;"><?php echo htmlspecialchars($app['research_title']); ?></div>
                                            </td>
                                            <td><small><?php echo htmlspecialchars($app['applicant_name']); ?></small></td>
                                            <td>
                                                <span class="badge bg-<?php echo getStatusBadgeClass($app['current_status']); ?> small">
                                                    <?php echo getStatusDisplayName($app['current_status']); ?>
                                                </span>
                                            </td>
                                            <td><small class="text-muted"><?php echo $app['days_since_update'] > 0 ? $app['days_since_update'].'d ago' : 'Today'; ?></small></td>
                                            <td class="pe-4 text-center">
                                                <a href="view-application.php?queue=<?php echo urlencode($app['queue_number']); ?>" class="btn btn-sm btn-light border">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="py-4 text-center text-muted">No reviews assigned to you yet.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sidebar / Chairperson Actions -->
        <div class="col-lg-4">
            <?php if ($is_chairperson): ?>
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-info text-white py-3 border-0">
                        <h5 class="fw-bold mb-0"><i class="bi bi-person-plus me-2"></i> New Assignments Needed</h5>
                    </div>
                    <div class="card-body p-3">
                        <?php if ($new_assignments && $new_assignments->num_rows > 0): ?>
                            <?php while ($new = $new_assignments->fetch_assoc()): ?>
                                <div class="p-2 border-bottom mb-2 last-child-border-0">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div class="flex-grow-1">
                                            <div class="fw-bold small"><?php echo htmlspecialchars($new['queue_number']); ?></div>
                                            <div class="small text-muted text-truncate" style="max-width: 150px;"><?php echo htmlspecialchars($new['research_title']); ?></div>
                                        </div>
                                        <a href="urec_admin/assign-application.php?queue=<?php echo urlencode($new['queue_number']); ?>" class="btn btn-xs btn-primary py-0">Assign</a>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                            <div class="mt-3 text-center">
                                <a href="urec_admin/assign-application.php" class="small text-decoration-none">View All Pending Assignments <i class="bi bi-arrow-right"></i></a>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-3">
                                <i class="bi bi-check-circle text-success display-6 mb-2"></i>
                                <p class="small text-muted">No new applications waiting for assignment.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3 border-0">
                    <h5 class="fw-bold mb-0"><i class="bi bi-info-circle me-2 text-primary"></i> UREC Resources</h5>
                </div>
                <div class="card-body">
                    <div class="list-group list-group-flush small">
                        <a href="#" class="list-group-item list-group-item-action border-0 px-0">
                            <i class="bi bi-file-earmark-pdf me-2 text-danger"></i> Ethics Review Guidelines
                        </a>
                        <a href="#" class="list-group-item list-group-item-action border-0 px-0">
                            <i class="bi bi-file-earmark-pdf me-2 text-danger"></i> UREC Policies & Procedures
                        </a>
                        <a href="#" class="list-group-item list-group-item-action border-0 px-0">
                            <i class="bi bi-calendar-event me-2 text-info"></i> Upcoming Meetings
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes_urec/footer.php'; ?>
