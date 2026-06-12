<?php
declare(strict_types=1);
/**
 * UREC Reports & Analytics
 * TAU-UREO Portal
 */

require_once '../config/config.php';
require_once '../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$user_id = $_SESSION['user_id'];
$committee_id = $_SESSION['committee_id'] ?? null;
$committee_designation = $_SESSION['committee_designation'] ?? '';
$is_chairperson = (stripos($committee_designation, 'Chair') !== false || stripos($committee_designation, 'Head') !== false);

$conn = getDBConnection();

// Get report period
$period = $_GET['period'] ?? 'month';
$date_filter = '';

switch ($period) {
    case 'week':
        $date_filter = "DATE_SUB(NOW(), INTERVAL 7 DAY)";
        break;
    case 'month':
        $date_filter = "DATE_SUB(NOW(), INTERVAL 30 DAY)";
        break;
    case 'quarter':
        $date_filter = "DATE_SUB(NOW(), INTERVAL 90 DAY)";
        break;
    case 'year':
        $date_filter = "DATE_SUB(NOW(), INTERVAL 365 DAY)";
        break;
    default:
        $date_filter = "DATE_SUB(NOW(), INTERVAL 30 DAY)";
}

// UREC specific status distribution
$status_query = "
    SELECT current_status, COUNT(*) as count
    FROM applications
    WHERE urec_committee_id = ? 
    AND last_updated >= $date_filter
    GROUP BY current_status
";
$stmt = $conn->prepare($status_query);
$stmt->bind_param("i", $committee_id);
$stmt->execute();
$status_result = $stmt->get_result();
$status_data = [];
while ($row = $status_result->fetch_assoc()) {
    $status_data[$row['current_status']] = $row['count'];
}

// Processing time stats (from Forwarded to Approved)
$time_stats_query = "
    SELECT 
        AVG(DATEDIFF(urec_decision_date, urec_assigned_at)) as avg_days,
        MIN(DATEDIFF(urec_decision_date, urec_assigned_at)) as min_days,
        MAX(DATEDIFF(urec_decision_date, urec_assigned_at)) as max_days
    FROM applications
    WHERE current_status IN ('APPROVED', 'REJECTED')
    AND urec_committee_id = ?
    AND urec_decision_date >= $date_filter
";
$stmt = $conn->prepare($time_stats_query);
$stmt->bind_param("i", $committee_id);
$stmt->execute();
$time_stats = $stmt->get_result()->fetch_assoc();

// Evaluator performance
$eval_performance = $conn->prepare("
    SELECT 
        u.full_name,
        COUNT(a.queue_number) as total_reviewed,
        SUM(CASE WHEN a.current_status = 'APPROVED' THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN a.current_status = 'REVISION_REQUIRED' THEN 1 ELSE 0 END) as revisions
    FROM users u
    LEFT JOIN applications a ON u.user_id = a.urec_reviewed_by 
        AND a.urec_assigned_at >= $date_filter
    WHERE u.committee_id = ?
    GROUP BY u.user_id, u.full_name
    ORDER BY total_reviewed DESC
");
$eval_performance->bind_param("i", $committee_id);
$eval_performance->execute();
$evaluators = $eval_performance->get_result();

closeDBConnection($conn);

$page_title = 'UREC Reports';
$active_menu = 'reports';
require_once __DIR__ . '/../includes_urec/header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold text-dark mb-1"><i class="bi bi-graph-up-arrow me-2 text-success"></i> UREC Analytics</h2>
            <p class="text-muted small mb-0">Review committee performance and application turnaround times.</p>
        </div>
        <div>
            <form method="GET" class="d-inline">
                <select name="period" class="form-select border-0 shadow-sm rounded-pill px-4" onchange="this.form.submit()">
                    <option value="week" <?php echo $period === 'week' ? 'selected' : ''; ?>>Last 7 Days</option>
                    <option value="month" <?php echo $period === 'month' ? 'selected' : ''; ?>>Last 30 Days</option>
                    <option value="quarter" <?php echo $period === 'quarter' ? 'selected' : ''; ?>>Last 90 Days</option>
                    <option value="year" <?php echo $period === 'year' ? 'selected' : ''; ?>>Last Year</option>
                </select>
            </form>
        </div>
    </div>

    <!-- Key Metrics Cards -->
    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100 bg-white" style="border-radius: 20px;">
                <div class="card-body text-center p-4">
                    <div class="bg-primary bg-opacity-10 p-3 rounded-circle d-inline-block mb-3">
                        <i class="bi bi-journal-check text-primary fs-3"></i>
                    </div>
                    <h3 class="fw-bold mb-1"><?php echo array_sum($status_data); ?></h3>
                    <p class="text-muted small mb-0 fw-bold text-uppercase">Total Processed</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100 bg-white" style="border-radius: 20px;">
                <div class="card-body text-center p-4">
                    <div class="bg-success bg-opacity-10 p-3 rounded-circle d-inline-block mb-3">
                        <i class="bi bi-clock-history text-success fs-3"></i>
                    </div>
                    <h3 class="fw-bold mb-1"><?php echo round($time_stats['avg_days'] ?? 0, 1); ?></h3>
                    <p class="text-muted small mb-0 fw-bold text-uppercase">Avg. Review Days</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100 bg-white" style="border-radius: 20px;">
                <div class="card-body text-center p-4">
                    <div class="bg-info bg-opacity-10 p-3 rounded-circle d-inline-block mb-3">
                        <i class="bi bi-check2-circle text-info fs-3"></i>
                    </div>
                    <h3 class="fw-bold mb-1"><?php echo $status_data['APPROVED'] ?? 0; ?></h3>
                    <p class="text-muted small mb-0 fw-bold text-uppercase">Full Approvals</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100 bg-white" style="border-radius: 20px;">
                <div class="card-body text-center p-4">
                    <div class="bg-warning bg-opacity-10 p-3 rounded-circle d-inline-block mb-3">
                        <i class="bi bi-arrow-repeat text-warning fs-3"></i>
                    </div>
                    <h3 class="fw-bold mb-1"><?php echo $status_data['REVISION_REQUIRED'] ?? 0; ?></h3>
                    <p class="text-muted small mb-0 fw-bold text-uppercase">Revision Requests</p>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Status Distribution -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100" style="border-radius: 20px;">
                <div class="card-header bg-white py-4 px-4 border-0">
                    <h5 class="fw-bold mb-0">Outcome Distribution</h5>
                </div>
                <div class="card-body p-4 pt-0">
                    <?php if (count($status_data) > 0): ?>
                        <?php foreach ($status_data as $status => $count): ?>
                            <div class="mb-4">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="fw-bold small"><?php echo getStatusDisplayName($status); ?></span>
                                    <span class="badge bg-light text-dark border"><?php echo $count; ?> Applications</span>
                                </div>
                                <div class="progress rounded-pill" style="height: 10px;">
                                    <div class="progress-bar bg-<?php echo getStatusBadgeClass($status); ?>" 
                                         style="width: <?php echo (array_sum($status_data) > 0) ? ($count / array_sum($status_data) * 100) : 0; ?>%"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center py-5 text-muted">No data available for this period.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Evaluator Performance -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100" style="border-radius: 20px;">
                <div class="card-header bg-white py-4 px-4 border-0">
                    <h5 class="fw-bold mb-0">Evaluator Activity</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light text-muted small text-uppercase">
                                <tr>
                                    <th class="ps-4">Member</th>
                                    <th class="text-center">Total</th>
                                    <th class="text-center">Approved</th>
                                    <th class="text-center pe-4">Revisions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($evaluators->num_rows > 0): ?>
                                    <?php while ($eval = $evaluators->fetch_assoc()): ?>
                                        <tr>
                                            <td class="ps-4 py-3">
                                                <div class="fw-bold small"><?php echo htmlspecialchars($eval['full_name']); ?></div>
                                            </td>
                                            <td class="text-center"><span class="badge bg-light text-dark border"><?php echo $eval['total_reviewed']; ?></span></td>
                                            <td class="text-center text-success fw-bold small"><?php echo $eval['approved']; ?></td>
                                            <td class="text-center text-warning fw-bold small pe-4"><?php echo $eval['revisions']; ?></td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="py-5 text-center text-muted">No evaluator activity recorded.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes_urec/footer.php'; ?>
