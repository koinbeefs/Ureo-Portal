<?php
/**
 * Reports
 * TAU-UREO Portal
 */

require_once '../config/config.php';
require_once '../includes/functions.php';

requireLogin();
checkSessionTimeout();

$staff_id = $_SESSION['user_id'];
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

// Status distribution
$status_query = $conn->query("
    SELECT current_status, COUNT(*) as count
    FROM applications
    WHERE submission_timestamp >= $date_filter
    GROUP BY current_status
");
$status_data = [];
while ($row = $status_query->fetch_assoc()) {
    $status_data[$row['current_status']] = $row['count'];
}

// Applicant type distribution
$type_query = $conn->query("
    SELECT applicant_type, COUNT(*) as count
    FROM applications
    WHERE submission_timestamp >= $date_filter
    GROUP BY applicant_type
");
$type_data = [];
while ($row = $type_query->fetch_assoc()) {
    $type_data[$row['applicant_type']] = $row['count'];
}

// Processing time stats
$time_stats = $conn->query("
    SELECT 
        AVG(DATEDIFF(last_updated, submission_timestamp)) as avg_days,
        MIN(DATEDIFF(last_updated, submission_timestamp)) as min_days,
        MAX(DATEDIFF(last_updated, submission_timestamp)) as max_days
    FROM applications
    WHERE current_status IN ('APPROVED', 'REJECTED')
    AND submission_timestamp >= $date_filter
")->fetch_assoc();

// Staff performance
$staff_performance = $conn->query("
    SELECT 
        u.full_name,
        COUNT(a.queue_number) as total_assigned,
        SUM(CASE WHEN a.current_status = 'APPROVED' THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN a.current_status = 'REJECTED' THEN 1 ELSE 0 END) as rejected
    FROM users u
    LEFT JOIN applications a ON u.user_id = a.assigned_staff_id 
        AND a.submission_timestamp >= $date_filter
    WHERE u.role IN ('staff', 'admin')
    GROUP BY u.user_id, u.full_name
    ORDER BY total_assigned DESC
");

// Recent activity grouped by queue number
$recent_activity = $conn->query("
    SELECT 
        sh.queue_number,
        a.applicant_name,
        GROUP_CONCAT(
            CONCAT(
                sh.timestamp, '|||',
                sh.previous_status, '|||', 
                sh.new_status, '|||',
                COALESCE(u.full_name, sh.changed_by_type), '|||',
                sh.notes
            ) 
            ORDER BY sh.timestamp DESC 
            SEPARATOR '###'
        ) as activities
    FROM status_history sh
    JOIN applications a ON sh.queue_number = a.queue_number
    LEFT JOIN users u ON sh.changed_by = u.user_id
    WHERE sh.timestamp >= $date_filter
    GROUP BY sh.queue_number, a.applicant_name
    ORDER BY MAX(sh.timestamp) DESC
    LIMIT 10
");

closeDBConnection($conn);

$page_title = 'Reports';
$base_url = '../';
$active_menu = 'reports';
include '../includes/auth_header.php';
?>

<style>
/* Section Card Design */
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
}

.info-value {
    font-weight: 600;
    color: #333;
    margin-bottom: 0;
}

.metric-card {
    background: linear-gradient(135deg, #f8f9fa, #e9ecef);
    border: 1px solid rgba(0, 0, 0, 0.05);
    transition: all 0.3s ease;
}

.metric-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15) !important;
}

.metric-card .card-body {
    padding: 1.5rem;
}

.metric-card h3 {
    font-size: 2rem;
    font-weight: 700;
    margin: 0.5rem 0 0 0;
}

.metric-card small {
    font-size: 0.8rem;
    font-weight: 600;
    color: #666;
}

.accordion-button:focus {
    box-shadow: none;
    border-color: rgba(0, 0, 0, 0.125);
}

.accordion-button:not(.collapsed) {
    background-color: #f8f9fa;
    color: #495057;
}

.timeline {
    position: relative;
    padding-left: 20px;
}

.timeline::before {
    content: '';
    position: absolute;
    left: 15px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: #e9ecef;
}

.timeline-item {
    position: relative;
}

.timeline-marker {
    position: absolute;
    left: -23px;
    top: 6px;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    border: 2px solid white;
    box-shadow: 0 0 0 2px #e9ecef;
}

.accordion-body {
    padding: 1rem 1.25rem;
}

.progress {
    border-radius: 10px;
    overflow: hidden;
}

.progress-bar {
    transition: width 0.6s ease;
}

.table th {
    border-top: none;
    font-weight: 700;
    color: #495057;
    font-size: 0.875rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.table td {
    vertical-align: middle;
    font-size: 0.9rem;
}

.badge {
    font-weight: 600;
    padding: 0.375rem 0.75rem;
}

@media (max-width: 768px) {
    .section-card {
        margin-bottom: 1rem;
    }

    .section-card .card-header {
        padding: 1rem 1.5rem;
        font-size: 1rem;
    }

    .section-card .card-body {
        padding: 1rem 1.5rem;
    }

    .metric-card .card-body {
        padding: 1rem;
    }

    .metric-card h3 {
        font-size: 1.5rem;
    }

    /* Stack sidebar below main content on mobile */
    .col-lg-9.col-md-8,
    .col-lg-3.col-md-4 {
        width: 100% !important;
        max-width: 100% !important;
    }
}
</style>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 style="color: #006400; font-weight: 600;">
            <i class="bi bi-bar-chart"></i> Reports & Analytics
        </h2>
        <div>
            <form method="GET" class="d-inline">
                <select name="period" class="form-select" onchange="this.form.submit()">
                    <option value="week" <?php echo $period === 'week' ? 'selected' : ''; ?>>Last 7 Days</option>
                    <option value="month" <?php echo $period === 'month' ? 'selected' : ''; ?>>Last 30 Days</option>
                    <option value="quarter" <?php echo $period === 'quarter' ? 'selected' : ''; ?>>Last 90 Days</option>
                    <option value="year" <?php echo $period === 'year' ? 'selected' : ''; ?>>Last Year</option>
                </select>
            </form>
        </div>
    </div>

    <!-- Key Metrics -->
    <div class="section-card mb-4">
        <div class="card-header" style="color: #006400; font-weight: 700;">
            <i class="bi bi-bar-chart"></i> Key Metrics
        </div>
        <div class="card-body p-4">
            <div class="row g-3">
                <div class="col-6 col-sm-4 col-md-3">
                    <div class="stats-card h-100 border rounded">
                        <div class="card-body">
                            <div class="d-flex flex-column align-items-center">
                                <i class="bi bi-file-earmark-text text-primary"></i>
                                <h5 class="text-primary"><?php echo array_sum($status_data); ?></h5>
                                <small class="text-muted fw-medium">Total Applications</small>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-sm-4 col-md-3">
                    <div class="stats-card h-100 border rounded">
                        <div class="card-body">
                            <div class="d-flex flex-column align-items-center">
                                <i class="bi bi-clock-history text-info"></i>
                                <h5 class="text-info"><?php echo round($time_stats['avg_days'] ?? 0, 1); ?> days</h5>
                                <small class="text-muted fw-medium">Avg. Processing Time</small>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-sm-4 col-md-3">
                    <div class="stats-card h-100 border rounded">
                        <div class="card-body">
                            <div class="d-flex flex-column align-items-center">
                                <i class="bi bi-check-circle-fill text-success"></i>
                                <h5 class="text-success"><?php echo $status_data['APPROVED'] ?? 0; ?></h5>
                                <small class="text-muted fw-medium">Approved</small>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-sm-4 col-md-3">
                    <div class="stats-card h-100 border rounded">
                        <div class="card-body">
                            <div class="d-flex flex-column align-items-center">
                                <i class="bi bi-x-circle-fill text-danger"></i>
                                <h5 class="text-danger"><?php echo $status_data['REJECTED'] ?? 0; ?></h5>
                                <small class="text-muted fw-medium">Rejected</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Main Content - Recent Activity -->
        <div class="col-lg-9 col-md-8" style="position: sticky; top: 80px; align-self: flex-start;">
            <div class="section-card">
                <div class="card-header" style="color: #006400; font-weight: 700;">
                    <i class="bi bi-clock-history"></i> Recent Activity
                </div>
                <div class="card-body p-4" style="max-height: calc(100vh - 180px); overflow-y: auto;">
                    <div class="accordion" id="activityAccordion">
                        <?php $index = 0; ?>
                        <?php $has_activity = false; ?>
                        <?php while ($group = $recent_activity->fetch_assoc()): ?>
                            <?php $has_activity = true; ?>
                            <div class="accordion-item border-0 mb-3">
                                <h2 class="accordion-header" id="heading<?php echo $index; ?>">
                                    <button class="accordion-button collapsed border rounded" type="button" data-bs-toggle="collapse" data-bs-target="#collapse<?php echo $index; ?>" aria-expanded="false" aria-controls="collapse<?php echo $index; ?>">
                                        <div class="d-flex justify-content-between align-items-center w-100 me-3">
                                            <div class="d-flex align-items-center">
                                                <i class="bi bi-ticket-detailed text-primary me-2"></i>
                                                <div>
                                                    <div class="fw-semibold"><?php echo htmlspecialchars($group['queue_number']); ?></div>
                                                    <small class="text-muted"><?php echo htmlspecialchars($group['applicant_name']); ?></small>
                                                </div>
                                            </div>
                                            <span class="badge bg-primary"><?php echo count(explode('###', $group['activities'])); ?> activities</span>
                                        </div>
                                    </button>
                                </h2>
                                <div id="collapse<?php echo $index; ?>" class="accordion-collapse collapse" aria-labelledby="heading<?php echo $index; ?>" data-bs-parent="#activityAccordion">
                                    <div class="accordion-body">
                                        <div class="timeline">
                                            <?php
                                            $activities = explode('###', $group['activities']);
                                            foreach ($activities as $activity_str) {
                                                $parts = explode('|||', $activity_str);
                                                $timestamp = $parts[0];
                                                $previous_status = $parts[1];
                                                $new_status = $parts[2];
                                                $changed_by = $parts[3];
                                                $notes = $parts[4] ?? '';
                                            ?>
                                                <div class="timeline-item mb-4">
                                                    <div class="d-flex align-items-start">
                                                        <div class="timeline-marker bg-primary me-3"></div>
                                                        <div class="flex-grow-1">
                                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                                <div class="flex-grow-1">
                                                                    <small class="text-muted d-block mb-2">
                                                                        <i class="bi bi-calendar-event me-1"></i><?php echo formatDate($timestamp); ?>
                                                                    </small>
                                                                    <div class="mb-2">
                                                                        <?php if ($previous_status && $previous_status !== 'NULL'): ?>
                                                                            <span class="badge bg-secondary me-2"><?php echo getStatusDisplayName($previous_status); ?></span>
                                                                            <i class="bi bi-arrow-right text-muted mx-2"></i>
                                                                        <?php endif; ?>
                                                                        <span class="badge bg-<?php echo getStatusBadgeClass($new_status); ?>">
                                                                            <?php echo getStatusDisplayName($new_status); ?>
                                                                        </span>
                                                                    </div>
                                                                    <?php if (!empty($notes)): ?>
                                                                        <div class="mt-2 p-3 bg-light rounded">
                                                                            <small class="text-muted">
                                                                                <em><?php echo htmlspecialchars($notes); ?></em>
                                                                            </small>
                                                                        </div>
                                                                    <?php endif; ?>
                                                                </div>
                                                                <small class="text-muted ms-3">
                                                                    <i class="bi bi-person me-1"></i><?php echo htmlspecialchars($changed_by); ?>
                                                                </small>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php } ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php $index++; ?>
                        <?php endwhile; ?>

                        <?php if (!$has_activity): ?>
                            <div class="text-center text-muted py-5">
                                <i class="bi bi-clock-history" style="font-size: 3rem;"></i>
                                <p class="mt-3 h5">No recent activity found</p>
                                <p class="text-muted">No status changes have been recorded for the selected period.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="col-lg-3 col-md-4">
            <!-- Status Distribution -->
            <div class="section-card mb-4">
                <div class="card-header" style="color: #006400; font-weight: 700;">
                    <i class="bi bi-pie-chart"></i> Status Distribution
                </div>
                <div class="card-body p-3">
                    <?php if (count($status_data) > 0): ?>
                        <?php foreach ($status_data as $status => $count): ?>
                            <div class="mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <div>
                                        <div class="info-label mb-1" style="font-size: 0.7rem;"><?php echo getStatusDisplayName($status); ?></div>
                                        <div class="info-value small"><?php echo $count; ?> apps</div>
                                    </div>
                                    <div class="text-end">
                                        <div class="h6 mb-0" style="color: #006400;"><?php echo round(($count / array_sum($status_data)) * 100, 1); ?>%</div>
                                    </div>
                                </div>
                                <div class="progress" style="height: 6px;">
                                    <div class="progress-bar" style="width: <?php echo (array_sum($status_data) > 0) ? ($count / array_sum($status_data) * 100) : 0; ?>%; background: linear-gradient(90deg, #006400, #228B22);">
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center text-muted py-3">
                            <i class="bi bi-bar-chart" style="font-size: 2rem;"></i>
                            <p class="mt-2 small">No data available</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Applicant Type Distribution -->
            <div class="section-card mb-4">
                <div class="card-header" style="color: #006400; font-weight: 700;">
                    <i class="bi bi-people"></i> Applicant Types
                </div>
                <div class="card-body p-3">
                    <?php if (count($type_data) > 0): ?>
                        <?php foreach ($type_data as $type => $count): ?>
                            <div class="mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <div>
                                        <div class="info-label mb-1 text-capitalize" style="font-size: 0.7rem;"><?php echo htmlspecialchars($type); ?></div>
                                        <div class="info-value small"><?php echo $count; ?> apps</div>
                                    </div>
                                    <div class="text-end">
                                        <div class="h6 mb-0" style="color: #228B22;"><?php echo round(($count / array_sum($type_data)) * 100, 1); ?>%</div>
                                    </div>
                                </div>
                                <div class="progress" style="height: 6px;">
                                    <div class="progress-bar" style="width: <?php echo (array_sum($type_data) > 0) ? ($count / array_sum($type_data) * 100) : 0; ?>%; background: linear-gradient(90deg, #228B22, #32CD32);">
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center text-muted py-3">
                            <i class="bi bi-people" style="font-size: 2rem;"></i>
                            <p class="mt-2 small">No data available</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Staff Performance -->
            <div class="section-card">
                <div class="card-header" style="color: #006400; font-weight: 700;">
                    <i class="bi bi-trophy"></i> Staff Performance
                </div>
                <div class="card-body p-3">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead class="table-light">
                                <tr>
                                    <th class="small">Staff</th>
                                    <th class="small">Rate</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $has_data = false; ?>
                                <?php while ($staff = $staff_performance->fetch_assoc()): ?>
                                    <?php $has_data = true; ?>
                                    <tr>
                                        <td class="small">
                                            <div class="text-truncate" style="max-width: 80px;" title="<?php echo htmlspecialchars($staff['full_name']); ?>">
                                                <?php echo htmlspecialchars(substr($staff['full_name'], 0, 8) . (strlen($staff['full_name']) > 8 ? '...' : '')); ?>
                                            </div>
                                        </td>
                                        <td class="small">
                                            <?php
                                            $total = $staff['approved'] + $staff['rejected'];
                                            $rate = $total > 0 ? round(($staff['approved'] / $total) * 100, 1) : 0;
                                            $rate_class = $rate >= 80 ? 'success' : ($rate >= 60 ? 'warning' : 'danger');
                                            ?>
                                            <span class="badge bg-<?php echo $rate_class; ?> small"><?php echo $rate; ?>%</span>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                                <?php if (!$has_data): ?>
                                    <tr>
                                        <td colspan="2" class="text-center text-muted py-2 small">
                                            <i class="bi bi-trophy"></i>
                                            <div>No data</div>
                                        </td>
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

<?php include '../includes/auth_footer.php'; ?>
