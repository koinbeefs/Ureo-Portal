<?php
declare(strict_types=1);
/**
 * UREC Review Queue
 * TAU-UREO Portal
 */

require_once '../config/config.php';
require_once '../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$user_id = $_SESSION['user_id'];
$conn = getDBConnection();

// Get applications assigned to this evaluator
$query = "
    SELECT a.*, 
           (SELECT COUNT(*) FROM documents WHERE queue_number = a.queue_number) as doc_count
    FROM applications a
    WHERE a.urec_reviewed_by = ?
    AND a.current_status IN (?, ?, ?)
    ORDER BY a.last_updated DESC
";
$stmt = $conn->prepare($query);
$status1 = STATUS_UNDER_ETHICAL_REVIEW;
$status2 = STATUS_COMPLIANCE_PENDING;
$status3 = STATUS_ASSIGNING_UREC_EVALUATOR; // In case they were just assigned
$stmt->bind_param("isss", $user_id, $status1, $status2, $status3);
$stmt->execute();
$applications = $stmt->get_result();

closeDBConnection($conn);

$page_title = 'Review Queue';
$active_menu = 'review';
require_once __DIR__ . '/../includes_urec/header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold text-dark"><i class="bi bi-list-check me-2 text-success"></i> My Review Queue</h2>
        <span class="badge bg-primary rounded-pill px-3 py-2"><?php echo $applications->num_rows; ?> Pending Reviews</span>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <?php if ($applications->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light text-muted small text-uppercase">
                            <tr>
                                <th class="ps-4 py-3">Reference No.</th>
                                <th>Research Title</th>
                                <th>Applicant</th>
                                <th>Status</th>
                                <th>Docs</th>
                                <th>Last Action</th>
                                <th class="pe-4 text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($app = $applications->fetch_assoc()): ?>
                                <tr>
                                    <td class="ps-4">
                                        <span class="fw-bold text-primary"><?php echo htmlspecialchars($app['queue_number']); ?></span>
                                    </td>
                                    <td>
                                        <div class="text-dark small fw-bold text-truncate" style="max-width: 300px;" title="<?php echo htmlspecialchars($app['research_title']); ?>">
                                            <?php echo htmlspecialchars($app['research_title']); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="small"><?php echo htmlspecialchars($app['applicant_name']); ?></div>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo getStatusBadgeClass($app['current_status']); ?> small">
                                            <?php echo getStatusDisplayName($app['current_status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark border"><i class="bi bi-file-earmark me-1"></i><?php echo $app['doc_count']; ?></span>
                                    </td>
                                    <td>
                                        <small class="text-muted"><?php echo formatDate($app['last_updated']); ?></small>
                                    </td>
                                    <td class="pe-4 text-center">
                                        <a href="view-application.php?queue=<?php echo urlencode($app['queue_number']); ?>" class="btn btn-success btn-sm shadow-sm px-3">
                                            <i class="bi bi-pencil-square me-1"></i> Review
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-5">
                    <i class="bi bi-check2-circle text-success display-1 mb-4"></i>
                    <h4 class="fw-bold">Your queue is empty!</h4>
                    <p class="text-muted">You have no pending applications to evaluate at this time.</p>
                    <a href="dashboard.php" class="btn btn-outline-success mt-2">Back to Dashboard</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes_urec/footer.php'; ?>
