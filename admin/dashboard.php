<?php
/**
 * Admin Dashboard
 * TAU-UREO Portal
 */

require_once '../config/config.php';
require_once '../includes/functions.php';

requireLogin();
checkSessionTimeout();

// Check if user is admin
if ($_SESSION['role'] !== 'admin') {
    header("Location: ../staff/dashboard.php");
    exit();
}

$conn = getDBConnection();

// System Statistics
$stats = [];

// Total users
$stats['total_users'] = $conn->query("SELECT COUNT(*) as count FROM users")->fetch_assoc()['count'];
$stats['active_users'] = $conn->query("SELECT COUNT(*) as count FROM users WHERE active_status = 1")->fetch_assoc()['count'];

// Total applications
$stats['total_applications'] = $conn->query("SELECT COUNT(*) as count FROM applications")->fetch_assoc()['count'];
$stats['today_applications'] = $conn->query("SELECT COUNT(*) as count FROM applications WHERE DATE(submission_timestamp) = CURDATE()")->fetch_assoc()['count'];

// Application status breakdown
$status_breakdown = $conn->query("
    SELECT current_status, COUNT(*) as count 
    FROM applications 
    GROUP BY current_status
");
$status_data = [];
while ($row = $status_breakdown->fetch_assoc()) {
    $status_data[$row['current_status']] = $row['count'];
}

// Recent activity (last 50 entries)
$recent_activity = $conn->query("
    SELECT sl.*, u.full_name, u.username, a.applicant_name
    FROM staff_logs sl
    JOIN users u ON sl.staff_id = u.user_id
    LEFT JOIN applications a ON sl.queue_number = a.queue_number
    ORDER BY sl.timestamp DESC
    LIMIT 50
");

// System settings
$settings_result = $conn->query("SELECT setting_key, setting_value FROM system_settings");
$settings = [];
while ($row = $settings_result->fetch_assoc()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

// Email statistics
$email_stats = $conn->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent,
        SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
    FROM email_logs
    WHERE DATE(sent_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
")->fetch_assoc();

closeDBConnection($conn);

$page_title = 'Admin Dashboard';
$base_url = '../';
$active_menu = 'dashboard';
include '../includes/auth_header.php';
?>

<div class="container-fluid py-4">
    <h2 class="mb-4" style="color: #006400; font-weight: 600;">
        <i class="bi bi-gear"></i> Admin Dashboard
    </h2>

    <!-- System Overview -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <small class="text-muted">Total Users</small>
                            <h3 class="mb-0" style="color: #006400;"><?php echo $stats['total_users']; ?></h3>
                            <small class="text-muted"><?php echo $stats['active_users']; ?> active</small>
                        </div>
                        <i class="bi bi-people-fill display-4" style="color: #006400;"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <small class="text-muted">Total Applications</small>
                            <h3 class="mb-0" style="color: #228B22;"><?php echo $stats['total_applications']; ?></h3>
                            <small class="text-muted"><?php echo $stats['today_applications']; ?> today</small>
                        </div>
                        <i class="bi bi-file-earmark-text-fill display-4" style="color: #228B22;"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <small class="text-muted">Emails (7 days)</small>
                            <h3 class="mb-0" style="color: #006400;"><?php echo $email_stats['total']; ?></h3>
                            <small class="text-success"><?php echo $email_stats['sent']; ?> sent</small> / 
                            <small class="text-danger"><?php echo $email_stats['failed']; ?> failed</small>
                        </div>
                        <i class="bi bi-envelope-fill display-4" style="color: #006400;"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <small class="text-muted">Queue Counter</small>
                            <h3 class="mb-0" style="color: #228B22;"><?php echo str_pad($settings['queue_counter'] ?? 0, 4, '0', STR_PAD_LEFT); ?></h3>
                            <small class="text-muted">Next: UREO-<?php echo str_pad(($settings['queue_counter'] ?? 0) + 1, 4, '0', STR_PAD_LEFT); ?></small>
                        </div>
                        <i class="bi bi-hash display-4" style="color: #228B22;"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Quick Actions -->
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header" style="background: linear-gradient(135deg, #006400, #228B22); color: white; border: none;">
                    <h6 class="mb-0"><i class="bi bi-lightning-fill"></i> Quick Actions</h6>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="manage-users.php" class="btn btn-outline-success">
                            <i class="bi bi-person-plus"></i> Manage Users
                        </a>
                        <a href="system-settings.php" class="btn btn-outline-success">
                            <i class="bi bi-gear-fill"></i> System Settings
                        </a>
                        <a href="activity-logs.php" class="btn btn-outline-success">
                            <i class="bi bi-clock-history"></i> Activity Logs
                        </a>
                        <a href="email-logs.php" class="btn btn-outline-success">
                            <i class="bi bi-envelope-check"></i> Email Logs
                        </a>
                        <a href="../staff/applications.php" class="btn btn-outline-primary">
                            <i class="bi bi-folder"></i> View All Applications
                        </a>
                    </div>
                </div>
            </div>

            <!-- Status Breakdown -->
            <div class="card border-0 shadow-sm mt-4">
                <div class="card-header" style="background: linear-gradient(135deg, #006400, #228B22); color: white; border: none;">
                    <h6 class="mb-0"><i class="bi bi-pie-chart"></i> Application Status</h6>
                </div>
                <div class="card-body">
                    <?php foreach ($status_data as $status => $count): ?>
                        <div class="mb-2">
                            <div class="d-flex justify-content-between">
                                <small><?php echo getStatusDisplayName($status); ?></small>
                                <small><strong><?php echo $count; ?></strong></small>
                            </div>
                            <div class="progress" style="height: 6px;">
                                <div class="progress-bar" style="width: <?php echo ($stats['total_applications'] > 0) ? ($count / $stats['total_applications'] * 100) : 0; ?>%; background: #006400;"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="col-md-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header" style="background: linear-gradient(135deg, #006400, #228B22); color: white; border: none;">
                    <h6 class="mb-0"><i class="bi bi-activity"></i> Recent Staff Activity</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive" style="max-height: 500px; overflow-y: auto;">
                        <table class="table table-sm table-hover">
                            <thead class="sticky-top" style="background: white;">
                                <tr>
                                    <th>Time</th>
                                    <th>Staff</th>
                                    <th>Action</th>
                                    <th>Queue</th>
                                    <th>Details</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($activity = $recent_activity->fetch_assoc()): ?>
                                    <tr>
                                        <td><small><?php echo formatDate($activity['timestamp']); ?></small></td>
                                        <td><small><?php echo htmlspecialchars($activity['full_name']); ?></small></td>
                                        <td>
                                            <span class="badge bg-secondary text-capitalize">
                                                <?php echo str_replace('_', ' ', $activity['action_type']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($activity['queue_number']): ?>
                                                <a href="../staff/view-application.php?queue=<?php echo urlencode($activity['queue_number']); ?>">
                                                    <?php echo htmlspecialchars($activity['queue_number']); ?>
                                                </a>
                                            <?php else: ?>
                                                <small class="text-muted">N/A</small>
                                            <?php endif; ?>
                                        </td>
                                        <td><small><?php echo htmlspecialchars(substr($activity['action_details'] ?? '', 0, 50)); ?></small></td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/auth_footer.php'; ?>
