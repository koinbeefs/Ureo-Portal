<?php
/**
 * Staff Dashboard
 * TAU-UREO Portal
 */

require_once '../config/config.php';
require_once '../includes/functions.php';

requireLogin();
checkSessionTimeout();

$staff_id = $_SESSION['user_id'];
$conn = getDBConnection();

// Get comprehensive statistics - count applications relevant to this staff member
$stats_query = "
    SELECT
        current_status,
        COUNT(*) as count
    FROM applications
    WHERE assigned_staff_id = ?
       OR (assigned_staff_id IS NULL AND current_status NOT IN ('APPROVED', 'REJECTED', 'CERTIFICATE_ISSUED', 'CLOSED'))
       OR current_status IN ('STAFF_REVIEW_REQUIRED', 'COMPLIANCE_REVIEW', 'INITIAL_REVIEW', 'REVISION_REQUIRED')
    GROUP BY current_status
";

$stats_stmt = $conn->prepare($stats_query);
$stats_stmt->bind_param("i", $staff_id);
$stats_stmt->execute();
$stats_result = $stats_stmt->get_result();

$status_counts = [];
while ($row = $stats_result->fetch_assoc()) {
    $status_counts[$row['current_status']] = $row['count'];
}

// Calculate statistics like applications.php does
$stats = [
    'total_assigned' => array_sum($status_counts),
    'pending_review' => ($status_counts['UNDER_STAFF_REVIEW'] ?? 0) + ($status_counts[STATUS_CHECKLIST_SUBMITTED] ?? 0),
    'completed' => ($status_counts['APPROVED'] ?? 0) + ($status_counts['CERTIFICATE_ISSUED'] ?? 0),
    'revisions_needed' => $status_counts['REVISIONS_REQUIRED'] ?? 0,
    'overdue' => 0 // This would need a separate query to calculate based on date logic
];

// Calculate overdue applications (applications not updated in 7+ days)
$overdue_stmt = $conn->prepare("
    SELECT COUNT(*) as overdue_count
    FROM applications
    WHERE (assigned_staff_id = ?
           OR (assigned_staff_id IS NULL AND current_status NOT IN ('APPROVED', 'REJECTED', 'CERTIFICATE_ISSUED', 'CLOSED'))
           OR current_status IN ('STAFF_REVIEW_REQUIRED', 'COMPLIANCE_REVIEW', 'INITIAL_REVIEW', 'REVISION_REQUIRED'))
      AND DATEDIFF(NOW(), last_updated) > 7
");
$overdue_stmt->bind_param("i", $staff_id);
$overdue_stmt->execute();
$overdue_result = $overdue_stmt->get_result()->fetch_assoc();
$stats['overdue'] = $overdue_result['overdue_count'];

// Get recent applications (assigned to staff or needing review)
$apps_stmt = $conn->prepare("
    SELECT a.*,
           u.full_name as assigned_staff_name,
           (SELECT COUNT(*) FROM documents WHERE queue_number = a.queue_number) as doc_count,
           (SELECT COUNT(*) FROM messages WHERE queue_number = a.queue_number AND read_status = 0 AND sender_type = 'applicant') as unread_messages,
           DATEDIFF(NOW(), a.last_updated) as days_since_update
    FROM applications a
    LEFT JOIN users u ON a.assigned_staff_id = u.user_id
    WHERE a.assigned_staff_id = ?
       OR (a.assigned_staff_id IS NULL AND a.current_status NOT IN ('APPROVED', 'REJECTED', 'CERTIFICATE_ISSUED', 'CLOSED'))
       OR a.current_status IN ('STAFF_REVIEW_REQUIRED', 'COMPLIANCE_REVIEW', 'INITIAL_REVIEW', 'REVISION_REQUIRED')
    ORDER BY a.last_updated DESC
    LIMIT 20
");
$apps_stmt->bind_param("i", $staff_id);
$apps_stmt->execute();
$applications = $apps_stmt->get_result();

// Get recent activity
$activity_stmt = $conn->prepare("
    SELECT sl.*, a.applicant_name, a.research_title
    FROM staff_logs sl
    LEFT JOIN applications a ON sl.queue_number = a.queue_number
    WHERE sl.staff_id = ?
    ORDER BY sl.timestamp DESC
    LIMIT 10
");
$activity_stmt->bind_param("i", $staff_id);
$activity_stmt->execute();
$recent_activity = $activity_stmt->get_result();


// Get urgent items (applications needing immediate attention)
$urgent_stmt = $conn->prepare("
    SELECT a.*,
           (SELECT COUNT(*) FROM messages WHERE queue_number = a.queue_number AND read_status = 0 AND sender_type = 'applicant') as unread_messages,
           DATEDIFF(NOW(), a.submission_timestamp) as days_pending
    FROM applications a
    WHERE (a.assigned_staff_id = ? OR a.assigned_staff_id IS NULL)
      AND a.current_status NOT IN ('APPROVED', 'REJECTED', 'CERTIFICATE_ISSUED', 'CLOSED')
      AND (DATEDIFF(NOW(), a.last_updated) > 7
           OR a.current_status = 'REVISION_REQUIRED'
           OR (SELECT COUNT(*) FROM messages WHERE queue_number = a.queue_number AND read_status = 0 AND sender_type = 'applicant') > 0)
    ORDER BY
        CASE
            WHEN DATEDIFF(NOW(), a.last_updated) > 14 THEN 1
            WHEN a.current_status = 'REVISION_REQUIRED' THEN 2
            WHEN (SELECT COUNT(*) FROM messages WHERE queue_number = a.queue_number AND read_status = 0 AND sender_type = 'applicant') > 0 THEN 3
            ELSE 4
        END,
        a.last_updated ASC
    LIMIT 5
");
$urgent_stmt->bind_param("i", $staff_id);
$urgent_stmt->execute();
$urgent_items = $urgent_stmt->get_result();

closeDBConnection($conn);

$page_title = 'Staff Dashboard';
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

    .stats-card {
        border: none;
        border-radius: 12px;
        background: white;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        transition: transform 0.2s ease, box-shadow 0.2s ease;
        height: 100%;
    }

    .stats-card:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    }

    .stats-card .card-body {
        padding: 1.5rem 1rem;
        text-align: center;
    }

    .stats-card i {
        font-size: 1.75rem;
        margin-bottom: 0.5rem;
    }

    .stats-card h5 {
        font-size: 1.5rem;
        font-weight: 700;
        margin-bottom: 0.25rem;
    }

    .dashboard-card {
        border: none;
        border-radius: 12px;
        background: white;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        transition: transform 0.2s ease, box-shadow 0.2s ease;
        height: 100%;
        position: relative;
    }

    .dashboard-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(0,0,0,0.1);
    }

    .dashboard-card .card-header {
        background: linear-gradient(135deg, #f8f9fa, #e9ecef);
        border: none;
        padding: 1rem 1.25rem;
        border-radius: 12px 12px 0 0;
    }

    .dashboard-card .card-body {
        padding: 1rem 1.25rem;
    }

    .dashboard-card .card-footer {
        background: #f8f9fa;
        border: none;
        padding: 0.75rem 1.25rem;
        border-radius: 0 0 12px 12px;
    }

    .application-card {
        transition: all 0.3s ease;
        border: 1px solid rgba(0, 0, 0, 0.05);
    }

    .application-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15) !important;
    }

    .application-card .card-header {
        border-radius: 0.375rem 0.375rem 0 0 !important;
    }

    .application-card .card-footer {
        border-radius: 0 0 0.375rem 0.375rem !important;
    }

    .urgent-card {
        border-left: 4px solid #DC3545 !important;
        background: linear-gradient(135deg, rgba(220, 53, 69, 0.05), rgba(220, 53, 69, 0.02));
        border-radius: 8px;
        padding: 1rem;
        margin-bottom: 0.75rem;
    }

    .card-view-item { display: none !important; }

    .list-view-item {
        border: none;
        border-radius: 8px;
        background: white;
        box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        padding: 0.5rem 0.75rem;
        margin-bottom: 0.5rem;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }

    .list-view-item:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    }

    @media (max-width: 768px) {
        .section-card .card-header {
            padding: 1rem 1.25rem !important;
            font-size: 1rem !important;
        }

        .stats-card .card-body {
            padding: 1rem !important;
        }

        .stats-card i {
            font-size: 1.5rem !important;
        }

        .stats-card h5 {
            font-size: 1.25rem !important;
        }

        .application-card {
            margin-bottom: 1rem;
        }

        .application-card .card-header {
            padding: 0.5rem !important;
        }

        .application-card .card-body {
            padding: 0.5rem !important;
        }
    }
</style>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 style="color: #006400; font-weight: 600;">
            <i class="bi bi-speedometer2"></i> Staff Dashboard
        </h2>
        <div class="text-muted">
            <i class="bi bi-clock"></i> <?php echo date('F j, Y g:i A'); ?>
        </div>
    </div>

    <!-- Statistics Overview -->
    <div class="section-card mb-4">
        <div class="card-header">
            <i class="bi bi-bar-chart"></i> Dashboard Overview
        </div>
        <div class="card-body p-4">
            <div class="row g-3">
                <div class="col-6 col-sm-4 col-md-2">
                    <div class="stats-card h-100">
                        <div class="card-body">
                            <div class="d-flex flex-column align-items-center">
                                <i class="bi bi-folder text-primary"></i>
                                <h5 class="text-primary"><?php echo $stats['total_assigned']; ?></h5>
                                <small class="text-muted fw-medium">Total Assigned</small>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-sm-4 col-md-2">
                    <div class="stats-card h-100">
                        <div class="card-body">
                            <div class="d-flex flex-column align-items-center">
                                <i class="bi bi-hourglass-split text-warning"></i>
                                <h5 class="text-warning"><?php echo $stats['pending_review']; ?></h5>
                                <small class="text-muted fw-medium">Pending Review</small>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-sm-4 col-md-2">
                    <div class="stats-card h-100">
                        <div class="card-body">
                            <div class="d-flex flex-column align-items-center">
                                <i class="bi bi-check-circle text-success"></i>
                                <h5 class="text-success"><?php echo $stats['completed']; ?></h5>
                                <small class="text-muted fw-medium">Completed</small>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-sm-4 col-md-2">
                    <div class="stats-card h-100">
                        <div class="card-body">
                            <div class="d-flex flex-column align-items-center">
                                <i class="bi bi-arrow-repeat text-warning"></i>
                                <h5 class="text-warning"><?php echo $stats['revisions_needed']; ?></h5>
                                <small class="text-muted fw-medium">Revisions</small>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-sm-4 col-md-2">
                    <div class="stats-card h-100">
                        <div class="card-body">
                            <div class="d-flex flex-column align-items-center">
                                <i class="bi bi-exclamation-triangle text-danger"></i>
                                <h5 class="text-danger"><?php echo $stats['overdue']; ?></h5>
                                <small class="text-muted fw-medium">Overdue</small>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-sm-4 col-md-2">
                    <div class="stats-card h-100">
                        <div class="card-body">
                            <div class="d-flex flex-column align-items-center">
                                <i class="bi bi-envelope text-info"></i>
                                <h5 class="text-info">
                                    <?php
                                    $total_unread = 0;
                                    $applications->data_seek(0);
                                    while ($app = $applications->fetch_assoc()) {
                                        $total_unread += $app['unread_messages'];
                                    }
                                    $applications->data_seek(0);
                                    echo $total_unread;
                                    ?>
                                </h5>
                                <small class="text-muted fw-medium">Unread Messages</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content Grid -->
    <div class="row g-4">
                
        <!-- Recent Applications -->
        <div class="col-lg-8" style="position: sticky; top: 80px; align-self: flex-start;">
            <div class="section-card">
                <div class="card-header">
                    <i class="bi bi-list-task"></i> Recent Applications
                </div>
                <div class="card-body" style="max-height: calc(100vh - 180px); overflow-y: auto;">
                    <?php if ($applications->num_rows > 0): ?>
                        <div id="applications-container">
                            <?php $applications->data_seek(0); ?>
                                <?php while ($app = $applications->fetch_assoc()):
                                    $urgency_class = '';
                                    $urgency_indicator = '';
                                    if ($app['days_since_update'] > 14) {
                                        $urgency_class = 'border-danger';
                                        $urgency_indicator = '<span class="badge bg-danger position-absolute" style="top: -8px; left: 10px; font-size: 0.65rem;">OVERDUE</span>';
                                    } elseif ($app['unread_messages'] > 0) {
                                        $urgency_class = 'border-warning';
                                        $urgency_indicator = '<span class="badge bg-warning position-absolute" style="top: -8px; left: 10px; font-size: 0.65rem;">NEW MSG</span>';
                                    } elseif ($app['current_status'] === 'REVISION_REQUIRED') {
                                        $urgency_class = 'border-info';
                                        $urgency_indicator = '<span class="badge bg-info position-absolute" style="top: -8px; left: 10px; font-size: 0.65rem;">REVISION</span>';
                                    }
                                ?>
                                    <!-- Card View -->
                                    <div class="col-xl-6 application-card card-view-item">
                                        <div class="card border-0 shadow-sm h-100 application-card position-relative <?php echo $urgency_class; ?>">
                                            <?php echo $urgency_indicator; ?>

                                            <div class="card-header" style="background: linear-gradient(135deg, #006400, #228B22); color: white; border: none; padding: 0.75rem;">
                                                <div class="row g-2 align-items-start">
                                                    <div class="col-7">
                                                        <h6 class="mb-1 small">
                                                            <i class="bi bi-ticket-detailed"></i> <?php echo htmlspecialchars($app['queue_number']); ?>
                                                        </h6>
                                                        <small class="opacity-75">
                                                            <i class="bi bi-calendar-event"></i> <?php echo formatDate($app['last_updated']); ?>
                                                        </small>
                                                    </div>
                                                    <div class="col-5 text-end">
                                                        <span class="badge bg-<?php echo getStatusBadgeClass($app['current_status']); ?> mb-1 small text-truncate d-block" style="max-width: 100%; font-size: 0.7rem;" title="<?php echo getStatusDisplayName($app['current_status']); ?>">
                                                            <?php echo $short_status[$app['current_status']] ?? substr(getStatusDisplayName($app['current_status']), 0, 12) . '...'; ?>
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="card-body" style="padding: 0.75rem;">
                                                <!-- Applicant Info (Compact) -->
                                                <div class="mb-2">
                                                    <div class="d-flex align-items-center mb-1">
                                                        <i class="bi bi-person-circle text-primary me-1"></i>
                                                        <div class="flex-grow-1">
                                                            <strong class="small"><?php echo htmlspecialchars($app['applicant_name']); ?></strong>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Research Title (Compact) -->
                                                <div class="mb-2">
                                                    <i class="bi bi-journal-text text-success me-1"></i>
                                                    <strong class="text-success small">Research Title</strong>
                                                    <p class="mb-0 mt-1 small text-truncate" title="<?php echo htmlspecialchars($app['research_title']); ?>">
                                                        <?php echo htmlspecialchars(substr($app['research_title'], 0, 60) . (strlen($app['research_title']) > 60 ? '...' : '')); ?>
                                                    </p>
                                                </div>

                                                <!-- Status & Assignment (Compact) -->
                                                <div class="row g-1 mb-2">
                                                    <div class="col-6">
                                                        <div class="text-center p-1 bg-light rounded small">
                                                            <i class="bi bi-person-check d-block text-primary mb-1"></i>
                                                            <small class="d-block text-muted fw-bold">Assigned</small>
                                                            <?php if ($app['assigned_staff_name']): ?>
                                                                <small class="fw-bold text-truncate d-block" style="max-width: 100px;" title="<?php echo htmlspecialchars($app['assigned_staff_name']); ?>">
                                                                    <?php echo htmlspecialchars(substr($app['assigned_staff_name'], 0, 12) . (strlen($app['assigned_staff_name']) > 12 ? '...' : '')); ?>
                                                                </small>
                                                            <?php else: ?>
                                                                <small class="text-warning">Unassigned</small>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                    <div class="col-6">
                                                        <div class="text-center p-1 bg-light rounded small">
                                                            <i class="bi bi-file-earmark d-block text-info mb-1"></i>
                                                            <small class="d-block text-muted fw-bold">Documents</small>
                                                            <span class="badge bg-info small"><?php echo $app['doc_count']; ?></span>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Messages & Updates (Compact) -->
                                                <div class="d-flex justify-content-between align-items-center mb-2 small">
                                                    <div>
                                                        <?php if ($app['unread_messages'] > 0): ?>
                                                            <span class="badge bg-danger small">
                                                                <i class="bi bi-envelope-exclamation"></i> <?php echo $app['unread_messages']; ?> unread
                                                            </span>
                                                        <?php else: ?>
                                                            <small class="text-muted">
                                                                <i class="bi bi-envelope"></i> No new messages
                                                            </small>
                                                        <?php endif; ?>
                                                    </div>
                                                    <?php if ($app['days_since_update'] > 0): ?>
                                                        <small class="text-<?php echo $app['days_since_update'] > 7 ? 'danger' : 'warning'; ?>">
                                                            <i class="bi bi-clock-history"></i> <?php echo $app['days_since_update']; ?>d ago
                                                        </small>
                                                    <?php endif; ?>
                                                </div>
                                            </div>

                                            <div class="card-footer bg-light border-0" style="padding: 0.5rem 0.75rem;">
                                                <div class="d-flex gap-1">
                                                    <a href="view-application.php?queue=<?php echo urlencode($app['queue_number']); ?>"
                                                       class="btn btn-clear btn-sm flex-fill small">
                                                        <i class="bi bi-eye"></i> View Details
                                                    </a>
                                                    <?php if ($app['unread_messages'] > 0): ?>
                                                        <a href="view-application.php?queue=<?php echo urlencode($app['queue_number']); ?>#messages"
                                                           class="btn btn-outline-danger btn-sm small">
                                                            <i class="bi bi-envelope"></i> Messages
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- List View -->
                                    <div class="list-view-item">
                                        <div class="card border-0 shadow-sm mb-1">
                                            <div class="card-body py-2">
                                                <div class="row align-items-center">
                                                    <div class="col-md-3">
                                                        <div class="d-flex align-items-center">
                                                            <i class="bi bi-ticket-detailed text-primary me-2"></i>
                                                            <div>
                                                                <strong class="small"><?php echo htmlspecialchars($app['queue_number']); ?></strong>
                                                                <br>
                                                                <small class="text-muted"><?php echo htmlspecialchars($app['applicant_name']); ?></small>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <small class="text-truncate d-block" title="<?php echo htmlspecialchars($app['research_title']); ?>">
                                                            <?php echo htmlspecialchars(substr($app['research_title'], 0, 50) . (strlen($app['research_title']) > 50 ? '...' : '')); ?>
                                                        </small>
                                                    </div>
                                                    <div class="col-md-2">
                                                        <span class="badge bg-<?php echo getStatusBadgeClass($app['current_status']); ?> small">
                                                            <?php echo $short_status[$app['current_status']] ?? substr(getStatusDisplayName($app['current_status']), 0, 12) . '...'; ?>
                                                        </span>
                                                    </div>
                                                    <div class="col-md-3">
                                                        <div class="d-flex justify-content-between align-items-center">
                                                            <div class="d-flex gap-2">
                                                                <a href="view-application.php?queue=<?php echo urlencode($app['queue_number']); ?>"
                                                                   class="btn btn-clear btn-sm">
                                                                    <i class="bi bi-eye"></i> View
                                                                </a>
                                                                <?php if ($app['unread_messages'] > 0): ?>
                                                                    <a href="view-application.php?queue=<?php echo urlencode($app['queue_number']); ?>#messages"
                                                                       class="btn btn-outline-danger btn-sm">
                                                                        <i class="bi bi-envelope"></i>
                                                                    </a>
                                                                <?php endif; ?>
                                                            </div>
                                                            <small class="text-muted">
                                                                <?php echo $app['days_since_update']; ?>d ago
                                                            </small>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center text-muted py-5">
                            <i class="bi bi-inbox display-4"></i>
                            <p class="mt-3">No applications assigned yet.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="col-lg-4">
            <!-- Urgent Items -->
            <div class="section-card">
                <div class="card-header">
                    <i class="bi bi-exclamation-triangle-fill"></i> Urgent Attention
                </div>
                <div class="card-body">
                    <?php if ($urgent_items->num_rows > 0): ?>
                        <?php while ($urgent = $urgent_items->fetch_assoc()): ?>
                            <div class="urgent-card mb-2">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div class="flex-grow-1">
                                        <small class="fw-bold"><?php echo htmlspecialchars($urgent['queue_number']); ?></small>
                                        <br><small><?php echo htmlspecialchars(substr($urgent['applicant_name'], 0, 20)); ?></small>
                                        <?php if ($urgent['unread_messages'] > 0): ?>
                                            <br><span class="badge bg-danger small"><?php echo $urgent['unread_messages']; ?> unread</span>
                                        <?php endif; ?>
                                    </div>
                                    <a href="view-application.php?queue=<?php echo urlencode($urgent['queue_number']); ?>"
                                       class="btn btn-sm btn-outline-danger small">View</a>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="text-center text-muted py-3">
                            <i class="bi bi-check-circle text-success"></i>
                            <p class="mb-0 small">All caught up!</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const applicationsContainer = document.getElementById('applications-container');

});
</script>

<?php include '../includes/auth_footer.php'; ?>
